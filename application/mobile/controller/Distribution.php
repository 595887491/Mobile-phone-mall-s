<?php
/**
 * @Author: 陈静
 * @Date: 2018/04/23 13:56:17
 * @Description:
 */

namespace app\mobile\controller;

use app\common\library\Logs;
use app\common\logic\distribution\DistributionDevideLogLogic;
use app\common\logic\distribution\PartnerLogic;
use app\common\logic\OrderLogic;
use app\common\logic\Pay;
use app\common\logic\PlaceOrder;
use app\common\logic\profit\ProfitLogLogic;
use app\common\logic\UserAddressLogic;
use app\common\model\AccountLogModel;
use app\common\model\ApplyPartnerModel;
use app\common\model\DistributeDivideLog;
use app\common\model\OrderGoods;
use app\common\model\PartnerRankActivity;
use app\common\model\UserAddress;
use app\common\model\UserAgentModel;
use app\common\model\UserModel;
use app\common\model\UserPartnerModel;
use app\common\model\Users;
use app\common\model\UserUserModel;
use app\mobile\validate\DistributionValidate;
use app\task\controller\CalculateKpi;
use think\AjaxPage;
use think\Cookie;
use think\Db;
use think\Exception;
use think\Page;
use think\Verify;

class Distribution extends MobileBase
{

    public function _initialize()
    {
        parent::_initialize();

        $this->checkUserLogin(['commonUserIndex']);
    }

    //分销普通用户首页
    public function index()
    {
        $userModel = new UserModel();
        //获取用户基本数据和身份
        $data = $userModel->getUserRelationIdentity($this->user_id);
        if ($data == false) {
            return outPut('用户数据有误');
        }

        //*****************************所有用户具有的相同数据****start***************************
        $distributionLogic = new DistributionDevideLogLogic();
        //1.待返利
        $data['wait_earnings'] = round($distributionLogic->where(['to_user_id' => $this->user_id,'is_divided' => 0])->sum('divide_money'),2);
        //2.已返利收益
        $data['have_earnings'] = round($data['agent_partner_earnings'],2) ?? 0;
        //2.会员收益（不可提现收益）
        $data['no_pick_money'] = $data['user_earnings'];
        //3.可提现收益
        $data['can_pick_money'] = $data['partner_earnings_residue'] + $data['agent_earnings_residue'];
        //4.累计收益(代表的是代理商和合伙人的收益)
        $data['total_earnings'] = round($data['agent_partner_earnings']+$data['wait_earnings'],2) ?? 0;
        //5.判断申请状态
        $data['apply_status'] = (new ApplyPartnerModel())->judgeApplyStatus($this->user_id,$data['identity']);
        //6.查询会员总数
        $userUserModel = new UserUserModel();
        $currentUserInfo = $userUserModel->getCurrentUserInfo($this->user_id);
        if ($currentUserInfo) {
            $data['total_child_num'] = $userUserModel->getLevelChild($this->user_id,[], true);
            $data['first_child_num'] = $userUserModel->getLevelChild($this->user_id,['a.level'=>$currentUserInfo['level'] + 1 ], true);
            $data['second_child_num']  = $userUserModel->getLevelChild($this->user_id,['a.level'=>$currentUserInfo['level'] + 2 ], true);
        }else{
            $data['total_child_num'] = 0;
            $data['first_child_num'] = 0;
            $data['second_child_num'] = 0;
        }

        //限制用户提取
        if ($data['can_pick_money'] >= 100) {
            $this->assign('limit_money',1);
        }else{
            $this->assign('limit_money',0);
        }

        //*****************************只有合伙人身份具有数据*****start************************
        $userPartnerModel = new UserPartnerModel();
        if ($data['identity']['partner'] == 1 && $data['identity']['agent'] == 0 ) {
            //合伙人的排行
            $data['rank_status'] = $userPartnerModel->getUserRankingByKpi($this->user_id);
            $data['agentInfo'] = $this->getAgentInfo();//获取合伙人的代理商信息
            $data['be_partner_end'] = date('Y-m-d',$data['agentInfo']['be_partner_end']);//身份有效期
        }

        //*****************************代理商具有数据*****start************************
        if ($data['identity']['agent']) {
            //合伙人数量
            $partnerListObj = $userPartnerModel->alias('a')
                ->join(['cf_users' => 'b'],'a.user_id = b.user_id','LEFT')
                ->field('a.user_id,a.partner_kpi')
                ->join(['tp_users' => 'c'],'a.user_id = c.user_id','LEFT')
                ->order('a.partner_kpi DESC,c.reg_time DESC')
                ->where('b.first_agent_id','=',$this->user_id);

            //平均成就值
            $data['avg_achievement'] = (clone $partnerListObj)->avg('partner_kpi');
            $data['partner_num'] = $partnerListObj->count();

            //代管代理商
            $userLevel = Db::table('cf_user_agent')->where('user_id',$this->user_id)->find();//获取等级
            $data['be_agent_end'] = date('Y-m-d',$userLevel['be_agent_end']);//身份有效期
            $manageAgent = (new UserAgentModel())->manageAgent($this->user_id,$userLevel['agent_level']);
            $this->assign('agentCount',count($manageAgent));//代管代理商数量


            //代理商二维码
//            $agentQRCodeUrl = $distributionLogic->getAgentQRCodeUrl($this->user_id,1, 1);
            $agentInviteCode = Db::table('cf_user_agent')->field('invite_partner_code')->where('user_id',$this->user_id)->find();
            $common_qrcode = urlencode(U('/Mobile/distribution/verifiyInviteCode',[ 'invite_partner_code' => $agentInviteCode['invite_partner_code']],'',true));
            $agentQRCodeUrl = '/index.php?m=Home&c=Index&a=qr_code&data='.urlencode($common_qrcode).'&head_pic='.urlencode($data['head_pic']);
            $this->assign('agentQRCodeUrl', $agentQRCodeUrl);
            $this->assign('agentInviteCode', $agentInviteCode['invite_partner_code']);
        }
        $this->assign('data',$data);

//        halt($data);
        return $this->fetch();
    }

    /**
     * @Author: 陈静
     * @Date: 2018/04/24 10:11:37
     * @Description: 会员列表
     * @param int $type 获得几级的会员 0 代表全部 $type >=3 表示获取缤纷会员
     */
    public function memberList()
    {
        $type = input('type', 0, 'intval');
        //获取用户基本数据和身份
        $data = (new UserModel())->getUserRelationIdentity($this->user_id);
        if ($data == false) {
            return outPut('用户数据有误');
        }
        //计算用户自己的层级
        $userUserModel = new UserUserModel();
        $currentUserInfo = $userUserModel->getCurrentUserInfo($this->user_id);
        if ($currentUserInfo) {
            $userLevel = Db::table('cf_user_agent')->field('agent_level')->where('user_id',$this->user_id)->find();//获取等级
            //代理商查看代管代理商 @Author:赵磊
            if ($userLevel['agent_level'] == 1 || $userLevel['agent_level'] == 2) {
                $manageAgent = (new UserAgentModel())->manageAgent($this->user_id, $userLevel['agent_level']);
                //合伙人数量
                $partnerNum = (new UserPartnerModel())->alias('a')
                    ->join(['cf_users' => 'b'],'a.user_id = b.user_id','LEFT')
                    ->field('a.user_id,a.partner_kpi')
                    ->join(['tp_users' => 'c'],'a.user_id = c.user_id','LEFT')
                    ->order('a.partner_kpi DESC,c.reg_time DESC')
                    ->where('b.first_agent_id','=',$this->user_id)->count();
            }
            //代理商查看合伙人 @Author:赵磊
            if ($userLevel) {
                $partner = (new UserPartnerModel())->getParter($this->user_id);
            }

            if ($type == 0) {
                $level = [];
            } elseif ($type == 1 || $type == 2) {
                $level = ['a.level'=>$currentUserInfo['level'] + $type ];
            } else {
                $level = ['a.level' => ['>=',$currentUserInfo['level'] + $type]];
            }
            $userData = $userUserModel->getLevelChild($this->user_id,$level, false);
            $totalUserNum = $userUserModel->getLevelChild($this->user_id,[], true);
            $firstUserNum = $userUserModel->getLevelChild($this->user_id,['a.level'=>$currentUserInfo['level'] + 1 ], true);
            $secondUserNum = $userUserModel->getLevelChild($this->user_id,['a.level'=>$currentUserInfo['level'] + 2 ], true);
            $overSecondUserNum = $userUserModel->getLevelChild($this->user_id,['a.level' => ['>=',$currentUserInfo['level'] + 3]], true);
        } else {
            $userData = [];
            $totalUserNum = 0;
            $firstUserNum = 0;
            $secondUserNum = 0;
            $overSecondUserNum = 0;
        }
        $func = function (&$value){
            $value['reg_time'] = date('Y.m.d',$value['reg_time']);
            $value['is_general'] = $value['user_type'] & 1 == 1; //普通用户
            $value['is_partner'] = $value['user_type'] & 2 == 2; //合伙人
            $value['is_agent'] = $value['user_type'] & 4 == 4; //代理商
            $value['is_angel'] = $value['user_type'] & 8 == 8;
            if ($value['mobile']) {
                $value['mobile'] = substr_replace($value['mobile'],'****',3,4);
            }
        };
        array_walk($userData,$func);
        if ($userLevel){
            if ($type == 0) {
                $this->assign('memberList', $manageAgent);//代管代理商列表
            } elseif ($type == 1 || $type == 2) {
                $this->assign('memberList', $userData);
            } else {
                $this->assign('memberList',$partner);//合伙人信息
            }
        }else{
            $this->assign('memberList', $userData);
        }

        $this->assign('data', $data);
        $this->assign('agentCount', count($manageAgent));//代管代理商数量
        $this->assign('partnerNum',$partnerNum);//合伙人数量
        $this->assign('total_user_num',$totalUserNum);
        $this->assign('first_user_num',$firstUserNum);
        $this->assign('second_user_num',$secondUserNum);
        $this->assign('over_second_user_num',$overSecondUserNum);
        $this->assign('type',$type);
        if (!IS_AJAX) {
            $this->assign('full_page', 1);
        }
        return $this->fetch('cf_member_list');
    }

    //提现
    public function withdrawDeposit()
    {
        //可提现的钱
        $userInfo = (new UserModel())->getUserInfo($this->user_id);
        $canPickMoney = $userInfo->wallet_partner_income + $userInfo->wallet_agent_income;
        if (IS_AJAX && $_POST) {
            C('TOKEN_ON', true);
            if(!$this->verifyHandle('withdrawals')){
                return outPut(-1,'验证码错误');
            };

            //校验支付密码
            $userModel = new Users();
            $paypwd =$userModel->where('user_id',$this->user_id)->getField('paypwd');
            if (empty($paypwd)) {
                return outPut(-1,'请设置支付密码');
            }
            if ($paypwd != encrypt($_POST['paypwd'])) {
                return outPut(-1,'支付密码错误');
            }

            if ( ($validate = (new DistributionValidate())->goCheck()) !== true) {
                return $validate;
            }

            $data['bank_name'] = $_POST['bank_name'];
            $data['bank_card'] = $_POST['bank_card'];
            $data['money'] = $_POST['money'];
            $data['realname'] = $_POST['realname'];
            $data['create_time'] = time();
            $data['user_id'] = $this->user_id;
            $data['status'] = 0;

            if ($data['money'] > $canPickMoney) {
                return outPut(-1,"你最多可提现{$canPickMoney}账户余额.");
            }

            if ( $data['money'] == 0||  ($data['money'] % 100) != 0 ) {
                return outPut(-1,"提现的金额必须是100的整数倍");
            }

            $withdrawal = M('withdrawals')->where(array('user_id' => $this->user_id, 'status' => 0))->sum('money');

            if ($canPickMoney < ($withdrawal + $data['money'])) {
                return outPut(-1,'已有申请待处理，本次提现余额不足');
            }

            Db::startTrans();
            if (M('withdrawals')->add($data)) {
                //保存银行卡信息
                $bankArr['user_id'] = $this->user_id;
                $bankArr['bank_name'] = $data['bank_name'];
                $bankArr['bank_card'] = $data['bank_card'];
                $bankArr['realname'] = $data['realname'];
                $bankArr['bind_time'] = time();
                $bankArr['is_verified'] = 0;

                $res = Db::table('cf_wallet_bank')->where([
                    'user_id' => $this->user_id,
                    'bank_name' => $data['bank_name'],
                    'bank_card' => $data['bank_card'],
                    'realname' => $data['realname'],
                ])->find();

                if (!$res) {
                    Db::table('cf_wallet_bank')->add($bankArr);
                }

                Db::commit();
                return outPut(1,"已提交申请");
            } else {
                Db::rollback();
                return outPut(-1,'提交失败,联系客服!');
            }
        }
        $this->assign('can_pick_money',$canPickMoney);
        return $this->fetch('withdraw_deposit');
    }

    /**
     * 验证码验证
     * $id 验证码标示
     */
    private function verifyHandle($id)
    {
        $verify = new Verify();
        if (!$verify->check(I('post.verify_code'), $id ? $id : 'user_login')) {
            return false;
        }
        return true;
    }

    //实名认证
    public function authentication()
    {
        $mobile = session('user')['mobile'];
        $this->assign('mobile', substr_replace($mobile, '****', 3, 4));
        //查看实名制认证
        $userModel = new UserModel();
        $userInfo = $userModel->getUserInfo($this->user_id);

        if ($userInfo && $userInfo->id_card_num && $userInfo->id_card_name) {
            $this->assign('is_auth',1);
            $this->assign('id_card_num',substr_replace($userInfo->id_card_num,str_repeat('*',strlen($userInfo->id_card_num) - 8),4,-4));
            $this->assign('id_card_name',$userInfo->id_card_name);
        }
        return $this->fetch();
    }

    //提现明细
    public function withdrawalDetail()
    {
        $dataObj = M('withdrawals')
            ->field('taxfee,pay_code,error_code',true)
            ->where('user_id','=',$this->user_id);

        //判断fenye
        if (I('get.p',0)) {
            $counts = M('withdrawals')
                ->field('taxfee,pay_code,error_code',true)
                ->where('user_id','=',$this->user_id)->count();
            $pageObj = new Page($counts,10);
            $data = $dataObj->limit($pageObj->firstRow ,10)->order('create_time DESC')->select();
        }else{
            $data = $dataObj->limit(0 ,10)->order('create_time DESC')->select();
        }

        if ($data) {
            $func = function (&$value){
                $value['create_time'] = date('Y-m-d H:i',$value['create_time']);
                $value['bank_card'] = substr($value['bank_card'],-4);
            };
            array_walk($data,$func);
        }

        $this->assign('data',$data);
        if (IS_AJAX) {
            return $this->fetch('withdrawal_ajaxGetMore');
        }
        return $this->fetch('withdrawal_detail');
    }

    //余额明细
    public function touchBalanceDetail()
    {

        //判断fenye
        if (I('get.p',0)) {
            $counts = (new AccountLogModel())->where('user_id',$this->user_id)->count();
            $pageObj = new Page($counts,10);
            $data = (new AccountLogModel())->where('user_id',$this->user_id)
                ->where('user_money','<>',0)
                ->limit($pageObj->firstRow ,10)
                ->order('change_time DESC')->select();
        }else{
            $data = (new AccountLogModel())->where('user_id',$this->user_id)
                ->where('user_money','<>',0)
                ->limit(0 ,10)->order('change_time DESC')->select();
        }

        if ($data) {
            $data = $data->toArray();
            $func = function (&$value){
                $value['change_time'] = date('Y-m-d H:i',$value['change_time']);
            };
            array_walk($data,$func);
        }else{
            $data = null;
        }

        $this->assign('data',$data);
        if (IS_AJAX) {
            return $this->fetch('touch_ajaxGetMore');
        }
        return $this->fetch('touch_balance_detail');
    }

    //收益明细
    public function earningsDetails()
    {
        $userModel = new UserModel();
        //获取用户基本数据和身份
        $currentUser = $userModel->getUserRelationIdentity($this->user_id);

        //订单收益
        $func = function(&$value) use (&$data,&$earns){
            $value['mobile'] = substr_replace($value['mobile'],'****',3,4);
            if (check_mobile($value['nickname'])) {
                $value['nickname'] = substr_replace($value['nickname'],'****',3,4);
            }
            //日期为键
            $data[strtotime(date('Y-m-d',$value['add_time']))][] = $value;
            //每日合计
            $earns[strtotime(date('Y-m-d',$value['add_time']))] = 0;
        };

        // -start- 订单收益列表
        $distributionLogic = new DistributionDevideLogLogic();
        $order_earnings_rows = $distributionLogic->getUserOrderEarnings($this->user_id);

        $data = [];
        $earns = [];
        array_walk($order_earnings_rows, $func);
        $order_earnings_rows = $data;
        // -end-订单收益列表
        $everyDayOrderEarns = $distributionLogic->getUserOrderEarnsByFewTime($this->user_id,$earns);

        // -start- 代理商发展合伙人收益
        $profitLogLogic = new ProfitLogLogic();
        $member_earnings_rows = $profitLogLogic->getMemberEarnings($this->user_id); // 会员收益

        $data = [];
        $earns = [];
        array_walk($member_earnings_rows, $func);
        $member_earnings_rows = $data;
        // -end- 代理商发展合伙人收益
        $everyDayProfitEarns = $profitLogLogic->getUserOrderEarnsByFewTime($this->user_id,$earns);

        if(IS_AJAX) {
            $type = input('get.type');
            if ($type == 'member') {
                // 代理商发展合伙人收益
                $this->assign('profit_first_time',array_shift(array_keys($member_earnings_rows)));//合伙人首次时间
                $this->assign('profit_last_time',array_pop(array_keys($member_earnings_rows)));//合伙人首次时间
                $this->assign('every_day_profit_earns',$everyDayProfitEarns);
                $this->assign('member_earnings_rows', $member_earnings_rows);  //会员收益分成
                $this->assign('current_user', $currentUser);  //用户信息
                return $this->fetch('earningsMember_ajaxGetMore');
            } elseif ($type == 'order') {
                // 订单收益
                $this->assign('order_first_time',array_shift(array_keys($order_earnings_rows)));//订单首次时间
                $this->assign('order_last_time',array_pop(array_keys($order_earnings_rows)));//订单最后时间
                $this->assign('every_day_order_earns',$everyDayOrderEarns);
                $this->assign('order_earnings_rows', $order_earnings_rows);  //订单收益分成
                $this->assign('current_user', $currentUser);  //用户信息
                return $this->fetch('earningsOrder_ajaxGetMore');
            }
        }

        //总收益统计
        $order_total = $distributionLogic->getUserTotalEarnings($this->user_id);
        // 发展合伙人收益统计
        $profit_total = $profitLogLogic->getUserTotalProfitEarnings($this->user_id);

        //-start-每日总收益
        $start_time = strtotime(date('Ymd',time()));
        $end_time = time();
        //今日订单收益
        $order_total_today = $distributionLogic->getUserTotalEarnings($this->user_id,$start_time, $end_time);
        // 今日收益（代理商发展合伙人额外收益）
        $profit_total_today = $profitLogLogic->getUserTotalProfitEarnings($this->user_id,$start_time, $end_time);
        //-end-每日总收益

        //-start-每月总收益
        $start_time = strtotime(date('Y-m').'-01');
        $end_time = time();
        //每月订单收益
        $order_total_month = $distributionLogic->getUserTotalEarnings($this->user_id,$start_time, $end_time);
        // 每月代理商发展合伙人收益
        $profit_total_month = $profitLogLogic->getUserTotalProfitEarnings($this->user_id,$start_time, $end_time);
        //-end-每月总收益

        $this->assign('order_first_time',array_shift(array_keys($order_earnings_rows)));//订单首次时间
        $this->assign('order_last_time',array_pop(array_keys($order_earnings_rows)));//订单最后时间
        $this->assign('profit_first_time',array_shift(array_keys($member_earnings_rows)));//合伙人首次时间
        $this->assign('profit_last_time',array_pop(array_keys($member_earnings_rows)));//合伙人首次时间
        $this->assign('current_user', $currentUser);  //用户信息
        $this->assign('member_earnings_rows', $member_earnings_rows);  //会员收益分成
        $this->assign('order_earnings_rows', $order_earnings_rows);  //订单收益分成
        $this->assign('every_day_order_earns',$everyDayOrderEarns); //每日订单收益
        $this->assign('every_day_profit_earns',$everyDayProfitEarns); //每日代理商发展合伙收益
        $this->assign('order_total', $order_total);//订单总收益
        $this->assign('order_total_today', $order_total_today);//订单今日总收益
        $this->assign('profit_total', $profit_total);//代理商的合伙人分成总收益
        $this->assign('profit_total_today', $profit_total_today);//代理商的合伙人分成今日总收益
        $this->assign('month_total_earns',$order_total_month + $profit_total_month);

        return $this->fetch();
    }
    
    //收益明细统计
    public function statisticsEarns()
    {
        $type = I('get.type',0);

        $distributionLogic = new DistributionDevideLogLogic();
        $profitLogLogic = new ProfitLogLogic();

        if ($type) {
            $start_time = I('get.month',0);
            $end_time = $start_time + date('t') * 24 * 3600 - 1;

            //每月订单收益
            $order_total_month = $distributionLogic->getUserTotalEarningsByTime($this->user_id,$start_time,$end_time);
            // 每月代理商发展合伙人收益
            $profit_total_month = $profitLogLogic->getUserTotalProfitEarningsByTime($this->user_id,$start_time,$end_time);

            $func1 = function ($value) use (&$data){
                $data[strtotime(date('Y-m-d',$value['add_time']))] += $value['divide_money'];
            };
            $func2 = function ($value) use (&$data){
                $data[strtotime(date('Y-m-d',$value['add_time']))] += $value['profit_money'];
            };

            array_walk($order_total_month,$func1);
            $data1 = (array)$data;
            $data = [];
            array_walk($profit_total_month,$func2);
            $data2 = (array)$data;

            $diffData1 = array_diff_key($data1,$data2);
            $diffData2 = array_diff_key($data2,$data1);
            $intersectData = array_intersect_key($data1,$data2);

            foreach ($intersectData as $k => $v){
                $intersectData[$k] = $v + $data2[$k];
            }

            $data = $diffData1+$intersectData+$diffData2;
            krsort($data);

            $this->assign('data',$data);
            $this->assign('title',date('Y年m月',$start_time));
            return $this->fetch('statisticsDayEarns');
        }

        //每月订单收益
        $order_total_month = $distributionLogic->getUserTotalEarningsByTime($this->user_id);
        // 每月代理商发展合伙人收益
        $profit_total_month = $profitLogLogic->getUserTotalProfitEarningsByTime($this->user_id);

        $func1 = function ($value) use (&$data){
            $data[strtotime(date('Y-m',$value['add_time']))] += $value['divide_money'];
        };
        $func2 = function ($value) use (&$data){
            $data[strtotime(date('Y-m',$value['add_time']))] += $value['profit_money'];
        };

        array_walk($order_total_month,$func1);
        $data1 = (array)$data;
        $data = [];
        array_walk($profit_total_month,$func2);
        $data2 = (array)$data;

        $diffData1 = array_diff_key($data1,$data2);
        $diffData2 = array_diff_key($data2,$data1);
        $intersectData = array_intersect_key($data1,$data2);

        foreach ($intersectData as $k => $v){
            $intersectData[$k] = $v + $data2[$k];
        }

        $data = $diffData1 + $intersectData + $diffData2;
        krsort($data);

        $this->assign('data',$data);
        return $this->fetch('statisticsMonthEarns');

    }
    

    // 收益排行
    public function earningsRanking(){
        if (empty($this->user_id)) {
            $this->redirect('User/login');
        }
        $distributionLogic = new DistributionDevideLogLogic();
        $user_user_model = new UserUserModel();

        $ranking_rows = $distributionLogic->getEarningsRanking(); //所有人排行
        foreach ($ranking_rows as $k => $v){
            if ($v['rank'] > 30) { //只显示前30名
                unset($ranking_rows[$k]);
                continue;
            }
            $first_child = $user_user_model->getFirstLevelUser($v['user_id']);
            $ranking_rows[$k]['share_days'] = empty($first_child['reg_time']) ? 0 : floor((time() - $first_child['reg_time'])/86400);
            $ranking_rows[$k]['wallet_accumulate_income'] = sprintf("%.2f", floatval($v['wallet_accumulate_income']));
        }

        // 查询自己的收益排行，为保证效率建议异步查询
        $user_rank = $distributionLogic->getMyEarningsRanking($this->user_id); // 当前用户排名

        $user_child = $user_user_model->getFirstLevelUser($this->user_id);
        $share_days = empty($user_child['reg_time']) ? 0 : floor((time() - $user_child['reg_time'])/86400);

        $front_of_other = ($user_rank['rank_user_count'] == 0 || $user_rank['rank'] == 0 || $user_rank['total_money'] ==0) ? '--' :sprintf("%.2f%%",($user_rank['rank_user_count'] - $user_rank['rank']) * 100/$user_rank['rank_user_count']);
        $this->assign('user_rank', $user_rank['rank']); //用户排名
        $this->assign('my_earning_total', sprintf("%.2f", floatval($user_rank['total_money']))); ; //用户累计收益
        $this->assign('front_of_other', $front_of_other); //用户排名{（n-x）+1  } / n×100%
        $this->assign('share_days',$share_days); //我的累计分享天数
        $this->assign('ranking_rows',$ranking_rows);
        return $this->fetch();
    }

    public function earningsRanking_ajaxGetMore(){
        $distributionLogic = new DistributionDevideLogLogic();
        $user_user_model = new UserUserModel();

        $ranking_rows = $distributionLogic->getEarningsRanking(); //所有人排行
        foreach ($ranking_rows as $k => $v){
            if ($v['rank'] > 30) { //只显示前30名
                unset($ranking_rows[$k]);
                continue;
            }
            $first_child = $user_user_model->getFirstLevelUser($v['user_id']);
            $ranking_rows[$k]['share_days'] = empty($first_child['reg_time']) ? 0 : floor((time() - $first_child['reg_time'])/86400);
            $ranking_rows[$k]['wallet_accumulate_income'] = sprintf("%.2f", floatval($v['wallet_accumulate_income']));
        }
        $this->assign('ranking_rows',$ranking_rows);
        return $this->fetch();
    }

    // 订单收益
    public function earningsFromOrder(){
        $distributionLogic = new DistributionDevideLogLogic();
        $rows = $distributionLogic->getUserOrderEarnings($this->user_id); //分成
        // 获取分页显示
        return $this->fetch();
    }

    /**
     * 会员订单
     * @return mixed
     * @date 2018-04-27 15:02:55
     */
    public function memberOrder(){
        $distributionLogic = new DistributionDevideLogLogic();
        $order_rows = $distributionLogic->getMemberOrder($this->user_id); //订单列表
        $order_count = $distributionLogic->getMemberOrder($this->user_id,1); //订单总条数
        $sub_order_amount = $distributionLogic->getSubOrderAmount($this->user_id);
        array_walk($order_rows,function(&$v){
            $v['mobile'] = substr_replace($v['mobile'],'****',3,4);
            $v['add_time'] = date('Y-m-d',$v['add_time']);
        });
        $this->assign('memberOrder', $order_rows);
        $this->assign('orderCount', $order_count);
        $this->assign('subOrderAmount', $sub_order_amount[0]);

        if (!IS_AJAX) {
            $this->assign('full_page', 1);
        }
        return $this->fetch();
    }

    /**
     * @date 2018-04-27 15:12:54
     * @return mixed
     */
    public function serveTime(){
        // TODO 不是合伙人怎么处理
        $partnerLogic = new PartnerLogic();
        $partnerUserInfo = $partnerLogic->getPartnerUserInfo($this->user_id);
        $partnerUserInfo['is_expired'] = time() > $partnerUserInfo['be_partner_end'] ? 1 : 0;
        if ($partnerUserInfo['is_expired']) {
            $partnerUserInfo['left_days'] = 0;
        } else {
            $partnerUserInfo['left_days'] = floor(($partnerUserInfo['be_partner_end'] - time())/86400);
        }

        // 申请记录
        $apply_log = $partnerLogic->getPartnerApplyLog($this->user_id);
        array_walk($apply_log,function(&$v){
            $apply_way = ['微信端扫码加入','点击微信分享链接', '微信直接登录注册','微信端手机号直接登录','WAP端扫码','小程序扫码','PC端扫码','APP扫码','后台添加'];
            $v['apply_time'] = date('Y.m.d',$v['apply_time']);
            $v['channel_type'] = $apply_way[$v['channel_type']];
            $v['apply_content'] = json_decode(trim($v['apply_content']), true);
        });

        $this->assign('performance_value',tpCache('partner','','cf_config')['performance_value']);//基准成就值
        $this->assign('applyLog', $apply_log);
        $this->assign('partnerUserInfo', $partnerUserInfo);
        return $this->fetch();
    }

    //合伙人数量（代理商拥有功能）
    public function agentPartnerNums()
    {
        $type = I('get.type',0);
        $userPartnerModel = new UserPartnerModel();

        $data = $userPartnerModel->getAgentPartnerNums($this->user_id,$type);

        $data['type'] = $type;
        $this->assign('data',$data);

        if (IS_AJAX) {
            return $this->fetch('agent_ajaxGetMore');
        }

        return $this->fetch();
    }

    //合伙人详情页
    public function agentPartnerDetail()
    {
        $userPartnerModel = new UserPartnerModel();
        $partnerData = $userPartnerModel->getPartnerInfoForAgent($this->user_id);
        $this->assign('partner_data',$partnerData);
        return $this->fetch();
    }

    //合伙人成就排行
    public function partnerAchievementRank()
    {
        //总收益
        $distributionLogic = new DistributionDevideLogLogic();
        $userUserModel = new UserUserModel();
        $func = function (&$value)use($distributionLogic,$userUserModel){
            $where = [
                'to_user_id' => $value['user_id'],
                'is_divided' => 1
            ];
            $divideData = $distributionLogic->where($where)->select();

            if ($divideData) {
                $orderSnArr = array_unique(array_column($divideData->toArray(),'order_sn'));
                //查询订单交易额
                $orderObj = new \app\common\model\Order();
                $orderData = $orderObj->where('order_sn','in',$orderSnArr)->select();
            }
            if ($orderData) {
                $orderData = $orderData->toArray();
                $value['order_deal_success'] = array_sum(array_column($orderData,'order_amount'));
            }

            $user_level = (new UserUserModel())->where('user_id',$value['user_id'])->getField('level');
            $childArr = $userUserModel->getLevelChild($value['user_id'],['a.level' => $user_level + 1],true);
            $value['first_child_nums'] = $childArr ?? 0;
        };

        $data['user_id'] = $this->user_id;
        $data['user_nickname'] = (new Users())->where('user_id',$this->user_id)->getField('nickname');
        $data['head_pic'] = (new Users())->where('user_id',$this->user_id)->getField('head_pic');

        $week = date('w');// 0 1 2  3  4  5  6
        if ($week) {
            $mondayDays = -$week - 6;
            $sundayDays = - $week;
        }else{
            $mondayDays =  -6;
            $sundayDays =  0;
        }
        $data['start_time'] = date('Y-m-d',strtotime(   $mondayDays.' days' ) - date('s')-date('i')*60-date('H')*60*60);
        $data['end_time'] = date('Y-m-d',strtotime(date('Y-m-d',strtotime(  $sundayDays .' days' ))) + 24 * 3600 -1);

        $func($data);
        unset($data['user_id'],$data['first_child_nums']);

        //合伙人数量
        $userPartnerModel = new UserPartnerModel();

        $partnerList = $userPartnerModel->alias('a')->join(['cf_users' => 'b'],'a.user_id = b.user_id','LEFT');
        $partnerListObj = clone $userPartnerModel->field('a.user_id,a.partner_kpi,c.nickname,c.head_pic')
            ->join(['tp_users' => 'c'],'a.user_id = c.user_id','LEFT')
            ->order('a.partner_kpi DESC,c.reg_time DESC');
        $partnerKpiAvgObj = clone $partnerListObj;
        //合伙人总数
        $data['partner_count'] = (clone $partnerList)->where('b.first_agent_id','=',$this->user_id)->count();

        $pageObj = new Page($data['partner_count'],10);
        $data['partner_list_data'] = $partnerListObj
            ->limit($pageObj->firstRow ,10)
            ->where('b.first_agent_id','=',$this->user_id)->select();

        if ($data['partner_list_data']) {
            $data['partner_list_data'] = $data['partner_list_data']->toArray();
            array_walk($data['partner_list_data'],$func);

            //增加排名
            foreach ($data['partner_list_data'] as $k => &$v) {
                $v['rank'] = $pageObj->firstRow + $k + 1;
            }
            //合伙人kpi总数平均值
            $data['avg_achievement'] = $partnerKpiAvgObj->where('b.first_agent_id','=',$this->user_id)->avg('a.partner_kpi');
        }else{
            return [];
        }

        $this->assign('data',$data);

        if(IS_AJAX){
            return $this->fetch('partner_ajaxGetMore');
        }

        return $this->fetch();
    }

    //普通用户分销首页
    public function commonUserIndex()
    {
        $userModel = new UserModel();
        //获取用户基本数据和身份
        $data = $userModel->getUserRelationIdentity($this->user_id);
        //判断申请状态
        $data1['apply_status'] = (new ApplyPartnerModel())->judgeApplyStatus($this->user_id,$data['identity']);
        $data1['identity'] = $data['identity'];
        //查询合伙人的商品
        $data1['goods_data'] = Db::name('goods')
            ->field('goods_id,goods_name,goods_remark,original_img,shop_price,market_price')
            ->where('goods_id','in','250,346')
            ->where('is_on_sale',1)
            ->select();

        dealGoodsPrice($data1['goods_data']);

        //查询合伙人
        $data1['partner_info'] = (new UserPartnerModel())->alias('a')
            ->join('users b','a.user_id = b.user_id')
            ->field('b.nickname,b.head_pic')
            ->order('be_partner_start DESC')
            ->limit(20)
            ->select();

        foreach ($data1['partner_info'] as &$v) {
            if (is_mobile($v['nickname'])) {
                $v['nickname'] = phoneToStar($v['nickname']);
            }
            if (empty($v['head_pic'])) {
                $v['head_pic'] = 'http://cdn.cfo2o.com/data/avatar/user_head_default01.png';
            }
        }

        $data1['is_login'] = $this->user_id ;
        $this->assign('data',$data1);
        return $this->fetch();
    }

    //验证邀请码
    public function verifiyInviteCode()
    {
        $invitePartnerCode = strtoupper(I('invite_partner_code'));

        if (empty($invitePartnerCode)) {
            $invitePartnerCode = Cookie::get('invite_partner_code');
        }else{
            Cookie::set('invite_partner_code',$invitePartnerCode);
        }

        $this->assign('invite_partner_code',$invitePartnerCode);

        return $this->fetch();
    }

    //合伙人申请协议
    public function applyAgreement()
    {
        $invitePartnerCode = strtoupper(I('invite_partner_code'));

        //更新Cookie
        if ( $invitePartnerCode ) {
            Cookie::set('invite_partner_code',$invitePartnerCode);
        }

        if (!Cookie::get('invite_partner_code')) {
            return $this->ajaxReturn(['code' => -1 , 'msg' => '没有检测到邀请码']);
        }

        $res = Db::table('cf_user_agent')
            ->where('invite_partner_code',Cookie::get('invite_partner_code'))->find();

        if (!$res) {
            return $this->ajaxReturn(['code' => -1 , 'msg' => '合伙人邀请码不正确']);
        }

        if (IS_AJAX) {
            return $this->ajaxReturn(['code' => 1 , 'msg' => 'success']);
        }

        return $this->fetch();
    }

    //选择领取精品收货地址,生成订单
    public function selectAddress()
    {
        if (!Cookie::get('invite_partner_code')) {
            return $this->error('没有检测到邀请码');
        }
        //查看实名制认证
        $userModel = new UserModel();
        $userInfo = $userModel->getUserInfo($this->user_id);
        if (empty($userInfo) || empty($userInfo->id_card_num) || empty($userInfo->id_card_name)){
            $this->redirect('distribution/authentication',['source'=>'apply_partner']);
        }

        //查询商品价格
        $goodsModel = new \app\common\model\Goods();
        $goodsInfo = $goodsModel->where('goods_id',1000000)->find();
        $goodsInfo->market_price = round($goodsInfo->market_price,2);
        //生成订单
        if (IS_POST && $_POST) {
            $address_id = I("address_id/d",0); //  收货地址id
            $address = Db::name('UserAddress')->where("address_id", $address_id)->find();
            $data['order_sn'] = (new OrderLogic())->get_order_sn('partner');
            $data['user_id'] = $this->user_id;
            $data['order_status'] = 0;
            $data['pay_status'] = 0;
            if (!$address_id) {
                $data['shipping_code'] = 'ZITI';
                $data['shipping_name'] = '门店自提';
            }
            $data['consignee'] = $address['consignee'] ? $address['consignee'] : '';
            $data['province'] = $address['province'] ? $address['province'] : '';
            $data['city'] = $address['city'] ? $address['city'] : '';
            $data['district'] = $address['district'] ? $address['district'] : '';
            $data['address'] = $address['address'] ? $address['address'] : '';
            $data['mobile'] = $address['mobile'] ? $address['mobile'] : '';
            $data['user_note'] = Cookie::get('invite_partner_code') ?? '用户浏览器数据异常';
            $data['goods_price'] = $goodsInfo->shop_price;
            $data['order_amount'] = $goodsInfo->shop_price;
            $data['total_amount'] = $goodsInfo->shop_price;
            $data['add_time'] = time();

            $res = Db::name('order')->insertGetId($data);
            Db::startTrans();
            if ($res) {
                //查询商品信息
                $data1['order_id'] = $res;
                $data1['goods_id'] = $goodsInfo->goods_id;
                $data1['goods_name'] = $goodsInfo->goods_name;
                $data1['goods_sn'] = $goodsInfo->goods_sn;
                $data1['goods_num'] = 1;
                $data1['final_price'] = $goodsInfo->shop_price;
                $data1['goods_price'] = $goodsInfo->shop_price;
                $data1['member_goods_price'] = $goodsInfo->shop_price;

                $res1 = (new OrderGoods())->insert($data1);

                if ($res1) {
                    Db::commit();
                    //发起支付
                    $this->redirect(U('Mobile/Cart/cart4',['order_sn' => $data['order_sn'],'source'=>'partner']));
                    return;
                }
                Db::rollback();
            }
            Db::rollback();
        }

        //查询用户地址
        $where = [
            'user_id' => $this->user_id,
            'is_default' => 1,
        ];

        $addressId = I('get.address_id',0);

        if ($addressId) {
            $where = ['address_id' => $addressId];
        }


        $userAddress = (new UserAddress())
            ->where($where)
            ->find();

        if ($userAddress) {
            $userAddress = $userAddress->toArray();
        }

        $start_time = date('Y-m-d');
        $end_time = date('Y-m-d',strtotime('+1 year'));

        $this->assign('user_address',$userAddress);
        $this->assign('start_time',$start_time);
        $this->assign('end_time',$end_time);
        $this->assign('good_info',$goodsInfo);
        $this->assign('source','apply_partner');
        return $this->fetch();
    }

    //申请结果页面
    public function applyResust(){
        $orderSn = I('get.out_trade_no');
        $payStatus = (new \app\common\model\Order())->where('order_sn',$orderSn)->getField('pay_status');
        if ( $orderSn && $payStatus ) {
            $result = true;
        }else {
            $result = false;
        }
        $this->assign('result',$result);
        return $this->fetch();
    }

    //分销说明
    public function distributionIntroduce()
    {
        return $this->fetch();
    }

    //转入余额
    public function shiftToBalances()
    {
        $applyShiftMoney = I('userMoney', 0);

        if (empty($applyShiftMoney) || $applyShiftMoney <= 0) {
            return outPut(-1, '转入金额有误');
        }

        $usersModel = new Users();
        $userInfo = $usersModel->getUserInfo($this->user_id);
        if ($applyShiftMoney > ($userInfo->user_info_relation->wallet_partner_income + $userInfo->user_info_relation->wallet_agent_income)) {
            return outPut(-1, '填入金额大于可转金额');
        }

        Db::startTrans();
        $cfUsersModel = new UserModel();
        try {
            //当申请金额小于合伙人收益时，只转合伙人收益
            if ($applyShiftMoney <= $userInfo->user_info_relation->wallet_partner_income) {
                $res1 = $cfUsersModel->where('user_id', $this->user_id)->update([
                    'wallet_partner_income' => $userInfo->user_info_relation->wallet_partner_income - $applyShiftMoney
                ]);
                $res2 = $usersModel->where('user_id', $this->user_id)->update([
                    'user_money' => ['exp', 'user_money + ' . $applyShiftMoney]
                ]);
            } else {//当申请金额大于合伙人收益时，转合伙人收益和代理商收益
                $res1 = $cfUsersModel->where('user_id', $this->user_id)->update([
                    'wallet_partner_income' => 0,
                    'wallet_agent_income' => $userInfo->user_info_relation->wallet_agent_income - $applyShiftMoney + $userInfo->user_info_relation->wallet_partner_income
                ]);
                $res2 = $usersModel->where('user_id', $this->user_id)->update([
                    'user_money' => ['exp', 'user_money + ' . $applyShiftMoney]
                ]);
            }

            //插入余额明细和提现明细
            $res3 = Db::name('withdrawals')->insert([
                'user_id' => $this->user_id,
                'money' => $applyShiftMoney,
                'create_time' => time(),
                'check_time' => time(),
                'remark' => '转入余额',
                'status' => 2,
            ]);

            $res4 = (new AccountLogModel())->insert([
                'user_id' => $this->user_id,
                'user_money' => $applyShiftMoney,
                'change_time' => time(),
                'desc' => '收益转出到余额',
            ]);

        } catch (Exception $e) {
            Db::rollback();
            return outPut(1, '转出失败，请联系客服',['apply_shift_money' => $applyShiftMoney]);
        }
        if ($res1 && $res2 && $res3 && $res4) {
            Db::commit();
            return outPut(1, '转出成功',['apply_shift_money' => $applyShiftMoney]);
        }
    }

    //排行榜单
    public function rankList(){
        $id = input('id');
        $PartnerRankActivity = new PartnerRankActivity();
        if ($id) {
            $activity = $PartnerRankActivity->activityDetail($id);
        } else {
            $activity = $PartnerRankActivity->nowActivity();
        }
        $activity['rule'] = htmlspecialchars_decode($activity['rule']);
        if (empty($activity) || $activity['status'] ==0) {
//            halt($activity);
            $this->error('该活动不存在或还未开始');
        }
        $rows = $PartnerRankActivity->activityData($activity);
        $scale = explode(',',$activity['reward_scale']);//奖金分配数组
        $saleSum = array_sum(array_column($rows,'total_order'));//有效的销售额
        if ($activity['status'] == 3) {
            $reward = max($activity['init_reward'],$activity['increased_sale_reward']);
            $func = function (&$list,$key) use ($scale,&$my_list,$rows){
                $list['total_order'] = ceil($list['total_order']);//经讨论，向上取整，个人觉得不是很科学
                $list['nickname'] = check_mobile($list['nickname']) ? substr_replace($list['nickname'], '****', 3, 4): mb_substr($list['nickname'],0,6);
                $list['scale'] = round($list['scale'],2).'%';
                if ($list['partner_id'] == session('user')['user_id']) {
                    $pre_user_contribution = isset($rows[$key-1]) ? $rows[$key-1]['contribution']:0;//前一名用户
                    $my_list = $list;
                    $my_list['my_rank'] = ($key+1) > count($scale) ? '--':($key+1);
                    $my_list['differ_member'] = ceil(($pre_user_contribution - $list['contribution']));
                    $my_list['differ_amount'] = ($pre_user_contribution - $list['contribution'])*100;
                }
            };
            array_walk($rows,$func);
        } else {
            $reward = max(round(($saleSum*$activity['sale_reward_scale'])/100, 2), $activity['init_reward']);//（销售额*奖金比例）和 初始奖金，谁大取谁
            $my_list = [];
            $func = function (&$list,$key) use ($scale,$reward,&$my_list,$rows){
                if ($key+1 > count($scale) && !empty($my_list)) return;
                $list['scale_amount'] = round(((isset($scale[$key]) ? $scale[$key] : 0)*$reward) /100, 2);//奖金
                $list['rank']       = $key+1;
                $list['scale']      = (isset($scale[$key]) ? round($scale[$key],2) : 0).'%';//瓜分比例
                $list['total_order'] = ceil($list['total_order']);//经讨论，向上取整，个人觉得不是很科学
                $list['nickname'] = check_mobile($list['nickname']) ? substr_replace($list['nickname'], '****', 3, 4): mb_substr($list['nickname'],0,6);
                if ($list['partner_id'] == session('user')['user_id']) {
                    $pre_user_contribution = isset($rows[$key-1]) ? $rows[$key-1]['contribution']:0;//前一名用户
                    $my_list = $list;
                    $my_list['my_rank'] = ($key+1) > count($scale) ? '--':($key+1);
                    $my_list['differ_member'] = ceil(($pre_user_contribution - $list['contribution']));
                    $my_list['differ_amount'] = ($pre_user_contribution - $list['contribution'])*100;
                }
            };
            array_walk($rows,$func);
        }

        $lists = array_slice($rows,0,count($scale));
        //还未霸占的奖金金额
        $noPersonPlace = count($scale) - count($lists);
        $lists_count = count($lists);
        if ($noPersonPlace > 0) {
            for ($i=1;$i<=$noPersonPlace ;$i++){
                $key = $lists_count+$i-1;
                $lists[] = [
                    'no_person' => 1,
                    'rank'      => $key + 1,
                    'scale'     => $scale[$key].'%',
                    'scale_amount'=> round(((isset($scale[$key]) ? $scale[$key] : 0)*$reward) /100, 2),//奖金
                ];
            }
        }
        $this->assign('reward',floor($reward));
        $this->assign('info',$activity);
        $this->assign('lists',$lists);
        $this->assign('my_list',$my_list);
        return $this->fetch();
    }
    // 所有榜单/往期榜单
    public function allRankList(){
        $PartnerRankActivity = new PartnerRankActivity();
        $condition = [
            'status' => ['<>', 4],//已关闭
            'start_time'    => ['<', time()]//已开始|已过去
        ];
        $count = $PartnerRankActivity->where($condition)->count();
        $page = new AjaxPage($count, 10);
        $lists = $PartnerRankActivity->getList($page,$condition);

        $this->assign('lists',$lists);
        $this->assign('count',$count);
        if (IS_AJAX) {
            return $this->fetch('allRankList_ajax');
        } else {
            return $this->fetch();
        }
    }


    /*
     * @Author:赵磊
     * 合伙人的代理商信息
     * */
    public function getAgentInfo()
    {
        $agentInfo = Db::table('cf_user_partner')
            ->alias('a')
            ->field('a.be_partner_start,a.be_partner_end,c.nickname,c.mobile,c.head_pic')
            ->join(['cf_user_agent'=>'b'],'a.first_agent_id=b.user_id')
            ->join('users c','b.user_id=c.user_id')
            ->where('a.user_id',$this->user_id)
            ->find();
        $agentInfo['be_partner_start'] = date('m-d H:i',$agentInfo['be_partner_start']);
        return $agentInfo;
    }

}