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
namespace app\home\controller; 
use app\common\library\Logs;
use app\common\logic\SmsLogic;
use app\common\model\ApplyPartnerModel;
use app\common\model\PayLogModel;
use app\common\model\DistributeDivideLog;
use think\Db;
use think\Exception;
use think\Log;

class Payment extends Base {
    
    public $payment; //  具体的支付类
    public $pay_code; //  具体的支付code
 
    /**
     * 析构流函数
     */
    public function  __construct() {   
        parent::__construct();           
        
        // tpshop 订单支付提交
        $pay_radio = $_REQUEST['pay_radio'];
        if(!empty($pay_radio)) 
        {                         
            $pay_radio = parse_url_param($pay_radio);
            $this->pay_code = $pay_radio['pay_code']; // 支付 code
        }
        else // 第三方 支付商返回
        {            
            //file_put_contents('./a.html',$_GET,FILE_APPEND);    
            $this->pay_code = I('get.pay_code');
            unset($_GET['pay_code']); // 用完之后删除, 以免进入签名判断里面去 导致错误
        }                        
        //获取通知的数据
        $xml = $GLOBALS['HTTP_RAW_POST_DATA'];      
        $xml = file_get_contents('php://input');
        if(empty($this->pay_code))
            exit('pay_code 不能为空');
        // 导入具体的支付类文件                
        include_once  "plugins/payment/{$this->pay_code}/{$this->pay_code}.class.php"; // D:\wamp\www\svn_tpshop\www\plugins\payment\alipay\alipayPayment.class.php                       
        $code = '\\'.$this->pay_code; // \alipay
        $this->payment = new $code();
        Logs::sentryLogs();
    }
   
    /**
     * tpshop 提交支付方式
     */
    public function getCode(){        
            //C('TOKEN_ON',false); // 关闭 TOKEN_ON
            header("Content-type:text/html;charset=utf-8");            
            $order_id = I('order_id/d'); // 订单id
            session('order_id',$order_id); // 最近支付的一笔订单 id
            if(!session('user')) $this->error('请先登录',U('User/login'));
            $order = Db::name('Order')->where(['order_id' => $order_id])->find();
            if(empty($order) || $order['order_status'] > 1){
                $this->error('非法操作！',U("Home/Index/index"));
            }
            if($order['pay_status'] == 1){
                $this->error('此订单，已完成支付!');
            }
        	// 修改订单的支付方式
            $payment_arr = M('Plugin')->where("`type` = 'payment'")->getField("code,name");
            M('order')->where("order_id",$order_id)->save(array('pay_code'=>$this->pay_code,'pay_name'=>$payment_arr[$this->pay_code]));

            // tpshop 订单支付提交
            $pay_radio = $_REQUEST['pay_radio'];
            $config_value = parse_url_param($pay_radio); // 类似于 pay_code=alipay&bank_code=CCB-DEBIT 参数
            $payBody = getPayBody($order_id);
            $config_value['body'] = $payBody;
            
            //微信JS支付
           if($this->pay_code == 'weixin' && $_SESSION['openid'] && strstr($_SERVER['HTTP_USER_AGENT'],'MicroMessenger')){
               $code_str = $this->payment->getJSAPI($order,$config_value);
               exit($code_str);
           }else{
           	    $code_str = $this->payment->get_code($order,$config_value);
           }
           $this->assign('code_str', $code_str); 
           $this->assign('order_id', $order_id);           
           return $this->fetch('payment');  // 分跳转 和不 跳转 
    }

    public function getPay(){
    	//C('TOKEN_ON',false); // 关闭 TOKEN_ON
    	header("Content-type:text/html;charset=utf-8"); 
    	$order_id = I('order_id/d'); // 订单id
        session('order_id',$order_id); // 最近支付的一笔订单 id
    	// 修改充值订单的支付方式
    	$payment_arr = M('Plugin')->where("`type` = 'payment'")->getField("code,name");
    	
    	M('recharge')->where("order_id", $order_id)->save(array('pay_code'=>$this->pay_code,'pay_name'=>$payment_arr[$this->pay_code]));
    	$order = M('recharge')->where("order_id", $order_id)->find();
    	if($order['pay_status'] == 1){
    		$this->error('此订单，已完成支付!');
    	}
    	$pay_radio = $_REQUEST['pay_radio'];
    	$config_value = parse_url_param($pay_radio); // 类似于 pay_code=alipay&bank_code=CCB-DEBIT 参数
        $order['order_amount'] = $order['account'];
    	$code_str = $this->payment->get_code($order,$config_value);
    	//微信JS支付
    	if($this->pay_code == 'weixin' && $_SESSION['openid'] && strstr($_SERVER['HTTP_USER_AGENT'],'MicroMessenger')){
    		$code_str = $this->payment->getJSAPI($order,$config_value);
    		exit($code_str);
    	}
    	$this->assign('code_str', $code_str);
    	$this->assign('order_id', $order_id);
    	return $this->fetch('recharge'); //分跳转 和不 跳转
    }
    
    // 服务器点对点 // http://www.tp-shop.cn/index.php/Home/Payment/notifyUrl        
    public function notifyUrl(){
        $this->payment->response();
        //异步通知地址，支付成功，生成带分成订单
        if ($this->pay_code == 'weixin') {
            $xml = file_get_contents('php://input');

            //如果返回成功则验证签名
            try {
                $result = \WxPayResults::Init($xml);
            } catch (\WxPayException $e){
                Logs::sentryLogs($e,['msg' => '微信异步通知失败']);
                return false;
            }
        }

        //微信异步通知结果

        //普通订单
//        $result = array (
//            'appid' => 'wx120d8f900766c570',
//            'attach' => 'weixin',
//            'bank_type' => 'CFT',
//            'cash_fee' => '1',
//            'fee_type' => 'CNY',
//            'is_subscribe' => 'Y',
//            'mch_id' => '1306533201',
//            'nonce_str' => '84mc4pjcf3p5nbw9obz5e30d6hs2p12e',
//            'openid' => 'oGSCeuPpquMNHUjzbasVlNF1yO70',
////            'out_trade_no' => '20180710182257241p1522757816',
//            'out_trade_no' => '2018072010225077111522757816',
//            'result_code' => 'SUCCESS',
//            'return_code' => 'SUCCESS',
//            'sign' => 'EAE8F39D696FF84BD2824753C30D7A02',
//            'time_end' => '20180403201706',
//            'total_fee' => '1',
//            'trade_type' => 'JSAPI',
//            'transaction_id' => '4200000070201804031524431787',
//        );

        //支付成功插入支付日志，和待分成订单
        if ($result && $result['result_code'] === 'SUCCESS') {
            //通知结果映射
            $result['out_trade_no'] = substr($result['out_trade_no'], 0, -10);

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
                ->join(['tp_users'=> 'b'],'a.user_id = b.user_id','LEFT')
                ->where('a.order_sn',$result['out_trade_no'])->find();
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
    public function returnUrl(){
        $result = $this->payment->respond2(); // $result['order_sn'] = '201512241425288593';
        
        if(stripos($result['order_sn'],'recharge') !== false)
        {
            $order = M('recharge')->where("order_sn", $result['order_sn'])->find();
            $this->assign('order', $order);
            if($result['status'] == 1)
                return $this->fetch('recharge_success');   
            else
                return $this->fetch('recharge_error');   
            exit();            
        }
                
        $order = M('order')->where("order_sn", $result['order_sn'])->find();
        if(empty($order)) // order_sn 找不到 根据 order_id 去找
        {
            $order_id = session('order_id'); // 最近支付的一笔订单 id        
            $order = M('order')->where("order_id", $order_id)->find();
        }
                
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
                    $team_follow = Db::name('team_follow')->where('found_id',$team_found['found_id'])->select();
                }

                $this->assign('team_found',$team_found);
                $this->assign('team_follow',$team_follow);

                return $this->fetch('teamResult');
            }elseif(strstr($order['order_sn'],'p') != false){
                return $this->fetch('distribution/applyResust');
            } else {
                return $this->fetch('success');
            }
        } else {
            return $this->fetch('error');
        }
    }  

    public function refundBack(){
    	$this->payment->refund_respose();
    	exit();
    }
    
    public function transferBack(){
    	$this->payment->transfer_response();
    	exit();
    }
}
