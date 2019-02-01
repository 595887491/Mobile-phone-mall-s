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
use app\common\library\Redis;
use app\common\logic\CartLogic;
use app\common\logic\GoodsActivityLogic;
use app\common\logic\CouponLogic;
use app\common\logic\GoodsPromFactory;
use app\common\logic\Integral;
use app\common\logic\OrderLogic;
use app\common\logic\Pay;
use app\common\logic\PlaceOrder;
use app\common\logic\ActivityLogic;
use app\common\logic\WechatLogic;
use app\common\model\DistributeDivideLog;
use app\common\model\Goods;
use app\common\model\SpecGoodsPrice;
use app\common\model\TeamFound;
use app\common\model\Users;
use app\common\util\TpshopException;
use think\Cache;
use think\Cookie;
use think\Db;
use think\Exception;
use think\Session;
use think\Url;

class Cart extends MobileBase {

    public $cartLogic; // 购物车逻辑操作类    
    public $user_id = 0;
    public $user = array();
    /**
     * 析构流函数
     */
    public function  __construct() {
        parent::__construct();
        $this->checkUserLogin(['index']);
        $this->assign('user',$this->user);
    }

    public function index(){
        if ($this->user_id) {
            // 优惠券信息,这里只查询1页优惠券，想领取更多优惠券需跳转到领券中心
            $activityLogic = new ActivityLogic();
            $result = $activityLogic->getCouponListCanUse('2', $this->user_id, 1);
            // 值返回第一章优惠券
            $res = array_splice($result, 0 , 1);
            $this->assign('coupon_list', $res);
        }
        $cartLogic = new CartLogic();
        $cartLogic->setUserId($this->user_id);
        $cartList = $cartLogic->getCartList();//用户购物车
        foreach ($cartList as $k => $v){
            if ($v['prom_goods']) {
                if ($v['prom_goods']['type'] == 0) {
                    $cartList[$k]['prom_goods']['info'] = '限时打'.($v['prom_goods']['expression']/10).'折';
                } elseif($v['prom_goods']['type'] == 1){
                    $cartList[$k]['prom_goods']['info'] = '减价'.($v['prom_goods']['expression']);
                }
            }
            if ($v['prom_type'] == 1) {
                //查询活动是否结束
                $endTime = Db::name('flash_sale')->where('id',$v['prom_id'])->getField('end_time');
                if ( time() > $endTime) {
                    Db::name('cart')->where('id',$v['id'])->update([
                        'member_goods_price' => $v['goods_price'],
                        'prom_type' => 0,
                        'prom_id' => 0,
                    ]);
                }
            }
        }
        $userCartGoodsTypeNum = $cartLogic->getUserCartGoodsTypeNum();//获取用户购物车商品总数
        $hot_goods = M('Goods')->where('is_hot=1 and is_on_sale=1')->limit(20)->select();

        // 这里随机将商品设为 假的 精选, 每次设置个数随机
        for ($i = 0; $i < count($hot_goods); $i++) {
            if ($i == 0 || $i == 1 || $i == 2) {
                $hot_goods[$i]['is_jx'] = 1;
            }
        }
        $this->assign('hot_goods', $hot_goods);
        $this->assign('userCartGoodsTypeNum', $userCartGoodsTypeNum);
        $this->assign('cartList', $cartList);//购物车列表
        return $this->fetch();
    }

    /**
     * 更新购物车，并返回计算结果
     *
     */
    public function AsyncUpdateCart()
    {
        $cart = input('cart/a', []);
        $cartLogic = new CartLogic();
        $cartLogic->setUserId($this->user_id);
        $result = $cartLogic->AsyncUpdateCart($cart);
        // codeAddStart
        // 加上对订单优惠的计算
        if ($result['status']) {
            $activityLogic = new ActivityLogic();
            $activityInfo = $activityLogic->getOrderPayProm($result['result']['total_fee']);
            $result['activity'] = $activityInfo;
            if ($activityInfo) {
                $total = $activityLogic->getOrderPromMoney($result['result']['total_fee']);
                $result['result']['goods_fee'] = round($result['result']['goods_fee'] + ($result['result']['total_fee'] - $total),2);//节省了多少钱
                $result['result']['total_fee'] = $total;
            }
        }
        isset($result['result']['total_fee']) and $result['result']['total_fee'] = sprintf("%.2f",$result['result']['total_fee']);
        // codeAddEnd
        $this->ajaxReturn($result);
    }

    /**
     *  购物车加减
     */
    public function changeNum(){
        $cart = input('cart/a',[]);
        if (empty($cart)) {
            $this->ajaxReturn(['status' => 0, 'msg' => '请选择要更改的商品', 'result' => '']);
        }
        $cartLogic = new CartLogic();
        $result = $cartLogic->changeNum($cart['id'],$cart['goods_num']);
        $this->ajaxReturn($result);
    }

    /**
     * 删除购物车商品
     */
    public function delete(){
        $cart_ids = input('cart_ids/a',[]);
        $cartLogic = new CartLogic();
        $cartLogic->setUserId($this->user_id);
        $result = $cartLogic->delete($cart_ids);
        if($result !== false){
            $this->ajaxReturn(['status'=>1,'msg'=>'删除成功','result'=>$result]);
        }else{
            $this->ajaxReturn(['status'=>0,'msg'=>'删除失败','result'=>$result]);
        }
    }

    /**
     * 购物车第二步确定页面
     */
    public function cart2(){
        $goods_id = input("goods_id/d"); // 商品id
        $goods_num = input("goods_num/d");// 商品数量
        $item_id = input("item_id/d"); // 商品规格id
        $action = input("action/s"); // 行为
        if ($this->user_id == 0){
            //记录上级地址
            $this->redirect('User/login');
        }
        $address_id = I('address_id/d');
        if($address_id){
            $address = M('user_address')->where("address_id", $address_id)->find();
        } else {
            $address = Db::name('user_address')->where(['user_id'=>$this->user_id])->order(['is_default'=>'desc'])->find();
        }
        if(empty($address)){
            $address = M('user_address')->where(['user_id'=>$this->user_id])->find();
            // 用户可以选择自提商品， 所以不需要跳转添加收货地址
//            if (empty($address)) {
//                // 未填写地址直接跳转到添加地址页面
//                $this->redirect('User/address_list',array('source'=>'cart2','goods_id'=>$goods_id,'goods_num'=>$goods_num,'item_id'=>$item_id,'action'=>$action));
//            }
        }
        $cartLogic = new CartLogic();
        $couponLogic = new CouponLogic();
        $cartLogic->setUserId($this->user_id);
        //立即购买
        if($action == 'buy_now'){
            $cartLogic->setGoodsModel($goods_id);
            $cartLogic->setSpecGoodsPriceModel($item_id);
            $cartLogic->setGoodsBuyNum($goods_num);
            $buyGoods = [];
            try{
                $buyGoods = $cartLogic->buyNow();
            }catch (TpshopException $t){
                $error = $t->getErrorArr();
                $this->error($error['msg']);
            }
            $cartList['cartList'][0] = $buyGoods;
            $cartGoodsTotalNum = $goods_num;
        }else{
            if ($cartLogic->getUserCartOrderCount() == 0){
                $this->redirect('Cart/index');
            }
            $cartList['cartList'] = $cartLogic->getCartList(1); // 获取用户选中的购物车商品
            $cartGoodsTotalNum = count($cartList['cartList']);
        }

//        $activityLogic = new ActivityLogic();
//        $unReceiveCoupon = $activityLogic->getCouponList(2, $this->user_id); // 未领取但可领取的优惠券
//        $this->assign('unReceiveCoupon', $unReceiveCoupon); // 未领取但可领取的优惠券
        $cartGoodsList = get_arr_column($cartList['cartList'],'goods');
        $cartGoodsId = get_arr_column($cartGoodsList,'goods_id');
        $cartGoodsCatId = get_arr_column($cartGoodsList,'cat_id');
        $cartPriceInfo = $cartLogic->getCartPriceInfo($cartList['cartList']);  //初始化数据。商品总额/节约金额/商品总共数量
        $userCouponList = $couponLogic->getUserAbleCouponList($this->user_id, $cartGoodsId, $cartGoodsCatId);//用户可用的优惠券列表
        $cartList = array_merge($cartList,$cartPriceInfo);
        $userCartCouponList = $cartLogic->getCouponCartList($cartList, $userCouponList);
        //拆分可用和不可用的状态
        $userCanUseCounpon = [];
        $userCanNotUseCounpon = [];
        $canUseMaxCounpon = [];
        foreach ($userCartCouponList as $v){
            if ($v['coupon']['able']) {
                $userCanUseCounpon[] = $v;
                if ($v['coupon']['money'] > $canUseMaxCounpon['coupon']['money']) {
                    $canUseMaxCounpon = $v;
                }elseif ($v['coupon']['money'] == $canUseMaxCounpon['coupon']['money']) {
                    if ($v['coupon']['use_end_time'] < $canUseMaxCounpon['coupon']['use_end_time']) {
                        $canUseMaxCounpon = $v;
                    }
                }
            }else{
                $userCanNotUseCounpon[] = $v;
            }
        }
//        dump(count($userCanUseCounpon));
//        dump(count($userCanNotUseCounpon));
//        dump($userCanUseCounpon);
//        dump($userCanNotUseCounpon);die;
        $this->assign('can_use_max_counpon',$canUseMaxCounpon);
        $this->assign('user_can_use_counpon',$userCanUseCounpon);
        $this->assign('user_can_not_use_counpon',$userCanNotUseCounpon);

        $this->assign('address',$address); //收货地址
        $this->assign('cartGoodsTotalNum', $cartGoodsTotalNum);
        $this->assign('cartList', $cartList['cartList']); // 购物车的商品
        $this->assign('cartPriceInfo', $cartPriceInfo);//商品优惠总价
        return $this->fetch();
    }


    /**
     * ajax 获取订单商品价格 或者提交 订单
     */
    public function cart3(){

        if($this->user_id == 0){
            exit(json_encode(array('status'=>-100,'msg'=>"登录超时请重新登录!",'result'=>null))); // 返回结果状态
        }
        $address_id = I("address_id/d"); //  收货地址id
        $invoice_title = I('invoice_title'); // 发票
        $taxpayer = I('taxpayer'); // 纳税人编号
        $coupon_id =  I("coupon_id/d"); //  优惠券id
//        $pay_points =  I("pay_points/d",0); //  使用积分
        $pay_points = 0; // 老商城规则，不能使用积分购买普通商品
        $user_money =  I("user_money/f",0); //  使用余额
        $user_note = trim(I('user_note'));   //买家留言
        $goods_id = input("goods_id/d"); // 商品id
        $goods_num = input("goods_num/d");// 商品数量
        $item_id = input("item_id/d"); // 商品规格id
        $action = input("action"); // 立即购买
        $payPwd =  I("payPwd",''); // 支付密码
        $shipping = input("shipping/d"); // 快递方式 0:到店自提  1：物流方式
        strlen($user_note) > 50 && exit(json_encode(['status'=>-1,'msg'=>"备注超出限制可输入字符长度！",'result'=>null]));
        $address = Db::name('UserAddress')->where("address_id", $address_id)->find();
        $cartLogic = new CartLogic();
        $pay = new Pay();
        $cartList = [];
        try {
            $cartLogic->setUserId($this->user_id);
            $pay->setUserId($this->user_id);
            if ($action == 'buy_now') {
                $cartLogic->setGoodsModel($goods_id);
                $cartLogic->setSpecGoodsPriceModel($item_id);
                $cartLogic->setGoodsBuyNum($goods_num);
                $buyGoods = $cartLogic->buyNow();
                array_push($cartList,$buyGoods);
                $pay->payGoodsList($cartList);
            } else {
                $userCartList = $cartLogic->getCartList(1);
                $cartLogic->checkStockCartList($userCartList);
                $cartList = array_merge_recursive($cartList,$userCartList);
                $pay->payCart($cartList);
            }
            if ($shipping) {
                if ($_REQUEST['act'] == 'submit_order') {
                    $pay->delivery($address['district']); //物流配送地址
                } else {
                    if (!empty($address)) {
                        $pay->delivery($address['district']);//物流配送地址
                    }
                }
            } else {
                if ($_REQUEST['act'] == 'submit_order') {
                    $pay->delivery_ziti($address['district']); //物流配送地址
                }
            }
            $pay->orderPromotion();
            $pay->useCouponById($coupon_id);
            $pay->useUserMoney($user_money);
            $pay->usePayPoints($pay_points);
        } catch (TpshopException $t) {
            $error = $t->getErrorArr();
            $this->ajaxReturn($error);
        }
        // 提交订单
        if ($_REQUEST['act'] == 'submit_order') {
            $placeOrder = new PlaceOrder($pay);
            $placeOrder->setUserAddress($address);//自提和物流都需要收货地址
            $placeOrder->setInvoiceTitle($invoice_title);
            $placeOrder->setUserNote($user_note);
            $placeOrder->setTaxpayer($taxpayer);
            $placeOrder->setPayPsw($payPwd);
            $placeOrder->setShipping($shipping);
            try{
                $placeOrder->addNormalOrder();
            }catch (TpshopException $t) {
                $error = $t->getErrorArr();
                $this->ajaxReturn($error);
            }
            $cartLogic->clear();
            $order = $placeOrder->getOrder();

            $this->ajaxReturn(['status'=>1,'msg'=>'提交订单成功','result'=>$order['order_sn']]);
        }
        $car_price = $pay->toArray();
        $this->ajaxReturn(['status'=>1,'msg'=>'计算成功','result'=>$car_price]);
    }
    /*
     * 订单支付页面
     */
    public function cart4(){
        if(empty($this->user_id)){
            $this->redirect('User/login');
        }
        $order_id = I('order_id/d');
        $order_sn= I('order_sn/s','');
        $order_where = ['user_id'=>$this->user_id];
        if($order_sn)
        {
            $order_where['order_sn'] = $order_sn;
        }else{
            $order_where['order_id'] = $order_id;
        }


        $order = M('Order')->where($order_where)->find();
        //拼团自动确认--start--  如果是拼团商品,查询是否是自动确认拼团并确认订单
        $need = Db::table('tp_team_activity')->field('needer,is_auto_confirm')->where('team_id',$order['prom_id'])->find();
        if ($order['prom_type'] == 6 && $order['pay_status'] == 1 && $need['is_auto_confirm'] == 1){
            $is_follow = Db::table('tp_team_follow')->where('order_id',$order['order_id'])->find();//参团
            if (!empty($is_follow)){
                $followNum = Db::table('tp_team_follow')->where('found_id',$is_follow['found_id'])->count();//已参团数
                $foundNum = Db::table('tp_team_found')->where('found_id',$is_follow['found_id'])->count();//该团开团人数一个
                $found_id = $is_follow['found_id'];
            }else{
                $is_found = Db::table('tp_team_found')->where('order_id',$order['order_id'])->find();//开团
                $followNum = Db::table('tp_team_follow')->where('found_id',$is_found['found_id'])->count();//已参团数
                $foundNum = Db::table('tp_team_found')->where('found_id',$is_found['found_id'])->count();//该团开团人数一个
                $found_id = $is_found['found_id'];
            }
            $confirm_condition = $need['needer'] - $followNum - $foundNum;//还差多少参团人数
            if($confirm_condition <= 0 && $need['is_auto_confirm'] == 1){//拼团成功并开启自动确认
                $TeamFound = new TeamFound();
                $teamFound = $TeamFound::get(['found_id'=>$found_id]);
                $follow_order_id = Db::name('team_follow')->where(['found_id' => $found_id, 'status' => 2])->getField('order_id', true);
                $follow_confirm = Db::name('order')->where('order_id', 'IN', $follow_order_id)->where(['prom_type' => 6])->update(['order_status' => 1]);
                if($follow_confirm !== false){
                    $teamFound->order->order_status = 1;
                    $found_confirm = $teamFound->order->save();
                    if($found_confirm){
                        Db::table('tp_team_found')->where('found_id',$teamFound->found_id)->update(['is_auto_confirm'=>1]);//自动确认拼团
                        $orderSnArr = Db::name('order')->where('order_id', 'IN', $follow_order_id)->getField('order_sn', true);
                        //生成待分成订单
                        array_push($orderSnArr,$teamFound->order['order_sn']);
                        foreach ($orderSnArr as $v) {
                            $result['out_trade_no'] = $v;
                            (new DistributeDivideLog())->createWaitDistribut($result);
                        }
                    }
                }
            }
        }
        //拼团自动确认--end--  @Author:赵磊
        empty($order) && $this->error('订单不存在！');
        if($order['order_status'] == 3){
            $this->error('该订单已取消',U("Mobile/Order/order_detail",array('id'=>$order['order_id'])));
        }
        if(empty($order) || empty($this->user_id)){
            $order_order_list = U("User/login");
            header("Location: $order_order_list");
            exit;
        }
        // 如果已经支付过的订单直接到订单详情页面. 不再进入支付页面
        if($order['pay_status'] == 1){
            if ($order['prom_type'] == 6) {
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
                $this->assign('order',$order);
                $this->assign('team_found',$team_found);
                $this->assign('team_follow',$team_follow);
                return $this->fetch('payment/teamResult');
            }
            $order_detail_url = U("Mobile/Order/order_detail",array('id'=>$order['order_id']));
            header("Location: $order_detail_url");
            exit;
        }
        $orderGoodsPromType = M('order_goods')->where(['order_id'=>$order['order_id']])->getField('prom_type',true);
        $payment_where['type'] = 'payment';
        $no_cod_order_prom_type = ['4,5'];//预售订单，虚拟订单不支持货到付款
        if(strstr($_SERVER['HTTP_USER_AGENT'],'MicroMessenger')){
            //微信浏览器
            if(in_array($order['prom_type'],$no_cod_order_prom_type) || in_array(1,$orderGoodsPromType)){
                //预售订单和抢购不支持货到付款
                $payment_where['code'] = 'weixin';
            }else{
                $payment_where['code'] = array('in',array('weixin','cod','alipayMobile'));
            }
        }else{
            if(in_array($order['prom_type'],$no_cod_order_prom_type) || in_array(1,$orderGoodsPromType)){
                //预售订单和抢购不支持货到付款
                $payment_where['code'] = array('neq','cod');
            }
            $payment_where['scene'] = array('in',array('0','1'));
        }
        $payment_where['status'] = 1;
        //预售和抢购暂不支持货到付款
        $orderGoodsPromType = M('order_goods')->where(['order_id'=>$order['order_id']])->getField('prom_type',true);
        if($order['prom_type'] == 4 || in_array(1,$orderGoodsPromType)){
            $payment_where['code'] = array('neq','cod');
        }
        $payment_where['code'] = array('in',array('weixin','alipayMobile'));//目前让其只显示这两种支付方式
        $paymentList = M('Plugin')->where($payment_where)->select();
        $paymentList = convert_arr_key($paymentList, 'code');

        foreach($paymentList as $key => $val)
        {
            $val['config_value'] = unserialize($val['config_value']);
            if($val['config_value']['is_bank'] == 2)
            {
                $bankCodeList[$val['code']] = unserialize($val['bank_code']);
            }
            //判断当前浏览器显示支付方式
//            if(($key == 'weixin' && !is_weixin()) || ($key == 'alipayMobile' && is_weixin())){
//                unset($paymentList[$key]);
//            }
            // 微信浏览器也能使用支付宝
            if($key == 'weixin' && !is_weixin()){
                unset($paymentList[$key]);
            }
        }

        $bank_img = include APP_PATH.'home/bank.php'; // 银行对应图片
        $this->assign('paymentList',$paymentList);
        $this->assign('bank_img',$bank_img);
        $this->assign('order',$order);
        $this->assign('bankCodeList',$bankCodeList);
        $this->assign('pay_date',date('Y-m-d', strtotime("+1 day")));
        return $this->fetch();
    }

    /**
     * ajax 将商品加入购物车
     */
    function ajaxAddCart()
    {
        $goods_id = I("goods_id/d"); // 商品id
        $goods_num = I("goods_num/d");// 商品数量
        $item_id = I("item_id/d"); // 商品规格id
        if(empty($goods_id)){
            $this->ajaxReturn(['status'=>-1,'msg'=>'请选择要购买的商品','result'=>'']);
        }
        if(empty($goods_num)){
            $this->ajaxReturn(['status'=>-1,'msg'=>'购买商品数量不能为0','result'=>'']);
        }
        $cartLogic = new CartLogic();
        $cartLogic->setUserId($this->user_id);
        $cartLogic->setGoodsModel($goods_id);
        if($item_id){
            $cartLogic->setSpecGoodsPriceModel($item_id);
        }
        $cartLogic->setGoodsBuyNum($goods_num);
        $result = $cartLogic->addGoodsToCart();
        exit(json_encode($result));
    }

    /**
     * ajax 获取购物车
     */
    public function getCartGoodsNum(){
        $cartLogic = new CartLogic();
        $num = $cartLogic->getUserCartGoodsNum();
        setcookie('cn', $num, null, '/');
        $this->ajaxReturn(['status'=>1,'num'=>$num]);
    }

    /**
     * ajax 获取用户收货地址 用于购物车确认订单页面
     */
    public function ajaxAddress(){
        $regionList = get_region_list();
        $address_list = M('UserAddress')->where("user_id", $this->user_id)->select();
        $c = M('UserAddress')->where("user_id = {$this->user_id} and is_default = 1")->count(); // 看看有没默认收货地址
        if((count($address_list) > 0) && ($c == 0)) // 如果没有设置默认收货地址, 则第一条设置为默认收货地址
            $address_list[0]['is_default'] = 1;

        $this->assign('regionList', $regionList);
        $this->assign('address_list', $address_list);
        return $this->fetch('ajax_address');
    }

    /**
     * 预售商品下单流程
     */
    public function pre_sell_cart()
    {
        $act_id = I('act_id/d');
        $goods_num = I('goods_num/d');
        $address_id = I('address_id/d');
        if(empty($act_id)){
            $this->error('没有选择需要购买商品');
        }
        if(empty($goods_num)){
            $this->error('购买商品数量不能为0', U('Home/Activity/pre_sell', array('act_id' => $act_id)));
        }
        if($address_id){
            $address = M('user_address')->where("address_id", $address_id)->find();
        } else {
            $address = Db::name('user_address')->where(['user_id'=>$this->user_id])->order(['is_default'=>'desc'])->find();
        }
        if(empty($address)){
            header("Location: ".U('Mobile/User/add_address',array('source'=>'pre_sell_cart','act_id'=>$act_id,'goods_num'=>$goods_num)));
            exit;
        }else{
            $this->assign('address',$address);
        }
        if($this->user_id == 0){
            $this->error('请先登录');
        }
        $pre_sell_info = M('goods_activity')->where(array('act_id' => $act_id, 'act_type' => 1))->find();
        if(empty($pre_sell_info)){
            $this->error('商品不存在或已下架',U('Home/Activity/pre_sell_list'));
        }
        $pre_sell_info = array_merge($pre_sell_info, unserialize($pre_sell_info['ext_info']));
        if ($pre_sell_info['act_count'] + $goods_num > $pre_sell_info['restrict_amount']) {
            $buy_num = $pre_sell_info['restrict_amount'] - $pre_sell_info['act_count'];
            $this->error('预售商品库存不足，还剩下' . $buy_num . '件', U('Home/Activity/pre_sell', array('id' => $act_id)));
        }
        $goodsActivityLogic = new GoodsActivityLogic();
        $pre_count_info = $goodsActivityLogic->getPreCountInfo($pre_sell_info['act_id'], $pre_sell_info['goods_id']);//预售商品的订购数量和订单数量
        $pre_sell_price['cut_price'] =$goodsActivityLogic->getPrePrice($pre_count_info['total_goods'], $pre_sell_info['price_ladder']);//预售商品价格
        $pre_sell_price['goods_num'] = $goods_num;
        $pre_sell_price['deposit_price'] = floatval($pre_sell_info['deposit']);
        // 提交订单
        if ($_REQUEST['act'] == 'submit_order') {
            $invoice_title = I('invoice_title'); // 发票
            $taxpayer  = I('taxpayer'); // 纳税识别号
            $address_id = I("address_id/d"); //  收货地址id
            if(empty($address_id)){
                exit(json_encode(array('status'=>-3,'msg'=>'请先填写收货人信息','result'=>null))); // 返回结果状态
            }
            $orderLogic = new OrderLogic();
            $result = $orderLogic->addPreSellOrder($this->user_id, $address_id, $invoice_title, $act_id, $pre_sell_price,$taxpayer); // 添加订单
            exit(json_encode($result));
        }
        $shippingList = M('Plugin')->where("`type` = 'shipping' and status = 1")->select();// 物流公司
        $this->assign('pre_sell_info', $pre_sell_info);// 购物车的预售商品
        $this->assign('shippingList', $shippingList); // 物流公司
        $this->assign('pre_sell_price',$pre_sell_price);
        return $this->fetch();
    }

    /**
     * 兑换积分商品
     */
    public function buyIntegralGoods(){
        $goods_id = input('goods_id/d');
        $item_id = input('item_id/d');
        $goods_num = input('goods_num');
        $Integral = new Integral();
        $Integral->setUserById($this->user_id);
        $Integral->setGoodsById($goods_id);
        $Integral->setSpecGoodsPriceById($item_id);
        $Integral->setBuyNum($goods_num);
        try{
            $Integral->checkBuy();
            $url = U('Cart/integral', ['goods_id' => $goods_id, 'item_id' => $item_id, 'goods_num' => $goods_num]);
            $result = ['status' => 1, 'msg' => '购买成功', 'result' => ['url' => $url]];
            $this->ajaxReturn($result);
        }catch (TpshopException $t){
            $result = $t->getErrorArr();
            $this->ajaxReturn($result);
        }
    }

    /**
     *  积分商品结算页
     * @return mixed
     */
    public function integral(){
        $goods_id = input('goods_id/d');
        $item_id = input('item_id/d');
        $goods_num = input('goods_num/d');
        $address_id = input('address_id/d');
        if(empty($this->user)){
            $this->error('请登录');
        }
        if(empty($goods_id)){
            $this->error('非法操作');
        }
        if(empty($goods_num)){
            $this->error('购买数不能为零');
        }
        $Goods = new Goods();
        $goods = $Goods->where(['goods_id'=>$goods_id])->find();
        if(empty($goods)){
            $this->error('该商品不存在');
        }
        if (empty($item_id)) {
            $goods_spec_list = SpecGoodsPrice::all(['goods_id' => $goods_id]);
            if (count($goods_spec_list) > 0) {
                $this->error('请传递规格参数');
            }
            $goods_price = $goods['shop_price'];
            //没有规格
        } else {
            //有规格
            $specGoodsPrice = SpecGoodsPrice::get(['item_id'=>$item_id,'goods_id'=>$goods_id]);
            if ($goods_num > $specGoodsPrice['store_count']) {
                $this->error('该商品规格库存不足，剩余' . $specGoodsPrice['store_count'] . '份');
            }
            $goods_price = $specGoodsPrice['price'];
            $this->assign('specGoodsPrice', $specGoodsPrice);
        }
        if($address_id){
            $address = Db::name('user_address')->where("address_id" , $address_id)->find();
        }else{
            $address = Db::name('user_address')->where(['user_id' => $this->user_id])->order(['is_default' => 'desc'])->find();
        }
        if(empty($address)){
            header("Location: ".U('Mobile/User/add_address',array('source'=>'integral','goods_id'=>$goods_id,'goods_num'=>$goods_num,'item_id'=>$item_id)));
            exit;
        }else{
            $this->assign('address',$address);
        }
        $point_rate = tpCache('shopping.point_rate');
        $backUrl = Url::build('Goods/goodsInfo',['id'=>$goods_id,'item_id'=>$item_id]);
        $this->assign('backUrl', $backUrl);
        $this->assign('point_rate', $point_rate);
        $this->assign('goods', $goods);
        $this->assign('goods_price', $goods_price);
        $this->assign('goods_num',$goods_num);
        return $this->fetch();
    }

    /**
     *  积分商品价格提交
     * @return mixed
     */
    public function integral2(){
        if ($this->user_id == 0){
            $this->ajaxReturn(['status' => -100, 'msg' => "登录超时请重新登录!", 'result' => null]);
        }
        $goods_id           = input('goods_id/d');
        $item_id            = input('item_id/d');
        $goods_num          = input('goods_num/d');
        $address_id         = input("address_id/d"); //  收货地址id
        $user_note          = input('user_note'); // 给卖家留言
        $invoice_title      = input('invoice_title'); // 发票
        $taxpayer           = input('taxpayer'); // 发票纳税人识别号
        $user_money         = input("user_money/f", 0); //  使用余额
        $payPwd                = input('payPwd');
        $integral = new Integral();
        $integral->setUserById($this->user_id);
        $integral->setGoodsById($goods_id);
        $integral->setBuyNum($goods_num);
        $integral->setSpecGoodsPriceById($item_id);
        $integral->setUserAddressById($address_id);
        $integral->useUserMoney($user_money);
        try{
            $integral->checkBuy();
            $pay = $integral->pay();
            // 提交订单
            if ($_REQUEST['act'] == 'submit_order') {
                $placeOrder = new PlaceOrder($pay);
                $placeOrder->setUserAddress($integral->getUserAddress());
                $placeOrder->setInvoiceTitle($invoice_title);
                $placeOrder->setUserNote($user_note);
                $placeOrder->setTaxpayer($taxpayer);
                $placeOrder->setPayPsw($payPwd);
                $placeOrder->addNormalOrder();
                $order = $placeOrder->getOrder();
                $this->ajaxReturn(['status'=>1,'msg'=>'提交订单成功','result'=>$order['order_id']]);
            }
            $this->ajaxReturn(['status' => 1, 'msg' => '计算成功', 'result' => $pay->toArray()]);
        }catch (TpshopException $t){
            $error = $t->getErrorArr();
            $this->ajaxReturn($error);
        }
    }
	
	 /**
     *  获取发票信息
     * @date2017/10/19 14:45
     */
    public function invoice(){

        $map = [];
        $map['user_id']=  $this->user_id;
        
        $field=[          
            'invoice_title',
            'taxpayer',
            'invoice_desc',	
        ];
        
        $info = M('user_extend')->field($field)->where($map)->find();
        if(empty($info)){
            $result=['status' => -1, 'msg' => 'N', 'result' =>''];
        }else{
            $result=['status' => 1, 'msg' => 'Y', 'result' => $info];
        }
        $this->ajaxReturn($result);            
    }
     /**
     *  保存发票信息
     * @date2017/10/19 14:45
     */
    public function save_invoice(){
        if(IS_AJAX){
            //A.1获取发票信息
            $invoice_title = trim(I("invoice_title")); //单位名称
            $taxpayer      = trim(I("taxpayer")); //纳税人识别号
            $invoice_desc  = trim(I("invoice_desc")); //不开发票、明细
            
            //B.1校验用户是否有历史发票记录
            $map            = [];
            $map['user_id'] =  $this->user_id;
            $info           = M('user_extend')->where($map)->find();
            
           //B.2发票信息
            $data=[];  
            $data['invoice_title'] = $invoice_title;
            $data['taxpayer']      = $taxpayer;  
            $data['invoice_desc']  = $invoice_desc;     
            
            //B.3发票抬头
            if($invoice_title=="个人"){
                $data['invoice_title'] ="个人";
                $data['taxpayer']      = "";
            }                              

            //是否存贮过发票信息
            if(empty($info)){   
                $data['user_id'] = $this->user_id;
                (M('user_extend')->add($data))?
                $status=1:$status=-1;                
             }else{
                (M('user_extend')->where($map)->save($data))?
                $status=1:$status=-1;                
            }            
            $result = ['status' => $status, 'msg' => '', 'result' =>''];           
            $this->ajaxReturn($result);
        }      
    }
    public function cf_order_invoice() {
        $referurl = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : U('Mobile/Cart/cart2');
        $map = [];
        $map['user_id']=  $this->user_id;

        $field=[
            'invoice_title',
            'taxpayer',
            'invoice_desc',
        ];

        $info = M('user_extend')->field($field)->where($map)->find();
        $this->assign('referurl', $referurl);
        $this->assign('invoice', $info);
        return $this->fetch();
    }
}
