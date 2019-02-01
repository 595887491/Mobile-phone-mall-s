<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/9/26
 * Time: 11:03
 */

namespace app\mobile\controller;


use app\common\logic\UsersLogic;
use app\common\model\OrderPickUp;
use app\common\model\OrderPickupLog;
use app\common\model\UserModel;
use think\Cookie;
use think\Db;
use think\Exception;

class OrdersPickUp extends MobileBase
{
    public function  __construct()
    {
        parent::__construct();

        $this->checkUserLogin([
        ]);

    }


    /*
     * @Author:赵磊
     * 2018年9月26日11:07:06
     * 自提码扫码验证*/
    public function  orderPickUpCode()
    {
        $condition['user_id'] = $this->user_id;
        $condition['status'] = 1;
//        $result = Db::table('cf_user_agent')->where($condition)->find();//是否是代理商
//        if (empty($result)) $this->redirect('OrdersPickUp/pickUpErr');//不是代理商跳到神秘地带
        //扫码
        $pickUpCode = str_replace('+',' ',I('pick_up_code'));
        $pickUpCode = strtoupper($pickUpCode);
        $check = (new UserModel())->errNumbers($this->user_id);//检验错误次数
        $this->assign('check',$check);
        $this->assign('pick_up_code',$pickUpCode);

        //验证成功的订单列表
        $list = (new OrderPickUp())->getPickUpOrder($this->user_id,1);
        $this->assign('list',$list);
        $this->assign('tel',tpCache('shop_info','','tp_config')['phone']);
        return $this->fetch();
    }

    /*
     * @Author:赵磊
     * 2018年9月26日16:19:53
     * 自提码验证
     * */
    public function verification()
    {
        $pick_up_code = I('pick_up_code');//接受到的自提吗
        $sure = I('sure','');//0,正常,1确认用户信息并发货
        $in_type = I('in_type',1);//输入类型,1,扫码;2输入
        if (empty($pick_up_code)) return json(['code'=>-300,'msg'=>'请输入自提码']);
        $OrderPickUp = new OrderPickUp();
        $pick = $OrderPickUp->where('code',$pick_up_code)->find();//自提码信息
        if (empty($pick)){//不存在
            (new OrderPickupLog())->creatPickupLog($this->user_id,$pick_up_code,$in_type,0);
            $check1 = (new UserModel())->errNumbers($this->user_id);//检验错误次数
            if ($check1 == 1){
                return json(['code'=>-600,'msg'=>'多次错误,请验证']);
            }
            if ($check1 == 2){
                return json(['code'=>-700,'msg'=>'重新验证身份']);
            }
            return json(['code'=>-400,'msg'=>'自提码不存在，请重新输入并验证']);
        }
        if ($pick->status == 1){//已使用
            //追加错误次数
            (new OrderPickupLog())->creatPickupLog($this->user_id,$pick_up_code,$in_type,0);
            $check2 = (new UserModel())->errNumbers($this->user_id);//检验错误次数
            if ($check2 == 1){
                return json(['code'=>-600,'msg'=>'多次错误,请输入验证码']);
            }
            if ($check2 == 2){
                return json(['code'=>-700,'msg'=>'重新验证身份']);
            }
            $useTime = date('Y-m-d H:i:s',$pick['verify_time']);//使用时间
            return json(['code'=>-500,'msg'=>'该自提码已使用','use_time'=>$useTime]);//已使用的自提码
        }

        //确认用户信息发货
        if ($sure == 1){
            $res = $this->usePickUpcode($pick_up_code);
            if ($res){
                (new OrderPickupLog())->creatPickupLog($this->user_id,$pick_up_code,$in_type,1);
                return json(['code'=>200,'msg'=>'发货成功']);
            }else{
                return json(['code'=>-200,'msg'=>'使用失败']);
            }
        }
        //自提码有效
        $info = (new OrderPickUp())->getOrderUserInfo($pick['order_id']);
        return json(['code'=>100,'msg'=>'该自提码可使用,确认用户信息发货','info'=>$info]);

    }





    /*
     * @Author:赵磊
     * 2018年9月26日17:07:57
     * 使用自提码*/
    protected function usePickUpcode($code)
    {
        $data['status'] = 1;//0未使用,1已使用
        $data['verify_time'] = time();//验证时间
        $data['verify_user_id'] = $this->user_id;//当前服务商id
        $res = (new OrderPickUp())->where('code',$code)->update($data);
        return $res;
    }



    /*
     * @Author:赵磊
     * 2018年9月26日15:04:38
     * 全部已验证订单
     * */
    public function pickUpedList()
    {
        //验证成功的订单
        $list = (new OrderPickUp())->getPickUpOrder($this->user_id,2);
        $this->assign('list',$list);
        if (IS_AJAX){
            return $this->fetch('pickUpedList_ajax');
        }
        return $this->fetch();
    }


    /*
     * @Author:赵磊
     * 2018年9月26日15:50:46
     * 神秘地带
     * */
    public function pickUpErr()
    {
        return $this->fetch();
    }

    /*
     * @Author:赵磊
     * 2018年9月27日10:02:18
     * 根据自提订单orderid获取自提码
     * */
    public function getPickUpCode($order_id='')
    {
        if (empty($order_id))  $order_id = I('order_id');
        $ship = (new \app\common\model\Order())->where('order_id',$order_id)->getField('shipping_code');
        if ($ship =='ZITI'){
            $pickupInfo = (new OrderPickUp())->where('order_id',$order_id)->find();
            if (IS_AJAX){
               if($pickupInfo){
                   $pickupInfo['verify_time'] = date('Y-m-d H:i:s',$pickupInfo['verify_time']);
                   return json(['pic_status'=>1,'res'=>$pickupInfo]);
               } else{
                   return json(['pic_status'=>-1,'msg'=>'版本更新前的订单暂无自提码']);
               }
            }
            if (empty($pickupInfo)){
//                $pickupInfo['error'] = '自提码出错';
                $pickupInfo = '';
            } else{
                $pickupInfo=$pickupInfo->toArray();
            }
            return $pickupInfo;
        }
    }


    /*
     * @Authro:赵磊
     * 2018年9月28日13:37:47
     * 身份验证
     * */
    public function checkPhone()
    {
        $mobile = I('mobile');//手机号
        if ($mobile != $this->user['mobile']) return json(['status'=>-1,'msg'=>'手机号与当前用户不匹配']);
        $reg_sms_enable = tpCache('sms.regis_sms_enable');
        $code = I('code');//验证码
        $session_id = session_id();
        $scene = I('post.scene', 1);
//        if (!config('APP_DEBUG')) {
            if(check_mobile($mobile)){
                if($reg_sms_enable){
                    //手机功能没关闭
                    $check_code = (new UsersLogic())->check_validate_code($code, $mobile, 'phone', $session_id, $scene);
                    if($check_code['status'] != 1){
                        $this->ajaxReturn($check_code);
                    }
                    $result = (new UserModel())->where('user_id',$this->user_id)->update(['order_pickup_verify_error'=>0]);
                    if ($result) return json(['status'=>1,'msg'=>'验证成功']);
                }
            }else{
                return outPut(-1,'手机号有误');
            }
//        }
    }




}