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
 * $Author: 当燃   2016-05-10
 */ 
namespace app\mobile\controller;
use app\common\logic\GoodsLogic;
use app\common\logic\GoodsActivityLogic;
use app\common\model\FlashRemindModel;
use app\common\model\FlashSale;
use app\common\model\GroupBuy;
use think\Cache;
use think\Cookie;
use think\Db;
use think\Exception;
use think\Page;
use app\common\logic\ActivityLogic;

class Activity extends MobileBase {
    /**
     * @Author: 陈静
     * @Date: 2018/04/11 09:10:37
     * @Description: 修改token令牌
     */
    public function _initialize()
    {
        parent::_initialize();
        $nologin = [
            'grantNewUserCoupon','reg','index','wechatOauth','bindMobile','flash_sale_list','ajax_flash_sale','getFlashTimeSpace','swimActivity'
        ];
        $this->checkUserLogin($nologin);
    }

    public function index(){      
        return $this->fetch();
    }

    /**
     * 团购活动列表
     */
    public function group_list()
    {
        $type =I('get.type');
        //以最新新品排序
        if ($type == 'new') {
            $order = 'gb.start_time';
        } elseif ($type == 'comment') {
            $order = 'g.comment_count';
        } else {
            $order = '';
        }
        $group_by_where = array(
            'gb.start_time'=>array('lt',time()),
            'gb.end_time'=>array('gt',time()),
            'g.is_on_sale'=>1
        );
        $GroupBuy = new GroupBuy();
    	$count =  $GroupBuy->alias('gb')->join('__GOODS__ g', 'g.goods_id = gb.goods_id')->where($group_by_where)->count();// 查询满足要求的总记录数
        $pagesize = C('PAGESIZE');  //每页显示数
    	$page = new Page($count,$pagesize); // 实例化分页类 传入总记录数和每页显示的记录数
    	$show = $page->show();  // 分页显示输出
    	$this->assign('page',$show);    // 赋值分页输出
        $list = $GroupBuy
            ->alias('gb')
            ->join('__GOODS__ g', 'gb.goods_id=g.goods_id AND g.prom_type=2')
            ->where($group_by_where)
            ->page($page->firstRow, $page->listRows)
            ->order($order)
            ->select();
        $this->assign('list', $list);
        if(I('is_ajax')) {
            return $this->fetch('ajax_group_list');      //输出分页
        }
        return $this->fetch();
    }

    /**
     * 活动商品列表
     */
    public function discount_list(){
        $prom_id = I('id/d');    //活动ID
        $where = array(     //条件
            'is_on_sale'=>1,
            'prom_type'=>3,
            'prom_id'=>$prom_id,
        );
        $count =  M('goods')->where($where)->count(); // 查询满足要求的总记录数
         $pagesize = C('PAGESIZE');  //每页显示数
        $Page = new Page($count,$pagesize); //分页类
        $prom_list = Db::name('goods')->where($where)->limit($Page->firstRow.','.$Page->listRows)->select(); //活动对应的商品
        $spec_goods_price = Db::name('specGoodsPrice')->where(['prom_type'=>3,'prom_id'=>$prom_id])->select(); //规格
        foreach($prom_list as $gk =>$goods){  //将商品，规格组合
            foreach($spec_goods_price as $spk =>$sgp){
                if($goods['goods_id']==$sgp['goods_id']){
                    $prom_list[$gk]['spec_goods_price']=$sgp;
                }
            }
        }
        foreach($prom_list as $gk =>$goods){  //计算优惠价格
            $PromGoodsLogicuse = new \app\common\logic\PromGoodsLogic($goods,$goods['spec_goods_price']);
            if(!empty($goods['spec_goods_price'])){
                $prom_list[$gk]['prom_price']=$PromGoodsLogicuse->getPromotionPrice($goods['spec_goods_price']['price']);
            }else{
                $prom_list[$gk]['prom_price']=$PromGoodsLogicuse->getPromotionPrice($goods['shop_price']);
            }

        }
        $this->assign('prom_list', $prom_list);
        if(I('is_ajax')){
            return $this->fetch('ajax_discount_list');
        }
        return $this->fetch();
    }

    /**
     * 商品活动页面
     * @author lxl
     * @time2017-1
     */
    public function promote_goods(){
        $now_time = time();
        $where = " start_time <= $now_time and end_time >= $now_time ";
        $count = M('prom_goods')->where($where)->count();  // 查询满足要求的总记录数
        $pagesize = C('PAGESIZE');  //每页显示数
        $Page  = new Page($count,$pagesize); //分页类
        $promote = M('prom_goods')->field('id,title,start_time,end_time,prom_img')->where($where)->limit($Page->firstRow.','.$Page->listRows)->select();    //查询活动列表
        $this->assign('promote',$promote);
        if(I('is_ajax')){
            return $this->fetch('ajax_promote_goods');
        }
        return $this->fetch();
    }


    /**
     * 抢购活动列表页
     */
    public function flash_sale_list()
    {
        $time_space_arr = $this->getFlashTimeSpace();
        $this->assign('time_space', $time_space_arr);
        return $this->fetch();
    }


    //获取秒杀时间段
    public function getFlashTimeSpace()
    {
        $time_space = flash_sale_time_space();
        $current_row = $time_space[1];
        $start_time = $current_row['start_time'];
        $time_space_arr = Db::name('flash_sale')
            ->field("FROM_UNIXTIME(start_time,'%H:%i') as font, start_time,end_time")
            ->where(['start_time'=>['egt',$start_time]])
            ->group('start_time')
            ->order('start_time','ASC')
            ->limit(4)
            ->select();
        if (empty($time_space_arr)) {
            return array_slice($time_space,0,4);
        }
        return $time_space_arr;
    }

    /**
     * 抢购活动列表ajax
     */
    public function ajax_flash_sale()
    {
        $p = I('p',1);
        $start_time = I('start_time');
        $end_time = I('end_time');
        $where1 = array(
            'fl.start_time'=>array('egt',$start_time),
            'fl.end_time'=>array('elt',$end_time),
            'fl.is_end'=>0,
            'g.is_on_sale'=>1
        );
        $FlashSale = new FlashSale();
        $flash_sale_goods = $FlashSale->alias('fl')
            ->join('__GOODS__ g', 'g.goods_id = fl.goods_id')->with(['specGoodsPrice','goods'])
            ->field('*,100*(FORMAT(buy_num/goods_num,2)) as percent')
            ->where($where1)
            ->page($p,10)
            ->order('flash_order ASC,g.goods_id DESC')
            ->select();
        //用户添加秒杀提醒
        if ($this->user_id) {
            $where2 = [
                'status' => 0,
                'user_id' => $this->user_id,
                'flash_start_time' => $start_time,
            ];
            $flashRemindGoodsIdArr = (new FlashRemindModel())
                ->field('goods_id')
                ->where($where2)->select()
                ->column('goods_id');

            foreach ($flash_sale_goods as &$v){
                if (in_array($v->goods_id,$flashRemindGoodsIdArr)) {
                    $v->is_remind = 1;
                }else{
                    $v->is_remind = 0;
                }
//                dump($v->is_remind);
            }
        }

        $this->assign('flash_sale_goods',$flash_sale_goods);
        return $this->fetch();
    }

    public function coupon_list()
    {
        $atype = I('atype', 1);
        $user = session('user');
        $p = I('p', '');

        $activityLogic = new ActivityLogic();
        $result = $activityLogic->getCouponList($atype, $user['user_id'], $p);
        $this->assign('coupon_list', $result);
        if (request()->isAjax()) {
            return $this->fetch('ajax_coupon_list');
        }
        return $this->fetch();
    }

    /**
     * 领券
     */
    public function getCoupon()
    {
        $id = I('coupon_id/d');
        $user = session('user');
        $user['user_id'] = $user['user_id'] ?: 0;
        $activityLogic = new ActivityLogic();
        $return = $activityLogic->get_coupon($id, $user['user_id']);
        if (IS_AJAX) {
            $this->ajaxReturn($return);
        } else {
            if ($return['return_url']) {
                header("Location:".$return['return_url']. "\n");
                exit();
            }
            $this->redirect('cart/index');
        }
    }
    
    /**
     * 预售列表页
     */
    public function pre_sell_list()
    {
    	$goodsActivityLogic = new GoodsActivityLogic();
    	$pre_sell_list = Db::name('goods_activity')->where(array('act_type' => 1, 'is_finished' => 0))->select();
    	foreach ($pre_sell_list as $key => $val) {
    		$pre_sell_list[$key] = array_merge($pre_sell_list[$key], unserialize($pre_sell_list[$key]['ext_info']));
    		$pre_sell_list[$key]['act_status'] = $goodsActivityLogic->getPreStatusAttr($pre_sell_list[$key]);
    		$pre_count_info = $goodsActivityLogic->getPreCountInfo($pre_sell_list[$key]['act_id'], $pre_sell_list[$key]['goods_id']);
    		$pre_sell_list[$key] = array_merge($pre_sell_list[$key], $pre_count_info);
    		$pre_sell_list[$key]['price'] = $goodsActivityLogic->getPrePrice($pre_sell_list[$key]['total_goods'], $pre_sell_list[$key]['price_ladder']);
    	}
    	$this->assign('pre_sell_list', $pre_sell_list);
    	return $this->fetch();
    }
    
    /**
     *   预售详情页
     */
    public function pre_sell()
    {
    	$id = I('id/d', 0);
    	$pre_sell_info = M('goods_activity')->where(array('act_id' => $id, 'act_type' => 1))->find();
    	if (empty($pre_sell_info)) {
    		$this->error('对不起，该预售商品不存在或者已经下架了', U('Home/Activity/pre_sell_list'));
    		exit();
    	}
    	$goods = M('goods')->where(array('goods_id' => $pre_sell_info['goods_id']))->find();
    	if (empty($goods)) {
    		$this->error('对不起，该预售商品不存在或者已经下架了', U('Home/Activity/pre_sell_list'));
    		exit();
    	}
    
    	$pre_sell_info = array_merge($pre_sell_info, unserialize($pre_sell_info['ext_info']));
    	$goodsActivityLogic = new GoodsActivityLogic();
    	$pre_count_info = $goodsActivityLogic->getPreCountInfo($pre_sell_info['act_id'], $pre_sell_info['goods_id']);//预售商品的订购数量和订单数量
    	$pre_sell_info['price'] = $goodsActivityLogic->getPrePrice($pre_count_info['total_goods'], $pre_sell_info['price_ladder']);//预售商品价格
    	$pre_sell_info['amount'] = $goodsActivityLogic->getPreAmount($pre_count_info['total_goods'], $pre_sell_info['price_ladder']);//预售商品数额ing
    	if ($goods['brand_id']) {
    		$brand = M('brand')->where(array('id' => $goods['brand_id']))->find();
    		$goods['brand_name'] = $brand['name'];
    	}
    	$goods_images_list = M('GoodsImages')->where(array('goods_id' => $goods['goods_id']))->select(); // 商品 图册
    	$goods_attribute = M('GoodsAttribute')->getField('attr_id,attr_name'); // 查询属性
    	$goods_attr_list = M('GoodsAttr')->where(array('goods_id' => $goods['goods_id']))->select(); // 查询商品属性表
    	$goodsLogic = new GoodsLogic();
    	$filter_spec = $goodsLogic->get_spec($goods['goods_id']);
    	$spec_goods_price = M('spec_goods_price')->where(array('goods_id' => $goods['goods_id']))->getField("key,price,store_count"); // 规格 对应 价格 库存表
    	$commentStatistics = $goodsLogic->commentStatistics($goods['goods_id']);// 获取某个商品的评论统计
        $user_id = cookie('user_id');
        $collect = M('goods_collect')->where(array("goods_id"=>$goods['goods_id'] ,"user_id"=>$user_id))->count();
        $this->assign('collect',$collect);
    	$this->assign('pre_count_info', $pre_count_info);//预售商品的订购数量和订单数量
    	$this->assign('commentStatistics', $commentStatistics);//评论概览
    	$this->assign('goods_attribute', $goods_attribute);//属性值
    	$this->assign('goods_attr_list', $goods_attr_list);//属性列表
    	$this->assign('filter_spec', $filter_spec);//规格参数
    	$this->assign('goods_images_list', $goods_images_list);//商品缩略图
    	$this->assign('spec_goods_price', json_encode($spec_goods_price, true)); // 规格 对应 价格 库存表\
    	$this->assign('siblings_cate', $goodsLogic->get_siblings_cate($goods['cat_id']));//相关分类
    	$this->assign('look_see', $goodsLogic->get_look_see($goods));//看了又看
    	$this->assign('pre_sell_info', $pre_sell_info);
    	$this->assign('goods', $goods);
    	return $this->fetch();
    }

    /**
     * 7.14游泳券活动
     */
    public function swimActivity(){
        $http = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') ? 'https://' : 'http://';
        $activity = [
            'goods_id'      => 1000133,//康博恒温泳池游泳券(3次卡)，赠30ml洛神诗玫瑰水体验装一瓶  商品ID
            'goods_id_arr'  =>[1000096,1000133],//最下方的商品列表
            'start_time'    => strtotime('2018-7-28'),
            'end_time'      => strtotime('2018-8-20 23:59:59'),
            'share_img'     => $http.$_SERVER['SERVER_NAME'].'/template/mobile/newbow/static/images/swimActivity/swim_share_img.jpg',
            'share_link'     => $http.$_SERVER['SERVER_NAME'].U('mobile/activity/swimActivity',['distribute_parent_id'=>session('user')['user_id']])
        ];
        $goods_id = $activity['goods_id'];//康博恒温泳池游泳券(3次卡)，赠30ml洛神诗玫瑰水体验装一瓶  商品ID
        $store_count = Db::name('goods')->where('goods_id',$goods_id)->getField('store_count');

        if (time() > $activity['end_time'] || $store_count == 0) {
            $this->assign('is_over',1);
        }

        //最近购买过的用户
        $users = Db::name('order_goods og')
            ->field('u.user_id, u.nickname,u.mobile,u.head_pic')
            ->join('order o','og.order_id=o.order_id','left')
            ->join('users u','o.user_id= u.user_id','left')
            ->group('og.goods_id')
            ->where('og.goods_id',$goods_id)
            ->where("(pay_status=1 or pay_code='cod') and order_status in(1,2,4)")
            ->limit(20)
            ->order('o.add_time','DESC')
            ->select();
        if (count($users) < 20) {
            $users_append = [
                [
                    'user_id'       =>11,
                    'nickname'      =>'',
                    'mobile'        =>'13693443872',
                    'head_pic'      =>'http://cdn.cfo2o.com/images/head_pic/20180727/1525512828402.jpg'
                ],
                [
                    'user_id'       =>11,
                    'nickname'      =>'',
                    'mobile'        =>'13193444515',
                    'head_pic'      =>'http://cdn.cfo2o.com/images/head_pic/20180727/153146919466500.png'
                ],
                [
                    'user_id'       =>11,
                    'nickname'      =>'',
                    'mobile'        =>'13693447412',
                    'head_pic'      =>'http://cdn.cfo2o.com/images/head_pic/20180727/head_pic_default.jpg'
                ],
                [
                    'user_id'       =>11,
                    'nickname'      =>'',
                    'mobile'        =>'18793443851',
                    'head_pic'      =>'http://cdn.cfo2o.com/images/head_pic/20180727/18793443851.jpg'
                ],
            ];
            $users = array_merge($users,$users_append);
        }
        foreach ($users as $k=>$v){
            if (check_mobile($v['mobile'])) {
                $users[$k]['mobile'] = substr_replace($v['mobile'],'****',3,4);
            }
        }

        $goodsIdArr = $activity['goods_id_arr'];
        $goodsIdStr = join(',',$goodsIdArr);
        $goodsList = Db::name('goods')
            ->where('goods_id','in',$goodsIdArr)
            ->where('is_on_sale',1)
            ->order('field(goods_id,'.$goodsIdStr.')')
            ->select();
        $this->assign('users',$users);
        $this->assign('goods_id',$goods_id);
        $this->assign('activity',$activity);
        $this->assign('goodsList',$goodsList);
        return $this->fetch();
    }
}