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

use app\admin\model\FlashSale;
use app\common\library\Logs;
use app\common\logic\ActivityLogic;
use app\common\logic\CartLogic;
use app\common\logic\GoodsPromFactory;
use app\common\logic\distribution\DistributionDevideLogLogic;
use app\common\logic\MessageLogic;
use app\common\logic\OssLogic;
use app\common\logic\UsersLogic;
use app\common\logic\OrderLogic;
use app\common\model\CfFormData;
use app\common\model\Coupon;
use app\common\model\CouponList;
use app\common\model\EsModel;
use app\common\model\FlashRemindModel;
use app\common\model\GoodsTopic;
use app\common\model\OrderGoods;
use app\common\model\PartnerRankActivity;
use app\common\model\SkinModel;
use app\common\model\SkinTypeModel;
use app\common\model\UserModel;
use app\common\model\Users;
use app\common\model\UserUserModel;
use app\common\model\VrOrderCode;
use think\AjaxPage;
use think\Cookie;
use think\Exception;
use think\Page;
use think\Session;
use think\Verify;
use think\db;

class User extends MobileBase
{

    public $user_id = 0;
    public $user = array();

    //测试发送优惠券
//    public function test1()
//    {
            //测试注册送券
//        (new ActivityLogic())->grantUserCoupon(3229);
//    }

    /**
     * @Author: 陈静
     * @Date: 2018/04/11 09:10:37
     * @Description: 修改token令牌
     */
    public function _initialize()
    {
        parent::_initialize();

        $nologin = array(
            'index','login', 'pop_login', 'do_login', 'logout', 'verify', 'set_pwd', 'finished',
            'verifyHandle', 'reg', 'send_sms_reg_code', 'find_pwd', 'check_validate_code',
            'forget_pwd', 'check_captcha', 'check_username', 'send_validate_code', 'express' ,
            'bind_guide', 'bind_account','wechatOauth','bindMobile','showUserReg','beInvited','test','checkPhone',
            'ajaxGetGuessUserLike'
        );
        $this->checkUserLogin($nologin);
    }

    /**
     * 修改tp原有逻辑
     *  注册/登陆（陈静--2018.3.23）
     */
    public function reg()
    {
        if (empty($this->user_id)) {
            $this->setRefer();
        }else{
            return $this->redirect(U('Mobile/User/index'));
        }

        $reg_sms_enable = tpCache('sms.regis_sms_enable');

        if (IS_POST) {
            $logic = new UsersLogic();

            $username = I('post.username', '');
            $code = I('post.mobile_code', '');
            $scene = I('post.scene', 1);
            $session_id = session_id();

            //是否开启注册验证码机制
            if (!config('APP_DEBUG')) {
                if (check_mobile($username)) {
                    if ($reg_sms_enable) {
                        //手机功能没关闭
                        $check_code = $logic->check_validate_code($code, $username, 'phone', $session_id, $scene);
                        if ($check_code['status'] != 1) {
                            $this->ajaxReturn($check_code);
                        }
                    }
                } else {
                    return $this->ajaxReturn(['status' => -1, 'msg' => '手机号有误']);
                }
            }


            //用户已存在，直接缓存登陆信息
            if ($userInfo = get_user_info($username,2)) {
                $data = [
                    'status'=>1,
                    'msg'=>'操作成功',
                    'result'=>$userInfo
                ];
            }else{
                Db::startTrans();
                try{
                    $data = $logic->reg($username, '', '',0,'');
                }catch (Exception $e){
                    Db::rollback();
                    Logs::sentryLogs($e,['msg' => '用户注册失败']);
                    $this->ajaxReturn(['status' => -1,'msg'=>'注册失败,请联系客服']);
                }
                //绑定第三方账号
                if ($data['status'] != 1) $this->ajaxReturn($data);
                Db::commit();
                $data['result']['is_first_login'] = 1;
                $data['result']['redirect_url'] = U('Mobile/User/userinfo');
            }
            if ($data['status'] != 1) $this->ajaxReturn($data);

            $this->setLoginInfo($data['result']);
            $this->ajaxReturn($data);
            exit;
        }else{
            if (is_weixin() && empty(session("third_oauth" )['openid'])){
                if (empty($this->weixin_config)) {
                    $this->weixin_config = M('wx_user')->find(); //取微获信配置
                    $this->assign('wechat_config', $this->weixin_config);
                }
                if(is_array($this->weixin_config) && $this->weixin_config['wait_access'] == 1){
                    //授权获取openid以及微信用户信息
                    $wxuser = $this->GetOpenid('snsapi_base');
                    session("third_oauth" , $wxuser);
                }
            }
        }

        $this->assign('regis_sms_enable',$reg_sms_enable); // 注册启用短信：
        $sms_time_out = tpCache('sms.sms_time_out')>0 ? tpCache('sms.sms_time_out') : 120;
        $referurl = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : U("Mobile/User/index");
        $this->assign('referurl',$referurl);
        $this->assign('sms_time_out', $sms_time_out); // 手机短信超时时间
        return $this->fetch();
    }

    /**
     * @Author: 陈静
     * @Date: 2018/03/27 13:03:14
     * @Description: 微信授权登陆
     */
    public function wechatOauth()
    {
        $this->wechatLogin();

        $refer_url = Cookie::get('refer_url');

        if ($refer_url) {
            header('Location:'.urldecode($refer_url));
        }else{
            $this->redirect(U('Mobile/User/index'));
        }
        exit;
    }


    /**
     * @Author: 陈静
     * @Date: 2018/05/17 10:04:47
     * @Description: 统一微信授权登陆
     * @return mixed
     * @throws \think\exception\DbException
     * @throws db\exception\DataNotFoundException
     * @throws db\exception\ModelNotFoundException
     */
    public function wechatLogin()
    {
        $thirdOauth = session('third_oauth');

        //重新拉取授权
        if ( empty($thirdOauth) || $thirdOauth['scope'] == 'snsapi_base') {
            $thirdOauth = $this->GetOpenid('snsapi_userinfo'); //授权获取openid以及微信用户信息
            session("third_oauth" , $thirdOauth);
            session('subscribe', $thirdOauth['subscribe']);// 当前这个用户是否关注了微信公众号
        }

        //缓存openid
        $_SESSION['openid'] = $thirdOauth['openid'];

        //检查用户是否注册，并且是否有手机号
        $userInfo = Db::name('users')
            ->alias('a')
            ->field('a.user_id,a.mobile,a.nickname,a.head_pic,a.token,a.reg_time,b.wx_bind')
            ->join('OauthUsers b','a.user_id = b.user_id','LEFT')
            ->where('b.openid','=',$_SESSION['openid'])
            ->where('b.wx_bind',1)
            ->find();

        if ($this->user_id) {
            $refer_url = Cookie::get('refer_url');
            if ($refer_url) {
                header('Location:'.urldecode($refer_url));
            }else{
                $this->redirect(U('Mobile/User/index'));
            }
        }

        //查看当前微信用户是否注册或绑定手机号
        if ( empty($userInfo) || empty($userInfo['mobile']) ) {
            echo $this->fetch('cf_bind_wx');exit;
        }

        $this->setLoginInfo($userInfo,0);
    }


    /**
     * @Author: 陈静
     * @Date: 2018/03/27 15:11:56
     * @Description: 微信授权绑定手机号
     */
    public function bindMobile()
    {
        $thirdOauth = session('third_oauth');

        $userDbInfoByOpenid = get_user_info($thirdOauth['openid'],3,'weixin');

        if ($userDbInfoByOpenid) {
            return outPut(1,'操作成功');
        }
        /**
         * 进入此方法的都是
         * 1.没有授权信息，有账户的人
         * 2.没有授权信息，没有账户的人
         * 3.有账户，有授权，无手机号
         * 4.有授权，有手机号，但是信息不一致的人
         */
        $mobile = I('post.mobile');
        $code = I('post.mobile_code');

        $reg_sms_enable = tpCache('sms.regis_sms_enable');
        $scene = I('post.scene', 1);

        $session_id = session_id();

        if (!config('APP_DEBUG')) {
            if(check_mobile($mobile)){
                if($reg_sms_enable){
                    //手机功能没关闭
                    $check_code = (new UsersLogic())->check_validate_code($code, $mobile, 'phone', $session_id, $scene);
                    if($check_code['status'] != 1){
                        $this->ajaxReturn($check_code);
                    }
                }
            }else{
                return outPut(-1,'手机号有误');
            }
        }

        //是否有授权的用户,无手机号
        $userDbInfoByMobile = get_user_info($mobile,2);

        $userLogic = new UsersLogic();

        //1.没有授权信息，有账户的人
        if ($userDbInfoByMobile && empty($userDbInfoByOpenid)) {
            Db::startTrans();
            $result = Db::name('OauthUsers')
                ->save([
                    'oauth' => 'weixin',
                    'openid' => $thirdOauth['openid'],
                    'user_id' => $userDbInfoByMobile['user_id'],
                    'unionid' => $thirdOauth['unionid'] ?? '',
                    'oauth_child' => $thirdOauth['oauth_child'],
                    'wx_bind' => 1
                ]);
            if (!$result) {
                Db::rollback();
                Logs::sentryLogs('用户绑定微信失败：'.$mobile);
                return outPut(-1,'注册失败，请联系客服');
            }

            Db::commit();
            $userInfo = $userDbInfoByMobile;

        }

        //2.没有授权信息，没有账户的人
        if (empty($userDbInfoByMobile) && empty($userDbInfoByOpenid)) {
            Db::startTrans();
            $data = $userLogic->reg($mobile, '', '',0,'');

            if ($data['status'] != 1) {
                Db::rollback();
                return outPut(-1,'注册失败，请联系客服');
            }

            $result = Db::name('OauthUsers')
                ->save([
                    'oauth' => 'weixin',
                    'openid' => $thirdOauth['openid'],
                    'user_id' => $data['result']['user_id'],
                    'unionid' => $thirdOauth['unionid'] ?? '',
                    'oauth_child' => $thirdOauth['oauth_child'],
                    'wx_bind' => 1
                ]);
            if ($result) {
                Db::commit();
                $userInfo = $data['result'];
            }else{
                Db::rollback();
                return outPut(-1,'注册失败，请联系客服');
            }

        }

        //3.有账户，有授权，无手机号
        if ($userDbInfoByOpenid
            && ($userInfo = get_user_info($userDbInfoByOpenid['user_id']))
            && empty($userInfo['mobile'])
            && ($userDbInfoByMobile['user_id'] == $userDbInfoByOpenid['user_id']))
        {
            Db::startTrans();
            $result = Db::name('users')
                ->where('user_id','=',$userInfo['user_id'])
                ->update([
                    'mobile' => $mobile,
                ]);
            if ($result) {
                Db::rollback();
                $userInfo['mobile'] = $mobile;
            }else{
                Db::rollback();
                return outPut(-1,'注册失败，请联系客服');
            }

        }

        //4.有授权，有手机号，但是信息不一致的人
        if ($userDbInfoByOpenid && $userDbInfoByMobile && ($userDbInfoByMobile['user_id'] != $userDbInfoByOpenid['user_id'])) {
            return outPut(-1,'此手机号已绑定其他微信，请联系客服');
        }

        $this->setLoginInfo($userInfo,0);
        return outPut(1,'操作成功');
    }

    /**
     * @Author: 陈静
     * @Date: 2018/03/26 15:40:03
     * @Description: 修改授权的地方，从进入商城授权，修改为登陆页授权  (展示未用)
     * @return mixed
     */
    public function bind_guide(){
        $data = session('third_oauth');

        //如果为空，重新授权
        if (empty($data)) {
            $data = $this->GetOpenid(); //授权获取openid以及微信用户信息
            session("third_oauth" , $data);
            session('subscribe', $data['subscribe']);// 当前这个用户是否关注了微信公众号
            setcookie('subscribe',$data['subscribe']);
        }

        $this->assign("nickname", $data['nickname']);
        $this->assign("oauth", $data['oauth']);
        $this->assign("head_pic", $data['head_pic']);

        return $this->fetch();
    }

    /**
     * 绑定已有账号
     * @return \think\mixed
     */
    public function bind_account()
    {
        if(IS_POST){
            $data = I('post.');
            $userLogic = new UsersLogic();
            $user['mobile'] = $data['mobile'];

            /****修改绑定用户为验证码----2018.03.23.13:00***start***/
//            $user['password'] = encrypt($data['password']);
            $user['mobile_code'] =$data['mobile_code'];

            $code = I('post.mobile_code', '');
            $scene = I('post.scene', 1);

            $session_id = session_id();

            //手机功能没关闭
//            $check_code = $userLogic->check_validate_code($code, $user['mobile'], 'phone', $session_id, $scene);
//
//            if($check_code['status'] != 1){
//                $this->ajaxReturn($check_code);
//            }

            /****修改绑定用户为验证码----2018.03.23.13:00***end***/
            $res = $userLogic->oauth_bind_new($user);

            /**
             * @Author: 陈静
             * @Date: 2018/03/26 23:12:47
             * @Description: 存在问题，因为关注后会生成没有手机号的用户，并且已授权，所以加逻辑需要绑定手机，检查手机号是否为空。
             */
            /*取消关注时注册用户
            if ($res != 1 && $thirdUser = session('third_oauth')) {
                //查找授权的信息
                $userDbInfo = D('users')
                    ->alias('a')
                    ->field('a.user_id,a.mobile')
                    ->join('OauthUsers b','a.user_id = b.user_id','LEFT')
                    ->where('b.openid','=',$thirdUser['openid'])
                    ->where('b.oauth','=','weixin')
                    ->where('b.oauth_child','=','mp')
                    ->find();
                $userDbInfoByMobile = get_user_info($user['mobile'],2);

                if (empty($userDbInfo['mobile']) && $userDbInfoByMobile) {

                    Db::startTrans();
                    $result1 = D('OauthUsers')
                        ->where('openid','=',$thirdUser['openid'])
                        ->where('oauth','=','weixin')
                        ->where('oauth_child','=','mp')
                        ->update([
                            'user_id' => $userDbInfoByMobile['user_id']
                        ]);

                    $result2 = D('users')
                        ->where('user_id','=', $userDbInfo['user_id'])
                        ->delete();
                    if ($result1 && $result2) {
                        Db::commit();
                        $res = [
                            'status'=>1,
                            'msg'=>'绑定成功',
                            'result'=>$userDbInfoByMobile
                        ];
                    }else{
                        Db::rollback();
                    }
                }
            }
            */
            if ($res['status'] == 1) {
                //绑定成功, 重新关联上下级
                $map['first_leader'] = cookie('first_leader');  //推荐人id
                // 如果找到他老爸还要找他爷爷他祖父等
                if($map['first_leader']){
                    $first_leader = M('users')->where("user_id = {$map['first_leader']}")->find();
                    if($first_leader){
                        $map['second_leader'] = $first_leader['first_leader'];
                        $map['third_leader'] = $first_leader['second_leader'];
                    }
                    //他上线分销的下线人数要加1
                    M('users')->where(array('user_id' => $map['first_leader']))->setInc('underling_number');
                    M('users')->where(array('user_id' => $map['second_leader']))->setInc('underling_number');
                    M('users')->where(array('user_id' => $map['third_leader']))->setInc('underling_number');
                }else
                {
                    $map['first_leader'] = 0;
                }
                $ruser = $res['result'];
                M('Users')->where('user_id' , $ruser['user_id'])->save($map);

                $res['url'] = urldecode(I('post.referurl'));
                $res['result']['nickname'] = empty($res['result']['nickname']) ? $res['result']['mobile'] : $res['result']['nickname'];
                setcookie('user_id', $res['result']['user_id'], null, '/');
                setcookie('is_distribut', $res['result']['is_distribut'], null, '/');
                setcookie('uname', urlencode($res['result']['nickname']), null, '/');
                setcookie('head_pic', urlencode($res['result']['head_pic']), null, '/');
                setcookie('cn', 0, time() - 3600, '/');
                //获取公众号openid,并保持到session的user中
                $oauth_users = M('OauthUsers')->where(['user_id'=>$res['result']['user_id'] , 'oauth'=>'weixin' , 'oauth_child'=>'mp'])->find();
                $oauth_users && $res['result']['open_id'] = $oauth_users['open_id'];
                session('user', $res['result']);
                $cartLogic = new CartLogic();
                $cartLogic->setUserId($res['result']['user_id']);
                $cartLogic->doUserLoginHandle();  //用户登录后 需要对购物车 一些操作
                $userlogic = new OrderLogic();//登录后将超时未支付订单给取消掉
                $userlogic->setUserId($res['result']['user_id']);
                $userlogic->abolishOrder();
                return $this->success("绑定成功", U('Mobile/User/index'));
            }else{
                return $this->error("绑定失败,失败原因:".$res['msg']);
            }
        }else{
            return $this->fetch();
        }
    }

    /*
     * 用户中心首页
     */
    public function index()
    {
        $user_id =$this->user_id;

        $logic = new UsersLogic();

        //当前登录用户信息
        $user = $logic->get_info($user_id);

        //用户头像下载并上传至cdn
        try{
            if ( $user['result']['user_id'] ) {
                //如果是网络头像
                if (strpos($user['result']['head_pic'], 'http://thirdwx.qlogo.cn/') === 0) {
                    //下载头像
                    $ch = curl_init();
                    curl_setopt($ch,CURLOPT_URL, $user['result']['head_pic']);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    $file_content = curl_exec($ch);
                    curl_close($ch);

                    //保存头像
                    if ($file_content) {
                        $head_pic_path = TEMP_PATH.time().rand(1, 10000).'.png';
                        file_put_contents($head_pic_path, $file_content);

                        $headPath = (new OssLogic())
                            ->uploadFile($head_pic_path,'images/head_pic/'.date('Ym').'/'.time().rand(1, 10000).'.png');
                        if ($headPath) {
                            (new Users)->where('user_id',$user['result']['user_id'])
                                ->update(['head_pic' => config('aliyun_oss.Oss_cdn').$headPath]);
                        }
                        @unlink($head_pic_path);
                    }
                }
            }
        }catch (Exception $e){
            Logs::sentryLogs('替换用户头像失败'.$e->getMessage());
        }

        $userModel = new UserModel();
        $userInfo = $userModel->getUserRelationIdentity($this->user_id);
        $identity = $userInfo['identity'];

        $user['result']['income'] = round($userInfo['user_earnings_residue'] + $userInfo['partner_earnings_residue'] + $userInfo['agent_earnings_residue'] + $userInfo['wait_income'] , 2 );

        //查询邀请码
        $user['result']['invite_friend_code'] = Db::table('cf_user_user')->where('user_id',$user_id)->getField('invite_friend_code');
        //查询普通二维码
        $common_qrcode = urlencode(U('/Mobile/User/beInvited',[ 'invite_code' => $user['result']['invite_friend_code']],'',true));
        $user['result']['common_qrcode'] = '/index.php?m=Home&c=Index&a=qr_code&data='.urlencode($common_qrcode).'&head_pic='.urlencode($user['result']['head_pic']);

        //代理商邀请码和二维码
        if ($identity['agent']) {
            $user['result']['invite_partner_code'] = Db::table('cf_user_agent')->where('user_id',$user_id)->getField('invite_partner_code');

            $agent_qrcode = urlencode(U('/Mobile/distribution/verifiyInviteCode',[ 'invite_partner_code' => $user['result']['invite_partner_code']],'',true));

            $user['result']['agent_qrcode'] = '/index.php?m=Home&c=Index&a=qr_code&data='.urlencode($agent_qrcode).'&head_pic='.urlencode($user['result']['head_pic']);

        }else{
            $user['result']['agent_qrcode'] = '';
            $user['result']['invite_partner_code'] = '';
        }

        //拼团订单，展示当前登录用户订单流程未结束的订单数量，
        //1.拼团失败且已退款、2.拼团成功已完成、3.拼团成功退换货，4.售后完成的订单   为流程已结束的订单
        $unfinishedTeamOrder1 = Db::name('team_found')->alias('a')
            ->join('order c','a.order_id = c.order_id','left')
            ->where(function ($query) use ($user_id) {
                $query->where([
                    'a.user_id' => $user_id ,
                    'a.status' => ['in','1,2'],
                    'c.pay_status' => 1,
                    'c.order_status' => ['in', '0,1,2'],
                ]);
            })
            ->whereOr(function ($query) use ($user_id) {
                $query->where([
                    'a.user_id' => $user_id ,
                    'c.pay_status' => ['<>',3],
                    'a.status' => 3,
                ]);
            })
            ->count();

        $unfinishedTeamOrder2 = Db::name('team_follow')->alias('a')
            ->join('team_found b','a.found_id = b.found_id','left')
            ->join('order c','a.order_id = c.order_id','left')
            ->where(function ($query) use ($user_id) {
                $query->where([
                    'a.follow_user_id' => $user_id ,
                    'a.status' => ['in','1,2'],
                    'c.pay_status' => 1,
                    'c.order_status' => ['in', '0,1,2'],
                ]);
            })
            ->whereOr(function ($query) use ($user_id) {
                $query->where([
                    'a.follow_user_id' => $user_id ,
                    'c.pay_status' => ['<>',3],
                    'a.status' => 3,
                ]);
            })
            ->count();
        $user['result']['unfinished_team_order'] = $unfinishedTeamOrder1 + $unfinishedTeamOrder2;

        //展示订单流程未结束的卡券订单数量
        // 1.卡券订单已取消、2.已全部退款、3.已全部消费，为订单流程已结束的订单

        $user['result']['unfinished_vittual_order'] = Db::name('order')->where([
            'user_id' => $user_id,
            'prom_type' => 5 ,
            'pay_status' => 1 ,
            'order_status' => ['in','0,1,2'] ,
        ])->count();

/*        // 我的评论数
        $comment_count = M('comment')->where("user_id", $user_id)->count();
        // 等级名称
        $level_name = M('user_level')->where("level_id", $this->user['level'])->getField('level_name');
        //获取用户信息的数量
        $messageLogic = new MessageLogic();
        $user_message_count = $messageLogic->getUserMessageCount();

        $this->assign('user_message_count', $user_message_count);
        $this->assign('level_name', $level_name);
        $this->assign('comment_count', $comment_count);
*/
        if (is_mobile($user['result']['nickname'])) {
            $user['result']['nickname'] = phoneToStar($user['result']['nickname']);
        }
        $this->assign('user',$user['result']);
        if ($identity) {
            $this->assign('user_identity', $identity);
            //合伙人打榜活动
            if ($identity['partner'] || $identity['agent']) {
                $PartnerRankActivity = new PartnerRankActivity();
                $rankActivity = $PartnerRankActivity->nowActivity();
                $this->assign('rankActivity',$rankActivity);
            }
        }
        return $this->fetch();
    }

    //猜你喜欢
    public function ajaxGetGuessUserLike()
    {
        $data = [];
        $totalPages = 0;
        if ($this->user_id) {
            //当前用户购买数
            $userBuyGoodsId = M('order_goods')->alias('a')
                ->field('a.goods_id,c.goods_name,c.goods_remark,c.original_img,c.shop_price,c.market_price')
                ->join('order b','a.order_id = b.order_id','LEFT')
                ->join('goods c','a.goods_id = c.goods_id','LEFT')
                ->where('b.user_id',$this->user_id)
                ->where('b.pay_status',1)
                ->where('c.is_on_sale',1)
                ->group('a.goods_id')
                ->order('b.add_time DESC')->getField('a.goods_id',true);

            //当前用户收藏数
            $userCollectGoodsId = Db::name('goods_collect')
                ->where('user_id',$this->user_id)
                ->order('add_time DESC')
                ->getField('goods_id',true);

            //当前用户浏览数
            $userViewGoodsId = Db::name('goods_visit')
                ->where('user_id',$this->user_id)
                ->order('visittime DESC')
                ->getField('goods_id',true);
            $goodsId = array_unique(array_merge($userBuyGoodsId,$userCollectGoodsId,$userViewGoodsId));

            $pageObj = new AjaxPage(count($goodsId), 20);
            $data =  Db::name('goods')
                ->field('goods_id,goods_name,goods_remark,original_img,shop_price,market_price')
                ->where('goods_id' , 'in',$goodsId)
                ->limit($pageObj->firstRow,$pageObj->listRows)
                ->order("INSTR(',".join(',',$goodsId).",',CONCAT(',',goods_id,','))")
                ->select();

            $totalPages = ceil($pageObj->totalRows / $pageObj->listRows);

        }
        $userBuyNum = count($data);
        if ( $userBuyNum < 20 ) {
            $favouriteGoodsIdArr = [];
            if ( isset($goodsId) && $goodsId) {
                $favouriteGoodsIdArr = $goodsId;
            }

            $page = (int)(I('get.p',$totalPages) - $totalPages);

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
                ->order('goods_num DESC,b.add_time DESC,a.goods_id DESC')
                ->limit($page * 20 ,20)
                ->select();

            $favourite_goods = array_merge($data,$favourite_goods);
        }else{
            $favourite_goods = $data;
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






    /*
     * 账户资金
     */
    public function account()
    {
        $user = session('user');
        //获取账户资金记录
        $logic = new UsersLogic();
        $data = $logic->get_account_log($this->user_id, I('get.type'));
        $account_log = $data['result'];

        $this->assign('user', $user);
        $this->assign('account_log', $account_log);
        $this->assign('page', $data['show']);

        if ($_GET['is_ajax']) {
            return $this->fetch('ajax_account_list');
            exit;
        }
        return $this->fetch();
    }

    public function account_list()
    {
        $type = I('type','all');
        $usersLogic = new UsersLogic;
        $result = $usersLogic->account($this->user_id, $type);

        $this->assign('type', $type);
        $this->assign('account_log', $result['account_log']);
        if ($_GET['is_ajax']) {
            return $this->fetch('ajax_account_list');
        }
        return $this->fetch();
    }

    public function account_detail(){
        $log_id = I('log_id/d',0);
        $detail = Db::name('account_log')->where(['log_id'=>$log_id])->find();
        $this->assign('detail',$detail);
        return $this->fetch();
    }

    /**
     * 优惠券
     */
    public function coupon()
    {
        $logic = new UsersLogic();
        $data = $logic->get_coupon($this->user_id, input('type'));
        foreach($data['result'] as $k =>$v){
            $user_type = $v['use_type'];
            $data['result'][$k]['use_scope'] = C('COUPON_USER_TYPE')["$user_type"];
            if($user_type==1){ //指定商品
                $data['result'][$k]['goods_id'] = M('goods_coupon')->field('goods_id')->where(['coupon_id'=>$v['cid']])->getField('goods_id');
            }
            if($user_type==2){ //指定分类
                $data['result'][$k]['category_id'] = Db::name('goods_coupon')->where(['coupon_id'=>$v['cid']])->getField('goods_category_id');
            }
        }
        $coupon_list = $data['result'];
        $this->assign('coupon_list', $coupon_list);
        $this->assign('page', $data['show']);
        if (IS_AJAX) {
            return $this->fetch('ajax_coupon_list');
            exit;
        }
        return $this->fetch();
    }

    public function voucher() {
        $couponCount = (new ActivityLogic())->getCouponQuery(0,$this->user_id,0)->count();
        $this->assign('coupon_count',$couponCount);
        return $this->fetch('cf_voucher');
    }

    /*
     * 用户地址列表
     */
    public function address_list()
    {
        $address_lists = get_user_address_list($this->user_id);
        $region_list = get_region_list();
        $this->assign('region_list', $region_list);
        $this->assign('lists', $address_lists);
        return $this->fetch();
    }

    /*
     * 添加地址
     */
    public function add_address()
    {
        $source = input('source');
        if (IS_POST) {
            $post_data = input('post.');
            $logic = new UsersLogic();
            $data = $logic->add_address($this->user_id, 0, $post_data);
            $goods_id = input('goods_id/d');
            $item_id = input('item_id/d');
            $goods_num = input('goods_num/d');
            $order_id = input('order_id/d');
            $action = input('action');
            if ($data['status'] != 1){
                if (IS_AJAX) {
                    return outPut(-1,'请输入详细地址');
                }else{
                    $this->error($data['msg']);
                }
            } elseif ($source == 'cart2') {
                $data['url']=U('/Mobile/Cart/cart2', array('address_id' => $data['result'],'goods_id'=>$goods_id,'goods_num'=>$goods_num,'item_id'=>$item_id,'action'=>$action));
                $this->ajaxReturn($data);
            } elseif ($_POST['source'] == 'integral') {
                $data['url']=U('/Mobile/Cart/integral', array('address_id' => $data['result'],'goods_id'=>$goods_id,'goods_num'=>$goods_num,'item_id'=>$item_id));
                $this->ajaxReturn($data);
            } elseif($source == 'pre_sell_cart'){
                $data['url']=U('/Mobile/Cart/pre_sell_cart', array('address_id' => $data['result'],'act_id'=>$post_data['act_id'],'goods_num'=>$post_data['goods_num']));
                $this->ajaxReturn($data);
            } elseif($source == 'select_address'){
                $data['url']=U('/Mobile/distribution/selectAddress', array('address_id' => $data['result']));
                $this->ajaxReturn($data);
            }elseif($_POST['source'] == 'team'){
                $data['url']= U('/Mobile/Team/order', array('address_id' => $data['result'],'order_id'=>$order_id));
                $this->ajaxReturn($data);
            }elseif ($_POST['source'] == 'apply_partner'){
                $data['url']= U('/Mobile/Distribution/selectAddress', array('address_id' => $data['result']));
                $this->ajaxReturn($data);
            }elseif ($_POST['source'] == 'addOrder'){
                $team_id = I('team_id',0);
                $found_id = I('found_id',0);
                $data['url']= U('/Mobile/Team/addOrder', array(
                    'goods_num' =>$goods_num ,
                    'goods_id' => $goods_id ,
                    'item_id' => $item_id ,
                    'team_id' => $team_id ,
                    'found_id' => $found_id
                ));
                $this->ajaxReturn($data);
            }elseif ($source == 'vote'){  //汉服投票填写后即刻下单
                $orderModel = new \app\common\model\Order();
                $orderId = $orderModel->addVoteOrder($goods_id,$this->user_id);
                if($orderId){
                    $data['url']= U('/Mobile/Order/order_detail', array('id' => $orderId));
                    $this->ajaxReturn($data);
                }
            }
            else{
                $data['url']= U('/Mobile/User/address_list');
                $this->ajaxReturn($data);
            }

        }
        $p = M('region')->where(array('parent_id' => 0, 'level' => 1))->select();
        $this->assign('province', $p);
        $this->assign('source', $source);
        return $this->fetch();

    }

    /*
     * 地址编辑
     */
    public function edit_address()
    {
        $id = I('id/d');
        $address = M('user_address')->where(array('address_id' => $id, 'user_id' => $this->user_id))->find();
        if (IS_POST) {
            $source = input('source');
            $goods_id = input('goods_id/d');
            $item_id = input('item_id/d');
            $goods_num = input('goods_num/d');
            $action = input('action');
            $order_id = input('order_id/d');
            $post_data = input('post.');
            $logic = new UsersLogic();
            $data = $logic->add_address($this->user_id, $id, $post_data);
            if ($post_data['source'] == 'cart2') {
                $data['url']=U('/Mobile/Cart/cart2', array('address_id' => $data['result'],'goods_id'=>$goods_id,'goods_num'=>$goods_num,'item_id'=>$item_id,'action'=>$action));
                $this->ajaxReturn($data);
            } elseif ($_POST['source'] == 'integral') {
                $data['url'] = U('/Mobile/Cart/integral', array('address_id' => $data['result'],'goods_id'=>$goods_id,'goods_num'=>$goods_num,'item_id'=>$item_id));
                $this->ajaxReturn($data);
            } elseif($source == 'pre_sell_cart'){
                $data['url'] = U('/Mobile/Cart/pre_sell_cart', array('address_id' => $data['result'],'act_id'=>$post_data['act_id'],'goods_num'=>$post_data['goods_num']));
                $this->ajaxReturn($data);
            } elseif($_POST['source'] == 'team'){
                $data['url']= U('/Mobile/Team/order', array('address_id' => $data['result'],'order_id'=>$order_id));
                $this->ajaxReturn($data);
            }elseif ($_POST['source'] == 'apply_partner'){
                $data['url']= U('/Mobile/Distribution/selectAddress', array('address_id' => $data['result']));
                $this->ajaxReturn($data);
            } else{
                $data['url']= U('/Mobile/User/address_list');
                $this->ajaxReturn($data);
            }
        }
        //获取省份
        $p = M('region')->where(array('parent_id' => 0, 'level' => 1))->select();
        $c = M('region')->where(array('parent_id' => $address['province'], 'level' => 2))->select();
        $d = M('region')->where(array('parent_id' => $address['city'], 'level' => 3))->select();
        if ($address['twon']) {
            $e = M('region')->where(array('parent_id' => $address['district'], 'level' => 4))->select();
            $this->assign('twon', $e);
        }
        $this->assign('province', $p);
        $this->assign('city', $c);
        $this->assign('district', $d);
        $this->assign('address', $address);
        return $this->fetch();
    }

    /**
     * 用户自提点列表
     * @return mixed
     */
    public function pickup_address_list(){
        //获取当前用户地址
        $user_default_address= M('user_address')
            ->where(array('is_default'=>1,'user_id'=>$this->user_id))
            ->find();

        $pickup_where = array('p.province_id' => $user_default_address['province'], 'p.city_id' => $user_default_address['city'], 'p.district_id' => $user_default_address['district']);
        $pickup_list = M('pick_up')
            ->alias('p')
            ->field('p.*,r1.name AS province_name,r2.name AS city_name,r3.name AS district_name')
            ->join('__REGION__ r1','r1.id = p.province_id','LEFT')
            ->join('__REGION__ r2','r2.id = p.city_id','LEFT')
            ->join('__REGION__ r3','r3.id = p.district_id','LEFT')
            ->where($pickup_where)
            ->select();
        $user_pickup_where = array(
            'ua.user_id' => $this->user_id,
            'ua.is_pickup' => 1,
            'ua.district' =>  $user_default_address['district']
        );
        //
        $user_pickup_list = M('user_address')
            ->alias('ua')
            ->field('ua.*,r1.name AS province_name,r2.name AS city_name,r3.name AS district_name')
            ->join('__REGION__ r1', 'r1.id = ua.province', 'LEFT')
            ->join('__REGION__ r2', 'r2.id = ua.city', 'LEFT')
            ->join('__REGION__ r3', 'r3.id = ua.district', 'LEFT')
            ->where($user_pickup_where)
            ->find();
        $this->assign('pickup_list', $pickup_list);
        $this->assign('address_list', $user_pickup_list);
        $this->assign('user_id',$this->user_id);
        return $this->fetch('pickup_address_list');
    }

    /**
     * 选择并替换当前自提点
     * @return array
     */
    public function save_pickup()
    {
        $post = I('post.');
        $user_id=$this->user_id;
        $user_pickup_address_id = M('user_address')->where(['user_id'=>$user_id,'is_pickup'=>1])->getField('address_id');
        $pick_up = M('pick_up')->where(array('pickup_id' => $post['pickup_id']))->find();
        $user_address_data['address'] = $pick_up['pickup_address'];
        $user_address_data['is_pickup'] = 1;
        $user_address_data['user_id'] = $user_id;
        $user_address_data['province']=intval($post['province']);
        $user_address_data['city']=intval($post['city']);
        $user_address_data['district']=intval($post['district']);
        $user_address_data['twon']=intval($post['twon'])?:0;
        $user_address_data['address']=$post['address'];

        if (!empty($user_pickup_address_id)){
            //更新自提点
            $user_address_save_result=M('user_address')->where(['address_id'=>$user_pickup_address_id])->update($user_address_data);
        }else{
            $user_address_save_result = M('user_address')->save($user_address_data);
            $user_pickup_address_id=$user_address_save_result;
        }
        if($user_address_save_result){
            $data['msg']='自提点设置成功!';
            $data['address_id']=$user_pickup_address_id;
            $data['code']=200;
        }else{
            $data['code']=201;
        }
        $this->ajaxReturn($data);
    }
    /**
     * @return mixed
     */
    public function edit_pickup_address()
    {
        $id = I('id/d');
        $address = M('user_address')->where(array('address_id' => $id, 'user_id' => $this->user_id))->find();
        $source = input('source');
        if (IS_POST) {
            $post_data = input('post.');
            $logic = new UsersLogic();
            $data = $logic->add_address($this->user_id, $id, $post_data);
            $goods_id = input('goods_id/d');
            $item_id = input('item_id/d');
            $goods_num = input('goods_num/d');
            $order_id = input('order_id/d');
            $action = input('action');
            if ($data['status'] != 1){
                $this->error($data['msg']);
            } elseif ($source == 'cart2') {
                $data['url']=U('/Mobile/Cart/cart2', array('address_id' => $data['result'],'goods_id'=>$goods_id,'goods_num'=>$goods_num,'item_id'=>$item_id,'action'=>$action));
                $this->ajaxReturn($data);
            } elseif ($_POST['source'] == 'integral') {
                $data['url']=U('/Mobile/Cart/integral', array('address_id' => $data['result'],'goods_id'=>$goods_id,'goods_num'=>$goods_num,'item_id'=>$item_id));
                $this->ajaxReturn($data);
            } elseif($source == 'pre_sell_cart'){
                $data['url']=U('/Mobile/Cart/pre_sell_cart', array('address_id' => $data['result'],'act_id'=>$post_data['act_id'],'goods_num'=>$post_data['goods_num']));
                $this->ajaxReturn($data);
            } elseif($_POST['source'] == 'team'){
                $data['url']= U('/Mobile/Team/order', array('address_id' => $data['result'],'order_id'=>$order_id));
                $this->ajaxReturn($data);
            }else{
                $data['url']= U('/Mobile/User/pickup_address_list');
                $this->ajaxReturn($data);
            }
        }
        //获取省份
        $p = M('region')->where(array('parent_id' => 0, 'level' => 1))->select();
        $c = M('region')->where(array('parent_id' => $address['province'], 'level' => 2))->select();
        $d = M('region')->where(array('parent_id' => $address['city'], 'level' => 3))->select();
        if ($address['twon']) {
            $e = M('region')->where(array('parent_id' => $address['district'], 'level' => 4))->select();
            $this->assign('twon', $e);
        }
        $this->assign('province', $p);
        $this->assign('city', $c);
        $this->assign('district', $d);
        $this->assign('address', $address);
        return $this->fetch();
    }
    /**
     * @author huwenjun
     * @time 2018-4-12
     * 获取自提点信息
     */
    public function ajaxPickup()
    {
        $province_id = I('province_id/d');
        $city_id = I('city_id/d');
        $district_id = I('district_id/d');
        if (empty($province_id) || empty($city_id) || empty($district_id)) {
            $data['code']=201;
            $data['msg']="请输入完整地址";
        }
        $pickup_where = array('p.province_id' => $province_id, 'p.city_id' => $city_id, 'p.district_id' => $district_id);
        $pickup_list = M('pick_up')
            ->alias('p')
            ->field('p.*,r1.name AS province_name,r2.name AS city_name,r3.name AS district_name')
            ->join('__REGION__ r1','r1.id = p.province_id','LEFT')
            ->join('__REGION__ r2','r2.id = p.city_id','LEFT')
            ->join('__REGION__ r3','r3.id = p.district_id','LEFT')
            ->where($pickup_where)
            ->find();
        if(!empty($pickup_list)){
            $data['pickup_list']=$pickup_list;
            $data['code']=200;
            $data['msg']="";
        }else{
            $data['code']=201;
            $data['msg']="暂无地址";
        }
        $this->ajaxReturn($data);
    }
    /*
     * 地址编辑
     */
    public function delete_address()
    {
        $id = I('address_id/d');
        $result = M('user_address')->where(array('address_id' => $id, 'user_id' => $this->user_id))->delete();
        if($result){
            return outPut(1,'success');
        }
        return outPut(-1,'error');

    }

    /*
     * 设置默认收货地址
     */
    public function set_default()
    {
        $id = I('get.id/d');
        $source = I('get.source');
        M('user_address')->where(array('user_id' => $this->user_id))->save(array('is_default' => 0));
        $row = M('user_address')->where(array('user_id' => $this->user_id, 'address_id' => $id))->save(array('is_default' => 1));
        if ($source == 'cart2') {
            header("Location:" . U('Mobile/Cart/cart2'));
            exit;
        } else {
            header("Location:" . U('Mobile/User/address_list'));
        }
    }

    /*
     * 地址删除
     */
    public function del_address()
    {
        $id = I('get.id/d');

        $address = M('user_address')->where("address_id", $id)->find();
        $row = M('user_address')->where(array('user_id' => $this->user_id, 'address_id' => $id))->delete();
        // 如果删除的是默认收货地址 则要把第一个地址设置为默认收货地址
        if ($address['is_default'] == 1) {
            $address2 = M('user_address')->where("user_id", $this->user_id)->find();
            $address2 && M('user_address')->where("address_id", $address2['address_id'])->save(array('is_default' => 1));
        }
        if (!$row)
            $this->error('操作失败', U('User/address_list'));
        else
            $this->success("操作成功", U('User/address_list'));
    }


    /*
     * 个人信息
     */
    public function userinfo()
    {
        $userLogic = new UsersLogic();
        $user_info = $userLogic->get_info($this->user_id); // 获取用户信息
        $user_info = $user_info['result'];
        if (IS_POST || IS_AJAX) {
            //修改头像
            if ($_FILES['head_pic']['tmp_name']) {
                $file = $this->request->file('head_pic');
                $fileInfo = $file->getInfo();
                $ext = substr( $fileInfo['name'],strripos($fileInfo['name'],'.') );
                $new_name = get13TimeStamp().getRandChar(2).$ext;
                $ossLogic = new OssLogic();
                $info = $ossLogic->uploadFile(
                    $fileInfo['tmp_name'] , config('aliyun_oss.img_object').'head_pic/'.date('Ymd').'/'.$new_name
                );
                if($info){
                    $post['head_pic'] = config('aliyun_oss.Oss_cdn').$info;
                }else{
                    return outPut(-1,$ossLogic->getError());
                }
            }

            I('post.head_pic') ? $post['head_pic'] = I('post.head_pic') : false; //头像地址
            I('post.nickname') ? $post['nickname'] = I('post.nickname') : false; //昵称
            I('post.mobile') ? $post['mobile'] = I('post.mobile') : false; //手机
            I('post.sex') ? $post['sex'] = I('post.sex') : $post['sex'] = false;  // 性别
            I('post.age') ? $post['age'] = I('post.age') : $post['age'] = false;  // 年龄
            I('post.skinQuality') ? $post['skinQuality'] = I('post.skinQuality') : $post['skinQuality'] = false;  // 肤质测试

            foreach ($post as $k => &$v) {
                if ($v === false) {
                    unset($post[$k]);
                }
            }

            if ($post['skinQuality']) {
                $skinDatas = explode('|',$post['skinQuality']);
                foreach ($skinDatas as $v){
                    $data = [
                        'user_id' => $user_info['user_id'],
                        'skin_type_id1' => current(explode(',',$v)),
                        'skin_type_id2' => end(explode(',',$v)),
                        'add_time' => time(),
                    ];
                    $where = [
                        'user_id' => $user_info['user_id'],
                        'skin_type_id1' => current(explode(',',$v)),
                    ];
                    $skinModel = new SkinModel();
                    $skinId = $skinModel->where($where)->getField('skin_id');

                    try{
                        if ($skinId) {
                            $skinModel->where('skin_id',$skinId)->save($data);
                        }else{
                            $skinModel->save($data);
                        }
                    }catch(Exception $e){
                        Logs::sentryLogs('保存用户皮肤数据失败');
                        return outPut(-1,'保存用户皮肤数据失败');
                    }
                }
            }

            $mobile = I('post.mobile');
            $code = I('post.mobile_code', '');

            //检测手机号
            if (!empty($mobile)) {
                $c = M('users')->where(['mobile' => input('post.mobile'), 'user_id' => ['<>', $this->user_id]])->count();
                if ($c) {
                    return outPut(-1,'手机已被使用');
                }
                if (!$code)
                    return outPut(-1,'请输入验证码');

                if (!config('APP_DEBUG')) {
                    $check_code = $userLogic->check_validate_code($code, $mobile, 'phone', session_id(), 4);
                    if ($check_code['status'] != 1)
                        return outPut(-1,$check_code['msg']);
                }
            }

            //修改用户信息
            if (!$userLogic->update_info($this->user_id, $post)){
                return outPut(-1,'保存失败');
            }

            //完善资料送券
            $res = (new Coupon())->sendCouponPerfectUserInfo($this->user_id);
            if ($res) {
                (new Users())->where('user_id',$this->user_id)->update([
                    'show_complete_info' => 0
                ]);
            }

            if (IS_AJAX) {
                return outPut(1,'操作成功');
            }else{
                return $this->success('操作成功');
            }
        }

        //是否实名制认证
        $authInfo = (new UserModel())->getUserInfo($user_info['user_id']);
        if ( $authInfo->id_card_num && $authInfo->id_card_name ) {
            $replaceStr = mb_substr($authInfo->id_card_name,1);
            $isAuth = str_replace($replaceStr,str_repeat('*',mb_strlen($replaceStr)),$authInfo->id_card_name);
        }else{
            $isAuth = 0;
        }
        if (check_mobile($user_info['nickname'])){
            $user_info['nickname'] = substr_replace($user_info['nickname'], '****', 3, 4);
        }
        $this->assign('isAuth',$isAuth);
        $this->assign('is_set_paypwd',$user_info['paypwd'] ? 1 : 0);
        $this->assign('user', $user_info);
        $this->assign('sex', C('SEX'));

        //用户选择皮肤数据
        $userSkinDatas = (new SkinModel())->getUserSkinInfo($this->user_id);

        $userSkinDatas = (new SkinTypeModel())->field('name')->where('id','in',$userSkinDatas)->select();
        $userSkinDatas = collection($userSkinDatas)->toArray();
        $userSkinDatas = array_splice($userSkinDatas,0,2) ?? 0;

        $this->assign('user_skin_datas',$userSkinDatas);

        //从哪个修改用户信息页面进来，
        $dispaly = I('action');
        if ($dispaly != '') {
            $this->assignInfo($dispaly,$user_info);
            return $this->fetch("$dispaly");
        }

        $this->assign('mobile',substr_replace($this->user['mobile'], '****', 3, 4));
        return $this->fetch();
    }

    //为每个页面附相应的值
    public function assignInfo($display,$user_info)
    {
        switch ($display){
            //改变手机号
            case 'changeMobile':
                $this->assign('mobile',$user_info['mobile'] ?? 0);
                break;
            case 'skinQuality':
                //皮肤数据
                $skinTypeDatas = (new SkinTypeModel())->getSkinTypeDatas();
                $this->assign('skin_datas',$skinTypeDatas);

                $userSkinDatas = (new SkinModel())->getUserSkinInfo($this->user_id);
                $this->assign('user_skin_info',$userSkinDatas);
                break;
        }
    }

    //完善页面中
    public function completeUserInfo()
    {
        $skinTypeDatas = (new SkinTypeModel())->getSkinTypeDatas();
        $this->assign('skin_datas',$skinTypeDatas);
        $this->assign('user_info',$this->user);

        //用户选择数据
        $userSkinDatas = (new SkinModel())->getUserSkinInfo($this->user_id);
        $this->assign('user_skin_datas',$userSkinDatas);

        return $this->fetch();
    }

    //完善后
    public function completedUserInfo()
    {
        $this->assign('user_info',$this->user);
        return $this->fetch();
    }



    /**
     * 修改绑定手机
     * @return mixed
     */
    public function setMobile(){
        $userLogic = new UsersLogic();
        $mobile = input('mobile');
        $mobile_code = input('mobile_code');
        $is_validate = input('validate',0);

        $c = Db::name('users')->where(['mobile' => $mobile, 'user_id' => ['<>', $this->user_id]])->count();
        if ($c) {
            return outPut(-1,'手机已被使用');
        }
        if (!$mobile_code)
            return outPut(-1,'请输入验证码');
        $check_code = $userLogic->check_validate_code($mobile_code, $mobile, 'phone', session_id(), 4);

        if($check_code['status'] !=1){
            return outPut(-1,$check_code['msg']);
        }

        if ($is_validate){
            return outPut(1,'验证成功');
        }

        $res = Db::name('users')->where(['user_id' => $this->user_id])->update(['mobile'=>$mobile]);

        if($res){
            return outPut(1,'操作成功');
        }
        return outPut(-1,'操作失败');
    }

    /*
     * 邮箱验证
     */
    public function email_validate()
    {
        $userLogic = new UsersLogic();
        $user_info = $userLogic->get_info($this->user_id); // 获取用户信息
        $user_info = $user_info['result'];
        $step = I('get.step', 1);
        //验证是否未绑定过
        if ($user_info['email_validated'] == 0)
            $step = 2;
        //原邮箱验证是否通过
        if ($user_info['email_validated'] == 1 && session('email_step1') == 1)
            $step = 2;
        if ($user_info['email_validated'] == 1 && session('email_step1') != 1)
            $step = 1;
        if (IS_POST) {
            $email = I('post.email');
            $code = I('post.code');
            $info = session('email_code');
            if (!$info)
                $this->error('非法操作');
            if ($info['email'] == $email || $info['code'] == $code) {
                if ($user_info['email_validated'] == 0 || session('email_step1') == 1) {
                    session('email_code', null);
                    session('email_step1', null);
                    if (!$userLogic->update_email_mobile($email, $this->user_id))
                        $this->error('邮箱已存在');
                    $this->success('绑定成功', U('Home/User/index'));
                } else {
                    session('email_code', null);
                    session('email_step1', 1);
                    redirect(U('Home/User/email_validate', array('step' => 2)));
                }
                exit;
            }
            $this->error('验证码邮箱不匹配');
        }
        $this->assign('step', $step);
        return $this->fetch();
    }

    /*
    * 手机验证
    */
    public function mobile_validate()
    {
        $userLogic = new UsersLogic();
        $user_info = $userLogic->get_info($this->user_id); // 获取用户信息
        $user_info = $user_info['result'];
        $step = I('get.step', 1);
        //验证是否未绑定过
        if ($user_info['mobile_validated'] == 0)
            $step = 2;
        //原手机验证是否通过
        if ($user_info['mobile_validated'] == 1 && session('mobile_step1') == 1)
            $step = 2;
        if ($user_info['mobile_validated'] == 1 && session('mobile_step1') != 1)
            $step = 1;
        if (IS_POST) {
            $mobile = I('post.mobile');
            $code = I('post.code');
            $info = session('mobile_code');
            if (!$info)
                $this->error('非法操作');
            if ($info['email'] == $mobile || $info['code'] == $code) {
                if ($user_info['email_validated'] == 0 || session('email_step1') == 1) {
                    session('mobile_code', null);
                    session('mobile_step1', null);
                    if (!$userLogic->update_email_mobile($mobile, $this->user_id, 2))
                        $this->error('手机已存在');
                    $this->success('绑定成功', U('Home/User/index'));
                } else {
                    session('mobile_code', null);
                    session('email_step1', 1);
                    redirect(U('Home/User/mobile_validate', array('step' => 2)));
                }
                exit;
            }
            $this->error('验证码手机不匹配');
        }
        $this->assign('step', $step);
        return $this->fetch();
    }

    /**
     * 用户收藏列表
     */
    public function collect_list()
    {
        $userLogic = new UsersLogic();
        $data = $userLogic->get_goods_collect($this->user_id);
        $this->assign('page', $data['show']);// 赋值分页输出
        $this->assign('goods_list', $data['result']);
        if (IS_AJAX) {      //ajax加载更多
            return $this->fetch('ajax_collect_list');
            exit;
        }
        return $this->fetch();
    }

    /*
     *取消收藏
     */
    public function cancel_collect()
    {
        $collect_id = input('collect_ids');
        if (!is_numeric($collect_id) && !is_string($collect_id)) {
            if (IS_AJAX) {
                $this->ajaxReturn(['code'=>0,'msg'=>'参数错误']);
            } else {
                $this->error("参数错误", U('User/collect_list'));
            }
        }
        $user_id = $this->user_id;
        $res = M('goods_collect')->where('collect_id','in',"$collect_id")->where(['user_id' => $user_id])->delete();
        // echo Db::getLastSql();die;
        if (IS_AJAX) {
            $this->ajaxReturn(['code'=>1]);
        } else {
            $this->success("取消收藏成功", U('User/collect_list'));
        }
    }

    /**
     * 我的留言
     */
    public function message_list()
    {
        C('TOKEN_ON', true);
        if (IS_POST) {
            if(!$this->verifyHandle('message')){
                $this->error('验证码错误', U('User/message_list'));
            };

            $data = I('post.');
            $data['user_id'] = $this->user_id;
            $user = session('user');
            $data['user_name'] = $user['nickname'];
            $data['msg_time'] = time();
            if (M('feedback')->add($data)) {
                $this->success("留言成功", U('User/message_list'));
                exit;
            } else {
                $this->error('留言失败', U('User/message_list'));
                exit;
            }
        }
        $msg_type = array(0 => '留言', 1 => '投诉', 2 => '询问', 3 => '售后', 4 => '求购');
        $count = M('feedback')->where("user_id", $this->user_id)->count();
        $Page = new Page($count, 100);
        $Page->rollPage = 2;
        $message = M('feedback')->where("user_id", $this->user_id)->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $showpage = $Page->show();
        header("Content-type:text/html;charset=utf-8");
        $this->assign('page', $showpage);
        $this->assign('message', $message);
        $this->assign('msg_type', $msg_type);
        return $this->fetch();
    }

    /**账户明细*/
    public function points()
    {
        $type = I('type', 'all');    //获取类型
        $this->assign('type', $type);
        if ($type == 'recharge') {
            //充值明细
            $count = M('recharge')->where("user_id", $this->user_id)->count();
            $Page = new Page($count, 16);
            $account_log = M('recharge')->where("user_id", $this->user_id)->order('order_id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        } else if ($type == 'points') {
            //积分记录明细
            $count = M('account_log')->where(['user_id' => $this->user_id, 'pay_points' => ['<>', 0]])->count();
            $Page = new Page($count, 16);
            $account_log = M('account_log')->where(['user_id' => $this->user_id, 'pay_points' => ['<>', 0]])->order('log_id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        } else {
            //全部
            $count = M('account_log')->where(['user_id' => $this->user_id])->count();
            $Page = new Page($count, 16);
            $account_log = M('account_log')->where(['user_id' => $this->user_id])->order('log_id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        }
        $showpage = $Page->show();
        $this->assign('account_log', $account_log);
        $this->assign('page', $showpage);
        $this->assign('listRows', $Page->listRows);
        if ($_GET['is_ajax']) {
            return $this->fetch('ajax_points');
            exit;
        }
        return $this->fetch();
    }


    public function points_list()
    {
        $type = I('type','all');
        $usersLogic = new UsersLogic;
        $result = $usersLogic->points($this->user_id, $type);

        $this->assign('type', $type);
        $showpage = $result['page']->show();
        $this->assign('account_log', $result['account_log']);
        $this->assign('page', $showpage);
        if ($_GET['is_ajax']) {
            return $this->fetch('ajax_points');
        }
        return $this->fetch();
    }


    /*
     * 密码修改
     */
    public function password()
    {
        if (IS_POST) {
            $logic = new UsersLogic();
            $data = $logic->get_info($this->user_id);
            $user = $data['result'];
            if ($user['mobile'] == '' && $user['email'] == '')
                $this->ajaxReturn(['status'=>-1,'msg'=>'请先绑定手机或邮箱','url'=>U('/Mobile/User/index')]);
            $userLogic = new UsersLogic();
            $data = $userLogic->password($this->user_id, I('post.old_password'), I('post.new_password'), I('post.confirm_password'));
            if ($data['status'] == -1)
                $this->ajaxReturn(['status'=>-1,'msg'=>$data['msg']]);
            $this->ajaxReturn(['status'=>1,'msg'=>$data['msg'],'url'=>U('/Mobile/User/index')]);
            exit;
        }
        return $this->fetch();
    }

    function forget_pwd()
    {
        if ($this->user_id > 0) {
            $this->redirect("User/index");
        }
        $username = I('username');
        if (IS_POST) {
            if (!empty($username)) {
                if(!$this->verifyHandle('forget')){
                    $this->error("验证码错误");
                };
                $field = 'mobile';
                if (check_email($username)) {
                    $field = 'email';
                }
                $user = M('users')->where("email", $username)->whereOr('mobile', $username)->find();
                if ($user) {
                    session('find_password', array('user_id' => $user['user_id'], 'username' => $username,
                        'email' => $user['email'], 'mobile' => $user['mobile'], 'type' => $field));
                    header("Location: " . U('User/find_pwd'));
                    exit;
                } else {
                    $this->error("用户名不存在，请检查");
                }
            }
        }
        return $this->fetch();
    }

    function find_pwd()
    {
        if ($this->user_id > 0) {
            header("Location: " . U('User/index'));
        }
        $user = session('find_password');
        if (empty($user)) {
            $this->error("请先验证用户名", U('User/forget_pwd'));
        }
        $this->assign('user', $user);
        return $this->fetch();
    }


    public function set_pwd()
    {
        if ($this->user_id > 0) {
            $this->redirect('Mobile/User/index');
        }
        $check = session('validate_code');
        if (empty($check)) {
            header("Location:" . U('User/forget_pwd'));
        } elseif ($check['is_check'] == 0) {
            $this->error('验证码还未验证通过', U('User/forget_pwd'));
        }
        if (IS_POST) {
            $password = I('post.password');
            $password2 = I('post.password2');
            if ($password2 != $password) {
                $this->error('两次密码不一致', U('User/forget_pwd'));
            }
            if ($check['is_check'] == 1) {
                $user = M('users')->where("mobile", $check['sender'])->whereOr('email', $check['sender'])->find();
                M('users')->where("user_id", $user['user_id'])->save(array('password' => encrypt($password)));
                session('validate_code', null);
                return $this->fetch('reset_pwd_sucess');
                exit;
            } else {
                $this->error('验证码还未验证通过', U('User/forget_pwd'));
            }
        }
        $is_set = I('is_set', 0);
        $this->assign('is_set', $is_set);
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
            return false;
        }
        return true;
    }

    /**
     * 验证码获取
     */
    public function verify()
    {
        //验证码类型
        $type = I('get.type') ? I('get.type') : 'user_login';
        $config = array(
            'fontSize' => 30,
            'length' => 4,
            'imageH' =>  60,
            'imageW' =>  350,
            'fontttf' => '5.ttf',
            'useCurve' => false,
            'useNoise' => false,
            'codeSet' => '0123456789'
        );
        $Verify = new Verify($config);
        $Verify->entry($type);
        exit();
    }

    /**
     * 账户管理
     */
    public function accountManage()
    {
        return $this->fetch();
    }

    public function recharge()
    {
        $order_id = I('order_id/d');
        $paymentList = M('Plugin')->where("`type`='payment' and code!='cod' and status = 1 and  scene in(0,1)")->select();
        //微信浏览器
        if (strstr($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger')) {
            $paymentList = M('Plugin')->where("`type`='payment' and status = 1 and code='weixin'")->select();
        }
        $paymentList = convert_arr_key($paymentList, 'code');

        foreach ($paymentList as $key => $val) {
            $val['config_value'] = unserialize($val['config_value']);
            if ($val['config_value']['is_bank'] == 2) {
                $bankCodeList[$val['code']] = unserialize($val['bank_code']);
            }
        }
        $bank_img = include APP_PATH . 'home/bank.php'; // 银行对应图片
        $payment = M('Plugin')->where("`type`='payment' and status = 1")->select();
        $this->assign('paymentList', $paymentList);
        $this->assign('bank_img', $bank_img);
        $this->assign('bankCodeList', $bankCodeList);

        if ($order_id > 0) {
            $order = M('recharge')->where("order_id", $order_id)->find();
            $this->assign('order', $order);
        }
        return $this->fetch();
    }

    public function recharge_list(){
        $usersLogic = new UsersLogic;
        $result= $usersLogic->get_recharge_log($this->user_id);  //充值记录
        $this->assign('page', $result['show']);
        $this->assign('lists', $result['result']);
        if (I('is_ajax')) {
            return $this->fetch('ajax_recharge_list');
        }
        return $this->fetch();
    }

    /**
     * 申请提现记录
     */
    public function withdrawals()
    {
        C('TOKEN_ON', true);
        if (IS_POST) {
            if(!$this->verifyHandle('withdrawals')){
                $this->ajaxReturn(['status'=>0,'msg'=>'验证码错误']);
            };
            $data = I('post.');
            $data['user_id'] = $this->user_id;
            $data['create_time'] = time();
            $distribut_min = tpCache('basic.min'); // 最少提现额度
            $distribut_need = tpCache('basic.need'); // 满多少才能提现
            if($this->user['user_money'] < $distribut_need)
            {
                $this->ajaxReturn(['status'=>0,'msg'=>'账户余额最少达到'.$distribut_need.'多少才能提现']);
                exit;
            }
            if(encrypt($data['paypwd']) != $this->user['paypwd']){
                $this->ajaxReturn(['status'=>0,'msg'=>'支付密码错误']);
            }
            if ($data['money'] < $distribut_min) {
                $this->ajaxReturn(['status'=>0,'msg'=>'每次最少提现额度' . $distribut_min]);
            }
            if ($data['money'] > $this->user['user_money']) {
                $this->ajaxReturn(['status'=>0,'msg'=>"你最多可提现{$this->user['user_money']}账户余额."]);
            }
            $withdrawal = M('withdrawals')->where(array('user_id' => $this->user_id, 'status' => 0))->sum('money');
            if ($this->user['user_money'] < ($withdrawal + $data['money'])) {
                $this->ajaxReturn(['status'=>0,'msg'=>'您有提现申请待处理，本次提现余额不足']);
            }
            if (M('withdrawals')->add($data)) {
                $this->ajaxReturn(['status'=>1,'msg'=>"已提交申请",'url'=>U('User/withdrawals_list')]);
            } else {
                $this->ajaxReturn(['status'=>0,'msg'=>'提交失败,联系客服!']);
            }
        }
        $this->assign('user_money', $this->user['user_money']);    //用户余额
        return $this->fetch();
    }

    /**
     * 申请记录列表
     */
    public function withdrawals_list()
    {
        $withdrawals_where['user_id'] = $this->user_id;
        $count = M('withdrawals')->where($withdrawals_where)->count();
        $pagesize = C('PAGESIZE');
        $page = new Page($count, $pagesize);
        $list = M('withdrawals')->where($withdrawals_where)->order("id desc")->limit("{$page->firstRow},{$page->listRows}")->select();

        $this->assign('page', $page->show());// 赋值分页输出
        $this->assign('list', $list); // 下线
        if (I('is_ajax')) {
            return $this->fetch('ajax_withdrawals_list');
        }
        return $this->fetch();
    }

    /**
     * 我的关注
     * @author lxl
     * @time   2017/1
     */
    public function myfocus()
    {
        return $this->fetch();
    }

    /**
     *  用户消息通知
     * @author dyr
     * @time 2016/09/01
     */
    public function message_notice()
    {
        return $this->fetch();
    }

    /**
     * ajax用户消息通知请求
     * @author dyr
     * @time 2016/09/01
     */
    public function ajax_message_notice()
    {
        $type = I('type');
        $user_logic = new UsersLogic();
        $message_model = new MessageLogic();
        if ($type === '0') {
            //系统消息
            $user_sys_message = $message_model->getUserMessageNotice();
        } else if ($type == 1) {
            //活动消息：后续开发
            $user_sys_message = array();
        } else {
            //全部消息：后续完善
            $user_sys_message = $message_model->getUserMessageNotice();
        }
        $user_logic->setSysMessageForRead(); //将系统消息设为已读
        $this->assign('messages', $user_sys_message);
        return $this->fetch('ajax_message_notice');

    }

    /**
     * ajax用户消息通知请求
     */
    public function set_message_notice()
    {
        $type = I('type');
        $msg_id = I('msg_id');
        $user_logic = new UsersLogic();
        $res =$user_logic->setMessageForRead($type,$msg_id);
        $this->ajaxReturn($res);
    }

    /**
     * ajax消息假删除
     */
    public function del_message_notice(){
        $msg_id = I('msg_id');
        $type = input('type','');
        $user_logic = new UsersLogic();
        if ($msg_id) {
            $res =$user_logic->delUserMsg($this->user_id,$msg_id);
            $this->ajaxReturn($res);
        } else {
            if (in_array($type,['',0,1])) {//清除全部消息
                $res =$user_logic->delUserTypeMsg($this->user_id,$type);
                $this->ajaxReturn($res);
            }
        }
    }

    /**
     * 设置消息通知
     */
    public function set_notice(){
        //暂无数据
        return $this->fetch();
    }

    /**
     * 浏览记录
     */
    public function visit_log()
    {
        $count = M('goods_visit')->where('user_id', $this->user_id)->count();
        $Page = new Page($count, 20);
        $visit = M('goods_visit')->alias('v')
            ->field('v.visit_id, v.goods_id, v.visittime,g.original_img, g.goods_name, g.shop_price, g.cat_id')
            ->join('__GOODS__ g', 'v.goods_id=g.goods_id')
            ->where('v.user_id', $this->user_id)
            ->order('v.visittime desc')
            ->limit($Page->firstRow, $Page->listRows)
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

        $this->assign('visit_list', $visit_list);
        if (I('get.is_ajax', 0)) {
            return $this->fetch('ajax_visit_log');
        }
        return $this->fetch();
    }

    /**
     * 删除浏览记录
     */
    public function del_visit_log()
    {
        $visit_ids = I('get.visit_ids', 0);
        $row = M('goods_visit')->where('visit_id','IN', $visit_ids)->delete();

        if(!$row) {
            $this->error('操作失败',U('User/visit_log'));
        } else {
            $this->success("操作成功",U('User/visit_log'));
        }
    }

    /**
     * 清空浏览记录
     */
    public function clear_visit_log()
    {
        $row = M('goods_visit')->where('user_id', $this->user_id)->delete();

        if(!$row) {
            $this->error('操作失败',U('User/visit_log'));
        } else {
            $this->success("操作成功",U('User/visit_log'));
        }
    }

    /**
     * 支付密码
     * @return mixed
     */
    public function paypwd()
    {
        $referer = input('get.referer/s','');
        //检查是否第三方登录用户
        $user = M('users')->where('user_id', $this->user_id)->find();
        if ($user['mobile'] == '')
            $this->error('请先绑定手机号',U('User/setMobile',['source'=>'paypwd']));
        $step = I('step', 1);
        if (!config('APP_DEBUG')) {
            if ($step > 1) {
                $check = session('validate_code');
                if (empty($check)) {
                    $this->error('验证码还未验证通过', U('mobile/User/paypwd'));
                }
            }
        }
        if (IS_POST && $step == 2) {
            $new_password = trim(I('new_password'));
            $confirm_password = trim(I('confirm_password'));
            if (strlen($new_password) >6 || strlen($confirm_password) >6 || !is_numeric($new_password) || !is_numeric($confirm_password)) {
                $this->ajaxReturn(['status'=>-1,'msg'=>'密码只能为六位数字','result'=>'']);
            }
            $oldpaypwd = trim(I('old_password'));
            //以前设置过就得验证原来密码
//            if(!empty($user['paypwd']) && ($user['paypwd'] != encrypt($oldpaypwd))){
//                $this->ajaxReturn(['status'=>-1,'msg'=>'原密码验证错误！','result'=>'']);
//            }
            $userLogic = new UsersLogic();
            $data = $userLogic->paypwd($this->user_id, $new_password, $confirm_password,$oldpaypwd);
            $this->ajaxReturn($data);
            exit;
        }
        $this->assign('referer', $referer);
        $this->assign('step', $step);
        $this->assign('mobile',$user['mobile']);
        $this->assign('user',$user);
        return $this->fetch();
    }


    /**
     * 会员签到积分奖励
     * 2017/9/28
     */
    public function sign()
    {
        $userLogic = new UsersLogic();
        $user_id = $this->user_id;
        $info = $userLogic->idenUserSign($user_id);//标识签到
        $this->assign('info', $info);
        return $this->fetch();
    }

    /**
     * Ajax会员签到
     * 2017/11/19
     */
    public function user_sign()
    {
        $userLogic = new UsersLogic();
        $user_id   = $this->user_id;
        $config    = tpCache('sign');
        $date      = I('date'); //2017-9-29
        //是否正确请求
        (date("Y-n-j", time()) != $date) && $this->ajaxReturn(['status' => false, 'msg' => '签到失败！', 'result' => '']);
        //签到开关
        if ($config['sign_on_off'] > 0) {
            $map['sign_last'] = $date;
            $map['user_id']   = $user_id;
            $userSingInfo     = Db::name('user_sign')->where($map)->find();
            //今天是否已签
            $userSingInfo && $this->ajaxReturn(['status' => false, 'msg' => '您今天已经签过啦！', 'result' => '']);
            //是否有过签到记录
            $checkSign = Db::name('user_sign')->where(['user_id' => $user_id])->find();
            if (!$checkSign) {
                $result = $userLogic->addUserSign($user_id, $date);            //第一次签到
            } else {
                $result = $userLogic->updateUserSign($checkSign, $date);       //累计签到
            }
            $return = ['status' => $result['status'], 'msg' => $result['msg'], 'result' => ''];
        } else {
            $return = ['status' => false, 'msg' => '该功能未开启！', 'result' => ''];
        }
        $this->ajaxReturn($return);
    }


    /**
     * vip充值
     */
    public function rechargevip(){
        $paymentList = M('Plugin')->where("`type`='payment' and code!='cod' and status = 1 and  scene in(0,1)")->select();
        //微信浏览器
        if (strstr($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger')) {
            $paymentList = M('Plugin')->where("`type`='payment' and status = 1 and code='weixin'")->select();
        }
        $paymentList = convert_arr_key($paymentList, 'code');

        foreach ($paymentList as $key => $val) {
            $val['config_value'] = unserialize($val['config_value']);
            if ($val['config_value']['is_bank'] == 2) {
                $bankCodeList[$val['code']] = unserialize($val['bank_code']);
            }
        }
        $bank_img = include APP_PATH . 'home/bank.php'; // 银行对应图片
        $payment = M('Plugin')->where("`type`='payment' and status = 1")->select();
        $this->assign('paymentList', $paymentList);
        $this->assign('bank_img', $bank_img);
        $this->assign('bankCodeList', $bankCodeList);
        return $this->fetch();
    }



    //修改用户手机号
//    public function chargeUserMobile()
//    {
//        if (IS_POST) {
//            $mobile = I('post.mobile');
//            $code = I('post.code');
//            $scene = I('post.scene', 1);
//
//            $session_id = session_id();
//            $reg_sms_enable = tpCache('sms.regis_sms_enable');
//
//            //是否开启注册验证码机制
//            if(check_mobile($mobile)){
//                if($reg_sms_enable){
//                    //手机功能没关闭
//                    $check_code = (new UsersLogic())->check_validate_code($code, $mobile, 'phone', $session_id, $scene);
//                    if($check_code['status'] != 1){
//                        $this->ajaxReturn($check_code);
//                    }
//                }
//            }
//
//            $result = Db::name('users')
//                ->where('user_id','=',$this->user_id)
//                ->update([
//                    'mobile' => $mobile,
//                ]);
//
//            if ($result) {
//                return outPut(1,'修改成功');
//            }
//
//            return outPut(-1,'修改失败，请联系客服');
//        }
//
//        return $this->fetch('charge_user_mobile');
//    }

    //获取分享连接中的distribute_parent_id绑定上下级关系，并生成cookie
    public function getDistributeParentId()
    {
        $distributeParentId = I('get.distribute_parent_id',0);
        if ($distributeParentId > 0) {
            Cookie::set('distribute_parent_id',$distributeParentId);
        }
    }

    public function showUserReg()
    {
        return $this->fetch('show_user_reg');
    }

    //秒杀提醒我
    public function flashRemindMe()
    {
        $timeSpaceArr = (new Activity())->getFlashTimeSpace();
        $startTime = current($timeSpaceArr)['start_time'];

        $where1 = [
            'user_id' => $this->user_id,
            'flash_start_time' => ['>=',$startTime],
            'status' => ['in','0,1']
        ];

        //提醒数据
        $flashRemindDatas = (new FlashRemindModel())
            ->where($where1)
            ->order('flash_start_time,add_time')
            ->select();

        $flashGoodIdArr = array_column(collection($flashRemindDatas)->toArray(),'goods_id');

        //秒杀商品信息
        $where2 = array(
            'fl.goods_id'=>array('in',$flashGoodIdArr),
            'g.is_on_sale'=>1
        );
        $FlashSale = new FlashSale();
        $flash_sale_goods = $FlashSale->alias('fl')
            ->join('__GOODS__ g', 'g.goods_id = fl.goods_id')->with(['specGoodsPrice','goods'])
            ->field('*,100*(FORMAT(buy_num/goods_num,2)) as percent')
            ->where($where2)
            ->select();

        $flash_sale_goods = collection($flash_sale_goods)->toArray();
        $flash_sale_goods = convert_arr_key($flash_sale_goods,'goods_id');

        $data = [];

        foreach ($flashRemindDatas as $v) {
            $v->goods_info = $flash_sale_goods[$v->goods_id];
            $data[$v->flash_start_time][] = $v->toArray();
        }

        $data = collection($data)->toArray();

        $this->assign('flash_remind_datas',$data);

        return $this->fetch();
    }

    //用户选择登陆注册页面
    public function login(){
        $this->setRefer();
        if ($this->user_id > 0) {
            $this->redirect(U('Mobile/User/index'));
        }
        return $this->fetch();
    }




/*****************************   美年大健康个人中心--赵磊   ******************************/

    /*
     * 卡券订单列表
     * Author:赵磊
     * */
    public function getVirtualOrdeList()
    {
        $Order = new \app\common\model\Order();
        $goodsInfo = $Order->VirtualOrderInfo($this->user_id);

        $count = count($goodsInfo);//总条数
        $page = new Page($count, 12);
        $goodsInfo = array_slice($goodsInfo,$page->firstRow,12);
        $this->assign('info',$goodsInfo);
        $this->assign('count',$count);// 总条数
        if(input('is_ajax')){
            return $this->fetch('ajax_getVirtualOrdeList'); //获取更多
        }
        return $this->fetch();
    }



    /*
    * 卡券订单详情
    * Author:赵磊
    * */
    public function getVirtualOrderInfo(VrOrderCode $vrOrder)
    {
        $orderId = I('order_id');
        //根据order_id 查的虚拟商品virtual_form_id, 1为美年大健康
        $Good = new OrderGoods();
        $formId = $Good
            ->alias('a')
            ->join('tp_goods b','a.goods_id = b.goods_id')
            ->field('b.virtual_form_id')
            ->where('a.order_id',$orderId)
            ->find();

        if ($formId->virtual_form_id == 1){ // 美年大健康优惠券
            $vrOrder->outTime($orderId);
            $Order = new \app\common\model\Order();
            $info = $Order->VirtualOrderMainInfo($orderId);
            if ($info['goods_num']==1){
                $dataId = Db::table('cf_form_data')
                    ->field('id')->where('order_id',$orderId)->find();
            }//优惠券只购买一张即只有一个体检人
            if ($vrInfo = '') $this->error('虚拟订单不存在');//虚拟订单不存在
            $this->assign('info',$info);//详情信息
            $this->assign('fillIn',$vrOrder->fillIn($orderId));//是否填写完成
            $this->assign('vrInfo',$info['vrInfo']);//虚拟订单中兑换码及消费状态
            $this->assign('dataId',$dataId['id']);//只有一个体检人时的dataid
            return  $this->fetch();
        }

    }

    /*
    * 体检人列表
    * Author:赵磊
    * */
    public function getVirtualFormList()
    {
        $orderId = I('order_id');
        $vrOrder = new VrOrderCode();
        $fields = 'id,user_id,order_id,show_order,content,form_id';
        $testInfo = Db::table('cf_form_data')
            ->field($fields)
            ->where('order_id',$orderId)
            ->order('show_order desc')
            ->select();

        $testNum = $vrOrder->where("order_id = $orderId and refund_lock = 0")->count();//体检人人数
        $tested = count($testInfo);//已填写体检人数
        for ($i=0;$i<$tested;$i++){
            $testInfo[$i]['content'] = json_decode($testInfo[$i]['content']); //获取体检人信息
        }

        //分页请求
        $page = new Page($testNum, 12);
        $testInfo = array_slice($testInfo,$page->firstRow,12);
        $this->assign('testNum',$testNum);//订单可添加的体检人数
        $this->assign('tested',$tested);//已体检人数
        $this->assign('need',$testNum-$tested);//还差体检人数
        $this->assign('orderId',$orderId);//订单id
        $this->assign('testInfo',$testInfo);

        if ($testInfo[0]['form_id']=='') $testInfo[0]['form_id']=1;
        $templateInfo = Db::table(cf_form_template)
            ->field('form_alias,contact_tel')
            ->where('form_id',$testInfo[0]['form_id'])
            ->find();//表单模板信息
        $this->assign('form_alias',$templateInfo['form_alias']); //虚拟订单自定义表单标题
        $this->assign('contact_tel',$templateInfo['contact_tel']); //客服电话
        if(input('is_ajax')){
            return $this->fetch('ajax_testUserList'); //获取更多
        }

        return $this->fetch();
    }

    /*
    * 体检人信息
    * Author:赵磊
    * */
    public function getVirtualFormInfo()
    {
        $dataId= I('id');//cf_form_data自增id
        $userId = $this->user_id;
        $info = Db::table('cf_form_data')
            ->field('content,form_id')
            ->where("id=$dataId and user_id=$userId")
            ->find();
        $templateInfo = Db::table(cf_form_template)
            ->field('form_alias,contact_tel')
            ->where('form_id',$info['form_id'])
            ->find();//表单模板信息
        $info = json_decode($info['content']);
        $this->assign('info',$info);
        $this->assign('form_alias',$templateInfo['form_alias']); //虚拟订单自定义表单标题
        $this->assign('contact_tel',$templateInfo['contact_tel']); //客服电话
        return $this->fetch();
    }





/*****************************   美年大健康个人中心--end    ******************************/


/*****************************   邀请有礼--start    ******************************/
    /*
     * @Author:赵磊
     * 2.3.2 邀请有礼主页面
     * */
    public function inviteGift()
    {
        $userModel = new Users();
        $roll = Db::query("select * from cf_friend_register WHERE parent_id <> 0 order BY be_user_start DESC limit 0,20");
        for ($i=0;$i<count($roll);$i++){
            $roll[$i]['parent_user'] = $userModel->getHeadpic($roll[$i]['parent_id'])['nickname']; // 获取上级用户信息
            $roll[$i]['head_pic'] = $userModel->getHeadpic($roll[$i]['parent_id'])['head_pic']; // 获取上级用户信息
            if (is_mobile($roll[$i]['parent_user'])) $roll[$i]['user'] = phoneToStar($roll[$i]['parent_user']);//昵称为手机号隐藏中间四位
        }
        $user = (new Users())->getHeadpic($this->user_id);//当前用户信息
        $inviteCode = Db::table('cf_user_user')->field('invite_friend_code')->where('user_id',$this->user_id)->find();
        $host = $_SERVER['HTTP_HOST'];//当前域名
        $inviteCode = $inviteCode['invite_friend_code'];//邀请吗
        //根据邀请码获取邀请用户信息
        $invitUser = (new UserUserModel())
            ->field('user_id')
            ->where('invite_friend_code',$inviteCode)
            ->find();
        $invitUser = $invitUser->user_id;
        $QRCodeUrl = "http://$host/mobile/user/beInvited/distribute_parent_id/$invitUser/invite_code/$inviteCode";//邀请码跳转地址->被邀请页面

        //注册信息
        $register = $this->registerSuccess($this->user_id);
        $this->assign('register_count',count($register));//注册人数
        //下首单信息
        $firstOrder = $this->firstOrder($this->user_id);
        $this->assign('firstOrder_count',count($firstOrder));//已下单人数
        //获得奖励金额
        $reward = $this->inviteCoupon($this->user_id);
        if (!empty($reward['result'])) $reward = array_column($reward['result'],'money');
        $this->assign('rewardNum',array_sum($reward));//kaquan总额

        $this->assign('roll',$roll);//滚动条信息
        $this->assign('inviteCode',$inviteCode);//邀请码
        $this->assign('QRCodeUrl',$QRCodeUrl);//二维码
        $this->assign('head_pic',$user['head_pic']);//用户头像
        return $this->fetch();
    }


    /*
     * @Author:赵磊
     * 2.3.2 我邀请的好友
     * */
    public function invitedFriends()
    {
        $type = I('type',1);//1注册成功;2下单成功;3获得奖励
        if ($type == 1){
            $list = $this->registerSuccess($this->user_id);
        }elseif ($type == 2){
            $list = $this->firstOrder($this->user_id);
        }else{
            $res = $this->inviteCoupon($this->user_id);
            $list = $res['result'];
        }
        $count = count($list);
        $page = new AjaxPage($count,10);
        if (!empty($list))$list = array_slice($list,$page->firstRow,10);
        $this->assign('list',$list);
        $this->assign('type',$type);
        if (IS_AJAX){
            return $this->fetch('invitedFriends_ajax');
        }
        return $this->fetch();
    }


    /*
     * @Author:赵磊
     * 2.3.2 邀请有礼被邀请页772WHY
     * */
    public function beInvited()
    {
        $invite_code = I('invite_code');
        Cookie::set('invite_code',$invite_code);

        //根据邀请码获取邀请用户信息AHAUC8
        $invitUser = (new UserUserModel())
            ->alias('a')
            ->field('b.nickname,b.head_pic,b.user_id')
            ->join('users b','a.user_id=b.user_id')
            ->where('a.invite_friend_code',$invite_code)
            ->find();
        if (is_mobile($invitUser->nickname) && !empty($invitUser->nickname)) $invitUser->nickname = phoneToStar($invitUser->nickname);
        $data['invitUserName'] = $invitUser->nickname;
        $data['invitUserHeadPic'] = $invitUser->head_pic;
        //邀请好友精选商品
        $goods = (new GoodsTopic())->field('goods_id')->where('topic_id',tpCache('invite.invite_friend_goods_topic_id',[],'cf_config'))->find();
        if ($goods->goods_id) $goodsArr = explode(',',$goods->goods_id);//商品id数组
        $count = count($goodsArr);
        $page = new Page($count,10);
        $where['goods_id'] = ['in',$goodsArr];
        $goodsInfo = Db::table('tp_goods')
            ->distinct(true)
            ->field('original_img,goods_remark,goods_name,goods_id,shop_price,market_price')
            ->where($where)->limit($page->firstRow,$page->listRows)->select();
        dealGoodsPrice($goodsInfo);
        $data['goods'] = $goodsInfo;

        //是否关注公众号
        $ua = $_SERVER['HTTP_USER_AGENT'];
        $QRCodeUrl = (new DistributionDevideLogLogic())->getAgentQRCodeUrl($invitUser->user_id,1, 1);
        $head_pic = (new Users())->getHeadpic($invitUser->user_id)['head_pic'];

        $host = $_SERVER['HTTP_HOST'];//当前域名
        $invit = $invitUser->user_id;//邀请人id
        $shareUrl = "http://$host/mobile/user/beInvited/distribute_parent_id/$invit/invite_code/$invite_code";//邀请码跳转地址->被邀请页面
        $this->assign('QRCodeUrl',$QRCodeUrl);//公众号<img src="/index.php?m=Home&c=Index&a=qr_code&data={$QRCodeUrl}&head_pic={$head_pic}"/>
        $this->assign('shareUrl',$shareUrl);
        $this->assign('invite_code',$invite_code);
        $this->assign('head_pic',$head_pic);
        $this->assign('data',$data);
        if (IS_AJAX){
            return $this->fetch('beInvited_ajax');
        }
        if(strpos($ua,"MicroMessenger")===false){ //判断是否在微信浏览器打开
            //非微信,默认未关注
            $subscribe = 0;
        }else{
            if (Session::has('subscribe')){
                $subscribe = Session::get('subscribe');
            }else{
                $loginInfo = $this->GetOpenid();
                $subscribe = $loginInfo['subscribe'];//获取subscribe,1为已关注
                Session::set('subscribe',$subscribe);
            }

        }
        $this->assign('subscribe',$subscribe);//是否关注
        return $this->fetch();
    }

    //注册成功
    public function registerSuccess($userId)
    {
        $userModel = new Users();
        $register = Db::query("select * from cf_friend_register where parent_id= $userId order BY be_user_start DESC");
        for ($i=0;$i<count($register);$i++){
            $register[$i]['user'] = $userModel->getHeadpic($register[$i]['user_id']);
            if($register[$i]['first_order_time'] != 0) {//1注册,2下单
                $register[$i]['type'] = 2;
            }else{
                $register[$i]['type'] = 1;
            }
            if ($register[$i]['first_order_time'])$register[$i]['first_order_time'] = date('m-d H:i',$register[$i]['first_order_time']);
            if ($register[$i]['be_user_start'])$register[$i]['be_user_start'] = date('m-d H:i',$register[$i]['be_user_start']);
            if (is_mobile($register[$i]['user']['nickname']))$register[$i]['user']['nickname'] = phoneToStar($register[$i]['user']['nickname']);
        }
//        $firstOrder = $this->firstOrder($userId);
//        if (!empty($register) && !empty($firstOrder))$register = array_merge($register,$firstOrder);
//        halt($register);
        return $register;
    }

    //首单下单成功
    public function firstOrder($userId)
    {
        $userModel = new Users();
        $user = Db::query("select * from cf_friend_register where parent_id = $userId order BY first_order_time DESC");//邀请的用户
        $user = array_column($user,'user_id');
        if (!empty($user)){
            $user = implode(',',$user);
            $res = Db::query("select *  from cf_friend_register where user_id IN ($user) AND status =1");
            for ($i=0;$i<count($res);$i++){
                $res[$i]['user'] = $userModel->getHeadpic($res[$i]['user_id']);
                $res[$i]['type'] = 2;//1注册,2下单
                $res[$i]['be_user_start'] = date('m-d H:i',$res[$i]['be_user_start']);
                $res[$i]['first_order_time'] = date('m-d H:i',$res[$i]['first_order_time']);
                if (is_mobile($res[$i]['user']['nickname']))$res[$i]['user']['nickname'] = phoneToStar($res[$i]['user']['nickname']);
            }
        }
        if (!empty($res)){
            $firstOrder = array_column($res,'first_order_time');
            array_multisort($firstOrder,SORT_DESC,$res);
        }
        return $res;
    }

    //奖励优惠券
    public function inviteCoupon($userId)
    {
        //优惠券过期
        $condition['use_end_time'] = ['<',time()];//已过期
        $where['uid'] = $userId;
        $where['type'] = ['in',[6,8]];//邀请新用户注册和新用户首单的券
        $where['status'] = 0;//未使用
         Db::table('tp_coupon_list')->where($condition)->update(['status'=>2]);//改变优惠券状态为已过期
        //视图cf_friend_reward
        $coupon_list = Db::query("SELECT * FROM `cf_friend_reward` `a` INNER JOIN `tp_users` `b` ON `a`.`uid`=`b`.`user_id` WHERE  `a`.`uid` = $userId ORDER BY use_start_time DESC ");
        for ($i=0;$i<count($coupon_list);$i++){
            $coupon_list[$i]['use_start_time'] = date('Y.m.d',$coupon_list[$i]['use_start_time']);
            $coupon_list[$i]['use_end_time'] = date('Y.m.d',$coupon_list[$i]['use_end_time']);
            $coupon_list[$i]['money'] = intval($coupon_list[$i]['money']);
            $coupon_list[$i]['condition'] = $coupon_list[$i]['description'];
            $coupon_list[$i]['coupon_name'] = (new Coupon())->where('id',$coupon_list[$i]['cid'])->find()->name;
        }
        return [
            'total'=>count($coupon_list),
            'result'=>$coupon_list
        ];
    }





    /*
     * @Author:赵磊
     * 验证手机号是否注册
     * */
    public function checkPhone()
    {
        $username = I('post.send', '');
        if (!empty($this->user_id)) return $this->ajaxReturn(['data'=>-2,'msg'=>'当前已有用户登录,请退出登录重试']);
        $count = (new Users())->where('mobile',$username)->count();
//        if (empty($username)) return $this->ajaxReturn(['data'=>-1,'msg'=>'请输入手机号']);
        if ($count>0){
            return $this->ajaxReturn(['data'=>-1,'msg'=>'该手机号已领取']);
        }else{
            return $this->ajaxReturn(['data'=>1,'msg'=>'该手机号可以注册']);
        }
    }


/*****************************   邀请有礼--end      ******************************/
}
