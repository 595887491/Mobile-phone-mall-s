<?php
/**
 * @Author: 陈静
 * @Date: 2018/05/08 20:24:43
 * @Description: 发送短信提醒
 */

namespace app\task\controller;

use app\common\library\Logs;
use app\common\logic\WechatLogic;
use app\common\model\Order;
use think\Cache;

class SendNoPayRemind
{
    public function sendMsg()
    {
        $redisObj = Cache::init()->handler();
        $placeOrderArr = $redisObj->getKeys('place_order_time:*');

        foreach ($placeOrderArr as $value){
            $expir = $redisObj->ttl($value);
            if ($expir < 1800) {
                $orderId = $redisObj->get($value);
                //查询订单状态
                $orderInfo = (new Order())->field('order_status,pay_status')->where('order_id',$orderId)->find();
                if ( $orderInfo->pay_status != 1 &&  ($orderInfo->order_status != 3 || $orderInfo->order_status != 5 ) ) {
                    $result = (new WechatLogic())->sendTemplateMsgOnNoPayOrder($orderId);
                    if ($result['status'] != 1) {
                        Logs::sentryLogs('发送提醒未支付订单失败:'.$result['msg']);
                    }
                }
                Cache::rm($value);
                sleep(1);
            }
        }
    }

}