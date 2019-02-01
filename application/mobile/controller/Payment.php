<?php
/**
 * tpshop
 * ============================================================================
 * * 版权所有 2015-2027 深圳搜豹网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.tp-shop.cn
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用最新Thinkphp5助手函数特性实现单字母函数M D U等简写方式
 * ============================================================================
 * $Author: IT宇宙人 2015-08-10 $
 */

namespace app\mobile\controller;

use app\common\library\Logs;
use app\common\logic\SmsLogic;
use app\common\model\ApplyPartnerModel;
use app\common\model\PayLogModel;
use app\common\model\DistributeDivideLog;
use think\Db;
use think\Exception;
use think\Log;

class Payment extends MobileBase
{
    public $payment; //  具体的支付类
    public $pay_code; //  具体的支付code

    /**
     * 析构流函数
     */
    public function __construct()
    {
        parent::__construct();

        // 获取支付类型
        $pay_radio = input('pay_radio');
        if (!empty($pay_radio)) {
            $pay_radio = parse_url_param($pay_radio);
            $this->pay_code = $pay_radio['pay_code']; // 支付 code
        } else {
            $this->pay_code = I('get.pay_code');
            unset($_GET['pay_code']); // 用完之后删除, 以免进入签名判断里面去 导致错误
        }
        if (ACTION_NAME == 'pay') {
            // 手机微信内置浏览器，选择支付宝支付时，使用这个配置
            $this->pay_code = 'alipayMobile';
        }
        // 获取通知的数据
        if (empty($this->pay_code)) {
            exit('pay_code 不能为空');
        }

        // 导入具体的支付类文件
        include_once "plugins/payment/{$this->pay_code}/{$this->pay_code}.class.php"; // D:\wamp\www\svn_tpshop\www\plugins\payment\alipay\alipayPayment.class.php
        $code = '\\' . $this->pay_code; // \alipay
        $this->payment = new $code();
    }

    /**
     * tpshop 提交支付方式
     */
    public function getCode()
    {
        header("Content-type:text/html;charset=utf-8");
        if (!session('user')) {
            $this->error('请先登录', U('User/login'));
        }

        // 修改订单的支付方式
        $order_id = I('order_id/d'); // 订单id
        $order = Db::name('order')->where("order_id", $order_id)->find();
        if ($order['pay_status'] == 1) {
            $this->error('此订单，已完成支付!');
        }

        $_SESSION['openid'] = $_SESSION['openid'] ?? session('third_oauth')['openid'];

        //微信支付的openid设置映射
        if (config('APP_DEBUG')) {
            $opend = [
                'oDU_ntyp4wFJMLhnLU37ZluQ5Ve4' => 'oGSCeuKIZ3iK_FzZ9MC_iKcbPnbs',
                'oEG-_wX9Mpoh3SQltnf5JV6luR_M' => 'oGSCeuPpquMNHUjzbasVlNF1yO70',
                'oDU_nt1rcLcOdAM7bts3DLKacO60' => 'oGSCeuCQAqLYSUQMX6zJB_FAgRWs',
                'oDU_nt8bgMJouHFwPO5xhj__S47Y' => 'oGSCeuP7HrEIbpgJuaD9svP-bzxk',
                'oDU_ntwFfja7h9sAJqa0vd4i9o1w' => 'oGSCeuMtAzQMFvltzxPHvDkVXCFY',
                'oDU_nt-WN5kGJmGnzc0Pxcnntbjo' => 'oGSCeuNqPQOMPWldo0S_wsw3-EiE',
                'oDU_nt2SD_4yq0MywRGT0avxhwNg' => 'oGSCeuE3jjYynWVRqYIIAzU9UOIQ',
                'oDU_nt5_F2sZ78-GKFylR6ucDHcY' => 'oGSCeuGLUo5-TxrclNTjMwU29w-s',
                'oDU_nt7lJd_RvWbTnvS6PI_IJ2zA' => 'oGSCeuLHuH6D4PFBsJgGGRZVEV1g',
                'oDU_nty_Z4mKwfpwz0uM-K2EKtdw' => 'oGSCeuDkLQb0hLqZHrUir2hYGQY0',
                'oDU_nt3X7am-S_D2wYEyr8pGjBWI' => 'oGSCeuPpquMNHUjzbasVlNF1yO70',
                'oDU_nt5bJTbB0tz0DEq2OIW0qFtc' => 'oGSCeuJoP5w4aW6qEt18LAOoICq4',
                'oDU_nt4Izr8vdZdfr7UIpiTbB9Z8' => 'oGSCeuNFlNHA28MaX7VC8B9BsRvY',
                'oDU_nt1d1OyGgDppHWOLbtN67oyg' => 'oGSCeuBP2czfMx2KYwBivxIhyuKU',
                'oDU_ntwXNlSJ1zRfFPaxNa4jzF5g' => 'oGSCeuFB-Cn81JALRzl6mu0ZQiK0',
                'oDU_nt6FHCIXOb9ka8yRpBkh_p6A' => 'oGSCeuIuU4Hq5bb8xJEOSk-vMQmc',
                'oDU_nt3x9BM-kBAofKG5TC_fmPD0' => 'oGSCeuGCUw549rV08v92Eee1ykyE',
                'oDU_nt2zqbpWdn07KLZ_gFMYBrOw' => 'oGSCeuFRXfthBnXeZJNBQF7___UY',
                'oDU_ntyN-99wfkgz68OmHYT8L3SQ' => 'oGSCeuGHs3tFHNeov3PCPYbBYa7Y',
                'oDU_nt7FG7hROKjkoddPIBMRolZg' => 'oGSCeuE8c7i2L4N1GuzqeD9zf83w',
                'oDU_nt7fc37koZoj0FvCevP1FHzw' => 'oGSCeuH-CNtdzmAJETu4Ebnb06gg',
                'oEG-_wXMYM5d6KkR462BXnqAhwwY' => 'oGSCeuNqPQOMPWldo0S_wsw3-EiE',
                'oEG-_wTtzOIbgqLupoC7Stj06FyY' => 'oGSCeuNCaTQjAcB_-bn19K6aQ-RQ',
                'oEG-_wQt2FJWlsYPE7Dze3c4-Ww8' => 'oGSCeuEAQP-0j1ywHFNNNZJjDlLg',

                'oGSCeuNCaTQjAcB_-bn19K6aQ-RQ' => 'oGSCeuNCaTQjAcB_-bn19K6aQ-RQ',
                'oGSCeuH-CNtdzmAJETu4Ebnb06gg' => 'oGSCeuH-CNtdzmAJETu4Ebnb06gg',
                'oGSCeuKIZ3iK_FzZ9MC_iKcbPnbs' => 'oGSCeuKIZ3iK_FzZ9MC_iKcbPnbs',
                'oGSCeuPpquMNHUjzbasVlNF1yO70' => 'oGSCeuPpquMNHUjzbasVlNF1yO70',
                'oGSCeuCQAqLYSUQMX6zJB_FAgRWs' => 'oGSCeuCQAqLYSUQMX6zJB_FAgRWs',
                'oGSCeuP7HrEIbpgJuaD9svP-bzxk' => 'oGSCeuP7HrEIbpgJuaD9svP-bzxk',
                'oGSCeuMtAzQMFvltzxPHvDkVXCFY' => 'oGSCeuMtAzQMFvltzxPHvDkVXCFY',
                'oGSCeuNqPQOMPWldo0S_wsw3-EiE' => 'oGSCeuNqPQOMPWldo0S_wsw3-EiE',
                'oGSCeuE3jjYynWVRqYIIAzU9UOIQ' => 'oGSCeuE3jjYynWVRqYIIAzU9UOIQ',
                'oGSCeuGLUo5-TxrclNTjMwU29w-s' => 'oGSCeuGLUo5-TxrclNTjMwU29w-s',
                'oGSCeuLHuH6D4PFBsJgGGRZVEV1g' => 'oGSCeuLHuH6D4PFBsJgGGRZVEV1g',
                'oGSCeuDkLQb0hLqZHrUir2hYGQY0' => 'oGSCeuDkLQb0hLqZHrUir2hYGQY0',
                'oGSCeuJoP5w4aW6qEt18LAOoICq4' => 'oGSCeuJoP5w4aW6qEt18LAOoICq4',
                'oGSCeuNFlNHA28MaX7VC8B9BsRvY' => 'oGSCeuNFlNHA28MaX7VC8B9BsRvY',
                'oGSCeuBP2czfMx2KYwBivxIhyuKU' => 'oGSCeuBP2czfMx2KYwBivxIhyuKU',
                'oGSCeuFB-Cn81JALRzl6mu0ZQiK0' => 'oGSCeuFB-Cn81JALRzl6mu0ZQiK0',
                'oGSCeuIuU4Hq5bb8xJEOSk-vMQmc' => 'oGSCeuIuU4Hq5bb8xJEOSk-vMQmc',
                'oGSCeuGCUw549rV08v92Eee1ykyE' => 'oGSCeuGCUw549rV08v92Eee1ykyE',
                'oGSCeuFRXfthBnXeZJNBQF7___UY' => 'oGSCeuFRXfthBnXeZJNBQF7___UY',
                'oGSCeuGHs3tFHNeov3PCPYbBYa7Y' => 'oGSCeuGHs3tFHNeov3PCPYbBYa7Y',
                'oGSCeuE8c7i2L4N1GuzqeD9zf83w' => 'oGSCeuE8c7i2L4N1GuzqeD9zf83w',
                'oGSCeuEAQP-0j1ywHFNNNZJjDlLg' => 'oGSCeuEAQP-0j1ywHFNNNZJjDlLg',
            ];
            $_SESSION['openid'] = $opend[$_SESSION['openid']];
        }

        $payment_arr = Db::name('Plugin')->where('type', 'payment')->getField("code,name");
        Db::name('order')->where("order_id", $order_id)->save(['pay_code' => $this->pay_code, 'pay_name' => $payment_arr[$this->pay_code]]);

        // 订单支付提交
        $config = parse_url_param($this->pay_code); // 类似于 pay_code=alipay&bank_code=CCB-DEBIT 参数
        $config['body'] = getPayBody($order_id);

        if ($this->pay_code == 'weixin' && $_SESSION['openid'] && strstr($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger')) {
            //微信JS支付
            $code_str = $this->payment->getJSAPI($order);
            exit($code_str);
        } elseif ($this->pay_code == 'weixinH5') {
            //微信H5支付
            $return = $this->payment->get_code($order, $config);
            if ($return['status'] != 1) {
                $this->error($return['msg']);
            }
            $this->assign('deeplink', $return['result']);
        } else {
            //其他支付（支付宝、银联...）
            $code_str = $this->payment->get_code($order, $config);
        }
        if (is_weixin() && $this->pay_code == 'alipayMobile') {
            $this->assign('wx_browser', 1);
            $this->assign('pay_code', 'alipayMobile');
            $code_str = "<script>_AP.pay('".$code_str."')</script>";
        }
        $this->assign('code_str', $code_str);
        $this->assign('order_id', $order_id);
        return $this->fetch('payment');  // 分跳转 和不 跳转
    }

    public function getPay()
    {
        //手机端在线充值
        //C('TOKEN_ON',false); // 关闭 TOKEN_ON 
        header("Content-type:text/html;charset=utf-8");
        $order_id = I('order_id/d'); //订单id
        $user = session('user');
        $data['account'] = I('account');
        if ($order_id > 0) {
            M('recharge')->where(array('order_id' => $order_id, 'user_id' => $user['user_id']))->save($data);
        } else {
        	$data['buy_vip'] = I('buy_vip',0);
        	if($data['buy_vip'] == 1){
        		$map['user_id'] = $user['user_id'];
        		$map['buy_vip'] = 1;
        		$map['pay_status'] = 1;
        		$info = M('recharge')->where($map)->order('order_id desc')->find();
        		if (($info['pay_time'] + 86400 * 365) > time() && $user['is_vip'] == 1) {
        			$this->error('您已是VIP且未过期，无需重复充值办理该业务！');
        		}
        	}

        	$data['user_id'] = $user['user_id'];
        	$data['nickname'] = $user['nickname'];
        	$data['order_sn'] = 'recharge'.get_rand_str(10,0,1);
        	$data['ctime'] = time();
        	$order_id = M('recharge')->add($data);
        }
        if ($order_id) {
            $order = M('recharge')->where("order_id", $order_id)->find();
            if (is_array($order) && $order['pay_status'] == 0) {
                $order['order_amount'] = $order['account'];
                $pay_radio = $_REQUEST['pay_radio'];
                $config_value = parse_url_param($pay_radio); // 类似于 pay_code=alipay&bank_code=CCB-DEBIT 参数
                $payment_arr = M('Plugin')->where("`type` = 'payment'")->getField("code,name");
                M('recharge')->where("order_id", $order_id)->save(array('pay_code' => $this->pay_code, 'pay_name' => $payment_arr[$this->pay_code]));
                //微信JS支付
                if ($this->pay_code == 'weixin' && $_SESSION['openid'] && strstr($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger')) {
                    $code_str = $this->payment->getJSAPI($order);
                    exit($code_str);
                } else {
                    $code_str = $this->payment->get_code($order, $config_value);
                }
            } else {
                $this->error('此充值订单，已完成支付!');
            }
        } else {
            $this->error('提交失败,参数有误!');
        }
        $this->assign('code_str', $code_str);
        $this->assign('order_id', $order_id);
        return $this->fetch('recharge'); //分跳转 和不 跳转
    }

    // 服务器点对点 // http://www.tp-shop.cn/index.php/Home/Payment/notifyUrl
    public function notifyUrl()
    {
        $this->payment->response();

        if ($this->pay_code == 'alipayMobile') {
            //如果返回成功则验证签名
            $result = $_POST;
        }

//        $result = array (
//            'payment_type' => '1',
//            'trade_no' => '2018042021001004770547582298',
//            'subject' => '尚美缤纷电商平台订单',
//            'buyer_email' => '280***@qq.com',
//            'gmt_create' => '2018-04-20 15:36:32',
//            'notify_type' => 'trade_status_sync',
//            'quantity' => '1',
//            'out_trade_no' => '20180420153552121p',
//            'out_trade_no' => '201804201535521217',
//            'seller_id' => '2088602222635224',
//            'notify_time' => '2018-04-20 15:36:33',
//            'trade_status' => 'TRADE_SUCCESS',
//            'is_total_fee_adjust' => 'N',
//            'total_fee' => '0.01',
//            'gmt_payment' => '2018-04-20 15:36:33',
//            'seller_email' => '18602859782',
//            'price' => '0.01',
//            'buyer_id' => '2088602203323775',
//            'notify_id' => 'ec9c04dadc1439e2b856a73e6659e0cly1',
//            'use_coupon' => 'N',
//            'sign_type' => 'MD5',
//            'sign' => '0203ac95638a5cdfd89263fbc95c39ff',
//            'pay_code' => 'alipayMobile',
//        );

        //支付成功插入支付日志，和待分成订单
        if ($result && $result['trade_status'] === 'TRADE_SUCCESS') {
            //通知结果映射
            $result['total_fee'] = $result['price'] * 100;
            $result['openid'] = $result['buyer_id'];

            $payLogModel = new PayLogModel();
            //先检查是否有此订单信息
            $orderInfo = $payLogModel->where('pay_code', '=', $this->pay_code)
                ->where('trade_num', '=', $result['out_trade_no'])->find();

            //查询订单是否是拼团
            $orderType = (new \app\common\model\Order())
                ->where('order_sn',$result['out_trade_no'])
                ->getField('prom_type');
            if (empty($orderInfo)) {
                //插入支付日志
                $payLogModel->insertPayLog($result,$this->pay_code);
                //申请合伙人逻辑
                if (strstr($result['out_trade_no'],'p') != false) {
                    (new ApplyPartnerModel())->addApply($result);
                }elseif ($orderType != 6) {
                    //生成待分层订单
                    (new DistributeDivideLog())->createWaitDistribut($result);
                }
            }
        }else{
            return false;
        }

        if (!config('APP_DEBUG')) {
            //短信通知
            $userInfo = (new \app\common\model\Order())->alias('a')
                ->field('b.nickname,b.mobile')
                ->join(['tp_users' => 'b'], 'a.user_id = b.user_id', 'LEFT')
                ->where('a.order_sn', $result['out_trade_no'])->find();
            if ($userInfo) {
                $scene = 5;
                if ($orderType == 6) {
                    $scene = 16;
                }
                if ($orderType == 5) {
                    $scene = 17;
                    $virtualIndate = (new \app\common\model\Order())->alias('a')
                        ->join('order_goods b','a.order_id = b.order_id','left')
                        ->join('goods c','b.goods_id = c.goods_id','left')
                        ->where('a.order_sn',$result['out_trade_no'])
                        ->getField('c.virtual_indate');
                    if ($virtualIndate) {
                        $virtualIndate = date('Y-m-d H:i:s',$virtualIndate);
                    }else{
                        $virtualIndate = date('Y-m-d H:i:s',strtotime('+2 month'));
                    }
                }
                try{
                    (new SmsLogic())->sendSms($scene, $userInfo->mobile, [
                        'name' => $userInfo->nickname, 'order_sn' => $result['out_trade_no'],'virtual_indate' => $virtualIndate
                    ]);
                }catch(Exception $e){
                    Logs::sentryLogs('发送支付成功消息失败：'.$e->getMessage(),$result);
                }
            }
        }
        exit();
    }

    // 页面跳转 // http://www.tp-shop.cn/index.php/Home/Payment/returnUrl
    public function returnUrl()
    {
        $result = $this->payment->respond2(); // $result['order_sn'] = '201512241425288593';
        if (stripos($result['order_sn'], 'recharge') !== false) {
            $order = M('recharge')->where("order_sn", $result['order_sn'])->find();
            $this->assign('order', $order);
            if ($result['status'] == 1)
                return $this->fetch('recharge_success');
            else
                return $this->fetch('recharge_error');
        }
        $order = M('order')->where("order_sn", $result['order_sn'])->find();
        $this->assign('order', $order);

        if ($result['status'] == 1) {
            if ($order['prom_type'] == 6) {//拼团订单
                $my_team = Db::name('team_found')->where('order_id',$order['order_id'])->find();//自己是团长，开团成功
                if (!empty($my_team)) {
                    $team_found = $my_team;
                    $team_follow = Db::name('team_follow')->where('found_id',$team_found['found_id'])->select();
                } else {
                    $my_team = Db::name('team_follow')->where('order_id',$order['order_id'])->find();//自己是成员，参团成功/拼团成功
                    $team_found = Db::name('team_found')->where('found_id',$my_team['found_id'])->find();
                    $team_follow = Db::name('team_follow')->field('follow_user_id,follow_user_nickname,follow_user_head_pic')->where('found_id',$team_found['found_id'])->where('status','>',0)->distinct(true)->select();
                }

                $teamInfo = Db::name('team_activity ta')->field('ta.*,g.original_img')->join('goods g','ta.goods_id=g.goods_id','left')->where('team_id',$order['prom_id'])->find();
                $this->assign('teamInfo',$teamInfo);
                $this->assign('team_found',$team_found);
                $this->assign('team_follow',$team_follow);

                return $this->fetch('teamResult');
            }elseif(strstr($order['order_sn'],'p') != false){
                return $this->redirect(U('Mobile/distribution/applyResust',array('out_trade_no'=> I('get.out_trade_no'))));
            } else {
                return $this->fetch('success');
            }
        } else {
            return $this->fetch('error');
        }
    }
    public function pay(){
        return $this->fetch();
    }
}
