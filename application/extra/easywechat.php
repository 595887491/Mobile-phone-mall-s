<?php
/**
 * @Author: 陈静
 * @Date: 2018/04/01 17:20:11
 * @Description:
 */

switch ($GLOBALS['ENV']){
    //测试环境
    default:
    case 'LOCAL':
    case 'TEST':
        $config = [
            'app_id' => 'wx804b2d75a8074087',
            'secret' => '2ca19b2ccd3af06b942274f707849078',

            // 指定 API 调用返回结果的类型：array(default)/collection/object/raw/自定义类名
            'response_type' => 'array',

            'log' => [
                'level' => 'debug',
                'file' => './runtime/wechat.log',
            ],
        ];
        break;
}

return $config;