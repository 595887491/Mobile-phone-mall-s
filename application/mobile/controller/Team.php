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
 * 采用最新Thinkphp5助手函数特性实现单字母函数M D U等简写方式
 * ============================================================================
 * $Author: IT宇宙人 2015-08-10 $
 */
namespace app\mobile\controller;

use app\common\logic\ActivityLogic;
use app\common\logic\CartLogic;
use app\common\logic\CouponLogic;
use app\common\logic\GoodsLogic;
use app\common\logic\Pay;
use app\common\logic\PlaceOrder;
use app\common\logic\team\TeamOrder;
use app\common\logic\UsersLogic;
use app\common\model\Goods;
use app\common\model\Order;
use app\common\model\OrderGoods;
use app\common\model\TeamActivity;
use app\common\model\TeamFollow;
use app\common\model\TeamFound;
use app\common\model\UserModel;
use app\common\model\Users;
use app\common\util\TpshopException;
use think\Cache;
use think\Cookie;
use think\Db;
use think\Page;


class Team extends MobileBase
{
    public $user_id = 0;
    public $user = array();
    /**
     * 构造函数
     */
    public function  __construct()
    {
        parent::__construct();

        $this->checkUserLogin([
            'index','category','info','AjaxTeamList','ajaxCheckTeam','ajaxTeamFound','ajaxGetMore','lottery','joinTeamInfo','joinGroup','teamRule'
        ]);
    }

    /**
     * 拼团首页
     * @return mixed
     */
    public function index()
    {
        if (IS_AJAX) {
            $p = input('p',1);
            //目前只有分享团
            $team_where['t.team_type'] = 0;
            $team_where['t.status'] = 1;

            $TeamActivity = new TeamActivity();
            $list = $TeamActivity->field('t.*')->alias('t')
                ->join('__GOODS__ g', 'g.goods_id = t.goods_id')
                ->join('team_found a','a.team_id = t.team_id','LEFT')
                ->with([
                    'goods'=>function($query) {
                        $query->field('goods_id,goods_name,shop_price,original_img,goods_remark');
                    },
                    'specGoodsPrice'=>function($query) {
                        $query->field('item_id,price');
                    },
                    'teamFound'=>function($query) {
                        $query->where('status',1)->whereOr('status',2);
                    },'teamFollow'=>function($query) {
                        $query->where('status',1)->whereOr('status',2);
                    },
                ])
                ->where(function ($query){
                    $query->where([
                        '`t`.`team_type`' => 0,
                        '`t`.`status`' => 1,
                        '`t`.`team_priv`' => 0,
                    ]);
                })->whereOr(function ($query){
                    $query->where([
                        '`t`.`team_type`' => 0,
                        '`t`.`status`' => 1,
                        '`t`.team_priv' => 1,
                        'a.found_end_time' => ['>',time()],
                        'a.need' => ['exp','>a.`join`'] ,
                        'a.status' =>1
                    ]);
                })
                ->group('t.goods_id')
                ->order('t.team_id desc')
                ->page($p, 10)->select();
            $this->assign('list',$list);
            return $this->fetch('ajax_info');
        }
        return $this->fetch();
    }

    //拼团 详情页
    public function info(){
        $userInfo = (new UserModel())->getUserRelationIdentity($this->user_id);

        $team_id = input('team_id');
        $goods_id = input('goods_id');
        if(empty($goods_id)){
            $this->error('参数错误', U('Mobile/Team/index'));
        }
        $TeamActivity = new TeamActivity();
        $Goods = new Goods();
        $goods = $Goods->where(['is_on_sale'=>1,'goods_id'=>$goods_id])->find();
        $teamList = $TeamActivity->where('goods_id', $goods_id)
            ->with([
            'teamFound'=>function($query) {
                $query->where('status',1)->whereOr('status',2);
            },'teamFollow'=>function($query) {
                $query->where('status',1)->whereOr('status',2);
            },
        ])->select();
        if (empty($teamList)) {
            $this->error('该商品拼团活动不存在或者已被删除', U('Mobile/Team/index'));
        }
        if(empty($goods)){
            $this->error('此商品不存在或者已下架', U('Mobile/Team/index'));
        }
        foreach($teamList as $teamKey=>$teamVal){
            if($team_id && $teamVal['team_id'] == $team_id){
                $team = $teamVal;
                break;
            }
        }
        if(empty($team)){
            $team = $teamList[0];
        }
        $user_id = cookie('user_id');
        if($user_id){
            $collect = Db::name('goods_collect')->where(array("goods_id"=>$goods_id ,"user_id"=>$user_id))->count();
            $this->assign('collect',$collect);
        }
        $spec_goods_price = Db::name('spec_goods_price')->where("goods_id",$goods_id)->getField("key,price,store_count,item_id,prom_id"); // 规格 对应 价格 库存表
        if($spec_goods_price){
            foreach($spec_goods_price as $specKey=>$specVal){
                $spec_goods_price[$specKey]['team_id'] = 0;
                $spec_goods_price[$specKey]['key_array'] = explode('_', $spec_goods_price[$specKey]['key']);
                foreach($teamList as $teamKey=>$teamVal){
                    if($specVal['item_id'] == $teamVal['item_id'] && $specVal['prom_id'] == $teamVal['team_id'] && $teamVal['status'] == 1){
                        $spec_goods_price[$specKey]['team_id'] = $teamVal['team_id'];
                        continue;
                    }
                }
            }
        }
        $this->assign('spec_goods_price', json_encode($spec_goods_price,true));


        //商品缩略图
        $goods_images_list = M('GoodsImages')->where(["goods_id"=>$goods_id,'image_url'=>['exp','<>""']])->order('img_id DESC')->select(); // 商品 图册
        $this->assign('goods_images_list',$goods_images_list);//商品缩略图
        //可赚多少钱
        if ($userInfo['identity']['partner'] || $userInfo['identity']['agent']) {
            $this->assign('can_earn_money',round($team->team_price * 0.06,2));
        }
        if ($team->needer_type && $this->user_id) {
            //判断新老会员
            $regTime = (new Users())->where('user_id',$this->user_id)->getField('reg_time');
            //判断是否参与过拼团
            $where = [
                'user_id' => $this->user_id,
                'prom_type' => 6,
                'pay_status' => 1
            ];
            $orderInfo = (new Order())->where($where)->find();

            $limitResult = $regTime - 7*24*3600 > 0 || $orderInfo;

            if ($limitResult) {
                $this->assign('limit_new_user',1);
            }else{
                $this->assign('limit_new_user',0);
            }
        }else{
            $this->assign('limit_new_user',0);
        }

        //商品拼团活动主体
        $this->assign('team', $team);

        //商品评论
        $goodsLogic = new GoodsLogic();
        $commentStatistics = $goodsLogic->commentStatistics($goods_id);// 获取某个商品的评论统计
        $this->assign('commentStatistics',$commentStatistics);//评论概览

        //可直接参加的团(当前商品)
        $TeamFound = new TeamFound();
        $TeamFoundDatas = $TeamFound->with('order,orderGoods,teamActivity,teamFollow.order,teamFollow.orderGoods')
            ->limit(3)
            ->where('team_id',$team_id)
            ->where('found_end_time','>=',time())
            ->where('need','exp',' > `join`')
            ->where('status','=',1)
            ->order('`join` desc,found_end_time')
            ->select();

        foreach ($TeamFoundDatas as &$v){
            if (check_mobile($v->nickname)){
                $v->nickname = substr_replace($v->nickname, '****', 3, 4);
            }
        }

        $this->assign('team_found_data',$TeamFoundDatas);

        //一条最近的好评
        $typeArr = array('1' => '0,1,2,3,4,5', '2' => '4,5', '3' => '3', '4' => '0,1,2');
        $where = array('is_show'=>1,'goods_id' => $goods_id, 'parent_id' => 0, 'ceil((deliver_rank + goods_rank + service_rank) / 3)' => ['in', $typeArr[2]]);
        $list = M('Comment')
            ->alias('c')
            ->join('__USERS__ u', 'u.user_id = c.user_id', 'LEFT')
            ->where($where)
            ->order("add_time desc")
            ->limit(1)
            ->find();
        if ($list) {
            $list['add_time'] = date('Y-m-d H:i',$list['add_time']);
        }

        if ($list['img']) {
            $list['img'] = array_slice(unserialize($list['img']),0,4);
        }

        $this->assign('good_comment',$list);

        //详情里商品规格，属性
        $goods_attribute = M('GoodsAttribute')->getField('attr_id,attr_name'); // 查询属性
        $goods_attr_list = M('GoodsAttr')->where("goods_id", $goods_id)->select(); // 查询商品属性表
        $this->assign('goods_attr_list',$goods_attr_list);
        $this->assign('goods_attribute',$goods_attribute);//属性值

        //购买时，规格参数
        $filter_spec = $goodsLogic->get_spec($goods_id);
        foreach ($filter_spec as $k => $value){
            foreach ($value as $kk => $vv) {
                if ($team->item_id != $vv['item_id']) {
                    unset($filter_spec[$k][$kk]);
                }
            }
            if (empty($filter_spec[$k])) {
                unset($filter_spec[$k]);
            }
        }
        $this->assign('filter_spec', $filter_spec);

        $goods['prom_type'] = 6;
        $goods['prom_id'] = $team_id;
        $this->assign('goods',$goods);
        $this->assign('team_id', $team_id);//商品拼团活动主体
        return $this->fetch();
    }

    //判断用户可参加哪些团
    public function joinTeamInfo()
    {
        //我要参团判断
        if ($this->user_id) {
            $foundId = I('post.found_id',0);
            $foundInfo = Db::table('tp_team_found')->alias('a')
                ->join(['tp_team_activity' => ' b'],'a.team_id = b.team_id','LEFT')
                ->where('a.found_id',$foundId)
                ->find();

            if ($foundInfo['join'] >= $foundInfo['need'] ) {
                $data = [
                    'code' => 1,
                    'message' => '该团已满员，请换一个试试'
                ];
                return $this->ajaxReturn($data);
            }

            if ($foundInfo['needer_type']) {
                //判断新老会员
                $regTime = (new Users())->where('user_id',$this->user_id)->getField('reg_time');
                //判断是否参与过拼团
                $where = [
                    'user_id' => $this->user_id,
//                    'prom_type' => 6,
                    'pay_status' => 1
                ];
                $orderInfo = (new Order())->where($where)->find();

//                if ( ($regTime + 7*24*3600 < time()) || $orderInfo) {
                if ( $orderInfo ) {
                    $data = [
                        'code' => 2,
                        'message' => '仅限新用户参团，老用户不可开团'
                    ];
                    return $this->ajaxReturn($data);
                }
            }

            $data = [
                'code' => 3,
                'message' => '可参团'
            ];
            return $this->ajaxReturn($data);
        }else{
            $data = [
                'code' => 0,
                'message' => '未登录'
            ];
            return $this->ajaxReturn($data);
        }
    }


    /**
     * 购物车第二步确定页面
     */
    public function addOrder(){
        C('TOKEN_ON', false);
        $goods_id = input("goods_id/d"); // 商品id
        $goods_num = input("goods_num/d");// 商品数量
        $item_id = input("item_id/d"); // 商品规格id
        $team_id = input('team_id/d');//拼团活动id
        $found_id = input('found_id/d');//拼团id，有此ID表示是团员参团,没有表示团长开团
        if ($this->user_id == 0){
            $this->redirect('User/login');
        }
        if (empty($team_id)) {
            $this->ajaxReturn(['status' => 0, 'msg' => '参数错误', 'result' => '']);
        }
        if(empty($goods_num)){
            $this->ajaxReturn(['status' => 0, 'msg' => '至少购买一份', 'result' => '']);
        }
        //用户地址
        $address_id = I('address_id/d');
        if($address_id){
            $address = M('user_address')->where("address_id", $address_id)->find();
        } else {
            //默认
            $address = Db::name('user_address')->where(['user_id'=>$this->user_id])
                ->where(['is_default'=> 1])->find();
            //不是默认地址
            if (empty($address)) {
                $address = Db::name('user_address')->where(['user_id'=>$this->user_id])->find();
            }

//            //最近使用
//            if (empty($address)) {
//                $address = (new Order())->field('consignee,country,province,city,district,twon,address,mobile,zipcode')
//                    ->where('user_id',$this->user_id)
//                    ->order('add_time DESC')->find();
//                if ( empty($address->consignee) || empty($address->province) ||
//                    empty($address->city) || empty($address->district) || empty($address->address) ) {
//                    $address = null;
//                }
//            }
        }

        $cartLogic = new CartLogic();
        $couponLogic = new CouponLogic();
        $cartLogic->setUserId($this->user_id);

        //立即购买
        $cartLogic->setGoodsModel($goods_id);
        $cartLogic->setSpecGoodsPriceModel($item_id);
        $cartLogic->setGoodsBuyNum($goods_num);
        $buyGoods = [];
        try{
            $buyGoods = $cartLogic->buyNow();
            $teamData = (new TeamActivity())->where('team_id', $team_id)->find();
            $buyGoods['team_price'] = $teamData->team_price;
            $buyGoods['member_goods_price'] = $teamData->team_price;
            $buyGoods['goods_fee'] = $teamData->team_price * $goods_num;
        }catch (TpshopException $t){
            $error = $t->getErrorArr();
            $this->error($error['msg']);
        }
        $cartList['cartList'][0] = $buyGoods;
        $cartGoodsTotalNum = $goods_num;
        $activityLogic = new ActivityLogic();
        $unReceiveCoupon = $activityLogic->getCouponList(2, $this->user_id); // 未领取但可领取的优惠券
        $cartGoodsList = get_arr_column($cartList['cartList'],'goods');
        $cartGoodsId = get_arr_column($cartGoodsList,'goods_id');
        $cartGoodsCatId = get_arr_column($cartGoodsList,'cat_id');
        $cartPriceInfo = $cartLogic->getCartPriceInfo($cartList['cartList']);  //初始化数据。商品总额/节约金额/商品总共数量
        $userCouponList = $couponLogic->getUserAbleCouponList($this->user_id, $cartGoodsId, $cartGoodsCatId);//用户可用的优惠券列表
        $cartList = array_merge($cartList,$cartPriceInfo);
        $userCartCouponList = $cartLogic->getCouponCartList($cartList, $userCouponList);
        $userCouponNum = $cartLogic->getUserCouponNumArr();
        $userMoney = (new Users())->where('user_id',$this->user_id)->getField('user_money');
        $this->assign('user_money',$userMoney ?? 0); //收货地址
        $this->assign('address',$address); //收货地址
        $this->assign('userCartCouponList', $userCartCouponList);  //优惠券，用able判断是否可用
        $this->assign('userCouponNum', $userCouponNum);  //优惠券数量
        $this->assign('cartGoodsTotalNum', $cartGoodsTotalNum);
        $this->assign('unReceiveCoupon', $unReceiveCoupon); // 未领取但可领取的优惠券
        $this->assign('cartList', $cartList['cartList']); // 购物车的商品
        $this->assign('cartPriceInfo', $cartPriceInfo);//商品优惠总价
        return $this->fetch();
    }


    /**
     * 获取订单详情
     */
    public function getOrderInfo()
    {
        if($this->user_id == 0){
            exit(json_encode(array('status'=>-100,'msg'=>"登录超时请重新登录!",'result'=>null))); // 返回结果状态
        }
        $address_id     = input('address_id/d');
        $invoice_title = I('invoice_title'); // 发票
        $taxpayer = I('taxpayer'); // 纳税人编号
        $coupon_id      = input('coupon_id/d');
//        $pay_points     = input('pay_points/d');
        $pay_points = 0; // 老商城规则，不能使用积分购买普通商品
        $user_money = input('user_money/f',0);//  使用余额
        $user_note = trim(I('user_note'));   //买家留言
//        $goods_id = input("goods_id/d"); // 商品id
        $goods_num = input('goods_num/d');
//        $item_id = input("item_id/d"); // 商品规格id
        $found_id = input('found_id/d');//拼团id，有此ID表示是团员参团,没有表示团长开团
        $team_id = input("team_id/d");

        $payPwd = trim(input("payPwd")); //  支付密码
        $shipping = input("shipping/d"); // 快递方式 0:到店自提  1：物流方式
        strlen($user_note) > 50 && exit(json_encode(['status'=>-1,'msg'=>"备注超出限制可输入字符长度！",'result'=>null]));
        $address = Db::name('UserAddress')->where("address_id", $address_id)->find();
        $pay = new Pay();
        $pay->setUserId($this->user_id);

        $team = new \app\common\logic\team\Team();
        try{
            $team->setTeamFoundById($found_id);
            $team->setUserById($this->user_id);
            $team->setTeamActivityById($team_id);
            $team->setBuyNum($goods_num);
            $team->buy();
            $teamActivity = $team->getTeamActivity();
            $goods = $team->getTeamBuyGoods();
            if ($teamActivity->is_shipping) {
                $goods->team_free_shipping = 1;
            }
            $goodsList[0] = $goods;
            $pay->payGoodsList($goodsList);

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
                $placeOrder->addTeamOrder($teamActivity);
            }catch (TpshopException $t) {
                $error = $t->getErrorArr();
                $this->ajaxReturn($error);
            }
            $order = $placeOrder->getOrder();
            $team->log($order);
            $this->ajaxReturn(['status'=>1,'msg'=>'提交订单成功','result'=>$order['order_sn']]);
        }
        $car_price = $pay->toArray();
        $this->ajaxReturn(['status'=>1,'msg'=>'计算成功','result'=>$car_price]);
    }

    /**
     * 拼团分享页
     * @return mixed
     */
    public function found()
    {
        $found_id = input('id');
        if (empty($found_id)) {
            $this->error('参数错误', U('Mobile/Team/index'));
        }
        $teamFound = TeamFound::get($found_id);
        $teamFollow = $teamFound->teamFollow()->where('status','IN', [1,2])->select();
        $team = $teamFound->teamActivity;

        if(time() - $teamFound['found_time'] > $team['time_limit']){
            //时间到了
            if($teamFound['join'] < $teamFound['need']){
                //人数没齐
                $teamFound->status = 3;//成团失败
                $teamFound->save();
                //更新团员成团失败
                Db::name('team_follow')->where(['found_id'=>$found_id,'status'=>1])->update(['status'=>3]);
            }
        }
        $this->assign('teamFollow', $teamFollow);//团员
        $this->assign('team', $team);//活动
        $this->assign('teamFound', $teamFound);//团长
        return $this->fetch();
    }

    public function ajaxGetMore(){
        $p = input('p/d',0);
        $TeamActivity = new TeamActivity();
        $team = $TeamActivity->with('goods')->where(['status'=>1])->page($p,4)->order(['is_recommend'=>'desc','sort'=>'desc'])->select();
        if(empty($team)){
            $this->ajaxReturn(['status'=>0,'msg'=>'已显示完所有记录']);
        }else{
            $result = collection($team)->append(['virtual_sale_num'])->toArray();
            $this->ajaxReturn(['status'=>1,'msg'=>'','result'=>$result]);
        }
    }

    public function lottery(){
        $team_id = input('team_id/d',0);
        $team_lottery = Db::name('team_lottery')->where('team_id',$team_id)->select();
        $TeamActivity = new TeamActivity();
        $team = $TeamActivity->with('specGoodsPrice,goods')->where('team_id',$team_id)->find();
        $this->assign('team',$team);
        $this->assign('team_lottery',$team_lottery);
        return $this->fetch();
    }

    //拼团规则
    public function teamRule()
    {
        return $this->fetch();
    }

    //拼团结果页面
    public function teamResult()
    {
        return $this->fetch();
    }


    /*************************************************     赵磊---我的拼团      ******************************/

    /*
     * 我的拼团
     * */
    public function myTeam()
    {
        $teamFollow = new TeamFollow();
        $res = $teamFollow->getTeamInfoList($this->user_id); //获取拼团列表信息
        $count = count($res);//总条数
        $page = new Page($count, 20);
        $res = array_slice($res,$page->firstRow,20);
        $this->assign('teamList',$res);
        $this->assign('totalPages',$page->totalPages);//总页数
        $this->assign('page',$page);// 赋值分页输出
        $this->assign('count',$count);// 总条数
//        dump(time());
        if(input('is_ajax')){
            return $this->fetch('ajax_myTeam'); //获取更多
        }
//        halt($res);
        return  $this->fetch();
    }



    /*
     * 倒计时结束Ajax更改状态
     * */
    public function changeStatus()
    {
        $model = new TeamFollow();
        if (IS_AJAX){
            $res = $model->EndTime(I('found_id'));
            if ($res){
                $data = [
                    'code' => 200,
                    'message' => 'success'
                ];
                $this->ajaxReturn($data);
            }
        };
    }

    public function test()
    {
        $model = new TeamFound();
        $model->checkStatus($this->user_id);
    }




    /*
       * 拼团详情
       * */
    public function myInfo(){
        $model = new TeamFollow();
        $foundId = input('found_id');
        $model->EndTime($foundId);
        $res = $model->getTeamInfo($foundId,$this->user_id);
        $res['orderInfo']['order_pick_up_code'] = (new OrdersPickUp())->getPickUpCode($res['orderInfo']['order_id']);//自提订单自提码信息

        $this->assign('order_goods', $res['order_goods']);
        $this->assign('startTime', time());//当前时间
        $this->assign('delivery', $res['delivery']);//物流信息
        $this->assign('orderStatus',$res['TeamOrderStatus']); //拼团信息
        $this->assign('orderInfo',$res['orderInfo']); //拼团订单信息
        $region_list = get_region_list();
        $map['order_id'] = $res['orderInfo']['order_id'];
        $map['user_id'] = $this->user_id;
        $order_info = M('order')->where($map)->find();
        $this->assign('region_list', $region_list);// 区域
        $this->assign('address', $order_info);// 地址
        $this->assign('teamInfo',$res['teamInfo']); //拼团信息
        $this->assign('followUser',$res['followUserInfo']); //参团人员
        $this->assign('param',$res['parameter']); //拼团商品参数
        return $this->fetch('myInfo');
    }



    /*
     * 参团
     * */
    public function joinGroup(){
        $model = new TeamFollow();
        $foundId = I('found_id');
        $res = $model->getTeamInfo($foundId,$this->user_id);
        //判断拼团是否满员
        $isFull = 2;//拼团未满
        if ($res['teamInfo']['status'] == 2 || $res['teamInfo']['join'] == $res['teamInfo']['need'])
        {
            $isFull = 1;//拼团已满
        }

        if (in_array($this->user_id,$res['inTeam'])){
            $inTeam = 1;//用户是否在拼团内;1 在; 2 不在
        }else{
            $inTeam = 2;//用户是否在拼团内;1 在; 2 不在
        }
        $isNewUser = $this->joinTeamInfos($res['teamInfo']['found_id']);
        $this->assign('isNewUser', $isNewUser['code']);//是否是新用户;2为老用户;3可参团
        $this->assign('isFull', $isFull);//拼团满员
        $this->assign('order_goods', $res['order_goods']);
        $this->assign('startTime', time());//当前时间
        $this->assign('delivery', $res['delivery']);//物流信息
        $this->assign('orderStatus',$res['TeamOrderStatus']); //拼团信息
        $this->assign('orderInfo',$res['orderInfo']); //拼团订单信息
        $this->assign('teamInfo',$res['teamInfo']); //拼团信息
        $this->assign('followUser',$res['followUserInfo']); //参团人员
        $this->assign('inTeam',$inTeam); //参团人员是否在团内
        $this->assign('param',$res['parameter']); //拼团商品参数
        return $this->fetch();
    }


    //分享判断用户可参加哪些团
    public function joinTeamInfos($foundId)
    {
        //我要参团判断
        if ($this->user_id) {
            $foundInfo = Db::table('tp_team_found')->alias('a')
                ->join(['tp_team_activity' => ' b'],'a.team_id = b.team_id','LEFT')
                ->where('a.found_id',$foundId)
                ->find();

            if ($foundInfo['join'] >= $foundInfo['need'] ) {
                $data = [
                    'code' => 1,
                    'message' => '该团已满员，请换一个试试'
                ];
                return $data;
            }

            if ($foundInfo['needer_type']) {
                //判断新老会员
                $regTime = (new Users())->where('user_id',$this->user_id)->getField('reg_time');
                //判断是否参与过拼团
                $where = [
                    'user_id' => $this->user_id,
                    'prom_type' => 6,
                    'pay_status' => 1
                ];
                $orderInfo = (new Order())->where($where)->find();

                if ( ($regTime - 7*24*3600 - time()) > 0 || $orderInfo) {
                    $data = [
                        'code' => 2,
                        'message' => '仅限新用户参团，老用户不可开团'
                    ];
                    return $data;
                }
            }

            $data = [
                'code' => 3,
                'message' => '可参团'
            ];
            return $data;
        }else{
            $data = [
                'code' => 0,
                'message' => '未登录'
            ];
            return $data;
        }
    }








}