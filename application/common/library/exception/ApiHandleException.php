<?php
/**
 * @Author: 陈静
 * @Date: 2018/03/26 08:50:35
 * @Description: API异常接管
 */

namespace app\common\library\exception;

use app\wechat\common\Logs;
use think\exception\Handle;

/**
 * 全局异常接管类,正式环境会把异常转换为json格式数据
 * Class ApiHandleException
 * @package app\wechat\common\lib\exception
 */
class ApiHandleException extends  Handle {

    /**
     * http 状态码
     * @var int
     */
    public $httpCode = 200;

    public function render(\Exception $e) {

        if(config('app_debug') == true && !($e instanceof ApiException)) {
            return parent::render($e);
        }

        if ($e instanceof ApiException) {
            $this->httpCode = $e->httpCode;
        }

        if (!($e instanceof ApiException) && config('app_debug') == false) {
            Logs::logRecoder($e);
        }
        return  outPut($e->getCode(), $e->getMessage(), [], $this->httpCode);
    }
}