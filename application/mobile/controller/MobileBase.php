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
 * $Author: IT宇宙人 2016-08-10 $
 */
namespace app\mobile\controller;

use app\common\library\Redis;
use app\common\logic\CartLogic;
use app\common\logic\OrderLogic;
use app\common\model\Users;
use think\Cache;
use think\Config;
use think\Controller;
use app\common\logic\wechat\WechatUtil;
use think\Cookie;
use think\Db;
use think\Exception;
use think\Session;

class MobileBase extends Controller {
    public $session_id;
    public $weixin_config;
    public $cateTrre = array();
    public $user;
    public $user_id;
    public $token = '';
    public $tokenName = '';
    public $clientType = [];

    /*
     * 初始化操作
     */
    public function _initialize() {
        header("Cache-control: private");  // history.back返回后输入框值丢失问题 参考文章 http://www.tp-shop.cn/article_id_1465.html  http://blog.csdn.net/qinchaoguang123456/article/details/29852881
        $this->session_id = session_id(); // 当前的 session_id
        define('SESSION_ID',$this->session_id); //将当前的session_id保存为常量，供其它方法调用

        $this->clientType = $this->judgeClientType();
        $deviceType = $this->clientType['device_type'];
        if ($this->clientType['device_type'] == 0 || $this->clientType['device_type'] == 1 ) {
            $deviceType = 100;
        }

        $this->tokenName = 'token_'.$deviceType.'_'.$this->clientType['app_type'];
        $this->token = Cookie::get($this->tokenName);

        //微信浏览器
        if(is_weixin()){
            $this->weixin_config = M('wx_user')->find(); //取微获信配置
            $this->assign('wechat_config', $this->weixin_config);
        }

        $this->public_assign();

        //分销商的一些操作
        $this->distributeSetting();

        \app\common\library\Logs::sentryLogs();
    }

    //获取分享连接中的distribute_parent_id绑定上下级关系，并生成cookie
    public function distributeSetting()
    {
        $distributeParentId = I('get.distribute_parent_id',0);
        if ($distributeParentId > 0) {
            Cookie::set('distribute_parent_id',$distributeParentId.'|'.time());
        }
    }


    /**
     * @Author: 陈静
     * @Date: 2018/03/29 14:36:30
     * @Description: 生成更新令牌并缓存
     * @param string $keyName 缓存的key
     * @param string $keyValue 缓存的value
     * @param int $expire
     * @return string
     */
    public function generateToken($keyName = '',$keyValue = '',$expire = 7200 )
    {
        $randChar = getRandChar(32);
        $timestamp = $_SERVER['REQUEST_TIME_FLOAT'];
        $tokenSalt = 'shangmeibinfen';
        $tokenkey = md5($randChar . $timestamp . $tokenSalt);
        Cookie::set($keyName,$tokenkey,['expire' => 360 * 24 * 3600]);
        //缓存到cache
        Redis::instance(config('redis'))->set($keyName.':'.$tokenkey,$keyValue['user_id'] , $expire);
        $this->token = $tokenkey;
        return $tokenkey;
    }

    //用户退出
    public function logout()
    {
        //删除token
        Redis::instance(config('redis'))->delete($this->tokenName.':'.$this->token);
        $token = Session::get('token');
        Redis::instance(config('redis'))->delete('token:'.$token);
        Db::table('cf_user_login')->where('token',$this->token)->update([
            'token_status' => 1 ,
            'logout_time' => time() ,
            'logout_status' => 0 ,
        ]);
        session_unset();
        session_destroy();
        setcookie('uname','',time()-3600,'/');
        setcookie('cn','',time()-3600,'/');
        setcookie('user_id','',time()-3600,'/');
        setcookie('PHPSESSID','',time()-3600,'/');
        setcookie('refer_url','',time()-3600,'/');
        setcookie('mobile','',time()-3600,'/');
        Cookie::delete($this->tokenName);
        $this->redirect(U('Mobile/Index/index'));
        exit();
    }

    //检查用户登陆
    public function checkUserLogin($nologin = [])
    {
        //获得客户端cookie中的token
        $userId = Redis::instance(config('redis'))->get($this->tokenName.':'.$this->token);

        //兼容老版本
        if (empty($userId)) {
            $token = Session::get('token');
            $userSomeInfo = Redis::instance(config('redis'))->hGet('token:'.$token,['userInfo','lastLoginTime']);
            $userId = json_decode($userSomeInfo['userInfo'],true)['user_id'];
        }


        if ($userId) {
            $userInfo = (new Users())->getUserInfo($userId)->toArray();
            Session::set('user',$userInfo);
            $this->user_id = $userInfo['user_id'];
            Cookie::set('user_id',$this->user_id,['expire' => 365*24*3600]);
            $this->user = $userInfo;
        }else{
            setcookie('uname','',time()-3600,'/');
            setcookie('cn','',time()-3600,'/');
            setcookie('user_id','',time()-3600,'/');
            Session::delete('user');
        }

        if (!$this->user_id && !in_array(ACTION_NAME, $nologin)) {
            $this->setRefer();
            $this->redirect(U('Mobile/User/login'));
            exit;
        }
    }
    //设置refer——url
    public function setRefer(){
        if (!IS_AJAX) {
            if (isset($_SERVER['HTTP_REFERER']) && (strpos($_SERVER['HTTP_REFERER'],'login') === false && strpos($_SERVER['HTTP_REFERER'],'reg') === false)) {
                Cookie::set('refer_url', urlencode($_SERVER['HTTP_REFERER']) ,['expire'=>time()+120]);
            } else {
                if (strtolower(ACTION_NAME) != 'reg' && strtolower(ACTION_NAME) != 'login') {
                    $http = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https://' : 'http://';
                    $url = $_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'];
                    $regRefer = $http.$url;
                    Cookie::set('refer_url', urlencode($regRefer) ,['expire'=>time()+120]);
                }
            }
        }
    }
    //缓存登陆信息
    public function setLoginInfo($userInfo,$loginType = 1)
    {
        //查询当前用户所有信息
        $userInfo = (new Users())->getUserInfo($userInfo['user_id'])->toArray();

        if ($this->clientType['device_type'] == 0 || $this->clientType['device_type'] == 1 ) {
            $oldToken1 =  Db::table('cf_user_login')
                ->where([
                    'token_status' => 0 ,
                    'user_id' => $userInfo['user_id'],
                    'device_type' => 0 ,
                    'app_type' => $this->clientType['app_type'] ,
                ])
                ->getField('token');
            $res1 = Redis::instance(config('redis'))->delete('token_100_'.$this->clientType['app_type'].':'.$oldToken1);
            if ($res1) {
                Db::table('cf_user_login')->where('token',$oldToken1)->update([
                    'token_status' => 1 ,
                    'logout_time' => time() ,
                    'logout_status' => 1 ,
                ]);
            }
            $oldToken2 =  Db::table('cf_user_login')
                ->where([
                    'token_status' => 0 ,
                    'user_id' => $userInfo['user_id'],
                    'device_type' => 1 ,
                    'app_type' => $this->clientType['app_type'] ,
                ])
                ->getField('token');
            $res2 = Redis::instance(config('redis'))->delete('token_100_'.$this->clientType['app_type'].':'.$oldToken2);
            if ($res2) {
                Db::table('cf_user_login')->where('token',$oldToken2)->update([
                    'token_status' => 1 ,
                    'logout_time' => time() ,
                    'logout_status' => 1 ,
                ]);
            }
        }else{
            $oldToken =  Db::table('cf_user_login')
                ->where([
                    'token_status' => 0 ,
                    'user_id' => $userInfo['user_id'],
                    'device_type' => $this->clientType['device_type'] ,
                    'app_type' => $this->clientType['app_type'] ,
                ])
                ->getField('token');

            $res = Redis::instance(config('redis'))->delete($this->tokenName.':'.$oldToken);
            if ($res) {
                Db::table('cf_user_login')->where('token',$oldToken)->update([
                    'token_status' => 1 ,
                    'logout_time' => time() ,
                    'logout_status' => 1 ,
                ]);
            }
        }

        $token = $this->generateToken($this->tokenName, $userInfo ,0 );

        $userInfo['token'] = $token;
        session('user', $userInfo);
        setcookie('mobile', $userInfo['mobile'], time()+3600*24*180, '/');
        setcookie('user_id', $userInfo['user_id'], time()+3600*24*180, '/');

        //插入cf_user_login数据
        $userLoginData = [
            'user_id' => $userInfo['user_id'],
            'device_type' => $this->clientType['device_type'],
            'app_type' => $this->clientType['app_type'],
            'login_type' => $loginType,
            'token' => $token,
            'token_start' => time(),
            'token_status' => 0,
            'login_time' => time(),
        ];

        Db::table('cf_user_login')->insert($userLoginData);

        //登陆对用户购物车做操作
        $cartLogic = new CartLogic();
        $cartLogic->setUserId($userInfo['user_id']);
        $cartLogic->doUserLoginHandle();

        //登录后将超时未支付订单给取消掉
        $orderLogic = new OrderLogic();
        $orderLogic->setUserId($userInfo['user_id']);
        $orderLogic->abolishOrder();

        $this->user_id = $userInfo['user_id'];
        $this->user = $userInfo;

        $this->updateAfterLoginData($this->user_id);
    }

    public function updateAfterLoginData($user_id)
    {
        //更改最后登陆时间和ip,token
        try{
            (new Users())
                ->where('user_id','=',$user_id)
                ->update([
                    'last_login' => time(),'last_ip' => $_SERVER['REMOTE_ADDR'] ,'token' => $this->token
                ]);
        }catch (Exception $exception){
            $this->error('登陆出错，请联系客服');
            //记录日志
            \app\common\library\Logs::sentryLogs($exception,['msg' => '更新用户登陆数据失败']);
        }
    }

    //判断客户端类型
    protected function judgeClientType()
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'];
        //1.判断ios   Android
        $android = stripos($userAgent,'Android');
        $deviceType = config('common')['device_type']['other'];
        if ( $android !== false ) {
            $deviceType = config('common')['device_type']['android'];
        }
        $ios = stripos($userAgent,'iPhone');
        if ( $ios !== false) {
            $deviceType = config('common')['device_type']['ios'];
        }
        $ipad = stripos($userAgent,'iPad');
        if ( $ipad !== false) {
            $deviceType = config('common')['device_type']['ipad'];
        }

        //2.判断浏览器手机还是电脑
        $mobile = stripos($userAgent,'mobile');
        if ( !$mobile ) {
            $deviceType = config('common')['device_type']['pc'];
            $appType = config('common')['app_type']['pc'];
        }else{
            $appType = config('common')['app_type']['wap'];
        }

        //3.判断是否是微信
        if (is_weixin()) {
            $appType = config('common')['app_type']['weixin'];
        }

        return [
            'device_type' => $deviceType,
            'app_type' => $appType,
        ];
    }

    /**
     * 保存公告变量到 smarty中 比如 导航
     */
    public function public_assign()
    {
        $first_login = session('first_login');
        $this->assign('first_login', $first_login);
        if (!$first_login && ACTION_NAME == 'login') {
            session('first_login', 1);
        }

        $tpshop_config = array();
        $tp_config = M('config')->cache(true,TPSHOP_CACHE_TIME)->select();
        foreach($tp_config as $k => $v)
        {
            if($v['name'] == 'hot_keywords'){
                $tpshop_config['hot_keywords'] = explode('|', $v['value']);
            }
            $tpshop_config[$v['inc_type'].'_'.$v['name']] = $v['value'];
        }

        //分类赋值
        $goods_category_tree = get_goods_category_tree();
        $this->cateTrre = $goods_category_tree;
        $this->assign('goods_category_tree', $goods_category_tree);
        $brand_list = M('brand')->cache(true,TPSHOP_CACHE_TIME)->field('id,cat_id,logo,is_hot')->where("cat_id>0")->select();
        $this->assign('brand_list', $brand_list);
        $this->assign('tpshop_config', $tpshop_config);
        /** 修复首次进入微商城不显示用户昵称问题 **/
        $user_id = cookie('user_id');
        $uname = cookie('uname');
        if(empty($user_id) && ($users = session('user')) ){
            $user_id = $users['user_id'];
            $uname = $users['nickname'];
        }
        $this->assign('user_id',$user_id);
        $this->assign('uname',$uname);

        //添加微信浏览器标识
        $this->assign('isWechat',is_weixin());
        // 判断当前用户是否手机
        setcookie('is_mobile',isMobile() ? 1 : 0, time()+3600*24*180, '/');
        if ( $user_id ) {
            $msgNum = D('user_message')->where([
                'user_id' => $user_id,
                'status' => 0
            ])->count();
            $this->assign('msg_num',$msgNum);
            //是否显示完善资料（购买过订单且支付后，show_complete_info为1的时候显示）
            $orderCount = (new \app\common\model\Order())
                ->where('user_id',$user_id)
                ->where('pay_status',1)
                ->count();
            $userShowComplete = (new Users())->where('user_id',$user_id)->getField('show_complete_info');
            if ($orderCount && $userShowComplete) {
                $show_complete_userinfo = 1;
            }else{
                $show_complete_userinfo = 0;
            }

            $this->assign('show_complete_userinfo', $show_complete_userinfo);
        }else{
            $this->assign('msg_num',0);
        }

        //订单变量
        $order_status_coment = array(
            'WAITPAY' => '待付款 ', //订单查询状态 待支付
            'WAITSEND' => '待发货', //订单查询状态 待发货
            'WAITRECEIVE' => '待收货', //订单查询状态 待收货
            'WAITCCOMMENT' => '待评价', //订单查询状态 待评价
        );

        $this->assign('order_status_coment', $order_status_coment);

        $this->assign('web_version','2.3.1');

    }

    // 网页授权登录获取 OpendId
    public function GetOpenid($type = 'snsapi_base')
    {
        //通过code获得openid
        if (!isset($_GET['code'])){
            //触发微信返回code码
            $baseUrl = urlencode($this->get_url());
            $url = $this->__CreateOauthUrlForCode($baseUrl,$type); // 获取 code地址
            Header("Location: $url"); // 跳转到微信授权页面 需要用户确认登录的页面
            exit();
        } else {
            //上面获取到code后这里跳转回来
            $code = $_GET['code'];
            $data = $this->getOpenidFromMp($code);//获取网页授权access_token和用户openid
            if (isset($data['errcode']) && $data['errcode'] == 40163) {
                $sys_protocal = isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '443' ? 'https://' : 'http://';
                $url = $sys_protocal.(isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '').'/'.$_SERVER['PATH_INFO'];
                Header("Location: $url"); // 跳转到微信授权页面 需要用户确认登录的页面
                exit();
            }

            $_SESSION['openid'] = $data['openid'];

            $data2 = $this->GetUserInfo($data['access_token'],$data['openid']);//获取微信用户信息
            $data['nickname'] = empty($data2['nickname']) ? '' : trim($data2['nickname']);
            $data['sex'] = $data2['sex'];
            $data['head_pic'] = $data2['headimgurl'];
            $data['subscribe'] = $data2['subscribe'];
            $data['oauth_child'] = 'mp';
            $data['oauth'] = 'weixin';
            if(isset($data2['unionid'])){
                $data['unionid'] = $data2['unionid'];
            }
            $_SESSION['data'] =$data;
            return $data;
        }
    }

    /**
     * 获取当前的url 地址
     * @return type
     */
    private function get_url() {
        $sys_protocal = isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '443' ? 'https://' : 'http://';
        $php_self = $_SERVER['PHP_SELF'] ? $_SERVER['PHP_SELF'] : $_SERVER['SCRIPT_NAME'];
        $path_info = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
        $relate_url = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : $php_self.(isset($_SERVER['QUERY_STRING']) ? '?'.$_SERVER['QUERY_STRING'] : $path_info);
        return $sys_protocal.(isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '').$relate_url;
    }

    /**
     *
     * 通过code从工作平台获取openid机器access_token
     * @param string $code 微信跳转回来带上的code
     *
     * @return openid
     */
    public function GetOpenidFromMp($code)
    {
        //通过code获取网页授权access_token 和 openid 。网页授权access_token是一次性的，而基础支持的access_token的是有时间限制的：7200s。
        //1、微信网页授权是通过OAuth2.0机制实现的，在用户授权给公众号后，公众号可以获取到一个网页授权特有的接口调用凭证（网页授权access_token），通过网页授权access_token可以进行授权后接口调用，如获取用户基本信息；
        //2、其他微信接口，需要通过基础支持中的“获取access_token”接口来获取到的普通access_token调用。
        $url = $this->__CreateOauthUrlForOpenid($code);
        $ch = curl_init();//初始化curl        
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);//设置超时
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,FALSE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $res = curl_exec($ch);//运行curl，结果以jason形式返回            
        $data = json_decode($res,true);
        curl_close($ch);
        return $data;
    }


    /**
     *
     * 通过access_token openid 从工作平台获取UserInfo
     * @return openid
     */
    public function GetUserInfo($access_token,$openid)
    {
        // 获取用户 信息
        $url = $this->__CreateOauthUrlForUserinfo($access_token,$openid);
        $ch = curl_init();//初始化curl        
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);//设置超时
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,FALSE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $res = curl_exec($ch);//运行curl，结果以jason形式返回            
        $data = json_decode($res,true);
        curl_close($ch);
        //获取用户是否关注了微信公众号， 再来判断是否提示用户 关注
        $wechat = new WechatUtil($this->weixin_config);
        $fan = $wechat->getFanInfo($openid);//获取基础支持的access_token

        if ($fan !== false && $fan['subscribe'] == 1) {
            $data = $fan;
        }
        return $data;
    }

    /**
     *
     * 构造获取code的url连接
     * @param string $redirectUrl 微信服务器回跳的url，需要url编码
     * @return 返回构造好的url
     */
    private function __CreateOauthUrlForCode($redirectUrl,$type = 'snsapi_userinfo')
    {
        $urlObj["appid"] = $this->weixin_config['appid'];
        $urlObj["redirect_uri"] = "$redirectUrl";
        $urlObj["response_type"] = "code";
        $urlObj["scope"] = $type;
        $urlObj["state"] = "STATE"."#wechat_redirect";
        $bizString = $this->ToUrlParams($urlObj);
        return "https://open.weixin.qq.com/connect/oauth2/authorize?".$bizString;
    }

    /**
     *
     * 构造获取open和access_toke的url地址
     * @param string $code，微信跳转带回的code
     *
     * @return 请求的url
     */
    private function __CreateOauthUrlForOpenid($code)
    {
        $urlObj["appid"] = $this->weixin_config['appid'];
        $urlObj["secret"] = $this->weixin_config['appsecret'];
        $urlObj["code"] = $code;
        $urlObj["grant_type"] = "authorization_code";
        $bizString = $this->ToUrlParams($urlObj);
        return "https://api.weixin.qq.com/sns/oauth2/access_token?".$bizString;
    }

    /**
     *
     * 构造获取拉取用户信息(需scope为 snsapi_userinfo)的url地址
     * @return 请求的url
     */
    private function __CreateOauthUrlForUserinfo($access_token,$openid)
    {
        $urlObj["access_token"] = $access_token;
        $urlObj["openid"] = $openid;
        $urlObj["lang"] = 'zh_CN';
        $bizString = $this->ToUrlParams($urlObj);
        return "https://api.weixin.qq.com/sns/userinfo?".$bizString;
    }

    /**
     *
     * 拼接签名字符串
     * @param array $urlObj
     *
     * @return 返回已经拼接好的字符串
     */
    private function ToUrlParams($urlObj)
    {
        $buff = "";
        foreach ($urlObj as $k => $v)
        {
            if($k != "sign"){
                $buff .= $k . "=" . $v . "&";
            }
        }

        $buff = trim($buff, "&");
        return $buff;
    }
    public function ajaxReturn($data){
        exit(json_encode($data,JSON_UNESCAPED_UNICODE));
    }

}