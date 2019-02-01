<?php
/**
 * @Author: 陈静
 * @Date: 2018/07/19 08:51:48
 * @Description: 5分钟取消未支付的拼团订单，并退款回余额
 */

namespace app\task\controller;

use app\common\library\Logs;
use app\common\model\Order;
use app\common\model\Users;
use think\Db;
use think\Exception;

class CancleNoPayOrder
{
    public function cancle()
    {
        $orderModel = new Order();
        $where = [
            'pay_status' => 0,
            'order_status' => 0,
            'prom_type' => 6,
            'user_money' => ['>',0],
            'order_amount' => ['>',0],
            'add_time' => ['exp', ' < '.time().' - 300 ' ]
        ];
        $noPayTeamOrder = $orderModel->where($where)->select();

        try{
            foreach ($noPayTeamOrder as $v) {
                Db::startTrans();
                //1.先将订单设置未取消状态
                $res1 = $orderModel->where('order_id',$v->order_id)->update(['order_status' => 3]);
                //2.返回相应的余额
                $res2 = (new Users())->where('user_id',$v->user_id)->setInc('user_money',$v->user_money);
                //3.插入余额记录
                $accountLogData = [
                    'user_id' => $v->user_id,
                    'user_money' => $v->user_money,
                    'pay_points' => 0,
                    'change_time' => time(),
                    'desc' => '拼团订单取消',
                    'order_sn'=>$v->order_sn,
                    'order_id'=>$v->order_id,
                ];
                $res3 = Db::name('account_log')->insert($accountLogData);
                if ($res1 && $res2 && $res3) {
                    Db::commit();
                }else{
                    Logs::sentryLogs('拼团订单取消，返还余额失败',$accountLogData);
                    Db::rollback();
                }
            }
        }catch (Exception $e){
            Logs::sentryLogs('拼团订单取消，返还余额失败:'.$e->getMessage(),$accountLogData);
        }

    }
}