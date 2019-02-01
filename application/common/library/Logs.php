<?php
/**
 * Created by PhpStorm.
 * User: baidu
 * Date: 17/8/6
 * Time: 上午2:45
 */

namespace app\common\library;

use think\Cookie;
use think\Log;

class Logs {

    //sentry日志收集器
    static public function sentryLogs($e = null,array $data = [])
    {
        if (config('app_debug')) {
            return;
        }

        Log::init([
            // 日志记录方式，内置 file socket 支持扩展
            'type'  => 'File',
            // 日志保存目录
            'path'  => LOG_PATH,
            // 日志记录级别
            'level' => ['error'],
            // 日志开关  1 开启 0 关闭
            'switch' => 1,
        ]);

        $sentryClient = new \Raven_Client(
            'http://52564908c075456283560cede76be4ff:e2c0f7a227c1475cbca9a4ffe23a2ad7@sentry.cfo2o.com/2',
            [
                'name' => \Raven_Compat::gethostname(),//服务器主机名
                'environment' => 'production',
                'level' => 'error',
                //附加数据
                'extra' => $data,
                'app_path' => ROOT_PATH,
                'sample_rate' => 1,//值0.00将拒绝发送任何事件，值1.00将发送100％的事件。
                'curl_method' => 'async',//curl异步发送，比同步体验好很多
            ]);

        $user_id = Cookie::get('user_id');
        //单独设置用户信息
        $sentryClient->user_context([
            'id' => $user_id ,
            'ip_address' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 0
        ]);

        if ( $e && is_object($e) ) {
            $sentryClient->captureException($e);
            $errorMsg = "\n文件:".$e->getFile()."\n行数:".$e->getLine()."\n错误代码:".$e->getCode()."\n错误信息:".$e->getMessage()."\n";
            Log::record($errorMsg);
        }elseif (is_string($e)){
            //当没有异常只想记录信息的时候可以使用这个
            $sentryClient->captureMessage($e);
            Log::record($e);
        }elseif(empty($e)){
            $error_handler = new \Raven_ErrorHandler($sentryClient);
            $error_handler->registerExceptionHandler();
            $error_handler->registerErrorHandler();
            $error_handler->registerShutdownFunction();
        }
    }
}