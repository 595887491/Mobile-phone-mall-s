<?php
/**
 * @Author: 陈静
 * @Date: 2018/08/23 14:59:03
 * @Description:
 */

namespace app\task\controller;


use app\common\logic\WechatLogic;
use think\Db;

class SendCouponRemind
{
    public function sendMsg()
    {
        $where = [
            'a.status' => 0 ,
            'a.use_start_time' => [ '<' , time() ] ,
            'a.use_end_time' => [ 'exp', ' < '.(strtotime(date('Ymd',strtotime('+1 day')))+3600*24 -1) ]
        ];

        $couponList = Db::name('coupon_list')->alias('a')
            ->join('oauth_users b','a.uid = b.user_id','left')
            ->join('coupon c','a.cid = c.id','left')
            ->field('count(uid) as coupon_num,a.uid,b.openid')
            ->where($where)
            ->where('a.use_start_time' , '>' , 0)
            ->where('a.use_end_time' , '>' , time())
            ->where('b.wx_bind',1)
            ->where('c.status','in','1,3')
            ->group('a.uid')
            ->select();

        foreach ($couponList as $v) {
            $res = (new WechatLogic())->sendTemplateMsgOnCouponRemind($v);
            sleep(1);
        }
    }

}