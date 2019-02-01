<?php
/**
 * @Author: 陈静
 * @Date: 2018/05/03 08:35:36
 * @Description:
 */

namespace app\task\controller;

use app\common\library\Logs;
use app\common\logic\distribution\DistributionDevideLogLogic;
use app\common\model\DistributeDivideLog;
use think\Controller;
use think\Db;
use think\Exception;
use think\Request;

class Distribute extends Controller
{
    public function __construct(Request $request = null)
    {
        parent::__construct($request);
        Logs::sentryLogs();
    }

    /**
     * @Author: 陈静
     * @Date: 2018/05/03 13:40:27
     * @Description: 自动分成和确认收货分成
     * @param string $order_id
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function autoDistribute($orderSn = '')
    {
        $auto_confirm_date = tpCache('shopping.auto_confirm_date');//后台设置多少天自动确认收货
        $auto_service_date = tpCache('shopping.auto_service_date');//后台设置多少天内可申请售后
        $auto_service_unix_time = $auto_service_date * 24 * 60 * 60;
        $auto_confirm_unix_time = $auto_confirm_date * 24 * 60 * 60;
        $divideLogModel = new DistributionDevideLogLogic();

        if (empty($orderSn)) {
            //至少7天后分成，因为7天后不可申请售后
            $where = [
                'is_divided' => 0,
                'add_time' => ['<=',  time() - $auto_service_unix_time]
            ];
            $distributArr = $divideLogModel->where($where)->select()->toArray();
            $orderSnDataArr = array_unique(array_column($distributArr,'order_sn'));

            //去除掉已经申请退货的订单分成
            $invalidOrderArr = [];
            foreach ($orderSnDataArr as $v){
                $order = M('order')->where(['order_sn'=>$v])->find();
                if ($order['pay_status'] == 1 && ( $order['order_status'] == 2 || $order['order_status'] == 4 ) ) {
                    //判断订单确认时间
                    if ((time() - $order['confirm_time']) < $auto_service_unix_time || empty($order['confirm_time'])) {
                        $invalidOrderArr[] = $v;
                    }
                }else{
                    //记录订单号，还没确认的订单号
                    $invalidOrderArr[] = $v;

                    //去除掉已经申请退货的订单分成
                    $invalidOrderArr = [];
                    foreach ($orderSnDataArr as $v){
                        $order = M('order')->where(['order_sn'=>$v])->find();
                        if ($order['pay_status'] == 1 && ( $order['order_status'] == 2 || $order['order_status'] == 4 ) ) {
                            //判断订单确认时间
                            if ((time() - $order['confirm_time']) < $auto_service_unix_time || empty($order['confirm_time'])) {
                                $invalidOrderArr[] = $v;
                            }
                        }else{
                            //记录订单号，还没确认的订单号
                            $invalidOrderArr[] = $v;
                            //未支付或者订单状态不是确认状态且添加时间大于了  （自动确认+ 可申请售后时间 + 15）天 就删除该比订单的分成
//                            if ($order['add_time'] < (time() - $auto_service_unix_time - $auto_confirm_unix_time - 15 * 24 * 3600) ) {
//                                try{
//                                    (new DistributeDivideLog())->cancleDivideData($order['order_sn']);
//                                }catch (Exception $e){
//                                    Logs::sentryLogs('删除过期分销订单失败:订单号：'.$order['order_sn'].''.$e->getMessage());
//                                }
//                            }
                        }
                    }
                }
            }

            foreach ($distributArr as $k => $v) {
                if (in_array($v['order_sn'],$invalidOrderArr)) {
                    unset($distributArr[$k]);
                }
            }
        }else{
            $distributArr = $divideLogModel
                ->where('order_sn',$orderSn)
                ->where('is_divided',0)
                ->select()->toArray();
        }

        if (empty($distributArr)) {
            return;
        }

        Db::startTrans();
        try{
            //1.给对应的用户增加金额
            $divideLogModel->divideMoneyToUser($distributArr);
            //2.改变订单状态
            $orderSnArr = array_unique(array_column($distributArr,'order_sn'));
            (new \app\common\model\Order())->where('order_sn','in',$orderSnArr)->update(['is_distribut' => 1]);
            //3.改变分成日志状态
            $distributLogIdArr = array_unique(array_column($distributArr,'id'));
            $res = $divideLogModel->where('id' , 'in' , $distributLogIdArr)->update([
                'is_divided' => 1,
                'divide_time' => time(),
                'divide_remarks'=> "确认收货后，满{$auto_service_date}天,程序自动分成."
            ]);

            if (!$res) {
                throw new Exception('改变分成日志状态失败');
            }
        }catch (Exception $e){
            Logs::sentryLogs($e,['msg'=>'分成失败']);
            Db::rollback();
            return;
        }
        Db::commit();
        return;
    }

    //自动确认收货
    public function autoTakeDeliveryOfGoods()
    {
        // 发货后满多少天自动收货确认
        $auto_confirm_date = tpCache('shopping.auto_confirm_date');
        $auto_confirm_date = $auto_confirm_date * (60 * 60 * 24); // 7天的时间戳
        $time = time() - $auto_confirm_date; // 比如7天以前的可用自动确认收货
        $order_id_arr = M('order')->where("order_status = 1 and shipping_status = 1 and shipping_time < $time")->getField('order_id',true);
        foreach($order_id_arr as $k => $v)
        {
            confirm_order($v);
        }
    }
}