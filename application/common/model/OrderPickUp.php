<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/9/26
 * Time: 13:19
 */

namespace app\common\model;


use app\common\logic\UsersLogic;
use think\Db;
use think\Model;
use think\Page;

class OrderPickUp extends Model
{
    protected $table = 'cf_order_pickup';

    /*
     * @Author:赵磊
     * 2018年9月26日13:57:40
     * 自提订单下单生成自提码
     * */
    public function addOrderPickUpCode($order_id)
    {
        $order_pickup_code_length = tpCache('order.order_pickup_code_length','','cf_config') ;  // 对应表cf_config中的 order_pickup_code_length
        $data['order_id'] = $order_id;//自提订单id
        $data['code'] = interval( generateOrderPickUpCode($order_pickup_code_length) ); //自提码
        $pickcode = urlencode(U('/Mobile/ordersPickUp/orderPickUpCode',['pick_up_code'=>$data['code']],'',true));
        $data['qrcode_url'] = '/index.php?m=Home&c=Index&a=qr_code&data='.urlencode($pickcode);
        $res = $this->add($data);
        return $res;
    }


    /*
     * @Author:赵磊
     * 2018年9月26日15:10:04
     * 自提码验证处的最近5笔订单 和全部订单
     * */
    public function getPickUpOrder($user_id,$type=1)
    {
        $condition['a.verify_user_id'] = $user_id;//服务方id
        $condition['a.status'] = 1;//0-未使用 1-已使用
        $count = $this
            ->alias('a')
            ->join(['tp_order'=>'b'],'a.order_id=b.order_id')
            ->join(['tp_users'=>'c'],'b.user_id=c.user_id')
            ->where($condition)
            ->count();
        $page = new Page($count,20);
        if ($type==1){ //只展示五条数据的
            $limit = 5;
        }else{//全部已验证的自提码
            $limit = "$page->firstRow,$page->listRows";
        }
        $list = $this
            ->alias('a')
            ->field('a.*,b.order_sn,b.order_id,b.total_amount,c.nickname,c.head_pic,c.mobile')
            ->join(['tp_order'=>'b'],'a.order_id=b.order_id')
            ->join(['tp_users'=>'c'],'b.user_id=c.user_id')
            ->where($condition)
            ->order('a.verify_time desc')
            ->limit($limit)
            ->select();
        $userLogic = new UsersLogic();
        foreach ($list as $k=>$v){
            $list[$k]['goods_info'] = $userLogic->get_order_goods($v['order_id']);//订单商品
            $v['verify_time'] = date('Y-m-d H:i:s',$v['verify_time']);//验证时间
            $count_goods_num = 0;
            foreach ($v['goods_info']['result'] as $kk=>$vv){
                $count_goods_num += $vv['goods_num'];
            }
            $v['count_goods_num'] = $count_goods_num;//订单商品数量
        }
        foreach ($list as $k=>$v){
            if (is_mobile($v['nickname'])) $v['nickname'] = phoneToStar($v['nickname']);
            $v['mobile'] = phoneToStar($v['mobile']);
        }
        return $list;
    }

    /*
     * @Author:赵磊
     * 2018年9月26日15:14:14
     * 验证成功订单信息
     * */
    public function getOrderUserInfo($order_id)
    {
        $info = (new Order())->alias('a')
            ->field('a.total_amount,b.nickname,b.mobile,b.user_id')
            ->join(['tp_users'=>'b'],'a.user_id=b.user_id')
            ->where('a.order_id',$order_id)
            ->find();
        $info['count_goods_num'] = (new OrderGoods())->where('order_id',$order_id)->count();

        $address = get_user_address_list($info['user_id']);

        if ($address) {
            $addressInfo = getTotalAddress($address[0]['province'],$address[0]['city'],$address[0]['district'],$address[0]['twon'],$address[0]['address']);
        }else {
            $addressInfo = '未填写';
        }
        $info['address'] = $addressInfo;
        return $info;
    }




}