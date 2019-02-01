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
 * $Author: 当燃 2016-01-09
 */
namespace app\mobile\controller;

use app\common\logic\GoodsLogic;
use app\common\library\Redis;
use app\common\logic\ActivityLogic;
use app\common\logic\CouponLogic;
use app\common\logic\GoodsPromFactory;
use app\common\logic\WechatLogic;
use app\common\model\GoodsTopic;
use app\common\model\Users;
use app\common\model\UserUserModel;
use app\mobile\model\ArticleModel;
use gmars\nestedsets\NestedSets;
use think\AjaxPage;
use think\Config;
use think\Cookie;
use think\Db;
use app\common\logic\wechat\WechatUtil;
use think\Exception;
use think\Page;
use think\Request;
use think\Session;

class Index extends MobileBase {

    public function __construct(Request $request = null)
    {
        parent::__construct($request);

        //微信浏览器
        if(is_weixin() && !IS_AJAX){
            $this->weixin_config = M('wx_user')->find(); //取微获信配置
            $this->assign('wechat_config', $this->weixin_config);

            if (empty(session("third_oauth" )['openid'])){
                if(is_array($this->weixin_config) && $this->weixin_config['wait_access'] == 1){
                    //授权获取openid以及微信用户信息
                    $wxuser = $this->GetOpenid('snsapi_base');
//                    $wxuser = $this->GetOpenid('snsapi_userinfo');
                    session("third_oauth" , $wxuser);
                }
            }
        }

        $this->clientType = $this->judgeClientType();

        $deviceType = $this->clientType['device_type'];
        if ($this->clientType['device_type'] == 0 || $this->clientType['device_type'] == 1 ) {
            $deviceType = 100;
        }
        $this->tokenName = 'token_'.$deviceType.'_'.$this->clientType['app_type'];

        $this->token = Cookie::get($this->tokenName);
        //获得客户端cookie中的token
        $userId = Redis::instance(config('redis'))->get($this->tokenName.':'.$this->token);

        //兼容老版本
        if (empty($userId)) {
            $token = Session::get('token');
            $userSomeInfo = Redis::instance(config('redis'))->hGet('token:'.$token,['userInfo','lastLoginTime']);
            $userId = json_decode($userSomeInfo['userInfo'],true)['user_id'];
        }

        if ($userId) {
            $this->userInfo = (new Users())->getUserInfo($userId)->toArray();
        }
    }

    //防止两次执行_initialize方法
    public function _initialize(){}
    public function index(){
        //1.查询模板，是否后台设置
        $diy_index = M('mobile_template')->where('is_index=1')->field('template_html,block_info')->find();
        if($diy_index){
            $html = htmlspecialchars_decode($diy_index['template_html']);
            $this->assign('html',$html);
            $this->assign('info',$diy_index['block_info']);
            return $this->fetch('index2');
            exit();
        }

        //首页热搜关键词
        $hotKeywords = tpCache('basic.hot_keywords');
        $hotKeywords = str_replace('|',' ',$hotKeywords);

        $this->assign('hot_keywords',$hotKeywords);
        //秒杀商品
        $time_space_arr = (new Activity())->getFlashTimeSpace();
        $start_time = current($time_space_arr)['start_time'];
        $end_time = $start_time+7200;   //结束时间

        $flash_sale_list = M('goods')->alias('g')
            ->field('g.goods_id,g.original_img,g.goods_name,f.price,s.item_id,g.market_price')
            ->join('flash_sale f','g.goods_id = f.goods_id','LEFT')
            ->join('__SPEC_GOODS_PRICE__ s','s.prom_id = f.id AND g.goods_id = s.goods_id','LEFT')
            ->where("f.start_time >= $start_time and f.end_time <= $end_time and is_end = 0")
            ->order('flash_order ASC,goods_id DESC')
            ->limit(8)->select();
        foreach ($flash_sale_list as &$v) {
            $v['save_money'] = round($v['market_price'] - $v['price'],2);
            $v['price'] = round($v['price'],2);
        }
        $this->assign('now_time',time());
        $this->assign('start_time',$start_time);
        $this->assign('end_time',$end_time);
        $this->assign('flash_sale_list',$flash_sale_list);

        //尚美头条
        $cf_article_toutiao = (new ArticleModel())
            ->field('article_id,thumb,title,description,cf_relate_goods')
            ->where('cat_id',17)
            ->where('is_open',1)
            ->order('publish_time DESC')
            ->limit(5)
            ->select()->toArray();

        foreach ($cf_article_toutiao as &$v) {
            if ($v['cf_relate_goods']) {
                //查找商品的最低价格
                $goodBestLowerPrice = (new \app\common\model\Goods())
                    ->where('goods_id','in',trim($v['cf_relate_goods'],','))
                    ->order('shop_price')->getField('shop_price');
                $v['best_low_price'] = round($goodBestLowerPrice,2);
            }else{
                $v['best_low_price'] = 0;
            }
        }

        $this->assign('cf_article_toutiao',$cf_article_toutiao);

        //品牌上新（topic_id = 18）
        $goodsTopic = new GoodsTopic();
        $info = $goodsTopic->topicInfo(18);
        $goodsList = Db::name('goods')
            ->field('goods_id,goods_name,goods_remark,original_img,shop_price,market_price')
            ->where('goods_id', 'in', array_reverse(explode(',',$info['goods_id'])))
            ->where('is_on_sale', 1)
            ->order('goods_id DESC')
            ->limit(9)
            ->select();
        foreach ($goodsList as &$v){
            $v['shop_price'] = round($v['shop_price'],2);
            $v['market_price'] = round($v['market_price'],2);
        }

        $this->assign('new_goods_data',$goodsList);


        //精选活动
        $choicenessActivityGoods = Db::table('cf_goods_topic')
            ->where('is_select',1)
            ->where('status',1)
            ->order('show_order DESC')
            ->select();

        foreach ($choicenessActivityGoods as &$v) {
            $v['goods'] = Db::name('goods')
                ->field('goods_id,goods_name,goods_remark,original_img,shop_price,market_price')
                ->where('goods_id', 'in', $v['goods_id'])
                ->where('is_on_sale', 1)
                ->limit(8)
                ->select();
            foreach ($v['goods'] as &$vv) {
                $vv['shop_price'] = round($vv['shop_price'],2);
                $vv['market_price'] = round($vv['market_price'],2);
            }
        }

        $this->assign('choicenessActivityGoods',$choicenessActivityGoods);

        //添加是否关注
        $isSubscribe = session('third_oauth')['subscribe'] ? 1 : 0;
        $this->assign('isSubscribe',$isSubscribe);
        $this->assign('isWechat',is_weixin() ? 1 : 0);


        //用户未登录或新用户（活动上线后注册的用户【2018.8.6】）已登录且未领取优惠券时展示
        $flag = false;
        $openWindFlag = true;
        $userId = $this->userInfo['user_id'];
        if ($userId) {
            $userInfo = (new Users())->getUserInfo($userId)->toArray();
            if ($userInfo['reg_time'] >= strtotime('2018-08-06')) {
                $isNewUser = 1;
                $flag = true;
            }
            //查询是否领取过优惠券
            $haveGetCount = Db::name('coupon_list')
                ->where('uid',$userInfo['user_id'])
                ->where('type',7)
                ->count();

            $haveGetCount1 = Db::name('coupon_list')
                ->where('uid',$userInfo['user_id'])
                ->where('type',4)
                ->count();

            //是否下过单
            $haveOrderCount = Db::name('order')
                ->where('user_id',$userId)
                ->where('pay_status',1)
                ->count();

            if ($haveGetCount > 0 && $haveOrderCount > 0) {
                $flag = false;
            }
            if ( $haveGetCount > 0 || $haveGetCount1 >0 ) {
                $openWindFlag = false;
            }
            $isLogin = true;
        }else{
            $isLogin = false;
            $flag = true;
        }
        $this->assign('flag',$flag);
        $this->assign('open_wind_flag',$openWindFlag);
        $this->assign('is_login',$isLogin);

        return $this->fetch('index');
    }

    //猜你喜欢
    public function ajaxGetGuessUserLike()
    {
        $data = [];
        if ($this->userInfo['user_id']) {
            //3个月中销售量排序
            $data = M('order_goods')->alias('a')
                ->field('a.goods_id,c.goods_name,c.goods_remark,c.original_img,c.shop_price,c.market_price')
                ->join('order b','a.order_id = b.order_id','LEFT')
                ->join('goods c','a.goods_id = c.goods_id','LEFT')
                ->where('b.user_id',$this->userInfo['user_id'])
                ->where('b.pay_status',1)
                ->where('c.is_on_sale',1)
                ->group('a.goods_id')
                ->order('b.add_time DESC')
                ->select();
        }
        $userBuyNum = count($data);

        $page = new AjaxPage(100 - $userBuyNum,20);

        $favouriteGoodsIdArr = [];
        if ($data) {
            $favouriteGoodsIdArr = array_column($data,'goods_id');
        }

        //3个月中销售量排序
        $favourite_goods = M('order_goods')->alias('a')
            ->field('a.goods_id,count(a.goods_num) as goods_num,c.goods_name,c.goods_remark,c.original_img,c.shop_price,c.market_price')
            ->join('order b','a.order_id = b.order_id','LEFT')
            ->join('goods c','a.goods_id = c.goods_id','LEFT')
            ->where('b.add_time','>',strtotime('-3 months'))
            ->where('c.goods_id','<>',1000000)
            ->where('c.goods_id','not in',$favouriteGoodsIdArr)
            ->where('c.is_on_sale',1)
            ->group('a.goods_id')
            ->order('goods_num DESC,b.add_time DESC')
            ->limit($page->firstRow,$page->listRows)
            ->select();

        $p = I('get.p');
        if ($data && $p == 1) {
            $favourite_goods = array_merge($data,$favourite_goods);
        }

        $ActivityLogic = new ActivityLogic();
        foreach ($favourite_goods as $k => &$v){
            $activity = $ActivityLogic->goodsRelatedActivity($v['goods_id']);
            if (!empty($activity)) {
                $v['prom_type'] = $activity['prom_type'];
                $v['prom_id']   = $activity['prom_id'];
                $goodsPromFactory = new GoodsPromFactory();
                $goodsPromLogic = $goodsPromFactory->makeModule($v,null);
                $v['shop_price'] = $goodsPromLogic->getActivityGoodsInfo()['shop_price'];
            }
            $v['shop_price'] = round($v['shop_price'],2);
            $v['market_price'] = round($v['market_price'],2);
        }

        $this->assign('favourite_goods',$favourite_goods);
        return $this->fetch();

    }

    //新人礼包
    public function newUserGift(){
        $type = 7;
        //查询优惠券
        $where = [
            'type' => $type,
            'status' => 1,
            'send_start_time' => ['<=',time()],
            'send_end_time' => ['>=',time()],
        ];
        $couponList = (new CouponLogic())
            ->where($where)
            ->select();
        foreach ($couponList as &$v) {
            $v->money = round($v->money,2);
            $v->condition = round($v->condition,2);
        }
        $this->assign('coupon_list',$couponList);

        //用户未登录或新用户（活动上线后注册的用户【2018.8.6】）已登录且未领取优惠券时展示
        $flag = false;
        $userId = $this->userInfo['user_id'];
        if ($userId) {
            $userInfo = (new Users())->getUserInfo($userId)->toArray();
            if ($userInfo['reg_time'] >= strtotime('2018-08-06')) {
                $isNewUser = 1;
                $flag = true;
            }
            //查询是否领取过优惠券
            $haveGetCount = Db::name('coupon_list')
                ->where('uid',$userInfo['user_id'])
                ->where('type',$type)
                ->count();

            if ($haveGetCount >= count($couponList)) {
                $flag = false;
            }
            $isLogin = true;
        }else{
            $isLogin = false;
            $flag = true;
        }

        $this->assign('is_login',$isLogin);
        $this->assign('flag',$flag);
        $this->assign('is_new_user',isset($isNewUser) && $isNewUser ? true : false);
        return $this->fetch();
    }

    //领取优惠券接口
    public function getNewUserGiftCounpon()
    {
        $type = 7;
        $userId = $this->userInfo['user_id'];
        if ($userId) {
            $userInfo = (new Users())->getUserInfo($userId)->toArray();
            //查询是否领取过优惠券
            $haveGetCount = Db::name('coupon_list')
                ->where('uid',$userInfo['user_id'])
                ->where('type',$type)
                ->count();

            if ($haveGetCount) {
                return outPut(-1,'已经领取过该礼包');
            }
            $res = (new ActivityLogic())->grantUserCoupon($userId,$type);

            if ($res) {
                return outPut(200,'领取成功');
            }else{
                return outPut(-200,'领取失败');
            }
        }else{
            return outPut(-2,'未登录');
        }
    }

    //新人首单福利
    public function newUserFirstWelfare(){
        $type = 7;
        $flag = false;

        if ($this->userInfo) {
            $userInfo = (new Users())->getUserInfo($this->userInfo['user_id'])->toArray();
            if ($userInfo['reg_time'] >= strtotime('2018-08-06')) {
                $flag = true;
                //查询是否领取过优惠券
                $haveGetCount = Db::name('coupon_list')
                    ->where('uid',$this->userInfo['user_id'])
                    ->where('type',$type)
                    ->count();
                if ($haveGetCount > 0) {
                    $flag = false;
                }
            }
        }else{
            $flag = true;
        }

        $this->assign('flag',$flag);

        //*******************
        $sort = I('sort','goods_id'); // 排序
        $sort_asc = I('sort_asc',''); // 排序


        //***********
/*
        //查询当前商品的所有分类
        $catIdArr = Db::name('goods')->where('goods_id','in',$goodsIdArr)->getField('cat_id',true);
        $catIdArr = array_unique($catIdArr);

        $catIdArrInfo = Db::name('goods_category')
            ->field('id,level,name,parent_id_path,parent_id')
            ->where('id','in',$catIdArr)->select();

        $goodsLogic = new GoodsLogic(); // 前台商品操作逻辑类
        $cateArr = [];
        foreach ($catIdArrInfo as $v) {
            $arr = array_filter(explode('_',$v['parent_id_path']));

            $cateArrTest =  Db::name('goods_category')
                ->where('id','in',$arr)->select();
            dump($v);

            $cateArrTest = convert_arr_key($cateArrTest,'level');

            $testtest = $cateArrTest[count($cateArrTest) - 1];
            $testtest['sub_menu'][] = $cateArrTest[count($cateArrTest)];

            $cateArr[] = $testtest;
//            $cateArr[] = $goodsLogic->get_goods_cate($v)[0];
//            halt($cateArr);
        }

        halt($cateArr);




        // 分类菜单显示
//        $goodsCate = M('GoodsCategory')->where("id", $id)->find();// 当前分类
//        $cateArr = $goodsLogic->get_goods_cate($goodsCate);
//halt($cateArr);
*/
        $goodsTopicInfo = Db::table('cf_goods_topic')->where('topic_id',46)->find();
        $goodsIdStr = $goodsTopicInfo['goods_id'];

        $pageObj = new AjaxPage( count(explode(',',$goodsIdStr)) , 20);

        $goods_list = M('goods')
            ->field('goods_id,goods_name,goods_remark,shop_price,market_price,original_img,virtual_sales_num + sales_sum as sales_sum')
            ->where("goods_id","in", $goodsIdStr)
            ->order("$sort $sort_asc")
            ->limit($pageObj->firstRow,$pageObj->listRows)
            ->select();
        foreach ($goods_list as &$v) {
            $v['shop_price'] = round($v['shop_price'],2);
            $v['market_price'] = round($v['market_price'],2);
        }

        $this->assign('goods_list',$goods_list);
        $this->assign('sort_asc', $sort_asc);
        C('TOKEN_ON',false);
        if(IS_AJAX)
            return $this->fetch('ajaxNewUserFirstWelfare');
        else
            return $this->fetch();
    }

    /**
     * @Author: 陈静
     * @Date: 2018/04/12 09:45:23
     * @Description: 判断是否是新用户
     */
    public function isNewUser()
    {
        $userRegTime = isset(session('user')['reg_time']) && session('user')['reg_time'] ? session('user')['reg_time'] : '';
        //获得后台设置的新老用户界限
        $newOldDiffDate = tpCache('basic.new_old_date_threshold');

        if ($userRegTime && ( (time() - $userRegTime) <= $newOldDiffDate * 24 * 3600) ) {
            // 分类菜单显示
            $cat_id_arr = getCatGrandson (721);

            $goods_where = ['cat_id'=>['in',$cat_id_arr]];

            $filter_goods_id = Db::name('goods')->where($goods_where)->cache(true,120)->getField("goods_id",true);

            $goods_list = M('goods')->where("goods_id","in", implode(',', $filter_goods_id))->limit(7)->select();

            return $goods_list;

        }else{
            return false;
        }
    }

    public function ajaxGetMore(){
        $p = I('p/d',1);
        //首页推荐商品
        $favourite_goods = Db::name('goods')
            ->where(function ($query){
                $where = [
                    'is_recommend' => 1,
                    'is_on_sale' => 1,
                    'is_virtual' => 0
                ];
                $query->where($where);
            })->whereOr(function ($query) {
                $where = [
                    'is_recommend' => 1,
                    'is_on_sale' => 1,
                    'is_virtual' => 1,
                    'virtual_indate' => ['>', time()]
                ];
                $query->where($where);
            })->order('goods_id DESC')
            ->page($p,C('PAGESIZE'))
            ->select();

        foreach ($favourite_goods as $k => &$v) {
            if ($k < 4) {
                $v['is_perfect'] = 1;
            }
        }
        //在猜你喜欢第一页添加广告位
        if ($p == 1 && $favourite_goods) {
            $result = M("ad")
                ->where("pid=51324  and enabled = 1 and start_time < ".time()." and end_time > ".time())
                ->order("orderby desc")->cache(true, TPSHOP_CACHE_TIME)->limit("2")->select();

            $adDatas = [];

            foreach ($result as $k => $value) {
                $adDatas[$k]['original_img'] = $value['ad_code'];
                $adDatas[$k]['url'] = $value['ad_link'];
            }

            $favourite_goods1 = array_slice($favourite_goods,0,2);

            $favourite_goods2 = array_slice($favourite_goods,2,2);
            $favourite_goods3 = array_slice($favourite_goods,5);
            $adDatas1 = array_slice($adDatas,0,1);

            $adDatas2 = array_slice($adDatas,1);
            $favourite_goods = array_merge($favourite_goods1,$adDatas1,$favourite_goods2,$adDatas2,$favourite_goods3);
        }
        $this->assign('favourite_goods',$favourite_goods);
        return $this->fetch();
    }

    public function index2(){
        $id=I('post.id');
        if($id){
            $arr=M('mobile_template')->where('id='.$id)->field('template_html,block_info')->find();
        }else{
            $arr=M('mobile_template')->order('id DESC')->limit(1)->field('template_html,block_info')->find();
        }

        $html=htmlspecialchars_decode($arr['template_html']);
        $this->assign('html',$html);
        $this->assign('info',$arr['block_info']);
        return $this->fetch();
    }

    //商品列表板块参数设置
    public function goods_list_block(){
        $data=I('post.');
        $count=I('post.num');

        if($data['ids']){
            $ids = substr($data['ids'],0,strlen($data['ids'])-1);   //ids是前台传递过来的商品2级分类
        }
        
        if($ids){
            $ids="(".$ids.")";
            //此处前台传递的是2级分类id 需要获取它的3级分类
            $cat_ids=Db::name('goods_category')->where("parent_id in".$ids." and is_show=1")->getField('id',true);  
        }
        if($cat_ids){
            $str="(".implode(",",$cat_ids).")";
        }
        
        $where='is_on_sale=1';
        if($cat_ids){
            $where.=" and cat_id in".$str;
        }
        if($data['label']){
            $where.=" and ".$data['label']."=1";
        }
        if($data['min_price']){
            $where.=" and shop_price>".$data['min_price'];
        }
        if($data['max_price']){
            $where.=" and shop_price<".$data['max_price'];
        }
        if($data['goods']){
            $goods_id = substr($data['goods'],0,strlen($data['goods'])-1);
            $goods_id = "(".$goods_id.")";
            $where.=" and goods_id in".$goods_id;
        }


        switch ($data['order']) {
            case '0':
                $order_str="sales_sum DESC";
                break;
            
            case '1':
                $order_str="sales_sum ASC";
                break;

            case '2':
                $order_str="shop_price DESC";
                break;

            case '3':
                $order_str="shop_price ASC";
                break;

            case '4':
                $order_str="last_update DESC";
                break;

            case '5':
                $order_str="last_update ASC";
                break;
        }

        $goodsList = M('Goods')->where($where)->order($order_str)->limit(0,$count)->select();

        $html='';
        foreach ($goodsList as $k => $v) {
            $html.='<li>';
            $html.='<a class="tpdm-goods-pic" href="javascript:;"><img src="'.$v[original_img].'" alt="" /></a>';
            $html.='<a href="/Mobile/Goods/goodsInfo/id/'.$vo[goods_id].'".htm" class="tpdm-goods-name">'.$v[goods_name].'</a>';
            $html.='<div class="tpdm-goods-des">';
            $html.='<div class="tpdm-goods-price">￥'.$v[shop_price].'</div>'; 
            $html.='<a class="tpdm-goods-like" href="javascript:;">看相似</a>'; 
            $html.='</div>';
            $html.='</li>';
        }
        $this->ajaxReturn(['status' => 1, 'msg' => '成功', 'result' =>$html]);
    }


    //自定义页面获取秒杀商品数据
    public function get_flash(){
        $now_time = time();  //当前时间
        if(is_int($now_time/7200)){      //双整点时间，如：10:00, 12:00
            $start_time = $now_time;
        }else{
            $start_time = floor($now_time/7200)*7200; //取得前一个双整点时间
        }
        $end_time = $start_time+7200;   //结束时间
        $flash_sale_list = M('goods')->alias('g')
            ->field('g.goods_id,g.original_img,g.shop_price,f.price,s.item_id')
            ->join('flash_sale f','g.goods_id = f.goods_id','LEFT')
            ->join('__SPEC_GOODS_PRICE__ s','s.prom_id = f.id AND g.goods_id = s.goods_id','LEFT')
            ->where("start_time = $start_time and end_time = $end_time")
            ->limit(4)->select();
        $str='';
        if($flash_sale_list){
            foreach ($flash_sale_list as $k => $v) {
                $str.='<a href="'.U('Mobile/Activity/flash_sale_list').'">';
                $str.='<img src="'.$v['original_img'].'" alt="" />';
                $str.='<span>￥'.$v['price'].'</span>';
                $str.='<i>￥'.$v['shop_price'].'</i></a>';
            }
        }
        $time=date('H',$start_time);
        $this->ajaxReturn(['status' => 1, 'msg' => '成功','html' => $str, 'start_time'=>$time, 'end_time'=>$end_time]);
    }

    /**
     * 分类列表显示
     */
    public function categoryList(){
        return $this->fetch();
    }

    /**
     * 模板列表
     */
    public function mobanlist(){
        $arr = glob("D:/wamp/www/svn_tpshop/mobile--html/*.html");
        foreach($arr as $key => $val)
        {
            $html = end(explode('/', $val));
            echo "<a href='http://www.php.com/svn_tpshop/mobile--html/{$html}' target='_blank'>{$html}</a> <br/>";            
        }        
    }
    
    /**
     * 商品列表页
     */
    public function goodsList(){
        $id = I('get.id/d',0); // 当前分类id
        $lists = getCatGrandson($id);
        $this->assign('lists',$lists);
        return $this->fetch();
    }
    
    //微信Jssdk 操作类 用分享朋友圈 JS
    public function ajaxGetWxConfig()
    {
        $askUrl = input('askUrl');//分享URL
        $askUrl = urldecode($askUrl);

        $wechat = new WechatUtil;
        $signPackage = $wechat->getSignPackage($askUrl);

        if (!$signPackage) {
            exit($wechat->getError());
        }

        $this->ajaxReturn($signPackage);
    }
    public function cf_complete_info(){
        return $this->fetch();
    }

//    public function test()
//    {
//        $wechat = new WechatUtil();
////        dump($wechat->setTemplateIndustry());
////        dump($wechat->setTemplateIndustryInfo());
////        dump($wechat->addTemplateMsg('TM00016'));
//        dump($wechat->addTemplateMsg('OPENTM410958953'));
//    }
    public function map()
    {
        return $this->fetch();
    }

    //测试发送模板消息
    public function testSendTemplate()
    {
        $res = (new WechatLogic())->sendTemplateMsgOnTeamSucess(24);

        halt($res);

    }


    public function changeUser()
    {
        $nestModel = new NestedSets((new UserUserModel()));

        $str = '4211';
        $arr = explode(',',$str);

        $parentId = 1004;
        foreach ($arr as $v) {
            $nestModel->delete($v);
            $nestModel->insert($parentId, [
                'user_id' => $v,
                'be_user_start' => '1536116676'
            ]);
        }
//
//        halt((new UserUserModel())->where('parent_id',$parentId)->select()->toArray());
    }


/*
    public function insertTestUser()
    {
        Db::query('DELETE FROM tp_users where user_id <= 22');
        Db::query('DELETE FROM cf_users where user_id <= 22');
        Db::query('DELETE FROM cf_user_user where user_id <= 22');

        $nestModel = new NestedSets('cf_user_user','left_key','right_key','parent_id','level','user_id');
        for ($i = 1 ;$i<= 22;$i++){
            if (strlen($i) == 1) {
                $mobile = '1711111110'.$i;
            }else{
                $mobile = '171111111'.$i;
            }

            Db::table('tp_users')->insert([
                'user_id' => $i,
                'mobile' => $mobile,
                'nickname' => $mobile,
                'user_money' => 10000
            ]);
            $first_partner_id = 0;
            $first_agent_id = 0;
            $user_type = 0;
            switch ($i){
                case 1:
                    $parent = 0;
                    $user_type = 4;
                    break;
                case 2:
                    $parent = 1;
                    $first_partner_id = 0;
                    $first_agent_id = 1;
                    break;
                case 3:
                    $parent = 2;
                    $first_partner_id = 0;
                    $first_agent_id = 1;
                    break;
                case 4:
                    $parent = 1;
                    $user_type = 4;
                    $first_partner_id = 0;
                    $first_agent_id = 1;
                    break;
                case 5:
                    $parent = 4;
                    $user_type = 4;
                    $first_partner_id = 0;
                    $first_agent_id = 4;
                    break;
                case 6:
                    $parent = 1;
                    $first_partner_id = 0;
                    $first_agent_id = 1;
                    break;
                case 7:
                    $parent = 6;
                    $user_type = 4;
                    $first_partner_id = 0;
                    $first_agent_id = 1;
                    break;
                case 8:
                    $parent = 6;
                    $user_type = 4;
                    $first_partner_id = 0;
                    $first_agent_id = 1;
                    break;
                case 9:
                    $parent = 7;
                    $first_partner_id = 7;
                    $first_agent_id = 7;
                    break;
                case 10:
                    $parent = 8;
                    $first_partner_id = 0;
                    $first_agent_id = 8;
                    break;
                case 11:
                    $parent = 9;
                    $user_type = 4;
                    $first_partner_id = 7;
                    $first_agent_id = 7;
                    break;
                case 12:
                    $parent = 9;
                    $first_partner_id = 7;
                    $first_agent_id = 7;
                    break;
                case 13:
                    $parent = 11;
                    $first_partner_id = 7;
                    $first_agent_id = 11;
                    break;
                case 14:
                    $parent = 11;
                    $first_partner_id = 7;
                    $first_agent_id = 11;
                    break;
                case 15:
                    $parent = 8;
                    $first_partner_id = 0;
                    $first_agent_id = 8;
                    break;
                case 16:
                    $parent = 10;
                    $first_partner_id = 0;
                    $first_agent_id = 8;
                    break;
                case 17:
                    $parent = 14;
                    $first_partner_id = 14;
                    $first_agent_id = 11;
                    break;
                case 18:
                    $parent = 14;
                    $first_partner_id = 14;
                    $first_agent_id = 11;
                    break;
                case 19:
                    $parent = 13;
                    $first_partner_id = 7;
                    $first_agent_id = 11;
                    break;
                case 20:
                    $parent = 17;
                    $first_partner_id = 17;
                    $first_agent_id = 11;
                    break;
                case 21:
                    $parent = 5;
                    $user_type = 4;
                    $first_partner_id = 0;
                    $first_agent_id = 4;
                    break;
                case 22:
                    $parent = 21;
                    $user_type = 4;
                    $first_partner_id = 21;
                    $first_agent_id = 4;
                    break;
            }

            Db::table('cf_users')->insert([
                'user_id' => $i,
                'user_type' => $user_type,
                'first_partner_id' => $first_partner_id,
                'first_agent_id' => $first_agent_id,
            ]);

            $nestModel->insert($parent, [
                'user_id' => $i,
            ]);
        }

        //合伙人
        Db::query('DELETE FROM cf_user_partner where user_id <= 22');
        $partnerNestedset = new NestedSets('cf_user_partner','left_key','right_key','parent_id','level','user_id');
        $partnerNestedset->insert(0, [
            'user_id' => 7,
            'status' => 1,
            'first_agent_id' => 1
        ]);
        $partnerNestedset->insert(0, [
            'user_id' => 14,
            'status' => 1,
            'first_agent_id' => 11
        ]);
        $partnerNestedset->insert(0, [
            'user_id' => 16,
            'status' => 1,
            'first_agent_id' => 8
        ]);
        $partnerNestedset->insert(0, [
            'user_id' => 17,
            'status' => 1,
            'first_agent_id' => 11
        ]);
        $partnerNestedset->insert(0, [
            'user_id' => 21,
            'status' => 1,
            'first_agent_id' => 5
        ]);
        $partnerNestedset->insert(0, [
            'user_id' => 22,
            'status' => 1,
            'first_agent_id' => 21
        ]);

        //代理商
        Db::query('DELETE FROM cf_user_agent where user_id <= 22');
        $agentNestedset = new NestedSets('cf_user_agent','left_key','right_key','parent_id','level','user_id');
        $agentNestedset->insert(0, [
            'user_id' => 1,
            'agent_level' => 1,
            'status' => 1
        ]);
        $agentNestedset->insert(1, [
            'user_id' => 4,
            'agent_level' => 2,
            'status' => 1
        ]);
        $agentNestedset->insert(1, [
            'user_id' => 7,
            'agent_level' => 2,
            'status' => 1
        ]);
        $agentNestedset->insert(1, [
            'user_id' => 8,
            'agent_level' => 2,
            'status' => 1
        ]);
        $agentNestedset->insert(4, [
            'user_id' => 5,
            'agent_level' => 3,
            'status' => 1
        ]);
        $agentNestedset->insert(4, [
            'user_id' => 21,
            'agent_level' => 3,
            'status' => 1
        ]);
        $agentNestedset->insert(4, [
            'user_id' => 22,
            'agent_level' => 3,
            'status' => 1
        ]);
        $agentNestedset->insert(7, [
            'user_id' => 11,
            'agent_level' => 3,
            'status' => 1
        ]);

    }
*/


    /*
     * @Author: 赵磊
     * 2.3.0 首页
     * */
    public function newIndex(){
//        //后台配置的首页
//        $diy_index = M('mobile_template')->where('is_index=1')->field('template_html,block_info')->find();
//        if($diy_index){
//            $html = htmlspecialchars_decode($diy_index['template_html']);
//            $this->assign('html',$html);
//            $this->assign('info',$diy_index['block_info']);
//            return $this->fetch('index2');
//            exit();
//        }
//
//        $hot_goods = M('goods')->where("is_hot=1 and is_on_sale=1")->order('goods_id DESC')->limit(20)->cache(true,TPSHOP_CACHE_TIME)->select();//首页热卖商品
//        $thems = M('goods_category')->where('level=1')->order('sort_order')->limit(9)->cache(true,TPSHOP_CACHE_TIME)->select();
//        $this->assign('thems',$thems);
//        $this->assign('hot_goods',$hot_goods);
//        $favourite_goods = M('goods')->where("is_recommend=1 and is_on_sale=1")->order('goods_id DESC')->limit(20)->cache(true,TPSHOP_CACHE_TIME)->select();//首页推荐商品
//
//        //秒杀商品
//        $time_space_arr = (new Activity())->getFlashTimeSpace();
//        $start_time = current($time_space_arr)['start_time'];
//        $end_time = $start_time+7200;   //结束时间
//
//        $flash_sale_list = M('goods')->alias('g')
//            ->field('g.goods_id,g.original_img,g.goods_name,f.price,s.item_id,g.market_price')
//            ->join('flash_sale f','g.goods_id = f.goods_id','LEFT')
//            ->join('__SPEC_GOODS_PRICE__ s','s.prom_id = f.id AND g.goods_id = s.goods_id','LEFT')
//            ->where("f.start_time >= $start_time and f.end_time <= $end_time and is_end = 0")
////            ->order('flash_order','ASC')
//            ->limit(3)->select();
//
//        //新老活动板块显示与隐藏
//        $result = $this->isNewUser();
//
//        if ($result != false) {
//            $this->assign('new_user_forum',$result);
//            $this->assign('is_new_user',1);
//        }else{
//            $this->assign('is_new_user',0);
//        }
//
//        //添加是否关注
//        $isSubscribe = session('third_oauth')['subscribe'] ? 1 : 0;
//        $this->assign('isSubscribe',$isSubscribe);
//
//        $this->assign('flash_sale_list',$flash_sale_list);
//
//        $this->assign('now_time',time());
//        $this->assign('start_time',$start_time);
//        $this->assign('end_time',$end_time);
//        $this->assign('favourite_goods',$favourite_goods);
        return $this->fetch();
    }

    public function test(){
//        $test = Db::table('cf_user_login')->where('token_status', 0)->select();
//
//        foreach ($test as $v) {
//            $deviceType = $v['device_type'];
//            if ($v['device_type'] == 0 || $v['device_type'] == 1 ) {
//                $deviceType = 100;
//            }
//
//            $tokenName = 'token_'.$deviceType.'_'.$v['app_type'].':'.$v['token'];
//            Redis::instance(config('redis'))->set($tokenName,$v['user_id'] , 0);
//        }

        halt($_COOKIE);



    }
}