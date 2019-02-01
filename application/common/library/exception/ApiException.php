<?php
/**
 * @Author: 陈静
 * @Date: 2018/03/26 08:50:35
 * @Description: API异常接管
 */
namespace app\common\library\exception;

use think\Exception;

/**
 * 异常抛出类(所有错误异常都这个处理)
 * Class ApiException
 * @package app\wechat\common\lib\exception
 */
class ApiException extends Exception {

    public $message = '';
    public $httpCode = 200;
    public $code = 0;
    /**
     * @param string $message
     * @param int $httpCode
     * @param int $code
     */
    public function __construct( $code = 0 ,$message = '', $httpCode = 0) {
        $this->httpCode = $httpCode;
        $this->message = $message;
        $this->code = $code;
    }
}