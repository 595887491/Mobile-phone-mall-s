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
use app\common\logic\ActivityLogic;
use app\common\logic\GoodsLogic;
use app\common\logic\GoodsPromFactory;
use app\common\model\EsModel;
use app\common\model\GoodsGlobal;
use app\common\model\GoodsTopic;
use app\common\model\SpecGoodsPrice;
use app\common\model\UserModel;
use think\AjaxPage;
use think\Page;
use think\Db;
use think\Session;

class Goods extends MobileBase {
    /**
     * 分类列表显示
     */
    public function categoryList(){
        return $this->fetch();
    }

    /**
     * 商品列表页
     */
    public function goodsList(){
        $filter_param = array(); // 帅选数组
        $id = I('id/d',1); // 当前分类id
        $brand_id = I('brand_id/d',0);
        $spec = I('spec',0); // 规格
        $attr = I('attr',''); // 属性
        $sort = I('sort','goods_id'); // 排序
        $sort_asc = I('sort_asc','asc'); // 排序
        $price = I('price',''); // 价钱
        $start_price = trim(I('start_price','0')); // 输入框价钱
        $end_price = trim(I('end_price','0')); // 输入框价钱
        if($start_price && $end_price) $price = $start_price.'-'.$end_price; // 如果输入框有价钱 则使用输入框的价钱
        $filter_param['id'] = $id; //加入帅选条件中
        $brand_id  && ($filter_param['brand_id'] = $brand_id); //加入帅选条件中
        $spec  && ($filter_param['spec'] = $spec); //加入帅选条件中
        $attr  && ($filter_param['attr'] = $attr); //加入帅选条件中
        $price  && ($filter_param['price'] = $price); //加入帅选条件中

        $goodsLogic = new GoodsLogic(); // 前台商品操作逻辑类
        // 分类菜单显示
        $goodsCate = M('GoodsCategory')->where("id", $id)->find();// 当前分类sss
        $this->assign('cate_title',$goodsCate['name']);
        $this->assign('cate_img',$goodsCate['image']);
        //($goodsCate['level'] == 1) && header('Location:'.U('Home/Channel/index',array('cat_id'=>$id))); //一级分类跳转至大分类馆
        $cateArr = $goodsLogic->get_goods_cate($goodsCate);

        // 帅选 品牌 规格 属性 价格
        $cat_id_arr = getCatGrandson ($id);
        $goods_where = ['is_on_sale' => 1, 'exchange_integral' => 0,'cat_id'=>['in',$cat_id_arr]];
        $filter_goods_id = Db::name('goods')->where($goods_where)->cache(true)->getField("goods_id",true);

        // 过滤帅选的结果集里面找商品
        if($brand_id || $price)// 品牌或者价格
        {
            $goods_id_1 = $goodsLogic->getGoodsIdByBrandPrice($brand_id,$price); // 根据 品牌 或者 价格范围 查找所有商品id
            $filter_goods_id = array_intersect($filter_goods_id,$goods_id_1); // 获取多个帅选条件的结果 的交集
        }
        if($spec)// 规格
        {
            $goods_id_2 = $goodsLogic->getGoodsIdBySpec($spec); // 根据 规格 查找当所有商品id
            $filter_goods_id = array_intersect($filter_goods_id,$goods_id_2); // 获取多个帅选条件的结果 的交集
        }
        if($attr)// 属性
        {
            $goods_id_3 = $goodsLogic->getGoodsIdByAttr($attr); // 根据 规格 查找当所有商品id
            $filter_goods_id = array_intersect($filter_goods_id,$goods_id_3); // 获取多个帅选条件的结果 的交集
        }

        //筛选网站自营,入驻商家,货到付款,仅看有货,促销商品
        $sel =I('sel');
        if($sel)
        {
            $goods_id_4 = $goodsLogic->getFilterSelected($sel,$cat_id_arr);
            $filter_goods_id = array_intersect($filter_goods_id,$goods_id_4);
        }

        $filter_menu  = $goodsLogic->get_filter_menu($filter_param,'goodsList'); // 获取显示的帅选菜单
        $filter_price = $goodsLogic->get_filter_price($filter_goods_id,$filter_param,'goodsList'); // 帅选的价格期间
        $filter_brand = $goodsLogic->get_filter_brand($filter_goods_id,$filter_param,'goodsList'); // 获取指定分类下的帅选品牌
        $filter_spec  = $goodsLogic->get_filter_spec($filter_goods_id,$filter_param,'goodsList',1); // 获取指定分类下的帅选规格
        $filter_attr  = $goodsLogic->get_filter_attr($filter_goods_id,$filter_param,'goodsList',1); // 获取指定分类下的帅选属性

        $count = count($filter_goods_id);
        $page = new Page($count,C('PAGESIZE'));
        if($count > 0)
        {
            $goods_list = M('goods')->where("goods_id","in", implode(',', $filter_goods_id))->order("$sort $sort_asc")->limit($page->firstRow.','.$page->listRows)->select();
            $filter_goods_id2 = get_arr_column($goods_list, 'goods_id');
            if($filter_goods_id2)
                $goods_images = M('goods_images')->where("goods_id", "in", implode(',', $filter_goods_id2))->cache(true)->select();
        }
        $goods_category = M('goods_category')->where('is_show=1')->cache(true)->getField('id,name,parent_id,level'); // 键值分类数组
        $this->assign('goods_list',$goods_list);
        $this->assign('goods_category',$goods_category);
        $this->assign('goods_images',$goods_images);  // 相册图片
        $this->assign('filter_menu',$filter_menu);  // 帅选菜单
        $this->assign('filter_spec',$filter_spec);  // 帅选规格
        $this->assign('filter_attr',$filter_attr);  // 帅选属性
        $this->assign('filter_brand',$filter_brand);// 列表页帅选属性 - 商品品牌
        $this->assign('filter_price',$filter_price);// 帅选的价格期间
        $this->assign('goodsCate',$goodsCate);
        $this->assign('cateArr',$cateArr);
        $this->assign('filter_param',$filter_param); // 帅选条件
        $this->assign('cat_id',$id);
        $this->assign('page',$page);// 赋值分页输出
        $this->assign('sort_asc', $sort_asc == 'asc' ? 'desc' : 'asc');
        C('TOKEN_ON',false);
        if(input('is_ajax'))
            return $this->fetch('ajaxGoodsList');
        else
            return $this->fetch();
    }

    /**
     * 商品列表页 ajax 翻页请求 搜索
     */
    public function ajaxGoodsList() {
        $where ='';

        $cat_id  = I("id/d",0); // 所选择的商品分类id
        if($cat_id > 0)
        {
            $grandson_ids = getCatGrandson($cat_id);
            $where .= " WHERE cat_id in(".  implode(',', $grandson_ids).") "; // 初始化搜索条件
        }

        $result = DB::query("select count(1) as count from __PREFIX__goods $where ");
        $count = $result[0]['count'];
        $page = new AjaxPage($count,10);

        $order = " order by goods_id desc"; // 排序
        $limit = " limit ".$page->firstRow.','.$page->listRows;
        $list = DB::query("select *  from __PREFIX__goods $where $order $limit");

        $this->assign('lists',$list);
        $html = $this->fetch('ajaxGoodsList'); //return $this->fetch('ajax_goods_list');
        exit($html);
    }

    /**
     * 商品详情页
     */
    public function goodsInfo(){
        C('TOKEN_ON',true);
        $goodsLogic = new GoodsLogic();
        $goods_id = I("get.id/d");
        $goodsModel = new \app\common\model\Goods();
        $goods = $goodsModel::get($goods_id);
        $goods->shop_price = round($goods->shop_price,2);
        $goods->market_price = round($goods->market_price,2);

        //可赚多少钱
        $userInfo = (new UserModel())->getUserRelationIdentity(Session::get('user')['user_id']);

        if ($userInfo['identity']['partner'] || $userInfo['identity']['agent']) {
            $this->assign('can_earn_money',round($goods->shop_price * 0.06,2));
        }

        if ($goods['is_virtual']) {
            header('Location:'.U('Mobile/Virtual/goodsInfo',['id' => $goods_id]));
        }

        if(empty($goods) || ($goods['is_on_sale'] == 0) || ($goods['is_virtual']==1 && $goods['virtual_indate'] <= time())){
            $this->error('此商品不存在或者已下架');
        }

        //排除非新用户进入新用户专属的详情页
        $result = (new Index())->isNewUser();
        $cat_id_arr = getCatGrandson (721);
        if ($result == false && array_search($goods->cat_id,$cat_id_arr) != false) {
            $this->error('此商品不存在或者已下架');
        }

        if (cookie('user_id')) {
            $goodsLogic->add_visit_log(cookie('user_id'), $goods);
        }
        if($goods['brand_id']){
            $brnad = M('brand')->where("id", $goods['brand_id'])->find();
            $goods['brand_name'] = $brnad['name'];
        }
        $goods_images_list = M('GoodsImages')->where(["goods_id"=>$goods_id,'image_url'=>['exp','<>""']])->order('img_id DESC')->select(); // 商品 图册
        $goods_attribute = M('GoodsAttribute')->getField('attr_id,attr_name'); // 查询属性
        $goods_attr_list = M('GoodsAttr')->where("goods_id", $goods_id)->select(); // 查询商品属性表
        $filter_spec = $goodsLogic->get_spec($goods_id);
        $spec_goods_price  = M('spec_goods_price')->where("goods_id", $goods_id)->getField("key,price,store_count,item_id"); // 规格 对应 价格 库存表
        $commentStatistics = $goodsLogic->commentStatistics($goods_id);// 获取某个商品的评论统计
        $this->assign('spec_goods_price', json_encode($spec_goods_price,true)); // 规格 对应 价格 库存表
        $goods['sale_num'] = M('order_goods')->where(['goods_id'=>$goods_id,'is_send'=>1])->count();
        //当前用户收藏
        $user_id = cookie('user_id');
        $collect = M('goods_collect')->where(array("goods_id"=>$goods_id ,"user_id"=>$user_id))->count();
        $goods_collect_count = M('goods_collect')->where(array("goods_id"=>$goods_id))->count(); //商品收藏数
        $goodsActivity = $this->goodsRelatedActivity($goods_id);//该商品能参与的所有优惠活动
        $this->assign('collect',$collect);
        $this->assign('commentStatistics',$commentStatistics);//评论概览
        $this->assign('goods_attribute',$goods_attribute);//属性值
        $this->assign('goods_attr_list',$goods_attr_list);//属性列表
        $this->assign('filter_spec',$filter_spec);//规格参数
        $this->assign('goods_images_list',$goods_images_list);//商品缩略图
        $this->assign('goods',$goods->toArray());
        $point_rate = tpCache('shopping.point_rate');
        $this->assign('goods_collect_count',$goods_collect_count); //商品收藏人数
        $this->assign('point_rate', $point_rate);
        $this->assign('goods_activity', $goodsActivity);
        //看相似
        $brandId = $goods->brand_id;
        $cartId = $goods->cat_id;

        $similarGoods1 = Db::name('goods')
            ->field('goods_id,goods_name,goods_remark,shop_price,market_price,original_img,virtual_sales_num + sales_sum as sales_sum')
            ->where('brand_id', $brandId)
            ->where('is_on_sale', 1)
            ->where('goods_id', '<>',$goods_id)
            ->order('sales_sum DESC')
            ->limit(3)
            ->select();

        $similarGoods2 = Db::name('goods')
            ->field('goods_id,goods_name,goods_remark,shop_price,market_price,original_img,virtual_sales_num + sales_sum as sales_sum')
            ->where('cat_id', $cartId)
            ->where('is_on_sale', 1)
            ->where('goods_id', '<>',$goods_id)
            ->where('goods_id', 'not in',join(',',array_column($similarGoods1,'goods_id')))
            ->order('sales_sum DESC')
            ->limit(9 - count($similarGoods1))
            ->select();

        $similarGoods3 = Db::name('goods')
            ->field('goods_id,goods_name,goods_remark,shop_price,market_price,original_img,virtual_sales_num + sales_sum as sales_sum')
            ->where('is_on_sale', 1)
            ->where('goods_id', '<>',$goods_id)
            ->where('goods_id', 'not in',join(',',array_column(array_merge($similarGoods1,$similarGoods2),'goods_id')))
            ->order('goods_id DESC')
            ->limit(9 - count($similarGoods1) - count($similarGoods2))
            ->select();

        $similarGoods = array_merge($similarGoods1,$similarGoods2,$similarGoods3);

        foreach ($similarGoods as &$v) {
            $v['shop_price'] = round($v['shop_price'],2);
            $v['market_price'] = round($v['market_price'],2);
        }

        $this->assign('similar_goods',$similarGoods);

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

        $userInfo = session('user');
        $this->assign('service_user_info',$func($userInfo));
        return $this->fetch();
    }

    public function activity(){
        $goods_id = input('goods_id/d');//商品id
        $item_id = input('item_id',0);//规格id
        $goods_num = input('goods_num/d');//欲购买的商品数量
        $Goods = new \app\common\model\Goods();
        $goods = $Goods::get($goods_id,'',true);
        $ActivityLogic = new ActivityLogic();
        $activity = $ActivityLogic->goodsRelatedActivity($goods_id,$item_id);//优先检查是否有
        if (!empty($activity)) {
            $goods['prom_type'] = $activity['prom_type'];
            $goods['prom_id']   = $activity['prom_id'];
        }
        $goodsPromFactory = new GoodsPromFactory();
        if ($goodsPromFactory->checkPromType($goods['prom_type'])) {
            //这里会自动更新商品活动状态，所以商品需要重新查询
            if($item_id){
                $specGoodsPrice = SpecGoodsPrice::get($item_id,'',false);
                $goodsPromLogic = $goodsPromFactory->makeModule($goods,$specGoodsPrice);
            }else{
                $goodsPromLogic = $goodsPromFactory->makeModule($goods,null);
            }
            //检查活动是否有效
            if($goodsPromLogic->checkActivityIsAble()){
                $goods = $goodsPromLogic->getActivityGoodsInfo();
                $goods['activity_is_on'] = 1;
                $this->ajaxReturn(['status'=>1,'msg'=>'该商品参与活动','result'=>['goods'=>$goods]]);
            }else{
                if(!empty($goods['price_ladder'])){
                    $goodsLogic = new GoodsLogic();
                    $price_ladder = unserialize($goods['price_ladder']);
                    $goods->shop_price = $goodsLogic->getGoodsPriceByLadder($goods_num, $goods['shop_price'], $price_ladder);
                }
                $goods['activity_is_on'] = 0;
                $this->ajaxReturn(['status'=>1,'msg'=>'该商品没有参与活动','result'=>['goods'=>$goods]]);
            }
        }
        // 没有参与任何活动
        if (!isset($goods['activity_is_on'])) {
            $goods['activity_is_on'] = 0;
            if ($item_id) {
                $specGoodsPrice = SpecGoodsPrice::get($item_id,'',false);
                $goods['shop_price'] = $specGoodsPrice['price'];
                $goods['store_count'] = $specGoodsPrice['store_count'];
                //如果价格有变化就将市场价等于商品规格价。
                $goods['market_price'] = $specGoodsPrice['price'];
                $goods['store_count'] = $specGoodsPrice['store_count'];
            }
        }
        if(!empty($goods['price_ladder'])){
            $goodsLogic = new GoodsLogic();
            $price_ladder = unserialize($goods['price_ladder']);
            $goods->shop_price = $goodsLogic->getGoodsPriceByLadder($goods_num, $goods['shop_price'], $price_ladder);
        }
        $this->ajaxReturn(['status'=>1,'msg'=>'该商品没有参与活动','result'=>['goods'=>$goods]]);
    }
    public function activity_(){
        $goods_id = input('goods_id/d');//商品id
        $item_id = input('item_id/d',0);//规格id
        $goods_num = input('goods_num/d');//欲购买的商品数量
        $Goods = new \app\common\model\Goods();
        $goods = $Goods::get($goods_id,'',true);
        $goodsPromFactory = new GoodsPromFactory();
        if ($goodsPromFactory->checkPromType($goods['prom_type'])) {
            //这里会自动更新商品活动状态，所以商品需要重新查询
            if($item_id){
                $specGoodsPrice = SpecGoodsPrice::get($item_id,'',true);
                $goodsPromLogic = $goodsPromFactory->makeModule($goods,$specGoodsPrice);
            }else{
                $goodsPromLogic = $goodsPromFactory->makeModule($goods,null);
            }
            //检查活动是否有效
            if($goodsPromLogic->checkActivityIsAble()){
                $goods = $goodsPromLogic->getActivityGoodsInfo();
                $goods['activity_is_on'] = 1;
                $this->ajaxReturn(['status'=>1,'msg'=>'该商品参与活动','result'=>['goods'=>$goods]]);
            }else{
                if(!empty($goods['price_ladder'])){
                    $goodsLogic = new GoodsLogic();
                    $price_ladder = unserialize($goods['price_ladder']);
                    $goods->shop_price = $goodsLogic->getGoodsPriceByLadder($goods_num, $goods['shop_price'], $price_ladder);
                }
                $goods['activity_is_on'] = 0;
                $this->ajaxReturn(['status'=>1,'msg'=>'该商品没有参与活动','result'=>['goods'=>$goods]]);
            }
        }
        if(!empty($goods['price_ladder'])){
            $goodsLogic = new GoodsLogic();
            $price_ladder = unserialize($goods['price_ladder']);
            $goods->shop_price = $goodsLogic->getGoodsPriceByLadder($goods_num, $goods['shop_price'], $price_ladder);
        }
        $this->ajaxReturn(['status'=>1,'msg'=>'该商品没有参与活动','result'=>['goods'=>$goods]]);
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
    /*
     * 商品评论
     */
    public function comment(){
        $goods_id = I("goods_id/d",0);
        $this->assign('goods_id',$goods_id);
        return $this->fetch();
    }

    /*
     * ajax获取商品评论
     */
    public function ajaxComment()
    {
        $goods_id = I("goods_id/d", 0);
        $commentType = I('commentType', '1'); // 1 全部 2好评 3 中评 4差评
        if ($commentType == 5) {
            $where = array(
                'goods_id' => $goods_id, 'parent_id' => 0, 'img' => ['<>', ''],'is_show'=>1
            );
        } else {
            $typeArr = array('1' => '0,1,2,3,4,5', '2' => '4,5', '3' => '3', '4' => '0,1,2');
            $where = array('is_show'=>1,'goods_id' => $goods_id, 'parent_id' => 0, 'ceil((deliver_rank + goods_rank + service_rank) / 3)' => ['in', $typeArr[$commentType]]);
        }
        $count = M('Comment')->where($where)->count();
        $page_count = C('PAGESIZE');
        $page = new AjaxPage($count, $page_count);
        $list = M('Comment')
            ->alias('c')
            ->join('__USERS__ u', 'u.user_id = c.user_id', 'LEFT')
            ->where($where)
            ->order("add_time desc")
            ->limit($page->firstRow . ',' . $page->listRows)
            ->select();
        $replyList = M('Comment')->where(['goods_id' => $goods_id, 'parent_id' => ['>', 0]])->order("add_time desc")->select();
        foreach ($list as $k => $v) {
            if ($v['img']) {
                $list[$k]['img'] = array_slice(unserialize($v['img']),0,4);; // 晒单图片
            }
            $replyList[$v['comment_id']] = M('Comment')->where(['is_show' => 1, 'goods_id' => $goods_id, 'parent_id' => $v['comment_id']])->order("add_time desc")->select();
            $list[$k]['reply_num'] = Db::name('reply')->where(['comment_id'=>$v['comment_id'],'parent_id'=>0])->count();
        }

        $this->assign('goods_id', $goods_id);//商品id
        $this->assign('commentlist', $list);// 商品评论
        $this->assign('commentType', $commentType);// 1 全部 2好评 3 中评 4差评 5晒图
        $this->assign('replyList', $replyList); // 管理员回复
        $this->assign('count', $count);//总条数
        $this->assign('page_count', $page_count);//页数
        $this->assign('current_count', $page_count * I('p'));//当前条
        $this->assign('p', I('p'));//页数
        return $this->fetch();
    }

    /*
     * 获取商品规格
     */
    public function goodsAttr(){
        $goods_id = I("get.goods_id/d",0);
        $goods_attribute = M('GoodsAttribute')->getField('attr_id,attr_name'); // 查询属性
        $goods_attr_list = M('GoodsAttr')->where("goods_id", $goods_id)->select(); // 查询商品属性表
        $this->assign('goods_attr_list',$goods_attr_list);
        $this->assign('goods_attribute',$goods_attribute);
        return $this->fetch();
    }

    /**
     * 积分商城
     */
    public function integralMall()
    {
        $rank= I('get.rank');
        //以兑换量（购买量）排序
        if($rank == 'num'){
            $ranktype = 'sales_sum';
            $order = 'desc';
        }
        //以需要积分排序
        if($rank == 'integral'){
            $ranktype = 'exchange_integral';
            $order = 'desc';
        }
        $point_rate = tpCache('shopping.point_rate');
        $goods_where = array(
            'is_on_sale' => 1,  //是否上架
        );
        //积分兑换筛选
        $exchange_integral_where_array = array(array('gt',0));

        // 分类id
        if (!empty($cat_id)) {
            $goods_where['cat_id'] = array('in', getCatGrandson($cat_id));
        }
        //我能兑换
        $user_id = cookie('user_id');
        if ($rank == 'exchange' && !empty($user_id)) {
            //获取用户积分
//            $user_pay_points = intval(M('users')->where(array('user_id' => $user_id))->getField('pay_points'));
//            if ($user_pay_points !== false) {
//                array_push($exchange_integral_where_array, array('lt', $user_pay_points));
//            }
        }
        $goods_where['exchange_integral'] =  $exchange_integral_where_array;  //拼装条件
        $goods_list_count = M('goods')->where($goods_where)->count();   //总页数
        $page = new Page($goods_list_count, 15);
        $goods_list = M('goods')->where($goods_where)->order($ranktype ,$order)->limit($page->firstRow . ',' . $page->listRows)->select();
        $goods_category = M('goods_category')->where(array('level' => 1))->select();

        $this->assign('goods_list', $goods_list);
        $this->assign('page', $page->show());
        $this->assign('goods_list_count',$goods_list_count);
        $this->assign('goods_category', $goods_category);//商品1级分类
        $this->assign('point_rate', $point_rate);//兑换率
        $this->assign('totalPages',$page->totalPages);//总页数
        if(IS_AJAX){
            return $this->fetch('ajaxIntegralMall'); //获取更多
        }
        return $this->fetch();
    }

    /**
     * 商品搜索列表页
     */
    public function search(){
        $EsSearch = new EsModel();
        header("Cache-control: private"); // 浏览器BF操作，表单数据缓存错误
        $filter_param = array(); // 帅选数组
        $id = I('get.id/d',0); // 当前分类id
        $goods_id = input('goods_id/d',0);
        $brand_id = I('brand_id/d',0);
        $sort = I('sort'); // 排序
        $sort_asc = I('sort_asc','asc'); // 排序
        $price = I('price',''); // 价钱
        $start_price = trim(I('start_price','0')); // 输入框价钱
        $end_price = trim(I('end_price','0')); // 输入框价钱
        if($start_price && $end_price) $price = $start_price.'-'.$end_price; // 如果输入框有价钱 则使用输入框的价钱
        $filter_param['id'] = $id; //加入帅选条件中
        $brand_id  && ($filter_param['brand_id'] = $brand_id); //加入帅选条件中
        $price  && ($filter_param['price'] = $price); //加入帅选条件中
        $q = (input("param.q/s",'','trim')); // 关键字搜索
        $q  && ($_GET['q'] = $filter_param['q'] = $q); //加入帅选条件中

        $q = (input("param.q/s",'','trim')); // 关键字搜索


        setHistory($q); //记录搜索历史
        $qtype = I('qtype','');
        $where  = array('is_on_sale' => 1);
        if ($goods_id) {
            $where['goods_id']=$goods_id;
        }
        $where['exchange_integral'] = 0;//不检索积分商品
        if($qtype){
            $filter_param['qtype'] = $qtype;
            $where[$qtype] = 1;
        }

        //排除新用户专区商品
        $cat_id_arr = getCatGrandson (721);
        $where['cat_id'] = ['not in',$cat_id_arr];

        $goodsLogic = new GoodsLogic();

        $allGoods = (new \app\common\model\Goods())->where($where)->getField('goods_id', true);//符合要求的所有商品id
        if (!empty($q)){
            $search_id = $EsSearch->finding($q);//调用分词查询方法查询商品相关结果id集
            if($search_id)$filter_goods_id = array_intersect($search_id,$allGoods);//查询与正常商品的交集
        }else{
            $filter_goods_id = $allGoods;
        }

        // 过滤帅选的结果集里面找商品
        if($brand_id || $price)// 品牌或者价格
        {
            $goods_id_1 = $goodsLogic->getGoodsIdByBrandPrice($brand_id,$price); // 根据 品牌 或者 价格范围 查找所有商品id
            $filter_goods_id = array_intersect($filter_goods_id,$goods_id_1); // 获取多个帅选条件的结果 的交集
        }

        //筛选网站自营,入驻商家,货到付款,仅看有货,促销商品
        $sel = I('sel');
        if($sel)
        {
            $goods_id_4 = $goodsLogic->getFilterSelected($sel);

            $filter_goods_id = array_intersect($filter_goods_id,$goods_id_4);
        }

        $filter_menu  = $goodsLogic->get_filter_menu($filter_param,'search'); // 获取显示的帅选菜单
        $filter_price = $goodsLogic->get_filter_price($filter_goods_id,$filter_param,'search'); // 帅选的价格期间
        $filter_brand = $goodsLogic->get_filter_brand($filter_goods_id,$filter_param,'search'); // 获取指定分类下的帅选品牌

        $count = count($filter_goods_id);
        $page = new Page($count,12);

        if (!empty($filter_goods_id)){
            $filter_goods_id = implode(',',$filter_goods_id);//数组转换成字符串
        }

        if($count > 0)
        {
            if (!empty($filter_goods_id)){
                if (empty($sort)){//无排序,
                    $goods_list = Db::query("SELECT * FROM tp_goods WHERE  goods_id IN ($filter_goods_id) order by field(goods_id,$filter_goods_id)");
                }else{
                    $goods_list = Db::query("SELECT * FROM tp_goods WHERE  goods_id IN ($filter_goods_id) order by $sort $sort_asc");
                }
            }else{
                $goods_list = [];
            }
//
            $goods_list = array_slice($goods_list,$page->firstRow,12);//数组结果分页
            $filter_goods_id2 = get_arr_column($goods_list, 'goods_id');

            if($filter_goods_id2)
                $goods_images = M('goods_images')->where("goods_id", "in",$filter_goods_id2)->cache(true)->select();
        }

        $goods_category = M('goods_category')->where('is_show=1')->cache(true)->getField('id,name,parent_id,level'); // 键值分类数组
        $this->assign('goods_list',$goods_list);
        $this->assign('goods_category',$goods_category);
        $this->assign('goods_images',$goods_images);  // 相册图片
        $this->assign('filter_menu',$filter_menu);  // 帅选菜单
        $this->assign('filter_brand',$filter_brand);// 列表页帅选属性 - 商品品牌
        $this->assign('filter_price',$filter_price);// 帅选的价格期间
        $this->assign('filter_param',$filter_param); // 帅选条件
        $this->assign('page',$page);// 赋值分页输出
        $this->assign('sort_asc', $sort_asc == 'asc' ? 'desc' : 'asc');
        C('TOKEN_ON',false);
        if(input('is_ajax'))
            return $this->fetch('ajaxGoodsList');
        else
            return $this->fetch();
    }

    /**
     * 商品搜索列表页
     */
    public function ajaxSearch()
    {
        $searchVal = cookie('searchVal');
        $this->assign('search_history', empty($searchVal) ? [] : json_decode($searchVal, true));

        return $this->fetch();
    }

    /**
     * 记录搜索历史 接口
     */
    public function recordSearchVal(){
        $q = input('q','','trim');
        setHistory($q);
    }

    /**
     * 品牌街
     */
    public function brandstreet()
    {
        $getnum = 9;   //取出数量
        $goods=M('goods')->where(array('is_recommend'=>1,'is_on_sale'=>1))->page(1,$getnum)->cache(true,TPSHOP_CACHE_TIME)->select(); //推荐商品
        for($i=0;$i<($getnum/3);$i++){
            //3条记录为一组
            $recommend_goods[] = array_slice($goods,$i*3,3);
        }
        $where = array(
            'is_hot' => 1,  //1为推荐品牌
        );
        $count = M('brand')->where($where)->count(); // 查询满足要求的总记录数
        $Page = new Page($count,20);
        $brand_list = M('brand')->where($where)->limit($Page->firstRow.','.$Page->listRows)->order('sort desc')->select();
        $this->assign('recommend_goods',$recommend_goods);  //品牌列表
        $this->assign('brand_list',$brand_list);            //推荐商品
        $this->assign('listRows',$Page->listRows);
        if(I('is_ajax')){
            return $this->fetch('ajaxBrandstreet');
        }
        return $this->fetch();
    }

    /**
     * 用户收藏某一件商品
     * @param type $goods_id
     */
    public function collect_goods($goods_id){
        $goods_id = I('goods_id/d');
        $goodsLogic = new GoodsLogic();
        $result = $goodsLogic->collect_goods(cookie('user_id'),$goods_id);
        exit(json_encode($result));
    }

    public function search_goods_by_keywords(){
        $EsSearch = new EsModel(); //实例化分词搜索模型
        $keywords = input('get.term', '', 'trim');

        if (empty($keywords)) {
            exit( json_encode([]) );
        } else {
            $idArr = $EsSearch->finding($keywords); //调用分词搜索方法,返回商品id数组
            if (empty($idArr)) die;

            $goodArrStr = implode(',',$idArr);//数组转换成字符串

            $goods_name_list =
                Db::query("SELECT goods_name as value,goods_id as lable FROM tp_goods WHERE  goods_id IN ($goodArrStr) AND `is_on_sale` = 1 order by FIELD(goods_id,$goodArrStr)");//按数组排序查询,避免默认id顺序查询
            $goods_name_list = array_slice($goods_name_list,0,15);//取15条数据显示

            exit( json_encode($goods_name_list) );
        }
    }

    public function goodsTopic(){
        $topic_id = input("get.topic_id/d");
        $goodsTopic = new GoodsTopic();
        $info = $goodsTopic->topicInfo($topic_id);
        if (empty($topic_id) || empty($info)) {
            if (IS_AJAX) {
                return '';
            } else {
                $this->redirect('/');
            }
        }
        $count = count(explode(',',$info['goods_id']));
        $page = new Page($count,C('PAGESIZE'));
        $goodsList = Db::name('goods')
            ->where('goods_id', 'in', $info['goods_id'])
            ->where('is_on_sale', 1)
            ->limit($page->firstRow.','.$page->listRows)
            ->order("INSTR(',".join(',',array_reverse(explode(',',$info['goods_id']))).",',CONCAT(',',goods_id,','))")
            ->cache(true,180)
            ->select();
        $goodsPromFactory = new GoodsPromFactory();
        $ActivityLogic = new ActivityLogic();
        array_walk($goodsList,function (&$goods)use($goodsPromFactory,$ActivityLogic){
            $activity = $ActivityLogic->goodsRelatedActivity($goods['goods_id']);
            if ($activity) {
                $goods['prom_type'] = $activity['prom_type'];
                $goods['prom_id'] = $activity['prom_id'];
            }
            $goods = collection($goods);
            if ($goodsPromFactory->checkPromType($goods['prom_type'])) {
                //这里会自动更新商品活动状态，所以商品需要重新查询
                $goodsPromLogic = $goodsPromFactory->makeModule($goods,null);
                //检查活动是否有效
                if($goodsPromLogic->checkActivityIsAble()){
                    $goods = $goodsPromLogic->getActivityGoodsInfo();
                    $goods['activity_is_on'] = 1;
                }else{
                    $goods['activity_is_on'] = 0;
                }
            }
        });
        $this->assign('info',$info);
        $this->assign('goods_list',$goodsList);
        $this->assign('page',$page);// 赋值分页输出
        $this->assign('topic_id',$topic_id);

        if (IS_AJAX) {
            return $this->fetch('ajaxGoodsTopic');
        }
        return $this->fetch();
    }
    //美丽课堂
    public function beautifulClass(){
        return $this->fetch();
    }
    //休闲体验
    public function leisureExperience()
    {
        return $this->fetch();
    }


    //获取索引信息
    public function getIndexInfo(EsModel $EsSearch)
    {
        $EsSearch->getIndexInfo();
    }

    public function goodsComment()
    {
        $goodsId = I('goods_id',0);

        $goodsLogic = new GoodsLogic();
        $commentStatistics = $goodsLogic->commentStatistics($goodsId);// 获取某个商品的评论统计
        $this->assign('commentStatistics',$commentStatistics);//评论概览

        $this->assign('goods_id',$goodsId);
        return $this->fetch();
    }

    /*
     * @Author : 赵磊
     * 全球购 2.3.0
     * */
    public function globalPurchase()
    {
        $data['id'] = I('id');//馆id
        /*排序*/
        $data['sort'] = I('sort','goods_id'); // 排序名
        $data['sort_asc'] = I('sort_asc','asc'); // 排序顺序
        /*排序*/

        //馆内商品

        $info = (new GoodsGlobal())->hallInfo($data);//馆内所有数据
        $goods = $info['goods'];//该馆内所有商品
        $count = count($goods);//总条数

        $page = new Page($count, 20);
        $goods = array_slice($goods,$page->firstRow,20);

        $this->assign('page',$page);
        $this->assign('goods',$goods);//馆内商品列表

        $this->assign('banner_img',$info['banner_img']);//专题图片
        $this->assign('hall_name',$info['hall_name']);//专题馆名
        $this->assign('hall_name_en',$info['hall_name_en']);//专题馆英文名
        $this->assign('hotBrand',$info['hotBrand']);//馆内热门品牌
        $this->assign('hallId',$data['id']);//馆id

        if (IS_AJAX){
            return $this->fetch('ajaxGlobalPurchase');
        }
        return $this->fetch();
    }

    /*
     * @Author : 赵磊
     * 2.3.0 首页品牌列表
     * */
    public function indexGoodsBrand()
    {
        $filter_param = array(); // 帅选数组
        $id = I('id/d',1); // 当前分类id
        $brand_id = I('brand_id/d',0);
        $spec = I('spec',0); // 规格
        $attr = I('attr',''); // 属性
        $sort = I('sort','goods_id'); // 排序
        $sort_asc = I('sort_asc','asc'); // 排序
        $price = I('price',''); // 价钱
        $start_price = trim(I('start_price','0')); // 输入框价钱
        $end_price = trim(I('end_price','0')); // 输入框价钱
        if($start_price && $end_price) $price = $start_price.'-'.$end_price; // 如果输入框有价钱 则使用输入框的价钱
        $filter_param['id'] = $id; //加入帅选条件中
        $brand_id  && ($filter_param['brand_id'] = $brand_id); //加入帅选条件中
        $spec  && ($filter_param['spec'] = $spec); //加入帅选条件中
        $attr  && ($filter_param['attr'] = $attr); //加入帅选条件中
        $price  && ($filter_param['price'] = $price); //加入帅选条件中

        $goodsLogic = new GoodsLogic(); // 前台商品操作逻辑类
        // 分类菜单显示
        $goodsCate = M('GoodsCategory')->where("id", $id)->find();// 当前分类
        //($goodsCate['level'] == 1) && header('Location:'.U('Home/Channel/index',array('cat_id'=>$id))); //一级分类跳转至大分类馆
        $cateArr = $goodsLogic->get_goods_cate($goodsCate);

        // 帅选 品牌 规格 属性 价格
        $cat_id_arr = getCatGrandson ($id);
        $goods_where = ['is_on_sale' => 1, 'exchange_integral' => 0,'cat_id'=>['in',$cat_id_arr]];
        $filter_goods_id = Db::name('goods')->where($goods_where)->cache(true)->getField("goods_id",true);

        // 过滤帅选的结果集里面找商品
        if($brand_id || $price)// 品牌或者价格
        {
            $goods_id_1 = $goodsLogic->getGoodsIdByBrandPrice($brand_id,$price); // 根据 品牌 或者 价格范围 查找所有商品id
            $filter_goods_id = array_intersect($filter_goods_id,$goods_id_1); // 获取多个帅选条件的结果 的交集
        }
        if($spec)// 规格
        {
            $goods_id_2 = $goodsLogic->getGoodsIdBySpec($spec); // 根据 规格 查找当所有商品id
            $filter_goods_id = array_intersect($filter_goods_id,$goods_id_2); // 获取多个帅选条件的结果 的交集
        }
        if($attr)// 属性
        {
            $goods_id_3 = $goodsLogic->getGoodsIdByAttr($attr); // 根据 规格 查找当所有商品id
            $filter_goods_id = array_intersect($filter_goods_id,$goods_id_3); // 获取多个帅选条件的结果 的交集
        }

        //筛选网站自营,入驻商家,货到付款,仅看有货,促销商品
        $sel =I('sel');
        if($sel)
        {
            $goods_id_4 = $goodsLogic->getFilterSelected($sel,$cat_id_arr);
            $filter_goods_id = array_intersect($filter_goods_id,$goods_id_4);
        }

        $filter_menu  = $goodsLogic->get_filter_menu($filter_param,'goodsList'); // 获取显示的帅选菜单
        $filter_price = $goodsLogic->get_filter_price($filter_goods_id,$filter_param,'goodsList'); // 帅选的价格期间
        $filter_brand = $goodsLogic->get_filter_brand($filter_goods_id,$filter_param,'goodsList'); // 获取指定分类下的帅选品牌
        $filter_spec  = $goodsLogic->get_filter_spec($filter_goods_id,$filter_param,'goodsList',1); // 获取指定分类下的帅选规格
        $filter_attr  = $goodsLogic->get_filter_attr($filter_goods_id,$filter_param,'goodsList',1); // 获取指定分类下的帅选属性

        $count = count($filter_goods_id);
        $page = new Page($count,C('PAGESIZE'));
        if($count > 0)
        {
            $goods_list = M('goods')->where("goods_id","in", implode(',', $filter_goods_id))->order("$sort $sort_asc")->limit($page->firstRow.','.$page->listRows)->select();
            $filter_goods_id2 = get_arr_column($goods_list, 'goods_id');
            if($filter_goods_id2)
                $goods_images = M('goods_images')->where("goods_id", "in", implode(',', $filter_goods_id2))->cache(true)->select();
        }
        $goods_category = M('goods_category')->where('is_show=1')->cache(true)->getField('id,name,parent_id,level'); // 键值分类数组
        $this->assign('goods_list',$goods_list);
        $this->assign('goods_category',$goods_category);
        $this->assign('goods_images',$goods_images);  // 相册图片
        $this->assign('filter_menu',$filter_menu);  // 帅选菜单
        $this->assign('filter_spec',$filter_spec);  // 帅选规格
        $this->assign('filter_attr',$filter_attr);  // 帅选属性
        $this->assign('filter_brand',$filter_brand);// 列表页帅选属性 - 商品品牌
        $this->assign('filter_price',$filter_price);// 帅选的价格期间
        $this->assign('goodsCate',$goodsCate);
        $this->assign('cateArr',$cateArr);
        $this->assign('filter_param',$filter_param); // 帅选条件
        $this->assign('cat_id',$id);
        $this->assign('page',$page);// 赋值分页输出
        $this->assign('sort_asc', $sort_asc == 'asc' ? 'desc' : 'asc');
        return $this->fetch();
    }


    //首页品牌页
    public function goodsBrand()
    {
        $brandId = I('brand_id/d',0);
        if ($brandId) {
            $brandInfo = Db::name('brand')
                ->where('id',$brandId)
                ->find();
            $countryName = Db::table('cf_country')->where('country_id',$brandInfo['country_id'])->getField('name');
            $brandInfo['country_name'] = $countryName;
            $sort = I('sort','goods_id'); // 排序
            $sort_asc = I('sort_asc',''); // 排序

            $where = [
                'is_on_sale' => 1 ,
                'brand_id' => $brandId,
            ];

            $count = (new \app\common\model\Goods())
                ->where($where)->count();

            $pageObj = new AjaxPage( $count , 20);

            $goodsList = (new \app\common\model\Goods())
                ->field('goods_id,goods_name,goods_remark,shop_price,market_price,original_img,virtual_sales_num + sales_sum as sales_num')
                ->where($where)
                ->order("$sort $sort_asc")
                ->limit($pageObj->firstRow,$pageObj->listRows)
                ->select();

            $this->assign('goods_list',$goodsList);
            $this->assign('sort_asc', $sort_asc);

            if(IS_AJAX) {
                return $this->fetch('ajaxGoodsBrand');
            }else{
                $this->assign('goods_count',$count);
                $this->assign('brand_info',$brandInfo);
                return $this->fetch();
            }
        }
    }
}