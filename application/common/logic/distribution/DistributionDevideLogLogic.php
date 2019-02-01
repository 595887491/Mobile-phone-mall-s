<?php
/**
 * @Author: 陈静
 * @Date: 2018/04/23 14:05:17
 * @Description:
 */

namespace app\common\logic\distribution;


use app\common\model\AccountLogModel;
use app\common\model\UserModel;
use app\common\model\Users;
use app\common\model\WalletBalanceModel;
use think\Db;
use think\Exception;
use app\common\model\UserUserModel;
use think\Model;

class DistributionDevideLogLogic extends Model
{
    protected $table = 'cf_distribute_divide_log';
    protected $resultSetType = 'collection';

    //获取用户累计收益
    public function getUserTotalEarnings($user_id, $start_time = 0, $end_time = 0): float
    {
        $where = [
            'to_user_id' => $user_id
        ];
        if ($end_time) {
            $where['add_time'] = ['between', [$start_time, $end_time]];
        }
        $result = $this
            ->where($where)->sum('divide_money');

        return $result>0 ? sprintf("%.2f", $result): 0.00 ;
    }

    //获取用户累计收益
    public function getUserTotalEarningsByTime($user_id, $start_time = 0, $end_time = 0)
    {
        $where = [
            'to_user_id' => $user_id
        ];
        if ($end_time) {
            $where['add_time'] = ['between', [$start_time, $end_time]];
        }

        $data = [];
        $this->field('id,divide_money,add_time')
            ->where($where)
            ->chunk(100, function ($users) use (&$data) {
                $data = array_merge($data,collection($users)->toArray());
            }, 'id', 'desc');

        return $data;
    }

    
    //获取用户昨日收益
    public function getUserYestodayEarnings($user_id)
    {
        $where = [
            'to_user_id' => $user_id,
            'is_divided' => 1 ,
        ];
        $result = $this
            ->where($where)
            ->where('divide_time',[
                '>=',strtotime(date('Y-m-d',strtotime('-1 day')))
            ],[
                '<=',strtotime(date('Y-m-d',strtotime('-1 day'))) + 24*3600 - 1
            ])
            ->sum('divide_money');

        return $result ?? 0 ;
    }

    // 订单收益列表
    public function getUserOrderEarnings($user_id){
        $p = input('get.p',1);
        $rows = $this->alias('d')
            ->field('d.*, u.nickname, u.mobile,tu.head_pic, (uu.level - uu1.level) as relative_level')
            ->join('users u', 'd.from_user_id = u.user_id','left')
            ->join(['tp_users'=>'tu'], 'd.from_user_id = tu.user_id','left')
            ->join(['cf_user_user'=>'uu'], 'd.from_user_id = uu.user_id','left')
            ->join(['cf_user_user'=>'uu1'], 'd.to_user_id = uu1.user_id','left')
            ->where([
                'to_user_id'=>$user_id,
                'd.divide_money'=> ['<>',0],
            ])
            ->order('add_time', 'DESC')
            ->page($p, 12)
            ->select()->toArray();
        return $rows;
    }

    //获取用户某段时间的收益
    public function getUserOrderEarnsByFewTime($user_id , $everyDayOrderEarns)
    {
        $data = [];
        foreach ($everyDayOrderEarns as $k => $v){
            $data[$k] = $this->getUserTotalEarnings($user_id,$k,$k + 24 * 3600 -1);
        }

        return $data;
    }

    /**
     * 根据收益分成表查询收益排行
     * @return array
     */
    public function getEarningsRankingFromDivide(){
        $p = input('get.p',1);
        $p_size = 12;
        $rows = $this->alias('d')
            ->field('SUM(divide_money) as total_money, u.user_id, u.nickname, u.head_pic')
            ->join('users u', 'd.to_user_id = u.user_id')
            ->order('total_money', 'DESC')
            ->group('to_user_id')
            ->page($p, $p_size)
            ->select()->toArray();
        array_walk($rows,function (&$v, $k, $data){
            $v['rank'] = ($data[0] - 1) * $data[1] + $k + 1;
        },[$p, $p_size]);
        return $rows;
    }

    public function getEarningsRanking(){
        $p = input('get.p',1);
        $p_size = 12;
        $row = $this->table('cf_users')->alias('cu')
            ->field('cu.*, u.nickname,u.mobile')
            ->join('users u', 'cu.user_id = u.user_id','left')
            ->order('wallet_accumulate_income', 'DESC')
            ->page($p, $p_size)
            ->select()
            ->toArray();
        array_walk($row, function(&$value, $kay)use($p, $p_size){
            $rank = ($p - 1) * $p_size + $kay + 1;
            $value['rank'] = sprintf("%02d",$rank); //将1、2...格式化为01、02...
        });
        return $row;
    }

    // 当前用户排名
    public function getMyEarningsRanking($user_id){
        $this->query('SET @row_number = 0'); //重置@row_number的值
        $subQuery = $this->table('cf_users')
            ->field('user_id, wallet_accumulate_income ,if(@row_number,@row_number:=@row_number + 1,@row_number:=1) as rank')
            ->order(['wallet_accumulate_income'=>'DESC','user_id'=>'ASC'])
            ->buildSql();

        $user_rank = $this->table($subQuery.' b')
            ->field('b.rank,b.wallet_accumulate_income,b.user_id ')
            ->where(['b.user_id'=>$user_id])
            ->find();
        if ($user_rank) {
            $user_rank = $user_rank->toArray();
        } else {
            $user_rank = [];
        }

        $rank_user_count = $this->table($subQuery.' a')
            ->count();
        return [
            'rank'=> $user_rank['rank'] ?? 0, //我的排名
            'total_money'=>$user_rank['wallet_accumulate_income'] ?? 0, //我的总收益
            'rank_user_count'=>$rank_user_count ?? 0 // 参与排名的总人数
        ];
    }

    //订单分成给用户
    public function divideMoneyToUser($divide_datas)
    {
        $userModel = new Users();
        //关系映射
        $incomeRelationArr[1] = 'wallet_user_income';
        $incomeRelationArr[2] = 'wallet_partner_income';
        $incomeRelationArr[4] = 'wallet_agent_income';

        $accumulateRelationArr[1] = 'wallet_accumulate_user_income';
        $accumulateRelationArr[2] = 'wallet_accumulate_partner_income';
        $accumulateRelationArr[4] = 'wallet_accumulate_agent_income';
        //执行sql
        foreach ($divide_datas as $k => $v){
            $data[$k] = [
                'user_id' => $v['to_user_id'],
                $incomeRelationArr[$v['distribute_type']] => ['exp',$incomeRelationArr[$v['distribute_type']].' + '.$v['divide_money']],
                $accumulateRelationArr[$v['distribute_type']] => ['exp',$accumulateRelationArr[$v['distribute_type']].' + '.$v['divide_money']],
                'wallet_accumulate_income' => ['exp','wallet_accumulate_income + '.$v['divide_money']]
            ];

            //余额操作明细
            if ($v['distribute_type'] == 1) {
                try{
                    $userModel->where('user_id',$v['to_user_id'])
                        ->setInc('user_money',$v['divide_money']);
                }catch (Exception $e){
                    throw new Exception('更改用户余额数据失败');
                }

                $accountLogModel = new AccountLogModel();
                $userMoney = $userModel->where('user_id' ,'=',$v['to_user_id'])->getField('user_money');
                $accountLogModel->addDistributionDatas($v,$userMoney);
            }
        }
        if ($data) {
            (new UserModel())->isUpdate(true)->saveAll($data);
        }
    }





    /**
     * 获取代理商二维码的url，默认为永久二维码
     * @param $user_id
     * @param int $qr_mode 0：商家二维码，1：微信二维码
     * @param int $qr_type 0:普通用户二维码，非永久, 1:代理商二维码，永久二维码
     */
    public function getAgentQRCodeUrl($user_id,$qr_mode = 1, $qr_type = 1){
        $user = M('users')->where('user_id', $user_id)->find();
        $wx_user = M('wx_user')->find(); //微信配置
        if ($qr_mode && $wx_user) {
            $wechatObj = new \app\common\logic\wechat\WechatUtil($wx_user);
            //指定生成二维码的有效期
            //查询当前用户是否存在二维码，是否过期
            $res = $this->table('cf_users u')
                ->field('u.user_id , u.user_type, u.user_qrcode_id, u.agent_qrcode_id, q1.time as time1, q1.qrcode_url as qrcode_url1, q2.time as time2, q2.qrcode_url as qrcode_url2')
                ->join(['cf_qrcode' => 'q1'],'u.user_qrcode_id = q1.id','left')
                ->join(['cf_qrcode' => 'q2'],'u.agent_qrcode_id = q2.id','left')
                ->where('user_id','=',$user_id)
                ->find();
            if ($res) {
                if ($qr_type == 0) {
                    if (!empty($res['qrcode_url1']) && time() - $res['time1'] < 29 * 24 * 3600 ) {
                        $wxdata['url'] = $res['qrcode_url1'];
                    } else {
                        // mei有二维码
                        $expire = 2592000;
                        $wxdata = $wechatObj->createTempQrcode($expire, $user['user_id']);
                        if ($wxdata && $wxdata['url']){
                            $insert_id = $this->table('cf_qrcode')->insertGetId(['is_forever'=>0, 'time'=>time(),'qrcode_url'=>$wxdata['url']]);
                            $this->table('cf_users')->where(['user_id'=>$user_id])->update(['user_qrcode_id'=>$insert_id]);
                        }
                    }
                } else {
                    // 代理商永久二维码
                    if (!empty($res['qrcode_url2'])) {
                        $wxdata['url'] = $res['qrcode_url2'];
                    } else {
                        $expire = 0;
                        $wxdata = $wechatObj->createTempQrcode($expire, 'a'.$user['user_id']);
                        if ($wxdata && $wxdata['url']){
                            $insert_id = $this->table('cf_qrcode')->insertGetId(['is_forever'=>1, 'time'=>0,'qrcode_url'=>$wxdata['url']]);
                            $this->table('cf_users')->where(['user_id'=>$user_id])->update(['agent_qrcode_id'=>$insert_id]);
                        }
                    }
                }
            } else {
                // 用户为新用户，没有二维码记录
                if ($qr_type == 0) {
                    $expire = 2592000;
                    $scene_id = $user['user_id'];
                } else {
                    $expire = 0;
                    $scene_id = 'a'.$user['user_id'];
                }
                $wxdata = $wechatObj->createTempQrcode($expire, $scene_id);
                if ($wxdata && $wxdata['url']){
                    $insert_data = ['is_forever'=>$qr_type == 0 ? 0 : 1, 'time'=>time(),'qrcode_url'=>$wxdata['url']];
                    $insert_id = Db::table('cf_qrcode')->insertGetId($insert_data);
                    $update_data = $qr_type == 0 ? ['user_qrcode_id'=>$insert_id] : ['agent_qrcode_id'=>$insert_id];
                    $this->table('cf_users')->where(['user_id'=>$user_id])->update($update_data);
                }
            }

            if (empty($wxdata['url'])) {
                $this->error('微信未成功接入或者数据库数据有误');
            }
        }

        if ($qr_mode && $wx_user && !empty($wxdata['url'])) {
            $shareLink = urlencode($wxdata['url']);
        } else {
            $shareLink = urlencode("http://{$_SERVER['HTTP_HOST']}/index.php?m=Mobile&c=Index&a=index&first_leader={$user['user_id']}"); //默认分享链接
        }
        return $shareLink;
    }
    /**
     * 会员订单
     * @date 2018-04-27 15:03:27
     * @param $user_id
     * @param $count 是否是统计订单总数 0:不是 1：是
     */
    public function getMemberOrder($user_id, $count = 0){
        $p = input('get.p',1);
        $user_user_model  = new UserUserModel();
        $currentUserInfo = $user_user_model->getCurrentUserInfo($user_id);
        $currentUserInfo = $currentUserInfo ? $currentUserInfo->toArray() : [];
        if (empty($currentUserInfo)) {
            return [];
        }
        if (empty($this->subUserArr)) {
            // 先查询出所有下级会员
            $user_rows = $user_user_model->getLevelChild($user_id);
            $this->subUserArr = $user_id_arr = array_column($user_rows, 'user_id');
        }

        $order = $this->table('tp_order o')
            ->field('o.user_id,o.order_id, o.order_sn,o.add_time,o.prom_type, o.order_status,o.order_amount,uu.level, (uu.level - '.$currentUserInfo['level'].') as relative_level,tu.head_pic,tu.mobile, tu.nickname')
            ->where([
                'o.user_id'=>['in',$user_id_arr],
            ])
            ->join(['cf_user_user'=>'uu'],'uu.user_id = o.user_id', 'left')
            ->join(['tp_users'=>'tu'],'tu.user_id = o.user_id', 'left')
            ->order('o.order_id', 'desc');
        if ($count) {
            return $order->count();
        }

        $rows = $order->page($p, 12)
            ->select()->toArray();
        return $rows;
    }

    /**
     * 下级订单总额 、 订单成交率
     * @param $user_id
     * @date 2018-04-27 15:03:51
     */
    public function getSubOrderAmount($user_id){
        if (empty($this->subUserArr)) {
            // 先查询出所有下级会员
            $user_user_model  = new UserUserModel();
            $user_rows = $user_user_model->getLevelChild($user_id);
            $this->subUserArr = $user_id_arr = array_column($user_rows, 'user_id');
        }
        // 下级订单总数、总额
        $sub_user_order = $this->table('tp_order o')
            ->field('sum(order_amount) as all_order_amount, count(1) as all_order_count')
            ->where(['user_id'=>['in',$this->subUserArr]])
            ->find()->toArray();

        //下级成交订单总数
        $sub_user_complete_order = $this->table('tp_order o')
            ->field('count(1) as complete_order_count')
            ->where([
                'user_id'=>['in',$this->subUserArr],
                'order_status'=>['in',[0,1,2,4]],
                'pay_status' => ['in',[1,2,4]]
            ])
            ->find()->toArray();

        $complete_probability = $sub_user_order['all_order_count'] == 0 ? '0%' :sprintf('%.2f',$sub_user_complete_order['complete_order_count'] * 100 /$sub_user_order['all_order_count']).'%';//成交率,转为百分比
        return [array_merge($sub_user_order, $sub_user_complete_order,['complete_probability'=>$complete_probability])];
    }
}