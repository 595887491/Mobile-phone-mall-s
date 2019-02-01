<?php
/**
 * tpshop
 * ============================================================================
 * 版权所有 2015-2027 深圳搜豹网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.tp-shop.cn
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * Author: IT宇宙人
 * Date: 2015-09-09
 */
namespace app\common\model;
use app\common\logic\ActivityLogic;
use think\Db;
use think\Model;
class VrOrderCode extends Model
{

    const ORDER_STATUS = [
        0 => 0,//待确认
        1 => 1,//已确认
        2 => 2,//已收货
        3 => 3,//已取消
        4 => 4,//已完成
        5 => 5,//已作废
    ];

    /*
     * @Author : 赵磊
     * 根据虚拟订单id获取虚拟兑换码信息
     * */
    public function getVrInfo($orderId)
    {
        $result = M('vr_order_code')->where('order_id',$orderId)->select();
        return $result;
    }


    /*
     * @Author : 赵磊
     * 根据虚拟订单id获取列表页兑换码消费状态
     * */
    public function listConsumeStaus($orderId)
    {
        $need = M('vr_order_code')->where('order_id',$orderId)->count();//共有多少张兑换券
        $tested = M('vr_order_code')->where("order_id = $orderId and vr_state > 0")->count();//已使用多少张兑换券
        if ($need-$tested > 0 || $need == 0){
            $status = 0;//兑换码全消费完后列表页状态为已消费;   0为未消费;1为已消费
        }else{
            $status = 1;//已消费
            $this->collectGoods($orderId);
        }
        return $status;
    }


    /*
    * @Author : 赵磊
    * 根据虚拟订单id查看体检信息是否填写完
    * */
    public function fillIn($orderId)
    {
        $need = M('vr_order_code')->where('order_id',$orderId)->count();//共有多少张兑换券
        $filled = Db::table('cf_form_data')->where("order_id = $orderId")->count();//已填写多少人
        if ($need-$filled > 0 || $need == 0){
            $fillIn = 0;//体检人信息是否填写完;   0为未填满;1为已填满
        }else{
            $fillIn = 1;//已填满
        }
        return $fillIn;
    }



    /*
    * @Author : 赵磊
    * 虚拟商品兑换码超时过期状态变更
    * */
    public function outTime($orderId)
    {
        $now = time();
        $condition = "order_id = $orderId and vr_indate < $now";
        $res = $this->where($condition)->update(['vr_state'=>2]);
        return $res;
    }

    /*
    * @Author : 赵磊
    * 虚拟商品兑换码全部消费后更改定单状态为已收货
    * */
    public function collectGoods($orderId)
    {
        $model = new Order();
        $payStatus = $model->field('pay_status,order_status,user_id')->where('order_id',$orderId)->find();//判断是否已支付
        if (($payStatus->pay_status == 1 || $payStatus->pay_status == 2) && $payStatus->order_status < 2){
            $result = $model->where('order_id',$orderId)->update(['order_status'=>self::ORDER_STATUS[2]]);
            if ($result) (new ActivityLogic())->firstOrderCoupon($payStatus->user_id);//首单收货分发上级优惠券
        }
        return $result;
    }


}