<?php

/**
 * User: dyr
 * Date: 2017/11/24 0024
 * Time: 下午 3:00
 */

namespace app\common\behavior;
use app\common\library\Logs;
use app\common\library\Redis;
use app\common\logic\wechat\WechatUtil;
use app\common\logic\WechatLogic;
use think\Cache;
use think\Db;
class Order
{
    public function userAddOrder(&$order)
    {

        // 记录订单操作日志
        $action_info = array(
            'order_id'        =>$order['order_id'],
            'action_user'     =>0,
            'action_note'     => '您提交了订单，请等待系统确认',
            'status_desc'     =>'提交订单',
            'log_time'        =>time(),
        );
        Db::name('order_action')->add($action_info);

        //发送模板消息
        $res = (new WechatLogic())->sendTemplateMsgOnOrderSuccess($order);
        //添加到redis实现一小时未支付提醒
        Cache::set('place_order_time:'.$order['order_id'],$order['order_id'],5400);

        if ($res['status'] == -1) {
            Logs::sentryLogs($res['msg']);
        }
    }

}