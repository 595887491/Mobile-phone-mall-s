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

use think\Model;

class Users extends Model
{
    //自定义初始化
    protected static function init()
    {
        //TODO:自定义的初始化
    }

    public function userInfoRelation()
    {
        return $this->hasOne('UserModel','user_id' ,'user_id');
    }

    //获取用户信息
    public function getUserInfo($user_id)
    {
        return $this->with('userInfoRelation')->where('user_id',$user_id)->find();
    }

    //获取用户头像,姓名,手机号
    public function getHeadpic($userId)
    {
        $res = $this->where('user_id',$userId)->field('nickname,head_pic,mobile,reg_time')->find();
        $result['nickname'] = $res->nickname;
        $result['head_pic'] = $res->head_pic;
        $result['mobile'] = $res->mobile;
        $result['reg_time'] = date('Y.m.d',$res->reg_time);
        return $result;
    }


}
