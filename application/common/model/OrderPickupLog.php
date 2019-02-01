<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/9/27
 * Time: 13:39
 */

namespace app\common\model;


use think\Db;
use think\Model;

class OrderPickupLog extends Model
{
    protected $table='cf_order_pickup_log';

    /*
     * @Author:赵磊
     * 2018年9月27日13:40:48
     * 服务商验证自提码记录
     * */
    public function creatPickupLog($user,$code,$in_type,$status)
    {
        $data['verify_user_id'] = $user;
        $data['order_code'] = $code;
        $data['verify_time'] = time();
        $data['veriy_type'] = $in_type;
        $data['status'] = $status;
        Db::startTrans();
        $res = $this->add($data);
        if ($res){
            if ($status == 1){
                $result = Db::table('cf_users')->where('user_id',$user)->update(['order_pickup_verify_error'=>0]);//成功清空错误自提码记录
//                $order_id = (new OrderPickUp())->where('code',$code)->getField('order_id');
                $data['order_status'] = 2;//收货
                $data['shipping_status'] = 1;//发货
                $result = (new Order())->where('order_id',(new OrderPickUp())->where('code',$code)->getField('order_id'))
                    ->update($data);
            }else{
                $result = Db::table('cf_users')->where('user_id',$user)->setInc('order_pickup_verify_error');//失效自提码记录

            }
            if($result) Db::commit();
            Db::rollback();
        }else{
            Db::rollback();
        }
        return $result;
    }





}