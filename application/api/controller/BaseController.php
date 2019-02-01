<?php
/**
 * @Author: 陈静
 * @Date: 2018/04/17 10:11:12
 * @Description: API 基础控制类
 */

namespace app\api\controller;

use app\common\library\Redis;
use think\Cache;
use think\Controller;
use think\Cookie;
use think\Session;

class BaseController extends Controller
{
    /**
     * 检测数组中指定的属性是否存在,并返回
     */
    public static function checkArrKey($array = [], $field = [])
    {
        if (empty($array) || empty($field) || !is_array($array) || !is_array($field)) {
            return false;
        }

        if (array_diff($field,array_keys($array))) {
            return false;
        }
        //返回$field的数据
        return array_intersect_key($array,array_flip($field));
    }

    //获取post参数
    public function getPostParams($name= '', $default = '' ,$filter = 'strip_tags')
    {
        if($_SERVER['REQUEST_METHOD'] != 'POST'){
            errOutPut(301,'请求方式错误');
        }
        return  $this->request->post($name,$default,$filter);
    }

    //获取get参数
    public function getGetParams( $name= '', $default = '' ,$filter = 'strip_tags')
    {
        if($_SERVER['REQUEST_METHOD'] != 'GET'){
            errOutPut(301,'请求方式错误');
        }
        return  $this->request->get($name,$default,$filter);
    }

    //获取ip
    public function getIp(){
        $ip = '';
        if(isset($_SERVER['HTTP_X_FORWARDED_FOR'])){
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }elseif(isset($_SERVER['HTTP_CLIENT_IP'])){
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        }else{
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        $ip_arr = explode(',', $ip);
        return $ip_arr[0];
    }

    //验证token并返回相应值
    public function validateToken()
    {
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

        if ( $userId ) {
            return $userId;
        }
        return errOutPut(-2,'未登录');
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


}