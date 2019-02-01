<?php
/**
 * @Author: 陈静
 * @Date: 2018/04/20 22:57:11
 * @Description:
 */
return [
    'host' => '127.0.0.1',
    'port' => 6379,
    'index' => $GLOBALS['ENV'] == 'PRODUCT' ? 2 : 15,//正式环境用2，其他环境都用1
];