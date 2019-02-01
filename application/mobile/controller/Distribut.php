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
 * 2015-11-21
 */
namespace app\mobile\controller;
use app\common\library\CropAvatar;
use app\common\logic\GoodsLogic;
use app\common\logic\OssLogic;
use app\common\logic\UsersLogic;
use app\common\logic\DistributLogic;
use app\common\model\DistributorLevelModel;
use think\Page;
use think\Verify;
use think\Db;

class Distribut extends MobileBase {
        /*
        * 初始化操作
        */
    public function _initialize() {
        parent::_initialize();
        if(tpCache('distribut.switch')==0){
            $this->error('分销功能已关闭',U('Mobile/User/index'));
        }
        if(session('?user'))
        {
        	$user = session('user');
        	$this->user = $user;
        	$this->user_id = $user['user_id'];
        	$this->assign('user',$user); //存储用户信息
        }        
        $nologin = array(
        	'login','pop_login','do_login','logout','verify','set_pwd','finished',
        	'verifyHandle','reg','send_sms_reg_code','find_pwd','check_validate_code',
        	'forget_pwd','check_captcha','check_username','send_validate_code','qr_code','upload_img',
        );
        if(!$this->user_id && !in_array(ACTION_NAME,$nologin)){
        	header("location:".U('Mobile/User/login'));
        	exit;
        }
        
        $first_leader = I('first_leader/d');
        if($user['is_distribut'] == 1){ //是分销商才查找用户店铺信息
            $store_user_id = ($first_leader>0) ? $first_leader :  $this->user_id;
            $user_store = Db::name('user_store')->where("user_id", $store_user_id)->find();
            $this->userStore=$user_store;
            $this->assign('store',$user_store);
        }

        $order_count = Db::name('order')->where("user_id", $this->user_id)->count(); // 我的订单数
        $goods_collect_count = Db::name('goods_collect')->where("user_id", $this->user_id)->count(); // 我的商品收藏
        $comment_count = Db::name('comment')->where("user_id", $this->user_id)->count();//  我的评论数
        $coupon_count = Db::name('coupon_list')->where("uid", $this->user_id)->count(); // 我的优惠券数量
        $first_nickname = Db::name('users')->where("user_id", $this->user['first_leader'])->getField('nickname');
        $level_name = Db::name('user_level')->where("level_id", $this->user['level'])->getField('level_name'); // 等级名称
        $this->assign('level_name',$level_name);        
        $this->assign('first_nickname',$first_nickname);        
        $this->assign('order_count',$order_count);
        $this->assign('goods_collect_count',$goods_collect_count);
        $this->assign('comment_count',$comment_count);
        $this->assign('coupon_count',$coupon_count);

    }
  
    /**
     * 分销用户中心首页（分销中心）
     */
    public function index(){
        // 销售额 和 我的奖励
        $result = DB::query("select sum(goods_price) as goods_price, sum(money) as money from __PREFIX__rebate_log where user_id = {$this->user_id}");
        $result = $result[0];
        $result['goods_price'] = $result['goods_price'] ? $result['goods_price'] : 0;
        $result['money'] = $result['money'] ? $result['money'] : 0;        
                
        $lower_count[1] = Db::name('users')->where("first_leader", $this->user_id)->count();
        $lower_count[2] = Db::name('users')->where("second_leader", $this->user_id)->count();
        $lower_count[3] = Db::name('users')->where("third_leader", $this->user_id)->count();


        $result2 = DB::query("select status,count(1) as c , sum(goods_price) as goods_price from `__PREFIX__rebate_log` where user_id = :user_id group by status",['user_id'=>$this->user_id]);
        $level_order = convert_arr_key($result2, 'status');
        for($i = 0; $i <= 5; $i++)
        {
            $level_order[$i]['c'] = $level_order[$i]['c'] ? $level_order[$i]['c'] : 0;
            $level_order[$i]['goods_price'] = $level_order[$i]['goods_price'] ? $level_order[$i]['goods_price'] : 0;
        }

        $money['withdrawals_money'] = Db::name('withdrawals')->where(['user_id'=>$this->user_id,'status'=>1])->sum('money'); // 已提现财富
        $money['achieve_money'] = Db::name('rebate_log')->where(['user_id'=>$this->user_id,'status'=>3])->sum('money');  //累计获得佣金
        $time=strtotime(date("Y-m-d"));
        $money['today_money'] = Db::name('rebate_log')->where("user_id=$this->user_id and status in(2,3) and create_time>$time")->sum('money');    //今日收入

        $this->assign('user_id',$this->user_id);
        $this->assign('level_order',$level_order); // 下线订单        
        $this->assign('lower_count',$lower_count); // 下线人数        
        $this->assign('sales_volume',$result['goods_price']); // 销售额
        $this->assign('reward',$result['money']);// 奖励
        $this->assign('money',$money);
        return $this->fetch();
    }
    
    /**
     * 下线列表(我的团队)
     */
    public function lower_list(){
        $user = $this->user;
        if($user['is_distribut'] != 1) $this->error('您还不是分销商');
        $level = I('get.level',1);
        $this->assign('level',$level);         
        $q = I('post.q','','trim');
        $condition = array(1=>'first_leader',2=>'second_leader',3=>'third_leader');

        $where = "{$condition[$level]} = {$this->user_id}";
        $bind = array();
        if($q){
            $where .= " and (nickname like :q1 or user_id = :q2 or mobile = :q3)";
            $bind['q1'] = "%$q%";
            $bind['q2'] = $q;
            $bind['q3'] = $q;
        }
        $count = Db::name('users')->where($where)->bind($bind)->count();
        $page = new Page($count,C('PAGESIZE'));
        $lists = Db::name('users')
            ->field('nickname,user_id,distribut_money,reg_time,head_pic')
            ->where($where)->bind($bind)
            ->limit("{$page->firstRow},{$page->listRows}")
            ->order('user_id desc')
            ->select();
        $this->assign('count', $count);// 总人数
        $this->assign('page', $page->show());// 赋值分页输出
        $this->assign('lists',$lists); // 下线
        $this->assign('regrade', tpCache('distribut.regrade'));
        if(I('is_ajax'))
        {
            return $this->fetch('ajax_lower_list');
        }                
        return $this->fetch();
    }    
    
    /**
     * 下线订单列表（分销订单）
     */
    public function order_list(){
        $user =$this->user;
        if($user['is_distribut'] != 1)
            $this->error('您还不是分销商');
        $status = I('get.status',0);
        $where = array('user_id'=>$this->user_id,'status'=>['in',$status]);
        $count = M('rebate_log')->where($where)->count();
        $Page  = new Page($count,C('PAGESIZE'));
        $list = M('rebate_log')->where($where)->order("id desc")->limit($Page->firstRow.','.$Page->listRows)->select(); //分成订单记录
        $user_id_list = get_arr_column($list, 'buy_user_id');
        if(!empty($user_id_list))
            $userList = M('users')->where("user_id", "in", implode(',', $user_id_list))->getField('user_id,nickname,mobile,head_pic');  //购买者信息
        /*获取订单商品*/
        $model = new UsersLogic();
        foreach ($list as $k => $v) {
            $data = $model->get_order_goods($v['order_id']);
            $list[$k]['goods_list'] = $data['result'];
        }
        $this->assign('count', $count);// 总人数
        $this->assign('page', $Page->show());// 赋值分页输出
        $this->assign('userList',$userList); //
        $this->assign('list',$list); // 下线
        if(I('is_ajax')){
            return $this->fetch('ajax_order_list');
        }
        return $this->fetch();
    } 


    /**
     * 验证码验证
     * $id 验证码标示
     */
    private function verifyHandle($id)
    {
        $verify = new Verify();
        if (!$verify->check(I('post.verify_code'), $id ? $id : 'user_login')) {
            $this->error("验证码错误");
        }
    }

    /**
     * 验证码获取
     */
    public function verify()
    {
        //验证码类型
        $type = I('get.type') ? I('get.type') : 'user_login';
        $config = array(
            'fontSize' => 40,
            'length' => 4,
            'useCurve' => true,
            'useNoise' => false,
        );
        $Verify = new Verify($config);
        $Verify->entry($type);
		exit();
    }

    /**
     * 剪切、上传二维码背景图片
     */
    public function upload_img(){
        $user_id = input('user_id/d',0);
        if (!$this->user_id) {
            $this->ajaxReturn(['state'=>0,'message'=>'请先登录']);//只能修改自己二维码的背景
        }
        if ($this->user_id != $user_id) {
            $this->ajaxReturn(['state'=>0,'message'=>'不能修改次背景']);//只能修改自己二维码的背景
        }
        $crop = new CropAvatar($_POST['avatar_src'], $_POST['avatar_data'], $_FILES['avatar_file']);
        $crop_err = $crop->getMsg();
        if (empty($crop_err)) {
            $ossLogic = new OssLogic();
            $file_path = $crop -> getResult();
            $ext = substr( $file_path,strripos($file_path,'.') );
            $new_name = get13TimeStamp().getRandChar(2).$ext;
            $info = $ossLogic->uploadFile(
                $file_path , config('aliyun_oss.img_object').'qrcode_bg/'.date('Ymd').'/'.$new_name
            );
            @unlink($file_path);
            @unlink($crop->original);
            if ($info) {
                Db::name('users')->where('user_id',$this->user_id)->update(['qrcode_bg_img'=>config('aliyun_oss.Oss_cdn').$info]);
                $response = array(
                    'state'  => 200,
                    'message' => '上传成功',
                    'result' => config('aliyun_oss.Oss_cdn').$info
                );
                $this->ajaxReturn($response);
            }
        }
        $this->ajaxReturn(['state'=>0,'message'=>'上传失败']);
    }
    /**
     * @Author: 陈静
     * @Date: 2018/04/02 16:50:42
     * @Description: 修改生成二维码的生成方式，代理商的场景值为 'a'.$user_id 字符串，与老商城保持一致
     * @return mixed
     */
    public function qr_code()
    {
        $qr_mode = input('qr_mode', 1); //0：商家二维码，1：微信二维码
        $user_id = input('user_id', 0);
        $qr_type = input('qr_type', 0); // 0:普通用户二维码，非永久, 1:代理商二维码，永久二维码

        if (!$user_id) {
            $this->redirect('user/login');
        }
        $is_owner = false;//是否是本网页的用户
        if ($user_id == $this->user_id) {
            $user = $this->user;
            $is_owner = true;
        } else {
            $user = M('users')->where('user_id', $user_id)->find();
            if (!$user && $user['is_distribut'] != 1) {
                return $this->fetch();
            }
        }
        
//        if ($qr_mode == 1 && $user['is_distribut'] != 1) {
//            $this->error('楼主已不是分销商');
//        }

        $wx_user = M('wx_user')->find(); //微信配置

        if ($qr_mode && $wx_user) {
            $wechatObj = new \app\common\logic\wechat\WechatUtil($wx_user);
            //指定生成二维码的有效期
            //查询当前用户是否存在二维码，是否过期
            $res = Db::table('cf_users u')
                ->field('u.user_id , u.user_type, u.user_qrcode_id, u.agent_qrcode_id, q1.time as time1, q1.qrcode_url as qrcode_url1, q2.time as time2, q2.qrcode_url as qrcode_url2')
                ->join(['cf_qrcode' => 'q1'],'u.user_qrcode_id = q1.id','left')
                ->join(['cf_qrcode' => 'q2'],'u.agent_qrcode_id = q2.id','left')
                ->where('user_id','=',$user_id)
                ->find();
            if ($res) {
                if ($qr_type == 0) {
                    if (!empty($res['qrcode_url1']) && time() - $res['time1'] < 29 * 24 * 3600 ) {
                        $wxdata['url'] = $res['qrcode_url1'];
                    } else {
                        // mei有二维码
                        $expire = 2592000;
                        $wxdata = $wechatObj->createTempQrcode($expire, $user['user_id']);
                        if ($wxdata && $wxdata['url']){
                            $insert_id = Db::table('cf_qrcode')->insertGetId(['is_forever'=>0, 'time'=>time(),'qrcode_url'=>$wxdata['url']]);
                            Db::table('cf_users')->where(['user_id'=>$user_id])->update(['user_qrcode_id'=>$insert_id]);
                        }
                    }
                } else {
                    // 代理商永久二维码
                    if (!empty($res['qrcode_url2']) && time() - $res['time2'] < 29 * 24 * 3600) {
                        $wxdata['url'] = $res['qrcode_url2'];
                    } else {
                        $expire = 0;
                        $wxdata = $wechatObj->createTempQrcode($expire, 'a'.$user['user_id']);
                        if ($wxdata && $wxdata['url']){
                            $insert_id = Db::table('cf_qrcode')->insertGetId(['is_forever'=>1, 'time'=>time(),'qrcode_url'=>$wxdata['url']]);
                            Db::table('cf_users')->where(['user_id'=>$user_id])->update(['agent_qrcode_id'=>$insert_id]);
                        }
                    }
                }
            } else {
                // 用户为新用户，没有二维码记录
                $expire = $qr_type == 0 ? 2592000 : 0;
                $scene_id = ($qr_type == 0 ? '' : 'a').$user['user_id'];
                $wxdata = $wechatObj->createTempQrcode($expire, $scene_id);
                if ($wxdata && $wxdata['url']){
                    $insert_data = ['is_forever'=>$qr_type == 0 ? 0 : 1, 'time'=>time(),'qrcode_url'=>$wxdata['url']];
                    $insert_id = Db::table('cf_qrcode')->insertGetId($insert_data);
                    $update_data = $qr_type == 0 ? ['user_qrcode_id'=>$insert_id] : ['agent_qrcode_id'=>$insert_id];
                    Db::table('cf_users')->where(['user_id'=>$user_id])->update($update_data);
                }
            }
            if (empty($wxdata['url'])) {
                $this->error('微信未成功接入或者数据库数据有误');
            }
        }
        $back_img = Db::name('users')->where('user_id',input('user_id'))->getField('qrcode_bg_img');
        $this->assign('bg_img',empty($back_img)?'':$back_img.'/s01');
        if ($qr_mode && $wx_user && !empty($wxdata['url'])) {
            $shareLink = urlencode($wxdata['url']);
        } else {
            $shareLink = urlencode("http://{$_SERVER['HTTP_HOST']}/index.php?m=Mobile&c=Index&a=index&distribute_parent_id={$user['user_id']}"); //默认分享链接
        }
        
        $head_pic = $user['head_pic'] ?: '';
        if ($head_pic && strpos($head_pic, 'http') !== 0) {
            $head_pic = '.'.$head_pic;
        }

        $config = tpCache('distribut');
//        $back_img = $config['qr_back'] ? '.'.$config['qr_back'] : './template/mobile/newbow/static/images/zz6.png';
        $back_img = '';
        $this->assign('user',  $user);
        $this->assign('user_id',  $user_id);
        $this->assign('is_owner', $is_owner);
        $this->assign('qr_mode',  $qr_mode);
        $this->assign('head_pic', $head_pic);
        $this->assign('back_img', $back_img);
        $this->assign('ShareLink', $shareLink);
        return $this->fetch();
    }

    /**
     * 手动生成二维码链接,浏览器直接访问上面地址（注意GET参数传入），就可以生成公众号二维码链接了，再用链接在草料等工具生成、美化等
     * 可以给公司内部的合伙人生成永久的二维码，方便线下推广
     * @param $user_id 用户id
     * @param $forever 是否永久二维码
     * @param $is_agent 是否为代理商二维码
     * http://tpformal.cfo2o.com/Mobile/Distribut/output_qrcode/user_id/1060/forever/0/is_agent/0.html
     */
    public function output_qrcode(){
        if ($this->user_id != '2210') { //这步骚操作，是为了避免人人都能访问
            die('你没有访问权限');
        }
        $user_id = input('user_id/d', 0);
        $forever = input('forever/d', 0);
        $is_agent = input('is_agent/d', 0);
        if (empty($user_id)) {
            halt('用户ID不能为空');
        }
        if ($forever== 0 && $is_agent ==1) {
            halt('请勿生成代理商的临时二维码');
        }
        $user = Db::name('users')->where('user_id',$user_id)->find();
        $codition = $is_agent == 1 ? 'cu.agent_qrcode_id=q.id':'cu.user_qrcode_id=q.id';
        $qrcode = Db::table('cf_users cu')->join(['cf_qrcode'=>'q'],$codition,'left')
        ->field('q.*')->where('cu.user_id',$user_id)->find();
        //如果有，看是否满足要求
        $flag = true;
        if ($qrcode['id']) {
            if ($qrcode['is_forever'] == 0 && $forever == 1){//原有临时二维码，现生成永久二维码
                $flag = false;
            } elseif ($qrcode['is_forever'] == 0 && $forever == 0) {//临时二维码过期
                if (time() - $qrcode['time'] > 29 * 24 * 3600) {
                    $flag = false;
                }
            }
        } else{
            $flag = false;
        }
        $qrcode_url = '';
        if ($flag) {
            $qrcode_url = $qrcode['qrcode_url'];
        } else {
            if ($qrcode['id']) {
                Db::table('cf_qrcode')->where('id',$qrcode['id'])->delete();
            }
            $wx_user = M('wx_user')->find(); //微信配置
            if (!empty($user) && !empty($wx_user)) {
                $wechatObj =  new \app\common\logic\wechat\WechatUtil($wx_user);
                // 用户为新用户，没有二维码记录
                $expire     = $forever == 1 ? 0 : 2592000;
                $scene_id   = ($is_agent == 1 ? 'a' : '').$user['user_id'];
                $wxdata = $wechatObj->createTempQrcode($expire, $scene_id);
                if ($wxdata && $wxdata['url']){
                    $insert_data = ['is_forever'=>$forever == 1 ? 1 : 0, 'time'=>$forever== 1? 0:time(),'qrcode_url'=>$wxdata['url']];
                    $insert_id = Db::table('cf_qrcode')->insertGetId($insert_data);
                    $update_data = $is_agent == 1 ? ['agent_qrcode_id'=>$insert_id] : ['user_qrcode_id'=>$insert_id];
                    Db::table('cf_users')->where(['user_id'=>$user_id])->update($update_data);
                    $qrcode_url = $wxdata['url'];
                }
            }
        }
        echo $qrcode_url == '' ? '生成二维码连接错误':$qrcode_url;
        die;
    }
    /**
     * 平台分销商品列表
     */
    public function goods_list()
    {
        if ($this->user['is_distribut'] != 1) {
            $this->error('您还不是分销商');
        }
        $goodsLogic = new GoodsLogic();
        $brandList = $goodsLogic->getSortBrands();
        $categoryList =  Db::name("GoodsCategory")->where(['level'=>1])->getField('id,name,parent_id,level');
        $this->assign('categoryList', $categoryList);    //品牌
        $this->assign('brandList', $brandList);  //分类
        return $this->fetch();
    }
    
    /**
     * 平台分销商品列表
     */
    public function ajax_goods_list()
    {
        $sort = I('sort', 'goods_id'); // 排序
        $order = I('sort_asc', 'asc'); // 排序
        $cat_id = I('cat_id/d', 0);
        $brand_id = I('brand_id/d', 0);//品牌
        $key_word = trim(I('key_word/s', ''));
        $logic = new DistributLogic;
        $result = $logic->goodsList($this->user_id, $sort, $order, $cat_id, $brand_id, $key_word);
        $this->assign('goodsList', $result['goodsList']);
        return $this->fetch();
    }

    /**
     * 添加分销商品
     * @author  lxl
     * @time2017-4-6
     */
    public function add_goods(){
        $user =$this->user;
        if($this->user_id == 0){  //判断登录是否有效
            $this->redirect('Mobile/User/index');
        }
        $goods_ids = I('post.goods_ids/a', []);
        $distributLogic = new DistributLogic;
        $result = $distributLogic->addGoods($this->user, $goods_ids);
        if($result){
            $this->success('成功',U('Mobile/Distribut/goods_list'));
        }else{
            $this->error('失败');
        }
    }

    /**
     * 店铺设置
     * @author  lxl
     * @time2017-4-6
     */
    public function set_store(){
        $user =$this->user;
        if($user['is_distribut'] != 1)
            $this->error('您还不是分销商');
        if(IS_POST){
            $data = input('post.');
            $UserStoreValidate = \think\Loader::validate('UserStore');
            if (!$UserStoreValidate->batch()->check($data)) {
                $return = ['status' => 0,'msg' => '操作失败','result' => $UserStoreValidate->getError()];
                $this->ajaxReturn($return);
            }
            // 上传图片
            if (!empty($_FILES['store_img']['tmp_name'])) {
                $files = request()->file('store_img');
                $save_url = UPLOAD_PATH.'user_tore';
                // 移动到框架应用根目录/public/uploads/ 目录下
                $image_upload_limit_size = config('image_upload_limit_size');
                $info = $files->rule('uniqid')->validate(['size' => $image_upload_limit_size, 'ext' => 'jpg,png,gif,jpeg'])->move($save_url);
                if ($info) {
                    // 成功上传后 获取上传信息
                    $return_imgs[] = '/'.$save_url . '/' . $info->getFilename();
                } else {
                    // 上传失败获取错误信息
                    $this->error($files->getError());
                }
            }
                if (!empty($return_imgs)) {
                    $data['store_img'] = implode(',', $return_imgs);
                }
                    $data['store_time']=time();
                if($this->userStore == null){ //添加
                    $data['user_id'] = $this->user_id;
                    $addres = Db::name('user_store')->add($data);
                    if($addres){
                        $return = ['status' =>1,'msg' => '添加店铺信息成功', 'result' =>''];
                    }else{
                        $return = ['status' => 0,'msg' => '添加店铺信息失败', 'result' =>''];
                    }
                }else{ //修改
                    $upres = Db::name('user_store')->where(array('user_id'=>$this->user_id))->update($data);
                    if($upres){
                        $return = ['status' =>1, 'msg' => '修改店铺信息成功','result' =>''];
                    }else{
                        $return =['status' => 0, 'msg' => '修改店铺信息失败','result' =>''];
                    }
                }
            $this->ajaxReturn($return);
            exit;
            }
        return $this->fetch();
    }

    /**
     * 用户分销商品
     * @author  lxl
     * @time2017-4-6
     */
    public function my_store(){
        $user =$this->user;
        if($user['is_distribut'] != 1){
            $this->error('您还不是分销商');
        }
        
        $first_leader = I('first_leader/d');
        if($first_leader > 0){ //如果是上级店铺的链接则显示上级的微店
            $firstLeader = M("Users")->where('user_id' , $first_leader)->field('nickname , mobile , head_pic')->find();
            $user_id = $first_leader;
            $first_leader_nickname = empty($firstLeader['nickname']) ? $firstLeader['mobile'] : $firstLeader['nickname'];
            $head_pic = $firstLeader['head_pic'];
            $store_name = $first_leader_nickname.'的微店';
        }else{
            $user_id = $this->user_id;
            $head_pic = $this->user['head_pic'];
            $store_name = "我的店铺";
        }

        $userDistributionModel = M('user_distribution');
        $goods_ids = $userDistributionModel->where(array('user_id'=>$user_id))->getField('goods_id',true);  //用户分销商品id
        
        
        $ids = !empty($goods_ids) ? implode(',',$goods_ids) : 0;  //以,号拼接ID
        $Page  = new Page(count($goods_ids),C('PAGESIZE'));
        $goodsModel = M('goods');
        $goodsWhere = " goods_id in ($ids) ";
        $lists = $goodsModel->where($goodsWhere)
            ->field('goods_id,goods_name,shop_price')
            ->limit($Page->firstRow.','.$Page->listRows)
            ->select();  //查找商品信息
        $countWhere = ' is_on_sale =1 and commission > 0 '; //公共统计条件
        $statistics['user_possess_goods'] = $goodsModel->where($countWhere)->count(); //平台全部分销商品
        $statistics['user_promotion_goods'] = $goodsModel->where("prom_type=1 and $countWhere")->count();  //平台全部促销分销商品
        $statistics['user_new_goods'] = $goodsModel->where("is_new=1 and $countWhere")->count();  //平台部新品分销全商品
        $this->assign('show',$Page->show());
        $this->assign('lists', $lists);
        $this->assign('statistics', $statistics);
        if(I('is_ajax')){
            return $this->fetch('ajax_my_store');
        }
        
        $this->assign('head_pic', $head_pic);
        $this->assign('store_name', $store_name);
        
        return $this->fetch();
    }


    /**
     * 新手必看
     * @author  lxl
     * @time2017-4-6
     */
    public function must_see(){
        $article = D('article')->field('article_id,title,content')->where(["cat_id"=>14,'is_open'=>1])->cache(true)->select();
        $this->assign('article',$article);
        return $this->fetch();
    }

    /**
     *分销排行
     * @author  lxl
     * @time2017-4-6
     */
    public function rankings(){
        $user = $this->user;
        $sort= I('sort','distribut_money');
//        $count = Db::name('users')->where("is_distribut = 1")->count(); //统计符合条件的总数
        $Page = new Page(200,C('PAGESIZE'));  //考虑用户不会看那么下去，不找那么多了
        $where = array('is_distribut' => 1);
        $lists = Db::name('users')->where(array('is_distribut' => 1))->order("$sort desc")->limit($Page->firstRow.','.$Page->listRows)->select(); //获排行列表
        $where["$sort"] = array('gt',$user["$sort"]);
        $place = Db::name('users')->where($where)->count($sort); //用户排行名
        $this->assign('lists',$lists);
        $this->assign('page',$Page->show());
        $this->assign('firsRrow',$Page->firstRow);  //当前分页开始数
        $this->assign('place',$place+1);  //当前分页开始数
        if(I('is_ajax')){
            return $this->fetch('ajax_rankings');
        }
        return $this->fetch();
    }

    /**
     * 分成记录
     * @author  lxl
     * @time2017-4-6
     */
    public function rebate_log(){
        $user =$this->user;
        if($user['is_distribut'] != 1)
            $this->error('您还不是分销商');
        $status = I('status',''); //日志状态
        $sort_asc = I('sort_asc','desc');  //排序
        $sort  = I('sort','create_time'); //排序条件
        $where['user_id'] = $this->user_id;
        if($status!=''){
            $where['status']= $status ;
        }
        $count = Db::name('rebate_log')->where($where)->count(); //统计符合条件的数量
        $Page = new Page($count,C('PAGESIZE'));
        $lists = Db::name('rebate_log')->where($where)->order("$sort  $sort_asc")->limit($Page->firstRow.','.$Page->listRows)->cache(true)->select(); //查询日志
        $this->assign('lists',$lists);
        if(I('is_ajax')){
            return $this->fetch('ajax_rebate_log');
        }
        return $this->fetch();
    }
}