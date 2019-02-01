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
use app\common\logic\FlashSaleLogic;
use app\common\logic\GroupBuyLogic;

class Coupon extends Model
{
    public function goodsCoupon()
    {
        return $this->hasMany('GoodsCoupon','coupon_id','id');
    }
    public function store(){
        return $this->hasOne('Store','store_id','store_id');
    }

    /**
     * 是否快到期|一天间隔
     * @param $value
     * @param $data
     * @return mixed
     */
    public function getIsExpiringAttr($value,$data)
    {
        if (($data['use_end_time'] - time()) < (60 * 60 * 24 * 1)) {
            return 1;
        }else{
            return 0;
        }
    }

    /**
     * 是否到期
     * @param $value
     * @param $data
     * @return bool
     */
    public function getIsExpireAttr($value,$data){
        if ((time() - $data['use_end_time']) > 0) {
            return 1;
        }else{
            return 0;
        }
    }

    /**
     * 格式化时间
     * @param $value
     * @param $data
     * @return bool|string
     */
    public function getUseStartTimeFormatDotAttr($value,$data){
        return date('Y.m.d', $data['use_start_time']);
    }
    /**
     * 格式化时间
     * @param $value
     * @param $data
     * @return bool|string
     */
    public function getUseEndTimeFormatDotAttr($value,$data){
        return date('Y.m.d', $data['use_end_time']);
    }

    /**
     * 是否被领完
     * @param $value
     * @param $data
     * @return bool|string
     */
    public function getIsLeadEndAttr($value, $data)
    {
        if ($data['createnum'] <= $data['send_num'] && $data['createnum'] != 0) {
            return 1;
        } else {
            return 0;
        }
    }

    /**
     * 使用范围描述：0全店通用1指定商品可用2指定分类商品可用
     * @param $value
     * @param $data
     * @return int
     */
    public function getUseTypeTitleAttr($value, $data)
    {
        if ($data['use_type'] == 1) {
            return '指定商品';
        } elseif($data['use_type'] == 2) {
            return '指定分类商品';
        }else{
            return '全店通用';
        }
    }

    //获取用户未领取的优惠券
    public function getUserCanGetCouponNums($user_id)
    {
        $where = array('type' => 2,'status'=>1,'send_start_time'=>['elt',time()],'send_end_time'=>['egt',time()], 'use_end_time'=>['egt',time()]);
        $time = time();

        $coupon_list = M('coupon')->field("*,send_end_time-'$time' as spacing_time")
            ->where($where)->order(['money' => 'desc'])->select();

        if (is_array($coupon_list) && count($coupon_list) > 0) {
            if ($user_id) {
                $user_coupon = M('coupon_list')->where(['uid' => $user_id, 'type' => 2])->getField('cid',true);
            }
            foreach ($coupon_list as $k => $val) {
                $coupon_list[$k]['isget'] = 0;
                if (in_array($val['id'],$user_coupon)) {
                    $coupon_list[$k]['isget'] = 1;
                    unset($coupon_list[$k]);
                    continue;
                }
                $coupon_list[$k]['use_scope'] = C('COUPON_USER_TYPE')[$coupon_list[$k]['use_type']];
            }
        }
        return count($coupon_list);
    }

    //用户完善资料送券
    public function sendCouponPerfectUserInfo($user_id = 0)
    {
        //1.检测用户是否领过此类的券
        $couponListModel = new CouponList();
        $res = $couponListModel->isGetThisTypeCoupon($user_id,5);
        if ($res) {
            return false;
        }

        //2.检测资料完善度
        $usersModel = new Users();
        $userInfo = $usersModel->getUserInfo($user_id);
        $userName = $userInfo->nickname;
        $userSex = $userInfo->sex;
        $userAge = $userInfo->age;

        //查询肤质
        $skinDatas = (new SkinModel())->where('user_id',$user_id)->count();

        if (
            empty($userName) || empty($userSex) ||
            empty($userAge) || empty($skinDatas)
        ) {
            return false;
        }

        //3.资料完善，送券
        //查询状态为注册发放的优惠券
        (new ActivityLogic())->grantUserCoupon($user_id,5);
        return true;

    }
}
