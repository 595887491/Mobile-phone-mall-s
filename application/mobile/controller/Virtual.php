<?php
/**
 * tpshop
 * ============================================================================
 * * 版权所有 2015-2027 深圳搜豹网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.tp-shop.cn
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 如果商业用途务必到官方购买正版授权, 以免引起不必要的法律纠纷.
 * ============================================================================
 * 2016-11-21
 */
namespace app\mobile\controller;

use app\common\library\Logs;
use app\common\logic\ActivityLogic;
use app\common\logic\GoodsLogic;
use app\common\logic\OrderLogic;
use app\common\logic\VirtualLogic;
use app\common\logic\WechatLogic;
use app\common\model\DistributeDivideLog;
use app\common\model\PayLogModel;
use app\common\model\UserModel;
use app\common\model\Users;
use think\Cache;
use think\Cookie;
use think\Page;
use think\Db;
class Virtual extends MobileBase
{
    public $user_id = 0;
    public $user = array();

    public function _initialize()
    {
        parent::_initialize();

        $nologin = array(
            'goodsInfo',
        );
        $this->checkUserLogin($nologin);

    }

    //虚拟商品详情页
    public function goodsInfo()
    {
        C('TOKEN_ON',true);
        $goodsLogic = new GoodsLogic();
        $goods_id = I("get.id/d");
        $goodsModel = new \app\common\model\Goods();
        $goods = $goodsModel::get($goods_id);

        if(empty($goods)){
            $this->error('此商品不存在');
        }

        if (cookie('user_id')) {
            $goodsLogic->add_visit_log(cookie('user_id'), $goods);
        }

        $userInfo = (new UserModel())->getUserRelationIdentity($this->user_id);
        //代理商，合伙人分享可赚多少钱
        if ($userInfo['identity']['partner'] || $userInfo['identity']['agent']) {
            $this->assign('can_earn_money',round($goods->shop_price * 0.06,2));
        }

        // 商品 图册
        $goods_images_list = M('GoodsImages')->where(["goods_id"=>$goods_id,'image_url'=>['exp','<>""']])->order('img_id DESC')->select();
        $this->assign('goods_images_list',$goods_images_list);//商品缩略图

        //商品信息
        if($goods['brand_id']){
            $brnad = M('brand')->where("id", $goods['brand_id'])->find();
            $goods['brand_name'] = $brnad['name'];
        }
        $goods->shop_price = round($goods->shop_price,2);
        $goods->market_price = round($goods->market_price,2);
        $goods->sales_sum += $goods->virtual_sales_num;

        $goods->goods_remark = preg_replace('#\r|\n|\s#','',$goods->goods_remark);
        $this->assign('goods',$goods);


        // 获取某个商品的评论统计,评论概览
        $commentStatistics = $goodsLogic->commentStatistics($goods_id);
        $this->assign('commentStatistics',$commentStatistics);

        //一条最近的好评
        $typeArr = array('1' => '0,1,2,3,4,5', '2' => '4,5', '3' => '3', '4' => '0,1,2');
        $where = array('is_show'=>1,'goods_id' => $goods_id, 'parent_id' => 0, 'ceil((deliver_rank + goods_rank + service_rank) / 3)' => ['in', $typeArr[2]]);
        $list = M('Comment')
            ->alias('c')->join('__USERS__ u', 'u.user_id = c.user_id', 'LEFT')
            ->where($where)->order("add_time desc")->limit(1)->find();
        if ($list) {
            $list['add_time'] = date('Y-m-d H:i',$list['add_time']);
        }

        if ($list['img']) {
            $list['img'] = array_slice(unserialize($list['img']),0,4);
        }
        $this->assign('good_comment',$list);
/*
        // 查询属性
//        $goods_attribute = M('GoodsAttribute')->getField('attr_id,attr_name');
//        $this->assign('goods_attribute',$goods_attribute);

        // 查询商品属性表
//        $goods_attr_list = M('GoodsAttr')->where("goods_id", $goods_id)->select();
//        $this->assign('goods_attr_list',$goods_attr_list);//属性列表

        // 规格 对应 价格 库存表
//        $filter_spec = $goodsLogic->get_spec($goods_id);
//        $this->assign('filter_spec',$filter_spec);//规格参数

//        $spec_goods_price  = M('spec_goods_price')->where("goods_id", $goods_id)->getField("key,price,store_count,item_id");
//        // 规格 对应 价格 库存表
//        $this->assign('spec_goods_price', json_encode($spec_goods_price,true));

        //当前用户收藏
//        $user_id = cookie('user_id');
//        $collect = M('goods_collect')->where(array("goods_id"=>$goods_id ,"user_id"=>$user_id))->count();
//        $goods_collect_count = M('goods_collect')->where(array("goods_id"=>$goods_id))->count(); //商品收藏数
//        $goodsActivity = $this->goodsRelatedActivity($goods_id);//该商品能参与的所有优惠活动
//        $this->assign('collect',$collect);
//
//        $point_rate = tpCache('shopping.point_rate');
//        $this->assign('goods_collect_count',$goods_collect_count); //商品收藏人数
//        $this->assign('point_rate', $point_rate);
//        $this->assign('goods_activity', $goodsActivity);
*/
        $func = function ($userInfo) {
            if (!$userInfo) {
                return [];
            }
            $userSex = '保密';
            if ($userInfo['sex'] == 1) {
                $userSex = '男';
            }elseif ($userInfo['sex'] == 2) {
                $userSex = '女';
            }
            $address = get_user_address_list($userInfo['user_id']);


            if ($address) {
                $addressInfo = getTotalAddress($address[0]['province'],$address[0]['city'],$address[0]['district'],$address[0]['twon'],$address[0]['address']);
            }else {
                $addressInfo = '未填写';
            }
            $returnData = [
                'name' => $userInfo['nickname'],
                'address' => $addressInfo,
                'age' => $userInfo['age'] ? $userInfo['age'] : '未填写',
                'email' => $userInfo['email'],
                'gender' => $userSex,
                'tel' => $userInfo['mobile'],
            ];
            return $returnData;
        };

        $this->assign('service_user_info',$func($userInfo));
        return $this->fetch();
    }

    /**
     * 商品相关活动，包含商品促销和订单促销
     */
    public function goodsRelatedActivity($goods_id,$item_id = 0){
        $activityLogic = new ActivityLogic();
        $activityArr['goods_activity'] = $activityLogic->goodsRelatedActivity($goods_id,$item_id);

        $Goods = new \app\common\model\Goods();
        $goods = $Goods::get($goods_id,'',true);
        //订单优惠
        $orderActivity = $activityLogic->getOrderPromSimpleInfo(0,$goods);
        if (!empty($orderActivity)) {
            $activityArr['order_activity'] = $orderActivity;
        }
        return $activityArr;
    }

    public function addVirtualOrder(){
    	$goods_id = I('goods_id/d');
        $Goods = new \app\common\model\Goods();
        $goods = $Goods::get($goods_id,'');
        $goods->shop_price = round($goods->shop_price,2);
        $goods->market_price = round($goods->market_price,2);
        $userMoney = (new Users())->where('user_id',$this->user_id)->getField('user_money');
        $payPwd = (new Users())->where('user_id',$this->user_id)->getField('paypwd');
        if ($payPwd){
            $this->assign('is_set_pwd',1);
        }
        $this->assign('user_money',round($userMoney,2));
    	$this->assign('goods',$goods);
    	return $this->fetch();
    }
    
    public function addOrder(){
    	C('TOKEN_ON',false);
    	$data = I('post.');
    	//检查商品基本信息
    	$goods = $this->check_virtual_goods();

        $CartLogic = new OrderLogic();

/*
//限制购买限制为所有订单
//        $isbuyWhere = [
//            'og.goods_id'=>$data['goods_id'],
//            'o.user_id'=>$this->user_id,
//            'o.deleted'=>0,
//            'o.order_status'=>['neq',3]
//        ];
//        $isbuy = M('order_goods')->alias('og')
//            ->join(C('DB_PREFIX').'order o','og.order_id = o.order_id','LEFT')
//    	    ->where($isbuyWhere)
//            ->sum('og.goods_num');
//        if(($goods['goods_num']+$isbuy)>$goods['virtual_limit']){
//            $this->ajaxReturn(['status'=>'-1','msg'=>'您已超过该商品的限制购买数']);
//        }
        halt($isbuyWhere);
*/

        $data['consignee'] = empty($this->user['nickname']) ? $this->user['mobile'] : $this->user['nickname'];

        $payStatus = 0;
        $payTime = 0;
        if ($goods['goods_fee'] == 0) {
            $payStatus = 1;
            $payTime = time();
        }

        $userMoney = $data['user_money'];
        $orderArr = array(
            'user_id' => $this->user_id,
            'mobile' => $this->user['mobile'],
            'order_sn' => $CartLogic->get_order_sn(),
            'goods_price' => $goods['shop_price'],
            'consignee' => $data['consignee'],
            'prom_type' => 5,
            'add_time' => time(),
            'pay_status' => $payStatus,
            'pay_time' => $payTime,
            'user_money' => $userMoney,
            'order_amount' => $goods['shop_price'] * $data['goods_num'] - $userMoney,
            'total_amount' => $goods['shop_price'] * $data['goods_num'],
            'shipping_time' => $goods['virtual_indate']//有效期限
        );

    	$order_id = M('order')->add($orderArr);

    	//修改用户余额
        $orderArr['order_id'] = $order_id;
        $this->changUserMoney($data['user_money'],$orderArr);

    	$data2['order_id'] = $order_id; // 订单id
    	$data2['goods_id']           = $goods['goods_id']; // 商品id
    	$data2['goods_name']         = $goods['goods_name']; // 商品名称
    	$data2['goods_sn']           = $goods['goods_sn']; // 商品货号
    	$data2['goods_num']          = $goods['goods_num']; // 购买数量
    	$data2['market_price']       = $goods['market_price']; // 市场价
    	$data2['goods_price']        = $goods['shop_price']; // 商品价
    	$data2['spec_key']           = $goods['goods_spec_key']; // 商品规格
    	$data2['spec_key_name']      = $goods['spec_key_name']; // 商品规格名称
    	$data2['sku']                = $goods['sku']; // 商品条码
    	$data2['member_goods_price'] = $goods['shop_price']; // 会员折扣价
    	$data2['cost_price']         = $goods['cost_price']; // 成本价
    	$data2['give_integral']      = $goods['give_integral']; // 购买商品赠送积分
    	$data2['prom_type']          = $goods['prom_type']; // 0 普通订单,1 限时抢购, 2 团购 , 3 促销优惠
    	$order_goods_id              = M("OrderGoods")->add($data2);

    	if($order_goods_id){
            $reduce = tpCache('shopping.reduce');
            if($reduce== 1 || empty($reduce)){
                $order = Db::name('order')->where(['order_id'=>$order_id])->find();
                minus_stock($order);//下单减库存
            }

            //当商品价格支付为0时，直接修改支付状态
            if (empty($goods['goods_fee'])) {
                // 如果应付金额为0  可能是余额支付 + 积分 + 优惠券 这里订单支付状态直接变成已支付
                $payLogModel = new PayLogModel();
                $payLogModel->insertPayLog([
                    'out_trade_no'=>$orderArr['order_sn'],
                    'total_fee' => $orderArr['user_money'] * 100,
                    'openid'=>'user_id:'.$orderArr['user_id']
                ],'userMoney'); //插入支付日志
                //生成待分层订单
                (new DistributeDivideLog())->createWaitDistribut(['out_trade_no'=>$orderArr['order_sn']]);

                //生成兑换码添加到tp_vr_order_code
                (new OrderLogic())->make_virtual_code($orderArr);

                //发送模板消息
                $orderArr['virtual_indate'] = $goods['virtual_indate'];
                $res = (new WechatLogic())->sendTemplateMsgOnPaySuccess($orderArr);
                if ($res != 1) {
                    Logs::sentryLogs('发送卡券订单模板消息提醒失败:'.$res['msg'],$orderArr);
                }
                //增加销量
                Db::name('goods')->where('goods_id',$data2['goods_id'])->setInc('sales_sum');
                return $this->ajaxReturn([
                    'status'=> 2,
                    'msg'=>'虚拟商品成功',
                    'result'=> U('Mobile/Virtual/orderResult',['order_id' => $order_id])
                ]);
            }

            $this->ajaxReturn(['status'=>'1','msg'=>'虚拟商品成功','result'=>$order_id]);
    	}else{
    		$this->ajaxReturn(['status'=>'-1','msg'=>'虚拟商品下单失败']);
    	}
    }

    public function check_virtual_goods(){
        $goods_id = I('goods_id/d');
        if(empty($goods_id)) return $this->ajaxReturn(['status' => -1,'msg' => '请求参数错误']);
        $goods = M('goods')->where(array('goods_id'=>$goods_id))->find();
        if(!$goods) return $this->ajaxReturn(['status' => -1,'msg' => '该商品不允许购买，原因有：商品下架、不存在、过期等']);
        if($goods['is_virtual'] == 1 && $goods['virtual_indate']>time() && $goods['store_count']>0){
            $goods_num = $goods['goods_num'] = I('goods_num/d');
            if($goods_num < 1){ return $this->ajaxReturn(['status' => -1,'msg' => '最少购买1件']);}
            if ($goods['virtual_limit'] > $goods['store_count'] || $goods['virtual_limit'] == 0) {
                $goods['virtual_limit'] = $goods['store_count'];
            }

            if ($goods_num > $goods['virtual_limit']) {
                return $this->ajaxReturn(['status' => -1,'msg' => '购买数量超过限制']);
            }

            $goods_spec = I('goods_spec/a');
            if(!empty($goods_spec) && $goods_spec !='undefined'){
                $specGoodsPriceList = M('SpecGoodsPrice')->where(array('goods_id'=>$goods_id))->cache(true,TPSHOP_CACHE_TIME)->getField("key,key_name,price,store_count,sku"); // 获取商品对应的规格价钱 库存 条码
                foreach($goods_spec as $key => $val){
                    if($val != 'undefined'){
                        $spec_item[] = $val; // 所选择的规格项
                    }
                }
                if(!empty($spec_item) && $spec_item !='undefined') // 有选择商品规格
                {
                    sort($spec_item);
                    $spec_key = implode('_', $spec_item);
                    if($specGoodsPriceList[$spec_key]['store_count'] < $goods_num){
                        return $this->ajaxReturn(['status' => -1,'msg' => '该商品规格库存不足']);
                    }
                    $goods['goods_spec_key'] = $spec_key;
                    $goods['spec_key_name'] = $specGoodsPriceList[$spec_key]['key_name'];
                    $spec_price = $specGoodsPriceList[$spec_key]['price']; // 获取规格指定的价格
                    $goods['shop_price'] = empty($spec_price) ? $goods['shop_price'] : $spec_price;
                }
            }

            $goods_spec_key = I('goods_spec_key');
            if(!empty($goods_spec_key)){
                $specGoods = M('SpecGoodsPrice')->where(array('goods_id'=>$goods_id,'key'=>$goods_spec_key))->find();
                if($specGoods['store_count']<$goods_num)
                    return $this->ajaxReturn(['status' => -1,'msg' => '该商品规格库存不足']);
                $goods['shop_price'] = empty($specGoods['price']) ? $goods['shop_price'] : $specGoods['price'];
                $goods['goods_spec_key'] = $goods_spec_key;
                $goods['spec_key_name'] = $specGoods['key_name'];
            }

            //检查用户余额
            $userMoney = I('user_money/f');
            $userLeftMoney = (new Users())->where('user_id',$this->user_id)->getField('user_money');
            if ($userMoney > $userLeftMoney || $userMoney < 0) {
                return $this->ajaxReturn(['status' => -1,'msg' => '余额不足']);
            }elseif ($userMoney > 0) {
                //判断支付密码
                $payPwd = I('pay_pwd');
                $userPayPwd = (new Users())->where('user_id',$this->user_id)->getField('paypwd');
                if (empty($userPayPwd)) {
                    return $this->ajaxReturn(['status' => -1,'msg' => '未设置支付密码']);
                }
                if (encrypt($payPwd) !== $userPayPwd) {
                    return $this->ajaxReturn(['status' => -1,'msg' => '支付密码错误']);
                }
            }
            $goods['goods_fee'] = $goods['shop_price']*$goods['goods_num'] - $userMoney;

            return $goods;
        }else{
            return $this->ajaxReturn(['status' => -1,'msg' => '该商品不允许购买，原因可能：商品下架、不存在、过期等']);
        }
    }

    public function changUserMoney($userMoney,$order)
    {
        if($userMoney > 0){
            $user = Users::get($this->user_id);
            if($userMoney > 0){
                $user->user_money = $user->user_money - $userMoney;// 抵扣余额
            }
            $user->save();
            $accountLogData = [
                'user_id' => $this->user_id,
                'user_money' => -$userMoney,
                'pay_points' => 0,
                'change_time' => time(),
                'desc' => '下单消费',
                'order_sn'=>$order['order_sn'],
                'order_id'=>$order['order_id'],
            ];
            Db::name('account_log')->insert($accountLogData);
        }
    }


    //订单支付结果通知
    public function orderResult()
    {
        $order_id = I('get.order_id/d');
        $map['order_id'] = $order_id;
        $map['user_id'] = $this->user_id;
        $order_info = M('order')->where($map)->find();
        $order_info['goods_num'] = M('order_goods')->where('order_id',$order_id)->getField('goods_num');
        if (!$order_info) {
            $this->error('没有获取到订单信息');
            exit;
        }
        $this->assign('order_info',$order_info);
        return $this->fetch();
    }

    //完善资料页面
    public function preferUserInfo()
    {
        $userNum = I('get.goods_num/d');;
        $orderId = I('get.order_id/d',0);
        $showOrder = I('get.show_order/d',0);
        $this->assign('mobile','17716145831');
        //查询当前用户信息
        //1.通过order_id查找form信息
        $virtualFormInfo = (new \app\common\model\Order())->alias('a')->field('b.goods_num,c.virtual_form_id')
            ->join('order_goods b','a.order_id = b.order_id','LEFT')
            ->join('goods c','b.goods_id = c.goods_id','LEFT')
            ->where('a.order_id',$orderId)
            ->where('a.user_id',$this->user_id)
            ->find();

        if (!$virtualFormInfo->virtual_form_id) {
            $this->error('表单模板错误');
        }

        $this->assign('order_id',$orderId);
        $this->assign('show_order',$showOrder);
        $this->assign('user_num',$virtualFormInfo->goods_num);
        $formDataNum = Db::table('cf_form_data')
            ->where('form_id',$virtualFormInfo->virtual_form_id)
            ->where('order_id',$orderId)
            ->where('user_id',$this->user_id)
            ->count();

        $this->assign('no_complete_num',$virtualFormInfo->goods_num - $formDataNum);
        if ($userNum == 1) {
            //查询表单模板（美年大健康）
            $formData = Db::table('cf_form_template')
                ->where('form_id',$virtualFormInfo->virtual_form_id)->select();
            $this->assign('form_data',$formData);
            return $this->fetch('onePreferUserInfo');
        }else{
            //2.通过order_id和form信息查找相应的用户信息
            $formDataInfo = Db::table('cf_form_data')
                ->where('form_id',$virtualFormInfo->virtual_form_id)
                ->where('order_id',$orderId)
                ->where('user_id',$this->user_id)
                ->order('show_order')
                ->select();
            $formDataInfo = convert_arr_key($formDataInfo,'show_order');
            $arr = [];
            for ($i = 0 ; $i <= $virtualFormInfo->goods_num - 1 ; $i++) {
                if ($formDataInfo[$i]) {
                    $formUserInfo = json_decode($formDataInfo[$i]['content']);
                    $formDataInfo[$i]['full_name'] = $formUserInfo->full_name;
                    $formDataInfo[$i]['mobile'] = $formUserInfo->mobile;
                    $arr[$i] = $formDataInfo[$i];
                }else{
                    $arr[$i] = [];
                }
            }

            $formDataInfo = $arr;

            $this->assign('form_data_info',$formDataInfo);
            return $this->fetch('morePreferUserInfo');
        }
    }

    //接受提交表单
    public function saveFormInfo()
    {
        $data = I('post.');
        if (!check_mobile($data['mobile'])) {
            return $this->ajaxReturn(['status' => -1,'msg' => '手机号不正确']);
        }
        if (!is_id_card($data['id_no'])) {
            return $this->ajaxReturn(['status' => -1,'msg' => '身份证号不正确']);
        }

        $saveData = [
            'user_id' => $this->user_id,
            'form_id' => $data['form_id'],
            'order_id' => $data['order_id'],
            'show_order' => $data['show_order'],
            'add_time' => time(),
        ];

        $formTemplateInfo = Db::table('cf_form_template')->where('form_id',$data['form_id'])->find();
        unset($data['form_id']);
        unset($data['order_id']);
        unset($data['show_order']);

        if (!$formTemplateInfo) {
            return $this->ajaxReturn(['status' => -1,'msg' => '表单模板不正确']);
        }
        $formInfo = json_decode($formTemplateInfo['dynamic_column'],true);
        //验证数据条数
        foreach ($formInfo as $k => $v){
            if ($v['type'] == 0) {
                unset($formInfo[$k]);
            }
        }

        if (count($formInfo) != count($data)) {
            return $this->ajaxReturn(['status' => -1,'msg' => '数据不完整，请检查']);
        }

        //检查已经添加的信息条数是否超过购买数量
        $goods_num = (new \app\common\model\Order())->alias('a')
            ->join('order_goods b','a.order_id = b.order_id','LEFT')
            ->where('a.order_id',$saveData['order_id'])
            ->where('a.user_id',$this->user_id)
            ->getField('b.goods_num');

        $formDataNum = Db::table('cf_form_data')
            ->where('form_id',$saveData['form_id'])
            ->where('order_id',$saveData['order_id'])
            ->where('user_id',$this->user_id)
            ->count();

        if ($formDataNum >= $goods_num) {
            return $this->ajaxReturn(['status' => -1,'msg' => '填写用户信息超过购买数量']);
        }

        $saveData['content'] = json_encode($data);
        $formId = Db::table('cf_form_data')->insertGetId($saveData);

        if ($formId) {
            //添加兑换码所对应的用户信息
            $recId = Db::name('vr_order_code')
                ->where('order_id',$saveData['order_id'])
                ->where('user_id',$this->user_id)
                ->where('form_data_id',0)
                ->getField('rec_id');
            $res = Db::name('vr_order_code')
                ->where('rec_id',$recId)
                ->update(['form_data_id' => $formId]);
            if (!$res){
                return $this->ajaxReturn(['status' => -1,'msg' => '绑定用户数据失败']);
            }
            return $this->ajaxReturn(['status' => 1,'msg' => '添加成功']);
        }
        return $this->ajaxReturn(['status' => -1,'msg' => '添加身份信息错误，请联系客服。']);
    }
}  