<?php
/**
 * tpshop
 * ============================================================================
 * 版权所有 2015-2027 深圳搜豹网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.tp-shop.cn
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用最新Thinkphp5助手函数特性实现单字母函数M D U等简写方式
 * ============================================================================
 * Author: IT宇宙人
 * Date: 2015-09-09
 */

namespace app\common\logic;

use app\common\library\Logs;
use app\common\model\Coupon;
use app\common\model\UserAddress;
use app\common\model\UserModel;
use app\common\model\UserPartnerModel;
use app\common\model\UserScanModel;
use app\common\model\UserUserModel;
use app\mobile\controller\Activity;
use gmars\nestedsets\NestedSets;
use think\Cookie;
use think\Model;
use think\Page;
use think\Db;
use think\response\Redirect;

/**
 * 分类逻辑定义
 * Class CatsLogic
 * @package Home\Logic
 */
class UsersLogic extends Model
{
    protected $user_id=0;

    /**
     * 设置用户ID
     * @param $user_id
     */
    public function setUserId($user_id)
    {
        $this->user_id = $user_id;
    }
    /*
     * 登陆
     */
    public function login($username,$password)
    {
        if (!$username || !$password) {
            return array('status' => 0, 'msg' => '请填写账号或密码');
        }

        $user = Db::name('users')->where("mobile", $username)->whereOr('email', $username)->find();
        if (!$user) {
            $result = array('status' => -1, 'msg' => '账号不存在!');
        } elseif (encrypt($password) != $user['password']) {
            $result = array('status' => -2, 'msg' => '密码错误!');
        } elseif ($user['is_lock'] == 1) {
            $result = array('status' => -3, 'msg' => '账号异常已被锁定！！！');
        } else {
            //查询用户信息之后, 查询用户的登记昵称
            $levelId = $user['level'];
            $levelName = Db::name("user_level")->where("level_id", $levelId)->getField("level_name");
            $user['level_name'] = $levelName;

            $result = array('status' => 1, 'msg' => '登陆成功', 'result' => $user);
        }
        return $result;
    }

    /*
     * app端登陆
     */
    public function app_login($username, $password, $capache, $push_id=0)
    {
    	$result = array();
        if(!$username || !$password)
           $result= array('status'=>0,'msg'=>'请填写账号或密码');
        $user = M('users')->where("mobile|email","=",$username)->find();
        if(!$user){
           $result = array('status'=>-1,'msg'=>'账号不存在!');
        }elseif($password != $user['password']){
           $result = array('status'=>-2,'msg'=>'密码错误!');
        }elseif($user['is_lock'] == 1){
           $result = array('status'=>-3,'msg'=>'账号异常已被锁定！！！');
        }else{
            //查询用户信息之后, 查询用户的登记昵称
            $levelId = $user['level'];
            $levelName = M("user_level")->where("level_id", $levelId)->getField("level_name");
            $user['level_name'] = $levelName;            
            $user['token'] = md5(time().mt_rand(1,999999999));
            $data = ['token' => $user['token'], 'last_login' => time()];
            $push_id && $data['push_id'] = $push_id;
            M('users')->where("user_id", $user['user_id'])->save($data);
            $result = array('status'=>1,'msg'=>'登陆成功','result'=>$user);
        }
        return $result;
    }    

    /*
     * app端登出
     */
    public function app_logout($token = '')
    {
        if (empty($token)){
            ajaxReturn(['status'=>-100, 'msg'=>'已经退出账户']);
        }

        $user = M('users')->where("token", $token)->find();
        if (empty($user)) {
            ajaxReturn(['status'=>-101, 'msg'=>'用户不在登录状态']);
        }

        M('users')->where(["user_id" => $user['user_id']])->save(['last_login' => 0, 'token' => '']);
        session(null);

        return ['status'=>1, 'msg'=>'退出账户成功'];
    }

    //绑定账号
    public function oauth_bind($data = array())
    {
        if (!empty($data['openid'])) {
            return false;
        }

        $user = session('user');
        if (empty($data['oauth_child'])) {
            $data['oauth_child'] = '';
        }

        if (empty($data['unionid'])) {
            $column = 'openid';
            $open_or_unionid = $data['openid'];
        } else {
            $column = 'unionid';
            $open_or_unionid = $data['unionid'];
        }

        $where = [$column => $open_or_unionid];
        if ($column == 'openid') {
            $where['oauth'] = $data['oauth']; //unionid不需要加这个限制
        }

        $ouser = Db::name('Users')->alias('u')->field('u.user_id,o.tu_id')->join('OauthUsers o', 'u.user_id = o.user_id')->where($where)->find();
        if ($ouser) {
            //删除原来绑定
            Db::name('OauthUsers')->where('tu_id', $ouser['tu_id'])->delete();
        }
        //绑定账号
        return Db::name('OauthUsers')->save(array('oauth' => $data['oauth'], 'openid' => $data['openid'], 'user_id' => $user['user_id'], 'unionid' => $data['unionid'], 'oauth_child' => $data['oauth_child']));
    }
    
    //绑定账号
    public function oauth_bind_new($user = array())
    {
        $thirdOauth = session('third_oauth');

        $thirdName = ['weixin'=>'微信' , 'qq'=>'QQ' , 'alipay'=>'支付宝', 'miniapp' => '微信小程序'];

        //1.检查账号密码是否正确
        $ruser = M('Users')->where(array('mobile'=>$user['mobile']))->find();
        if(empty($ruser)){
            return array('status'=>-1,'msg'=>'账号不存在','result'=>'');
        }
        
//        if($ruser['password'] != $user['password']){
//            return array('status'=>-1,'msg'=>'账号或密码错误','result'=>'');
//        }
    
        //2.检查第三方信息是否完整
        $openid = $thirdOauth['openid'];   //第三方返回唯一标识
        $unionid = $thirdOauth['unionid'];   //第三方返回唯一标识
        $oauth = $thirdOauth['oauth'];      //来源
        $oauthCN = $platform = $thirdName[$oauth];
        if((empty($unionid) || empty($openid)) && empty($oauth)){
            return array('status'=>-1,'msg'=>'第三方平台参数有误[openid:'.$openid.' , unionid:'.$unionid.', oauth:'.$oauth.']','result'=>'');
        }
    
        //3.检查当前当前账号是否绑定过开放平台账号
        //1.判断一个账号绑定多个QQ
        //2.判断一个QQ绑定多个账号
        if($unionid){ 
            //如果有 unionid
            
            //1.1此oauth是否已经绑定过其他账号
            $thirdUser = M('OauthUsers')->where(['unionid'=>$unionid, 'oauth'=> $oauth])->find();
            if($thirdUser && $ruser['user_id'] != $thirdUser['user_id'] ){ 
                return array('status'=>-1,'msg'=>'此'.$oauthCN.'已绑定其它账号','result'=>'');
            } 
            
            //1.2此账号是否已经绑定过其他oauth
            $thirdUser = M('OauthUsers')->where(['user_id'=>$ruser['user_id'], 'oauth'=> $oauth])->find();
            if($thirdUser && $thirdUser['unionid'] != $unionid){         
                return array('status'=>-1,'msg'=>'此'.$oauthCN.'已绑定其它账号','result'=>'');
            }
         
        }else{
            //如果没有unionid
            
            //2.1此oauth是否已经绑定过其他账号
            $thirdUser = M('OauthUsers')->where(['openid'=>$openid, 'oauth'=> $oauth])->find();
            if($thirdUser){ 
                return array('status'=>-1,'msg'=>'此'.$oauthCN.'已绑定其它账号','result'=>'');
            }
            
            //2.2此账号是否已经绑定过其他oauth
            $thirdUser = M('OauthUsers')->where(['user_id'=>$ruser['user_id'], 'oauth'=> $oauth])->find();
            if($thirdUser){
                return array('status'=>-1,'msg'=>'此账号已绑定其它'.$oauthCN.'账号','result'=>'');
            } 
        }
       
        if(!isset($thirdOauth['oauth_child'])){
            $thirdOauth['oauth_child'] = '';
        }
        //4.账号绑定
        M('OauthUsers')->save(array('oauth'=>$oauth , 'openid'=>$openid ,'user_id'=>$ruser['user_id'] , 'unionid'=>$unionid, 'oauth_child'=>$thirdOauth['oauth_child']));
        $ruser['token'] = md5(time().mt_rand(1,999999999));
        $ruser['last_login'] = time();
        
        M('Users')->where('user_id' , $ruser['user_id'])->save(array('token'=>$ruser['token'] , 'last_login'=>$ruser['last_login']));
        
        return array('status'=>1,'msg'=>'绑定成功','result'=>$ruser);

    }

    /**
     * 获取第三方登录的用户
     * @param $openid
     * @param $unionid
     * @param $oauth
     * @param $oauth_child
     * @return array
     */
    private function getThirdUser($data)
    {
        $user = [];
        $thirdUser = Db::name('oauth_users')->where(['openid' => $data['openid'], 'oauth' => $data['oauth']])->find();
        if (!$thirdUser) {
            if ($data['unionid']) {
                $thirdUser = Db::name('oauth_users')->where(['unionid' => $data['unionid']])->find();
                if ($thirdUser) {
                	$data['user_id'] = $thirdUser['user_id'];
                	Db::name('oauth_users')->insert($data);//补充其他第三方登录方式
                }
            }
        }
        
        if ($thirdUser) {
            $user = Db::name('users')->where('user_id', $thirdUser['user_id'])->find();
            if (!$user) {
                Db::name('oauth_users')->where(['openid' => $data['openid'], 'oauth' => $data['oauth']])->delete();//删除残留数据
            }
        }
        return $user;
    }

    /*
     * 第三方登录: (第一种方式:第三方账号直接创建账号, 不需要额外绑定账号)
     */
    public function thirdLogin($data = array())
    {
        if (!$data['openid'] || !$data['oauth']) {
            return array('status' => -1, 'msg' => '参数有误', 'result' => 'aaa');
        }
        $user2 = session('user');
        if (!empty($user2)) {
            $r = $this->oauth_bind($data);//绑定账号
            if ($r) {
                return array('status' => 1, 'msg' => '绑定成功', 'result' => $user2);
            } else {
                return array('status' => -1, 'msg' => '您的' . $data['oauth'] . '账号已经绑定过账号', 'bind_status' => 0);
            }
        }

        $data['push_id'] && $map['push_id'] = $data['push_id'];
        $map['token'] = md5(time() . mt_rand(1, 999999999));
        $map['last_login'] = time();
        
        $user = $this->getThirdUser($data);
        if(!$user){
            //账户不存在 注册一个
            $map['password'] = '';
            $map['openid'] = $data['openid'];
            $map['nickname'] = $data['nickname'];
            $map['reg_time'] = time();
            $map['oauth'] = $data['oauth'];
            $map['head_pic'] = !empty($data['head_pic']) ? $data['head_pic'] : '/public/images/icon_goods_thumb_empty_300.png';
            $map['sex'] = $data['sex'] === null ? 0 :  $data['sex'];
            $map['first_leader'] = cookie('first_leader'); // 推荐人id
            if($_GET['first_leader'])
                $map['first_leader'] = $_GET['first_leader']; // 微信授权登录返回时 get 带着参数的
    
            // 如果找到他老爸还要找他爷爷他祖父等
            if ($map['first_leader']) {
                $first_leader = Db::name('users')->where("user_id = {$map['first_leader']}")->find();
                $map['second_leader'] = $first_leader['first_leader']; //  第一级推荐人
                $map['third_leader'] = $first_leader['second_leader']; // 第二级推荐人
                //他上线分销的下线人数要加1
                Db::name('users')->where(array('user_id' => $map['first_leader']))->setInc('underling_number');
                Db::name('users')->where(array('user_id' => $map['second_leader']))->setInc('underling_number');
                Db::name('users')->where(array('user_id' => $map['third_leader']))->setInc('underling_number');
            } else {
                $map['first_leader'] = 0;
            }
            // 成为分销商条件
            //$distribut_condition = tpCache('distribut.condition');
            //if($distribut_condition == 0)  // 直接成为分销商, 每个人都可以做分销
            $map['is_distribut']  = 1;
            $row_id = Db::name('users')->add($map);
            $user = Db::name('users')->where(array('user_id'=>$row_id))->find();
            
            if (!isset($data['oauth_child'])) {
                $data['oauth_child'] = '';
            }
            
            //不存在则创建个第三方账号
            $data['user_id'] = $user['user_id'];
            $user_level =Db::name('user_level')->where('amount = 0')->find(); //折扣
            $data['discount'] = !empty($user_level) ? $user_level['discount']/100 : 1;  //新注册的会员都不打折
            Db::name('OauthUsers')->save($data);
            
        } else {
            Db::name('users')->where('user_id', $user['user_id'])->save($map);
            $user['token'] = $map['token'];
            $user['last_login'] = $map['last_login'];
        }
    
        return array('status'=>1,'msg'=>'登陆成功','result'=>$user);
    }
    
    /*
     * 第三方登录(第二种方式:第三方账号登录必须绑定账号)
     */
    public function thirdLogin_new($data = array())
    {
        if((empty($data['openid']) && empty($data['unionid'])) || empty($data['oauth'])){
            return ['status' => -1, 'msg' => '参数错误, openid,unionid或oauth为空','result'=>''];
        }

        $user = $this->getThirdUser($data);
        if( ! $user){
            return ['status' => -1, 'msg' => '请绑定账号' , 'result'=>'100'];
        }

        $data['push_id'] && $map['push_id'] = $data['push_id'];
        $map['token'] = md5(time() . mt_rand(1, 999999999));
        $map['last_login'] = time();

        Db::name('users')->where(array('user_id' => $user['user_id']))->save($map);
        //重新加载一次用户信息
        $user = Db::name('users')->where(array('user_id' => $user['user_id']))->find();

        return array('status' => 1, 'msg' => '登陆成功', 'result' => $user);
    }

    /**
     * 注册
     * @param $username  邮箱或手机
     * @param $password  密码
     * @param $password2 确认密码
     * @return array
     */
    public function reg($username,$password,$password2,$push_id = 0,$invite=array(),$nickname="",$head_pic=""){
        if(check_mobile($username)){
            $is_validated = 1;
            $map['mobile_validated'] = 1;
            $map['mobile'] = $username; //手机注册
            $map['nickname'] = isset(session('third_oauth')['nickname']) && !empty(session('third_oauth')['nickname']) ? session('third_oauth')['nickname'] : $username;
        }else{
            return $this->ajaxReturn(['status' => -1,'msg' => '手机号有误']);
        }
        if(!empty($head_pic)){
            $map['head_pic'] = $head_pic;
        }else{
            $map['head_pic'] = isset(session('third_oauth')['head_pic']) && !empty(session('third_oauth')['head_pic']) ? session('third_oauth')['head_pic'] : 'http://cdn.cfo2o.com/data/avatar/user_head_default01.png';
        }

        if($is_validated != 1)
            return array('status'=>-1,'msg'=>'请用手机号','result'=>'');

        $map['password'] = '';
        $map['show_complete_info'] = 1;
        $map['reg_time'] = time();

        // 成为分销商条件  
        $distribut_condition = tpCache('distribut.condition'); 
        if($distribut_condition == 0)  // 直接成为分销商, 每个人都可以做分销        
            $map['is_distribut']  = 1;        
        
        $map['push_id'] = $push_id; //推送id
        $map['token'] = md5(time().mt_rand(1,999999999));
        $map['last_login'] = time();
        $user_level =Db::name('user_level')->where('amount = 0')->find(); //折扣
        $map['discount'] = !empty($user_level) ? $user_level['discount']/100 : 1;  //新注册的会员都不打折

        $user_id = M('users')->insertGetId($map);
        if($user_id === false)
            return array('status'=>-1,'msg'=>'注册失败');

        //注册完成分销逻辑
        $result = $this->regSetting($user_id);

        //插入cf_users
        $data['user_id'] = $user_id;
        $data['user_type'] = 1;

        if ($result != false) {
            $data = array_merge($data,$result);
        }

        $res = (new UserModel())->insert($data);

        //插入用户授权信息，但是没绑定
        if (session('third_oauth')['openid']) {
            //删除之前没绑定的相同的openid
            Db::name('OauthUsers')
                ->where('openid',session('third_oauth')['openid'])
                ->where('wx_bind',0)
                ->delete();

            Db::name('OauthUsers')
                ->save([
                    'oauth' => 'weixin',
                    'openid' => session('third_oauth')['openid'],
                    'user_id' => $user_id,
                    'unionid' => session('third_oauth')['unionid'] ?? '',
                    'oauth_child' => session('third_oauth')['oauth_child'],
                    'wx_bind' => 0
                ]);
        }

        if (!$res) {
            return array('status'=>-1,'msg'=>'注册失败');
        }

        $pay_points = tpCache('basic.reg_integral'); // 会员注册赠送积分

        //会员注册送券
        (new ActivityLogic())->grantUserCoupon($user_id);

        if($pay_points > 0){
            accountLog($user_id, 0,$pay_points, '会员注册赠送积分'); // 记录日志流水
        }
        $user = M('users')->where("user_id", $user_id)->find();
        Cookie::delete('invite_code');
        return array('status'=>1,'msg'=>'注册成功','result'=>$user);
    }

    /**
     * @Author: 陈静
     * @Date: 2018/05/05 10:09:05
     * @Description: 注册后的一些操作,上下级关系，上级送券等
     * @param $user_id
     * @return bool
     * @throws \think\Exception
     */
    public function regSetting($user_id)
    {
        $userUserModel = new UserUserModel();
        $nestedsetsModel = new NestedSets($userUserModel);
        //1.查询是否在cf_user_user中有数据
        $userUserInfo = $userUserModel->where('user_id' ,$user_id)->find();

        if ($userUserInfo) {
            return false;
        }

        //判断cookie中是否有点击过分享链接
        $distributeInfo = cookie('distribute_parent_id');

        //查询是否扫码，时间
        if ($_SESSION && $_SESSION['openid']) {
            $scanInfo = (new UserScanModel())->where('open_id' , $_SESSION['openid'])
                ->where('scan_type' , 0)
                ->order('scan_time DESC')
                ->find();
        }

        $inviteCode = I('invite_code');//查看是否有邀请码
        if (empty($inviteCode))$inviteCode = Cookie::get('invite_code');
        if (empty($inviteCode) && empty($distributeInfo) && (!isset($scanInfo) || empty($scanInfo)) ) {
            $res = $nestedsetsModel->insert(0, [
                'user_id' => $user_id,
                'be_user_start' => time(),
                'invite_friend_code' => generateInviteCode()
            ]);
            if (!$res) {
                Logs::sentryLogs('插入下级失败，新用户' . $user_id );
            }
            return false;
        }

        $parentId = 0;
        //是否有邀请码
        if(!empty($inviteCode)) $parentId = $userUserModel->field('user_id')->where('invite_friend_code',$inviteCode)->find()->user_id;

        if ($distributeInfo) {
            $parentId = explode('|' , $distributeInfo)[0];
            $distributeTime = explode('|' , $distributeInfo)[1];
            //是否有邀请码
            if(!empty($inviteCode)) $parentId = $userUserModel->field('user_id')->where('invite_friend_code',$inviteCode)->find()->user_id;

            $data['channel_type'] = 1;//注册方式

            //1.分享链接不为空，扫码不为空
            if (isset($scanInfo) && $scanInfo) {
                //比较扫码时间
                if (time() < $scanInfo->expire_time ) {
                    if ( $distributeTime < $scanInfo->scan_time ) {
                        $parentId = $scanInfo->parent_id;
                        $data['channel_type'] = 0;//注册方式
                    }
                }
            }
        }else{
            if (time() < $scanInfo->expire_time ) {
                $parentId = $scanInfo->parent_id;
                $data['channel_type'] = 0;//注册方式
            }
        }

        if ($parentId) {
            $res = $nestedsetsModel->insert($parentId, [
                'user_id' => $user_id,
                'be_user_start' => time(),
                'invite_friend_code' => generateInviteCode()
            ]);
            //给父级送券
            (new ActivityLogic())->grantUserCoupon($parentId, 6);
            if (!$res) {
                Logs::sentryLogs('插入下级失败，新用户' . $user_id . ';上级为：' . $parentId);
                return false;
            }
        }


        //查询父级id是不是合伙人
        $parentInfo = (new UserPartnerModel())->where('user_id',$parentId)->find();

        //不是合伙人，查询所有上级为合伙人的会员
        if (empty($parentInfo)) {
            $parentInfo = (new UserUserModel())->where('user_id',$parentId)->find();
            $parentInfoArr = (new UserUserModel())->alias('a')
                ->field('b.*')
                ->join(['cf_user_partner' => 'b'],'a.user_id = b.user_id','LEFT')
                ->where('a.left_key','<',$parentInfo->left_key)
                ->where('a.right_key','>',$parentInfo->right_key)
                ->where('a.right_key','>',$parentInfo->right_key)
                ->where('b.user_id','>',0)
                ->order('a.level DESC')
                ->select();
            if ($parentInfoArr) {
                $parentInfo = current($parentInfoArr)[0];
                $parentId = $parentInfo->user_id;
            }
        }


        $userModel = new UserModel();
        if (!empty($parentInfo)) {
            $data['first_partner_id'] = $parentId;
            //查找第一级代理商
            $findAgentIdInfo = $userModel->where('user_id',$parentId)->find();
            if (empty($findAgentIdInfo) || empty($findAgentIdInfo->first_agent_id)) {
                Logs::sentryLogs('加入分销数据有误，此处正常数据不会出现错误，请详细检查。此用户身'.$parentId.'份应该是合伙人，代理商id不为空；');
                return false;
            }
            $data['first_agent_id'] = $findAgentIdInfo->first_agent_id;
        }

        return $data;
    }

     /*
      * 获取当前登录用户信息
      */
    public function get_info($user_id)
    {
        if (!$user_id) {
            return array('status'=>-1, 'msg'=>'缺少参数');
        }

        $user = M('users')->where('user_id', $user_id)->find();
        if (!$user) {
            return false;
        }

        $activityLogic = new \app\common\logic\ActivityLogic;             //获取能使用优惠券个数
        $user['coupon_count'] = $activityLogic->getUserCouponNum($user_id, 0);
        
        $user['collect_count'] = Db::name('goods_collect')->where('user_id', $user_id)->count(); //获取收藏数量
        $user['return_count'] = M('return_goods')->where(['user_id'=>$user_id,'status'=>['in', '0,1,2,3']])->count();   //退换货数量
        $user['waitPay']     = M('order')->where('prom_type','<',5)->where("user_id = :user_id ".C('WAITPAY'))->bind(['user_id'=>$user_id])->count(); //待付款数量
        $user['waitSend']    = M('order')->where("user_id = :user_id ".C('WAITSEND'))->bind(['user_id'=>$user_id])->where('prom_type','<',5)->count(); //待发货数量
        $user['waitReceive'] = M('order')->where("user_id = :user_id ".C('WAITRECEIVE'))->bind(['user_id'=>$user_id])->where('prom_type','<',5)->count(); //待收货数量
        $user['order_count'] = $user['waitPay'] + $user['waitSend'] + $user['waitReceive'];
        
        $commentLogic = new CommentLogic;
        $user['uncomment_count'] = $commentLogic->getCommentNum($user_id, 0); //待评论数
        $user['comment_count'] = $commentLogic->getCommentNum($user_id, 1); //已评论数

        //足迹数量
        $user['visit_count'] = $visit = M('goods_visit')->alias('v')
            ->field('v.visit_id, v.goods_id, v.visittime, g.goods_name, g.shop_price, g.cat_id')
            ->join('__GOODS__ g', 'v.goods_id=g.goods_id')
            ->where('v.user_id', $user_id)
            ->order('v.visittime desc')
            ->count();

        //地址数量
        $user['address_count'] = count(get_user_address_list($user_id));

        //可领取优惠券数量
        $user['coupon_count'] = (new Coupon())->getUserCanGetCouponNums($user_id);

        //已领取优惠券的数量
        $user['have_coupon_count'] = (new CouponLogic())->getNotUseCoupon($user_id);
        $user['user_money'] = round($user['user_money'],2);
        return ['status' => 1, 'msg' => '获取成功', 'result' => $user];
     }
     
    /*
      * 获取当前登录用户信息
      */
    public function getApiUserInfo($user_id)
    {
        if (!$user_id) {
            return array('status'=>-1, 'msg'=>'账户未登陆');
        }

        $user = M('users')->where('user_id', $user_id)->find();
        if (!$user) {
            return false;
        }

        $activityLogic = new \app\common\logic\ActivityLogic;             //获取能使用优惠券个数
        $user['coupon_count'] = $activityLogic->getUserCouponNum($user_id, 0);
        
        $user['collect_count'] = Db::name('goods_collect')->where('user_id', $user_id)->count();//获取收藏数量
        $user['visit_count']   = M('goods_visit')->where('user_id', $user_id)->count();   //商品访问记录数
        $user['return_count'] = M('return_goods')->where("user_id=$user_id and status<2")->count();   //退换货数量
        $order_where = "deleted=0 AND order_status<>5 AND prom_type<5 AND user_id=$user_id ";
        $user['waitPay']     = M('order')->where($order_where.C('WAITPAY'))->count(); //待付款数量
        $user['waitSend']    = M('order')->where($order_where.C('WAITSEND'))->count(); //待发货数量
        $user['waitReceive'] = M('order')->where($order_where.C('WAITRECEIVE'))->count(); //待收货数量
        $user['order_count'] = $user['waitPay'] + $user['waitSend'] + $user['waitReceive'];
        
        $messageLogic = new \app\common\logic\MessageLogic();
        $user['message_count'] = $messageLogic->getUserMessageCount();
        
        $commentLogic = new CommentLogic;
        $user['uncomment_count'] = $commentLogic->getCommentNum($user_id, 0);; //待评论数
        $user['comment_count'] = $commentLogic->getCommentNum($user_id, 1); //已评论数
        $cartLogic = new CartLogic();
        $cartLogic->setUserId($user_id);
        $user['cart_goods_num'] = $cartLogic->getUserCartGoodsNum();
            
         return ['status' => 1, 'msg' => '获取成功', 'result' => $user];
     }
     
    /*
     * 获取最近一笔订单
     */
    public function get_last_order($user_id){
        $last_order = M('order')->where("user_id", $user_id)->order('order_id DESC')->find();
        return $last_order;
    }


    /*
     * 获取订单商品
     */
    public function get_order_goods($order_id){
        $sql = "SELECT og.*,g.commission, g.original_img FROM __PREFIX__order_goods og LEFT JOIN __PREFIX__goods g ON g.goods_id = og.goods_id WHERE order_id = :order_id";
        $bind['order_id'] = $order_id;
        $goods_list = DB::query($sql,$bind);

        $return['status'] = 1;
        $return['msg'] = '';
        $return['result'] = $goods_list;
        return $return;
    }

    /**
     * 获取账户资金记录
     * @param $user_id|用户id
     * @param int $account_type|收入：1,支出:2 所有：0
     * @param null $order_sn
     * @return array
     */
    public function get_account_log($user_id, $account_type = 0, $order_sn = null){
        $account_log_where = ['user_id'=>$user_id];
        if($account_type == 1){
            $account_log_where['user_money|pay_points'] = ['gt',0];
        }elseif($account_type == 2){
            $account_log_where['user_money|pay_points'] = ['lt',0];
        }
        $order_sn && $account_log_where['order_sn'] = $order_sn;
        $count = M('account_log')->where($account_log_where)->count();
        $Page = new Page($count,15);
        $account_log = M('account_log')->where($account_log_where)
            ->order('change_time desc')
            ->limit($Page->firstRow.','.$Page->listRows)
            ->select();
        $return = [
            'status'    =>1,
            'msg'       =>'',
            'result'    =>$account_log,
            'show'      =>$Page->show()
        ];
        return $return;
    }

    /**
     * 提现记录
     * @author lxl 2017-4-26
     * @param $user_id
     * @param int $withdrawals_status 提现状态 0:申请中 1:申请成功 2:申请失败
     * @return mixed
     */
    public function get_withdrawals_log($user_id,$withdrawals_status=''){
        $withdrawals_log_where = ['user_id'=>$user_id];
        if($withdrawals_status){
            $withdrawals_log_where['status']=$withdrawals_status;
        }
        $count = M('withdrawals')->where($withdrawals_log_where)->count();
        $Page = new Page($count, 15);
        $withdrawals_log = M('withdrawals')->where($withdrawals_log_where)
            ->order('id desc')
            ->limit($Page->firstRow . ',' . $Page->listRows)
            ->select();
        $return = [
            'status'    =>1,
            'msg'       =>'',
            'result'    =>$withdrawals_log,
            'show'      =>$Page->show()
        ];
        return $return;
    }

    /**
     * 用户充值记录
     * $author lxl 2017-4-26
     * @param $user_id 用户ID
     * @param int $pay_status 充值状态0:待支付 1:充值成功 2:交易关闭
     * @return mixed
     */
    public function get_recharge_log($user_id,$pay_status=0){
        $recharge_log_where = ['user_id'=>$user_id];
        if($pay_status){
            $pay_status['status']=$pay_status;
        }
        $count = M('recharge')->where($recharge_log_where)->count();
        $Page = new Page($count, 15);
        $recharge_log = M('recharge')->where($recharge_log_where)
            ->order('order_id desc')
            ->limit($Page->firstRow . ',' . $Page->listRows)
            ->select();
        $return = [
            'status'    =>1,
            'msg'       =>'',
            'result'    =>$recharge_log,
            'show'      =>$Page->show()
        ];
        return $return;
    }
    /*
     * 获取优惠券
     */
    public function get_coupon($user_id, $type =0, $orderBy = null,$order_money = 0,$p=12)
    {
        $activityLogic = new \app\common\logic\ActivityLogic;
        $count = $activityLogic->getUserCouponNum($user_id, $type, $orderBy,$order_money );
        
        $page = new Page($count, $p);
        $list = $activityLogic->getUserCouponList($page->firstRow, $page->listRows, $user_id, $type, $orderBy,$order_money);

        $return['status'] = 1;
        $return['msg'] = '获取成功';
        $return['result'] = $list;
        $return['show'] = $page->show();
        return $return;
    }

    /**
     * 获取商品收藏列表
     * @param $user_id
     * @return mixed
     */
    public function get_goods_collect($user_id){
        $count = Db::name('goods_collect')->where('user_id', $user_id)->count();
        $page = new Page($count,10);
        $show = $page->show();
        //获取我的收藏列表
            $result = M('goods_collect')->alias('c')
            ->field('c.collect_id,c.add_time,g.goods_id,g.goods_name,g.shop_price,g.is_on_sale,g.store_count,g.cat_id,g.original_img,g.is_virtual')
            ->join('goods g','g.goods_id = c.goods_id','INNER')
            ->where("c.user_id = $user_id")
            ->limit($page->firstRow,$page->listRows)
            ->select();
        $return['status'] = 1;
        $return['msg'] = '获取成功';
        $return['result'] = $result;
        $return['show'] = $show;        
        return $return;
    }

    /**
     * 获取评论列表
     * @param $user_id 用户id
     * @param $status  状态 0 未评论 1 已评论 2全部
     * @return mixed
     */
    public function get_comment($user_id,$status=2){
        if($status == 1){
            //已评论
            $commented_count = Db::name('comment')
                ->alias('c')
                ->join('__ORDER_GOODS__ g','c.goods_id = g.goods_id and c.order_id = g.order_id', 'inner')
                ->where('c.user_id',$user_id)
                ->count();
            $page = new Page($commented_count,10);
            $comment_list = Db::name('comment')
                ->alias('c')
                ->field('c.*,g.*,(select order_sn from  __PREFIX__order where order_id = c.order_id ) as order_sn')
                ->join('__ORDER_GOODS__ g','c.goods_id = g.goods_id and c.order_id = g.order_id', 'inner')
                ->where('c.user_id',$user_id)
                ->order('c.add_time desc')
                ->limit($page->firstRow,$page->listRows)
                ->select();
        }else{
            $comment_where = ['o.user_id'=>$user_id,'og.is_send'=>1,'o.order_status'=>['in',[2,4]]];
            if($status == 0){
                $comment_where['og.is_comment'] = 0;
                $comment_where['o.order_status'] = 2;
            }
            $comment_count = Db::name('order_goods')->alias('og')->join('__ORDER__ o','o.order_id = og.order_id','left')->where($comment_where)->count();
            $page = new Page($comment_count,10);
            $comment_list = Db::name('order_goods')
                ->alias('og')
                ->join('__ORDER__ o','o.order_id = og.order_id','left')
                ->where($comment_where)
                ->order('o.order_id desc')
                ->limit($page->firstRow,$page->listRows)
                ->select();
        }
        $show = $page->show();
        if($comment_list){
        	$return['result'] = $comment_list;
        	$return['show'] = $show; //分页
        	return $return;
        }else{
        	return array();
        }
    }

    /**
     * 添加评论
     * @param $add
     * @return array
     */
    public function add_comment($add){
        if(!$add['order_id'] || !$add['goods_id']) 
            return array('status'=>-1,'msg'=>'非法操作','result'=>'');
        
        //检查订单是否已完成
        $order = M('order')->field('order_status')->where("order_id", $add['order_id'])->where('user_id', $add['user_id'])->find();
        if($order['order_status'] == 4){
            return array('status'=>-1,'msg'=>'您已经评论过该商品','result'=>'');
        }
        if($order['order_status'] != 2)
            return array('status'=>-1,'msg'=>'该笔订单还未确认收货','result'=>'');

        //检查是否已评论过
        $goods = M('comment')->where(['rec_id'=>$add['rec_id']])->find();
        if($goods)return array('status'=>-1,'msg'=>'您已经评论过该商品','result'=>'');
        if($add['goods_rank']<1 || $add['service_rank']<1){
            return array('status'=>-1,'msg'=>'请给商品评分','result'=>'');
        }
        $row = M('comment')->add($add);
        if($row)
        {
            //更新订单商品表状态
            M('order_goods')->where(array('rec_id'=>$add['rec_id'],'order_id'=>$add['order_id']))->save(array('is_comment'=>1));
            M('goods')->where(array('goods_id'=>$add['goods_id']))->setInc('comment_count',1); // 评论数加一
            // 查看这个订单是否全部已经评论,如果全部评论了 修改整个订单评论状态            
            $comment_count   = M('order_goods')->where("order_id", $add['order_id'])->where('is_comment', 0)->count();
            if($comment_count == 0) // 如果所有的商品都已经评价了 订单状态改成已评价
            {
                M('order')->where("order_id",$add['order_id'])->save(array('order_status'=>4));
            }
            return array('status'=>1,'msg'=>'评论成功','result'=>'');
        }
        return array('status'=>-1,'msg'=>'评论失败','result'=>'');
    }

    /**
     * 邮箱或手机绑定
     * @param $email_mobile  邮箱或者手机
     * @param int $type  1 为更新邮箱模式  2 手机
     * @param int $user_id  用户id
     * @return bool
     */
    public function update_email_mobile($email_mobile,$user_id,$type=1){
        //检查是否存在邮件
        if($type == 1)
            $field = 'email';
        if($type == 2)
            $field = 'mobile';
        $condition['user_id'] = array('neq',$user_id);
        $condition[$field] = $email_mobile;

        $is_exist = M('users')->where($condition)->find();
        if($is_exist)
            return false;
        unset($condition[$field]);
        $condition['user_id'] = $user_id;
        $validate = $field.'_validated';
        M('users')->where($condition)->save(array($field=>$email_mobile,$validate=>1));
        return true;
    }

    /**
     * 更新用户信息
     * @param $user_id
     * @param $post  要更新的信息
     * @return bool
     */
    public function update_info($user_id,$post=array()){
        $model = M('users')->where("user_id", $user_id);
        $row = $model->setField($post);
        if($row === false)
           return false;
        return true;
    }

    /**
     * 地址添加/编辑
     * @param $user_id 用户id
     * @param $user_id 地址id(编辑时需传入)
     * @return array
     */
    public function add_address($user_id,$address_id=0,$data){
        $post = $data;
        if($address_id == 0)
        {
            $c = M('UserAddress')->where("user_id", $user_id)->count();
            if($c >= 20)
                return array('status'=>-1,'msg'=>'最多只能添加20个收货地址','result'=>'');
        }

        //检查手机格式
        if($post['consignee'] == '')
            return array('status'=>-1,'msg'=>'收货人不能为空','result'=>'');
        if (!($post['province'] > 0)|| !($post['city']>0) || !($post['district']>0))
            return array('status'=>-1,'msg'=>'所在地区不能为空','result'=>'');
        if(!$post['address'])
            return array('status'=>-1,'msg'=>'地址不能为空','result'=>'');
        if(!check_mobile($post['mobile']) && !check_telephone($post['mobile']))
            return array('status'=>-1,'msg'=>'手机号码格式有误','result'=>'');

        //编辑模式
        if($address_id > 0){

            $address = M('user_address')->where(array('address_id'=>$address_id,'user_id'=> $user_id))->find();
            if($post['is_default'] == 1 && $address['is_default'] != 1)
                M('user_address')->where(array('user_id'=>$user_id))->save(array('is_default'=>0));
            $row = M('user_address')->where(array('address_id'=>$address_id,'user_id'=> $user_id))->save($post);
            if($row !== false){
                return array('status'=>1,'msg'=>'编辑成功','result'=>$address_id);
            }else{
                return array('status'=>-1,'msg'=>'操作完成','result'=>$address_id);
            }

        }
        //添加模式
        $post['user_id'] = $user_id;
        
        // 如果目前只有一个收货地址则改为默认收货地址
        $c = M('user_address')->where("user_id", $post['user_id'])->count();
        if($c == 0)  $post['is_default'] = 1;
        
        $address_id = M('user_address')->add($post);
        //如果设为默认地址
        $insert_id = DB::name('user_address')->getLastInsID();
        $map['user_id'] = $user_id;
        $map['address_id'] = array('neq',$insert_id);
               
        if($post['is_default'] == 1)
            M('user_address')->where($map)->save(array('is_default'=>0));
        if(!$address_id)
            return array('status'=>-1,'msg'=>'添加失败','result'=>'');
        
        
        return array('status'=>1,'msg'=>'添加成功','result'=>$address_id);
    }

    /**
     * 添加自提点
     * @author dyr
     * @param $user_id
     * @param $post
     * @return array
     */
    public function add_pick_up($user_id, $post)
    {
        //检查用户是否已经有自提点
        $user_pickup_address_id = M('user_address')->where(['user_id'=>$user_id,'is_pickup'=>1])->getField('address_id');
        $pick_up = M('pick_up')->where(array('pickup_id' => $post['pickup_id']))->find();
        $post['address'] = $pick_up['pickup_address'];
        $post['is_pickup'] = 1;
        $post['user_id'] = $user_id;
        $user_address = new UserAddress();
        if (!empty($user_pickup_address_id)) {
            //更新自提点
            $user_address_save_result = $user_address->allowField(true)->validate(true)->save($post,['address_id'=>$user_pickup_address_id]);
        } else {
            //添加自提点
            $user_address_save_result = $user_address->allowField(true)->validate(true)->save($post);
        }
        if (false === $user_address_save_result) {
            return array('status' => -1, 'msg' => '保存失败', 'result' => $user_address->getError());
        } else {
            return array('status' => 1, 'msg' => '保存成功', 'result' => '');
        }
    }

    /**
     * 设置默认收货地址
     * @param $user_id
     * @param $address_id
     */
    public function set_default($user_id,$address_id){
        M('user_address')->where(array('user_id'=>$user_id))->save(array('is_default'=>0)); //改变以前的默认地址地址状态
        $row = M('user_address')->where(array('user_id'=>$user_id,'address_id'=>$address_id))->save(array('is_default'=>1));
        if(!$row)
            return false;
        return true;
    }

    /**
     * 修改密码
     * @param $user_id  用户id
     * @param $old_password  旧密码
     * @param $new_password  新密码
     * @param $confirm_password 确认新 密码
     * @param bool|true $is_update
     * @return array
     */
    public function password($user_id,$old_password,$new_password,$confirm_password,$is_update=true){
        $user = M('users')->where('user_id', $user_id)->find();
        if(strlen($new_password) < 6)
            return array('status'=>-1,'msg'=>'密码不能低于6位字符','result'=>'');
        if($new_password != $confirm_password)
            return array('status'=>-1,'msg'=>'两次密码输入不一致','result'=>'');
        //验证原密码
        if($is_update && ($user['password'] != '' && encrypt($old_password) != $user['password']))
            return array('status'=>-1,'msg'=>'原密码验证失败','result'=>'');
        $row = M('users')->where("user_id", $user_id)->save(array('password'=>encrypt($new_password)));
        if(!$row)
            return array('status'=>-1,'msg'=>'修改失败','result'=>'');
        return array('status'=>1,'msg'=>'修改成功','result'=>'');
    }

    /**
     *  针对 APP 修改密码的方法
     * @param $user_id  用户id
     * @param $old_password  旧密码
     * @param $new_password  新密码
     * @param bool $is_update
     * @return array
     */
    public function passwordForApp($user_id,$old_password,$new_password,$is_update=true){
        $user = M('users')->where('user_id', $user_id)->find();
        if(strlen($new_password) < 6){
            return array('status'=>-1,'msg'=>'密码不能低于6位字符','result'=>'');
        }
        //验证原密码
        if($is_update && ($user['password'] != '' && $old_password != $user['password'])){
            return array('status'=>-1,'msg'=>'旧密码错误','result'=>'');
        }

        $row = M('users')->where("user_id='{$user_id}'")->update(array('password'=>$new_password));
        if(!$row){
            return array('status'=>-1,'msg'=>'密码修改失败','result'=>'');
        }
        return array('status'=>1,'msg'=>'密码修改成功','result'=>'');
    }
    
    /**
     * 设置支付密码
     * @param $user_id  用户id
     * @param $new_password  新密码
     * @param $confirm_password 确认新 密码
     */
    public function paypwd($user_id,$new_password,$confirm_password,$oldpaypwd){
        if(strlen($new_password) < 6)
            return array('status'=>-1,'msg'=>'密码不能低于6位字符','result'=>'');
        if($new_password != $confirm_password)
            return array('status'=>-1,'msg'=>'两次密码输入不一致','result'=>'');
        if (encrypt($oldpaypwd) == encrypt($confirm_password)) {
            return array('status'=>-1,'msg'=>'密码和原密码一致','result'=>'');
        }
        $row = M('users')->where("user_id",$user_id)->update(array('paypwd'=>encrypt($new_password)));
        if($row === false){
            return array('status'=>-1,'msg'=>'修改失败','result'=>'');
        }
        $url = session('payPriorUrl') ? session('payPriorUrl'): U('User/userinfo');
        session('payPriorUrl',null);
    	return array('status'=>1,'msg'=>'修改成功','url'=>$url);
    }

    /**
     *  针对 APP 修改支付密码的方法
     * @param $user_id  用户id
     * @param $new_password  新密码
     * @return array
     */
    public function payPwdForApp($user_id, $new_password)
    {
        if (strlen($new_password) < 6) {
            return array('status' => -1, 'msg' => '密码不能低于6位字符', 'result' => '');
        }

        $row = Db::name('users')->where(['user_id'=>$user_id])->update(array('paypwd' => $new_password));
        if (!$row) {
            return array('status' => -1, 'msg' => '密码修改失败', 'result' => '');
        }
        return array('status' => 1, 'msg' => '密码修改成功', 'result' => '');
    }
    /**
     * 发送验证码: 该方法只用来发送邮件验证码, 短信验证码不再走该方法
     * @param $sender 接收人
     * @param $type 发送类型
     * @return json
     */
    public function send_email_code($sender){
    	$sms_time_out = tpCache('sms.sms_time_out');
    	$sms_time_out = $sms_time_out ? $sms_time_out : 180;
    	//获取上一次的发送时间
    	$send = session('validate_code');
    	if(!empty($send) && $send['time'] > time() && $send['sender'] == $sender){
    		//在有效期范围内 相同号码不再发送
    		$res = array('status'=>-1,'msg'=>'规定时间内,不要重复发送验证码');
            return $res;
    	}
    	$code =  mt_rand(1000,9999);
		//检查是否邮箱格式
		if(!check_email($sender)){
			$res = array('status'=>-1,'msg'=>'邮箱码格式有误');
            return $res;
		}
		$send = send_email($sender,'验证码','您好，你的验证码是：'.$code);
    	if($send['status'] == 1){
    		$info['code'] = $code;
    		$info['sender'] = $sender;
    		$info['is_check'] = 0;
    		$info['time'] = time() + $sms_time_out; //有效验证时间
    		session('validate_code',$info);
    		$res = array('status'=>1,'msg'=>'验证码已发送，请注意查收');
    	}else{
    		$res = $send;
    	}
    	return $res;
    }    
     
    /**
     * 检查短信/邮件验证码验证码
     * @param unknown $code
     * @param unknown $sender
     * @param unknown $session_id
     * @return multitype:number string
     */
    public function check_validate_code($code, $sender, $type ='email', $session_id=0 ,$scene = -1){
    	
        $timeOut = time();
        $inValid = true;  //验证码失效

        //短信发送否开启
        //-1:用户没有发送短信
        //空:发送验证码关闭
        $sms_status = checkEnableSendSms($scene);

        //邮件证码是否开启
        $reg_smtp_enable = tpCache('smtp.regis_smtp_enable');
        
        if($type == 'email'){            
            if(!$reg_smtp_enable){//发生邮件功能关闭
                $validate_code = session('validate_code');
                $validate_code['sender'] = $sender;
                $validate_code['is_check'] = 1;//标示验证通过
                session('validate_code',$validate_code);
                return array('status'=>1,'msg'=>'邮件验证码功能关闭, 无需校验验证码');
            }            
            if(!$code)return array('status'=>-1,'msg'=>'请输入邮件验证码');                
            //邮件
            $data = session('validate_code');
            $timeOut = $data['time'];
            if($data['code'] != $code || $data['sender']!=$sender){
            	$inValid = false;
            }  
        }else{
            if($scene == -1){
                return array('status'=>-1,'msg'=>'参数错误, 请传递合理的scene参数');
            }elseif($sms_status['status'] == 0){
                $data['sender'] = $sender;
                $data['is_check'] = 1; //标示验证通过
                session('validate_code',$data);
                return array('status'=>1,'msg'=>'短信验证码功能关闭, 无需校验验证码');
            } 
            
            if(!$code)return array('status'=>-1,'msg'=>'请输入短信验证码');
            //短信
            $sms_time_out = tpCache('sms.sms_time_out');
            $sms_time_out = $sms_time_out ? $sms_time_out : 180;
            $data = M('sms_log')->where(array('mobile'=>$sender,'session_id'=>$session_id , 'status'=>1))->order('id DESC')->find();
            //file_put_contents('./test.log', json_encode(['mobile'=>$sender,'session_id'=>$session_id, 'data' => $data]));
            if(is_array($data) && $data['code'] == $code){
            	$data['sender'] = $sender;
            	$timeOut = $data['add_time']+ $sms_time_out;
            }else{
            	$inValid = false;
            }           
        }
        
       if (!config('APP_DEBUG')){
           if(empty($data)){
               $res = array('status'=>-1,'msg'=>'请先获取验证码');
           }elseif($timeOut < time()){
               $res = array('status'=>-1,'msg'=>'验证码已超时失效');
           }elseif(!$inValid)
           {
               $res = array('status'=>-1,'msg'=>'验证失败,验证码有误');
           }else{
               $data['is_check'] = 1; //标示验证通过
               session('validate_code',$data);
               $res = array('status'=>1,'msg'=>'验证成功');
           }
       }else{
           $data['is_check'] = 1; //标示验证通过
           session('validate_code',$data);
           $res = array('status'=>1,'msg'=>'验证成功');
       }
        return $res;
    }
     
    
    /**
     * @time 2016/09/01
     * 设置用户系统消息已读
     */
    public function setSysMessageForRead()
    {
        $user_info = session('user');
        if (!empty($user_info['user_id'])) {
            $data['status'] = 1;
            M('user_message')->where(array('user_id' => $user_info['user_id'], 'category' => 0,'status'=>0))->save($data);
        }
    }

    /**
     * 设置用户消息已读
     * @param int $category 0:系统消息|1：活动消息
     * @param $msg_id
     * @throws \think\Exception
     */
    public function setMessageForRead($category = 0,$msg_id)
    {
        $user_info = session('user');
        if (!empty($user_info['user_id'])) {
            $data['status'] = 1;
            $set_where['user_id'] = $user_info['user_id'];
            $set_where['category'] = $category;
            if($msg_id){
                $set_where['message_id'] = $msg_id;
            }
            $updat_meg_res = Db::name('user_message')->where($set_where)->update($data);
            if ($updat_meg_res !== false){
                return ['status'=>1,'msg'=>'操作成功'];
            }
        }
        return ['status'=>-1,'msg'=>'操作失败'];
    }

    /**
     * 用户假删除站内信
     */
    public function delUserMsg($user_id,$id){
        Db::name('user_message')->where(['user_id'=>$user_id,'message_id'=>$id])->update(['status'=>2]);
        return ['status'=>1];
    }

    /**
     * 假删除用户某一类型的消息
     */
    public function delUserTypeMsg($user_id,$type){
        Db::name('user_message')->where(function ($query)use($type){
            if ($type !== '') $query->where(['category'=>$type]);
        })->where(['user_id'=>$user_id])->update(['status'=>2]);
        return ['status'=>1];
    }
    /**
     * 获取访问记录
     * @param type $user_id
     * @param type $p
     * @return type
     */
    public function getVisitLog($user_id, $p = 1)
    {
        $visit = M('goods_visit')->alias('v')
            ->field('v.visit_id, v.goods_id, v.visittime, g.goods_name, g.shop_price, g.cat_id')
            ->join('__GOODS__ g', 'v.goods_id=g.goods_id')
            ->where('v.user_id', $user_id)
            ->order('v.visittime desc')
            ->page($p, 20)
            ->select();

        /* 浏览记录按日期分组 */
        $curyear = date('Y');
        $visit_list = [];
        foreach ($visit as $v) {
            if ($curyear == date('Y', $v['visittime'])) {
                $date = date('m月d日', $v['visittime']);
            } else {
                $date = date('Y年m月d日', $v['visittime']);
            }
            $visit_list[$date][] = $v;
        }

        return $visit_list;
    }
    
    /**
     * 上传头像
     */
    public function upload_headpic($must_upload = true)
    {
        if ($_FILES['head_pic']['tmp_name']) {
            $file = request()->file('head_pic');
            $image_upload_limit_size = config('image_upload_limit_size');
            $validate = ['size'=>$image_upload_limit_size,'ext'=>'jpg,png,gif,jpeg'];
            $dir = UPLOAD_PATH.'head_pic/';
            if (!($_exists = file_exists($dir))) {
                mkdir($dir);
            }
            $parentDir = date('Ymd');
            $info = $file->validate($validate)->move($dir, true);
            if ($info) {
                $pic_path = '/'.$dir.$parentDir.'/'.$info->getFilename();
            } else {
                return ['status' => -1, 'msg' => $file->getError()];
            }
        } elseif ($must_upload) {
            return ['status' => -1, 'msg' => "图片不存在！"];
        }
        return ['status' => 1, 'msg' => '上传成功', 'result' => $pic_path];
    }
    
    /**
     * 账户明细
     */
    public function account($user_id, $type='all'){
    	if($type == 'all'){
    		$count = M('account_log')->where("user_money!=0 and user_id=" . $user_id)->count();
    		$page = new Page($count, 16);
    		$account_log = M('account_log')->field("*,from_unixtime(change_time,'%Y-%m-%d %H:%i:%s') AS change_data")->where("user_money!=0 and user_id=" . $user_id)
                ->order('log_id desc')->limit($page->firstRow . ',' . $page->listRows)->select();
    	}else{
    		$where = $type=='plus' ? " and user_money>0 " : " and user_money<0 ";
    		$count = M('account_log')->where("user_id=" . $user_id.$where)->count();
    		$page = new Page($count, 16);
    		$account_log = Db::name('account_log')->field("*,from_unixtime(change_time,'%Y-%m-%d %H:%i:%s') AS change_data")->where("user_id=" . $user_id.$where)
                ->order('log_id desc')->limit($page->firstRow . ',' . $page->listRows)->select();
    	}
    	$result['account_log'] = $account_log;
    	$result['page'] = $page;
    	return $result;
    }
    
    /**
     * 积分明细
     */
    public function points($user_id, $type='all')
    {
 		 if($type == 'all'){
    		$count = M('account_log')->where("user_id=" . $user_id ." and pay_points!=0 ")->count();
    		$page = new Page($count, 16);
    		$account_log = M('account_log')->where("user_id=" . $user_id." and pay_points!=0 ")->order('log_id desc')->limit($page->firstRow . ',' . $page->listRows)->select();
    	}else{
    		$where = $type=='plus' ? " and pay_points>0 " : " and pay_points<0 ";
    		$count = M('account_log')->where("user_id=" . $user_id.$where)->count();
    		$page = new Page($count, 16);
    		$account_log = M('account_log')->where("user_id=" . $user_id.$where)->order('log_id desc')->limit($page->firstRow . ',' . $page->listRows)->select();
    	}

        $result['account_log'] = $account_log;
        $result['page'] = $page;
        return $result;
    }

    /**
     * 添加用户签到信息
     * @param $user_id int 用户id
     * @param $date date 日期
     * @return array
     */
    public function addUserSign($user_id, $date)
    {
        $config = tpCache('sign');
        $data['user_id'] = $user_id;
        $data['sign_total'] = 1;
        $data['sign_last'] = $date;
        $data['cumtrapz'] = $config['sign_integral'];
        $data['sign_time'] = "$date";
        $data['sign_count'] = 1;
        $data['this_month'] = $config['sign_integral'];
        $result['status'] = false;
        $result['msg'] = '签到失败!';
        if (Db::name('user_sign')->add($data)) {
            $result['status'] = true;
            $result['msg'] = '签到旅程开始啦,积分奖励!';
            accountLog($user_id, 0, $config['sign_integral'], '第一次签到赠送' . $config['sign_integral'] . '积分');
        }
        return $result;
    }

    /**
     * 累计用户签到信息
     * @param $userInfo  array   用户信息
     * @param $date      date    日期
     * @return array
     */
    public function updateUserSign($userInfo, $date)
    {
        $config = tpCache('sign');
        $update_data = array(
            'sign_total' => ['exp', 'sign_total+' . 1],                                     //累计签到天数
            'sign_last'  => ['exp', "'$date'"],                                             //最后签到时间
            'cumtrapz'   => ['exp', 'cumtrapz+' . $config['sign_integral']],                //累计签到获取积分
            'sign_time'  => ['exp', "CONCAT_WS(',',sign_time ,'$date')"],                   //历史签到记录
            'sign_count' => ['exp', 'sign_count+' . 1],                                     //连续签到天数
            'this_month' => ['exp', 'this_month+' . $config['sign_integral']],              //本月累计积分
        );
        $daya = $userInfo['sign_last'];
        $dayb = date("Y-n-j", strtotime($date) - 86400);
        if ($daya != $dayb) {                                                               //不是连续签
            $update_data['sign_count'] = ['exp', 1];
        }
        $mb = date("m", strtotime($date));
        if (intval($mb) != intval(date("m", strtotime($daya)))) {                            //不是本月签到
            $update_data['sign_count'] = ['exp', 1];
            $update_data['sign_time']  = ['exp', "'$date'"];
            $update_data['this_month'] = ['exp', $config['sign_integral']];
        }
        $update = Db::name('user_sign')->where(['user_id' => $userInfo['user_id']])->update($update_data);
        $result['status'] = false;
        $result['msg'] = '签到失败!';
        if ($update>0) {
            accountLog($userInfo['user_id'], 0, $config['sign_integral'], '签到赠送' . $config['sign_integral'] . '积分');
            $result['status'] = true;
            $result['msg']    = '签到成功!奖励' . $config['sign_integral'] . '积分';
            $userFind = Db::name('user_sign')->where(['user_id' => $userInfo['user_id']])->find();
            //满足额外奖励
            if ($userFind['sign_count'] >= $config['sign_signcount']) {
                $result['msg']    = '哇，你已经连续签到' . $userFind['sign_count'] . '天,得到额外奖励！';
                $this->extraAward($userInfo, $config);
            }
        }
        return $result;
    }

    /**
     * 累计签到额外奖励
     * @param $userSingInfo array 用户信息
     */
    public function extraAward($userSingInfo)
    {
        $config = tpCache('sign');
        //满足额外奖励
        Db::name('user_sign')->where(['user_id' => $userSingInfo['user_id']])->update([
            'cumtrapz' => ['exp', 'cumtrapz+' . $config['sign_award']],
            'this_month' => ['exp', 'this_month+' . $config['sign_award']]
        ]);
        $msg = '连续签到奖励' . $config['sign_award'] . '积分';
        accountLog($userSingInfo['user_id'], 0, $config['sign_award'], $msg);
    }

    /**
     * 标识用户签到信息
     * @param $user_id int 用户id
     * @return      array
     */
    public function idenUserSign($user_id)
    {
        $config = tpCache('sign');
        $map['us.user_id'] = $user_id;
        $field = [
            'u.user_id as user_id',
            'u.nickname',
            'u.mobile',
            'us.*',
        ];
        $join = [['users u', 'u.user_id=us.user_id', 'left']];
        $info = Db::name('user_sign')->alias('us')->field($field)->join($join)->where($map)->find();
        ($info['sign_last'] != date("Y-n-j", time())) && $tab = "1";
        $signTime = explode(",", $info['sign_time']);
        $str = "";
        //是否标识历史签到
        if (date("m", strtotime($info['sign_last'])) == date("m", time())) {
            foreach ($signTime as $val) {
                $str .= date("j", strtotime($val)) . ',';
            }
        } else {
            $info['sign_count'] = 0; //不是本月清除连续签到
        }
        if( $info['sign_count']>=$config['sign_signcount'])
            $display_sign=  $config['sign_award']+ $config['sign_integral'];
        else
            $display_sign=  $config['sign_integral'];
        $jiFen = ($config['sign_signcount'] * $config['sign_integral']) + $config['sign_award'];
        return ['info' => $info, 'str' => $str, 'jifen' => $jiFen, 'config' => $config, 'tab' => $tab,'display_sign'=>$display_sign];
    }
    public function userRelation($user_id){
        // 查找是否有扫码记录
        $scanCode = $this->table('cf_user_scan')
            ->where('user_id', $user_id)
            ->find();
    }
}