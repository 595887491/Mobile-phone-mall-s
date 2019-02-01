<?php
/**
 * @Author: 陈静
 * @Date: 2018/04/19 14:21:00
 * @Description:
 */

namespace app\common\model;


use think\Exception;
use think\Model;

class PayLogModel extends Model
{
    protected $table = 'cf_pay_log';
    protected $pk = 'id';
    protected $resultSetType = 'collection';

    //插入支付日志
    public function insertPayLog($notify_datas,$pay_code)
    {
        //查询一遍订单
        $orderInfo = (new Order())->where('order_sn',$notify_datas['out_trade_no'])->find();
        try{
            $this->insert([
                'pay_code' => $pay_code,
                'trade_num' => $notify_datas['out_trade_no'],
                'add_time' => time(),
                'total_money' => $orderInfo->order_amount + $orderInfo->user_money,
                'open_id' => $notify_datas['openid'],
                'pay_msg' => json_encode($notify_datas)
            ]);
        }catch (Exception $exception){
            //记录日志
            \app\common\library\Logs::sentryLogs($exception,['msg' => '插入支付日志失败']);
        }

    }

}