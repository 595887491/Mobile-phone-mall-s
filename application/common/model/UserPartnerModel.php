<?php
/**
 * @Author: 陈静
 * @Date: 2018/04/19 17:59:40
 * @Description:
 */

namespace app\common\model;

use app\common\logic\distribution\DistributionDevideLogLogic;
use gmars\nestedsets\NestedSets;
use think\Db;
use think\Page;

class UserPartnerModel extends NestedsetsModel
{
    protected $table = 'cf_user_partner';
    protected $pk = 'user_id';
    protected $resultSetType = 'collection';

    //nested配置
    public $nestedConfig = [
        'primaryKey' => 'user_id',
        'leftKey' => 'left_key',
        'rightKey' => 'right_key',
        'levelKey' => 'level',
        'parentKey' => 'parent_id',
    ];

    //对应cf_users的表
    public function usersRelation()
    {
        $this->hasOne('UserModel','user_id','user_id')->field('user_type,id_card_num,id_card_name',true);
    }

    //对应tp_users的表
    public function userRelation()
    {
        $this->hasOne('Users','user_id','user_id')->field('user_id,nickname,reg_time,mobile,last_login');
    }

    //获取用户当前的,kpi的排名(整条分支)
    public function getUserRankingByKpi($user_id)
    {
        try{
            //查找当前节点信息
            $currentUserInfo = $this->getCurrentUserInfo($user_id)->toArray();

            //查找整条链接的所有父节点
            $parentInfoDatas = $this
                ->field('user_id,parent_id,partner_kpi,level')
                ->where('left_key','<=',$currentUserInfo['left_key'])
                ->where('right_key','>=',$currentUserInfo['right_key'])
                ->whereOr(function ($query)use($currentUserInfo){
                    $query->where('left_key','>=',$currentUserInfo['left_key'])
                        ->where('right_key','<=',$currentUserInfo['right_key']);
                })
                ->order('partner_kpi DESC')
                ->select()->toArray();
        }catch (\Throwable $exception){
            //记录日志
            $parentInfoDatas = [];
            \app\common\library\Logs::sentryLogs($exception);
        }
        $rankStatus = [];

        if (empty($parentInfoDatas) || count($parentInfoDatas) == 1) {
            $rankStatus = [
                'rank_num' => 1,
                'msg' => '江山稳固'
            ];
        }
        $rankingNum = array_search($user_id,array_column($parentInfoDatas,'user_id')) + 1;

        if ($rankingNum >= 2) {
            $rankStatus['rank_num'] = $rankingNum;
            $rankStatus['msg'] = '加油你行的';
        }

        if ($rankingNum == 1){
            $rankStatus['rank_num'] = 1;
            if ( count($parentInfoDatas) >= 2 && ($parentInfoDatas[0]['partner_kpi'] - $parentInfoDatas[0]['partner_kpi'] ) > 10 ) {
                $rankStatus['msg'] = '江山稳固';
            }
            if ( count($parentInfoDatas) >= 2
                && ($parentInfoDatas[0]['partner_kpi'] - $parentInfoDatas[0]['partner_kpi']) < 10
                && ($parentInfoDatas[0]['partner_kpi'] - $parentInfoDatas[0]['partner_kpi']) >1
            ) {
                $rankStatus['msg'] = '要努力了';
            }
            if ( count($parentInfoDatas) >= 2
                && ($parentInfoDatas[0]['partner_kpi'] - $parentInfoDatas[0]['partner_kpi']) <= 1
            ) {
                $rankStatus['msg'] = '加油你行的';
            }
        }

        return $rankStatus;
    }

    //获取用户当前的排名(所有合伙人)
    public function getUserRankingByAllParnter($user_id)
    {

        $userPartnerInfo = $this->alias('a')
            ->field('a.*,b.reg_time')
            ->where('a.user_id',$user_id)
            ->join(['tp_users' => 'b'],'a.user_id = b.user_id','LEFT')
            ->find();

        $num = $this->alias('a')
            ->field('a.user_id,a.partner_kpi,c.nickname,c.head_pic')
            ->join(['cf_users' => 'b'], 'a.user_id = b.user_id', 'LEFT')
            ->join(['tp_users' => 'c'], 'a.user_id = c.user_id', 'LEFT')
            ->order('a.partner_kpi DESC,c.reg_time DESC')
            ->where('b.first_agent_id', '=', $user_id)
            ->where('a.partner_kpi', '>=', $userPartnerInfo->partner_kpi)
            ->where('c.reg_time', '>=', $userPartnerInfo->reg_time)
            ->count();

        return $num + 1;

    }

    //获取用户下级会员订单总额
    public function getUserChildTotalOrderAmount($user_id)
    {
        $userUserModel = new UserUserModel();
        $childUserArr = $userUserModel->getLevelChild($user_id);

        $childUserIdArr = array_column($childUserArr,'user_id');

        //查询订单（不含支付后取消，发起退款或退货的订单）
        $orderModel = new Order();

        //已完成
        $where1 = [
            'user_id' => ['in',$childUserIdArr],
            'prom_type' => ['<',5],
            'order_status' => 4
        ];

        //待收货
        $where2 = [
            'user_id' => ['in',$childUserIdArr],
            'prom_type' => ['<',5],
            'order_status' => 1 ,
            'shipping_status' => 1
        ];

        //待发货
        $where3 = [
            'user_id' => ['in',$childUserIdArr],
            'prom_type' => ['<',5],
            'order_status' => ['in',[0,1]] ,
            'shipping_status' => ['neq',1] ,
            'pay_status' => 1 ,
        ];

        $totalAmount = $orderModel
            ->whereOr(function ($query) use ($where1){
                $query->where($where1);
            })
            ->whereOr(function ($query) use ($where2){
                $query->where($where2);
            })
            ->whereOr(function ($query) use ($where3){
                $query->where($where3);
            })
            ->sum('total_amount');

        return $totalAmount;
    }
    
    
    //获取代理商的下级所有合伙人
    public function getAgentPartnerNums($agent_id,$type = 0)
    {
        $partnerList = $this->alias('a')->join(['cf_users' => 'b'],'a.user_id = b.user_id','LEFT');
        $partnerListObj = clone $partnerList;
        //总数
        $counts = (clone $partnerList)->where('b.first_agent_id','=',$agent_id)->count();

        //今日数量
        $todayCount = (clone $partnerList)->where('be_partner_start', [
            '>=', strtotime(date('Y-m-d', time()))
        ])->bind([
            'where_be_partner_start_0'=> strtotime(date('Y-m-d', time())),
        ])->where('b.first_agent_id','=',$agent_id)->count();

        //全部会员数量
        $userUserModel = new UserUserModel();
        $func = function (&$value) use ($userUserModel){
            $value['be_partner_start'] = date('Y-m-d H:i',$value['be_partner_start']);
            if ($value['mobile']) {
                $value['mobile'] = substr_replace($value['mobile'],'****',3,4);
            }
            $childArr = array_count_values(array_column($userUserModel->getLevelChild($value['user_id']),'relative_level'));
            $value['first_child_nums'] = $childArr[1] ?? 0;
            unset($childArr[1]);
            $value['second_child_nums'] = $childArr[2] ?? 0;
            unset($childArr[2]);
            $value['third_child_nums'] = array_sum($childArr) ?? 0;
        };

        $pageObj = new Page($counts,10,['type'=>$type]);
        if ($type == 0) {
            $partnerListData = $partnerListObj
                ->field('a.user_id,a.be_partner_start,c.nickname,c.mobile,c.reg_time,c.head_pic')
                ->join(['tp_users' => 'c'],'a.user_id = c.user_id','LEFT')
                ->limit($pageObj->firstRow ,10)
                ->where('b.first_agent_id','=',$agent_id)->order('be_partner_start DESC')->select();

            if ($partnerListData) {
                $partnerListData = $partnerListData->toArray();
            }else{
                return [
                    'total_count' => $counts,
                    'today_count' => $todayCount,
                    'partner_list_data' => [],
                ];
            }
        }
        if ($type == 1 ) {
            //查询出县代理商
            $userAgentModel = new UserAgentModel();
            $countyAgent = $userAgentModel->getTreeByUserId($agent_id,2);

            if ($countyAgent) {
                $countyAgentArr = array_column($countyAgent,'user_id');
            }else{
                return [
                    'total_count' => $counts,
                    'today_count' => $todayCount,
                    'partner_list_data' => [],
                ];
            }

            $partnerListData = $partnerListObj
                ->field('a.user_id,a.be_partner_start,c.nickname,c.mobile,c.reg_time')
                ->join(['tp_users' => 'c'],'a.user_id = c.user_id','LEFT')
                ->limit($pageObj->firstRow ,10)
                ->where('b.first_agent_id','in',$countyAgentArr)->order('be_partner_start DESC')
                ->select();

            if ($partnerListData) {
                $partnerListData = $partnerListData->toArray();
            }else{
                return [
                    'total_count' => $counts,
                    'today_count' => $todayCount,
                    'partner_list_data' => [],
                ];
            }
        }

        if ($type == 2 ) {
            //查询出县代理商
            $userAgentModel = new UserAgentModel();
            $townAgent = $userAgentModel->getTreeByUserId($agent_id,3);
            if ($townAgent) {
                $townAgentArr = array_column($townAgent,'user_id');
            }else{
                return [
                    'total_count' => $counts,
                    'today_count' => $todayCount,
                    'partner_list_data' => [],
                ];
            }

            $partnerListData = $partnerListObj
                ->field('a.user_id,a.be_partner_start,c.nickname,c.mobile,c.reg_time')
                ->join(['tp_users' => 'c'],'a.user_id = c.user_id','LEFT')
                ->limit($pageObj->firstRow ,10)
                ->where('b.first_agent_id','in',$townAgentArr)->order('be_partner_start DESC')
                ->select();

            if ($partnerListData) {
                $partnerListData = $partnerListData->toArray();
            }else{
                return [
                    'total_count' => $counts,
                    'today_count' => $todayCount,
                    'partner_list_data' => [],
                ];
            }
        }

        define('NO_P',1);//解决分页数据问题
        array_walk($partnerListData,$func);

        return [
            'total_count' => $counts,
            'today_count' => $todayCount,
            'partner_list_data' => $partnerListData,
        ];
    }

    public function getPartnerInfoForAgent($agent_id)
    {
        $partnerId = I('get.partner_id',0);

        $userInfo = $this->alias('a')
            ->field('a.partner_kpi,b.nickname,b.reg_time,b.head_pic,b.mobile,b.last_login,c.wallet_accumulate_income')
            ->join(['tp_users' => 'b'],'a.user_id = b.user_id','LEFT')
            ->join(['cf_users' => 'c'],'a.user_id = c.user_id','LEFT')
            ->where('b.user_id',$partnerId)->find();

        if ($userInfo) {
            $userUserModel = new UserUserModel();
            $userData = $userInfo->toArray();
            $userData['reg_time'] = date('Y-m-d',$userData['reg_time']);
            $userData['last_login'] = date('Y-m-d',$userData['last_login']);
            $userData['mobile'] = substr_replace($userData['mobile'],'****',3,4);
            define('NO_P',1);//$userUserModel->getLevelChild()方法有分页，这里不要它分页 TODO 右后有时间重写一下这个方法
            $childArr = array_count_values(array_column($userUserModel->getLevelChild($partnerId),'relative_level'));
            $userData['first_child_nums'] = $childArr[1] ?? 0;
            unset($childArr[1]);
            $userData['second_child_nums'] = $childArr[2] ?? 0;
            unset($childArr[2]);
            $userData['third_child_nums'] = array_sum($childArr) ?? 0;

            //查询当前用户的子用户
            $nestedObj = new NestedSets($userUserModel);

            $userTreeDatas = $nestedObj->getPath($partnerId);

            //找出分成日志中属于自己子类的订单
            $distributModel = new DistributionDevideLogLogic();
            //找出分成表中关于合伙人的订单
            $divideLogData = $distributModel->field('order_sn')
                ->where('to_user_id',$agent_id)
                ->where('from_user_id','in',array_column($userTreeDatas,'user_id'))
                ->select();

            if ($divideLogData) {
                $divideLogData = array_unique(array_column(collection($divideLogData)->toArray(),'order_sn'));
            }

            $userData['order_nums'] = count(collection($divideLogData)->toArray());
            $partnerDivideOrder = $distributModel
                ->where('to_user_id',$partnerId)
                ->getField('order_sn',true);
            $userData['order_nums'] = count(array_unique($partnerDivideOrder));

            $contributIncome = $distributModel->where('order_sn','in',$divideLogData)->sum('divide_money');

            $userData['contribut_income'] = round($contributIncome,2);
        }
        return $userData;
    }

    /*
     * @Author :赵磊
     * 代理商获取合伙人信息
     * */
    public function getParter($agentId)
    {
        $page = I('get.p',1);
        $userModel = new Users();
        $partner = $this->alias('a')
                 ->join(['cf_users' => 'b'],'a.user_id = b.user_id','LEFT')
                 ->field('a.user_id,a.partner_kpi')
                 ->join(['tp_users' => 'c'],'a.user_id = c.user_id','LEFT')
                 ->order('a.partner_kpi DESC,c.reg_time DESC')
                 ->where('b.first_agent_id','=',$agentId)->select();
        $partner = $partner->toArray();
        for ($i=0;$i<count($partner);$i++){
            $partner[$i]['user'] = $userModel->getHeadpic($partner[$i]['user_id']);
            $partner[$i]['user']['mobile']= phoneToStar($partner[$i]['user']['mobile']);
            $partner[$i]['mobile']= $partner[$i]['user']['mobile'];
            $partner[$i]['nickname']= $partner[$i]['user']['nickname'];
            $partner[$i]['head_pic']= $partner[$i]['user']['head_pic'];
            $partner[$i]['reg_time']= $partner[$i]['user']['reg_time'];
        }
        if ($page && !defined('NO_P') && !empty($res)) {
            $counts = count($partner);
            $pageObj = new Page($counts,20);
            $partner = array_slice($partner,$pageObj->firstRow ,20);
        }
        if (!empty($partner)){
            $reg_time = array_column($partner,'reg_time');
            array_multisort($reg_time, SORT_DESC, $partner);
        }
        return $partner;
    }

}