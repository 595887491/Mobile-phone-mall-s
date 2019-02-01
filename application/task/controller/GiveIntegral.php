<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/6/11
 * Time: 15:09
 */

namespace app\task\controller;


use think\Controller;
use think\Db;

class GiveIntegral extends Controller
{
    public function giveOrder(){
        $daysAgo7S = strtotime(date('Y-m-d',strtotime('-7 day')));
        $daysAgo7E = $daysAgo7S + 86399;
        $orderGive = Db::name('order o')
            ->join('order_goods g','o.order_id=g.order_id','left')
            ->field('o.user_id,o.order_id,o.order_sn,o.order_amount,o.user_money,sum(if(is_send=3,(g.final_price * g.goods_num),0)) as return_money,o.shipping_price')
            ->group('g.order_id')
            ->where('o.confirm_time','between',[$daysAgo7S,$daysAgo7E])
            ->where(['o.order_status'=>['in',[1,2,4]],'o.shipping_status'=>['in',[1,2]],'pay_status'=>1])
            ->select();
        foreach ($orderGive as $value){
            $pay_point = floor($value['order_amount'] + $value['user_money'] + $value['shipping_price'] - $value['return_money']);
            if ($pay_point <= 0) continue;
            accountLog($value['user_id'], 0, $pay_point, "下单赠送积分", 0, $value['order_id'], $value['order_sn']);
        }
    }
}