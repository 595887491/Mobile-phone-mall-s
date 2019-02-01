<?php
/**
 * @Author: 陈静
 * @Date: 2018/08/14 13:55:14
 * @Description:
 */

namespace app\task\controller;


use app\common\logic\WechatLogic;
use think\Db;

class SendTeamRemind
{
    //发送提醒
    public function sendMsg()
    {
        $data = Db::name('team_found')->where([
            'status' => 1 ,
            'join' => ['exp' , '< `need` ' ] ,
            'found_time' => [ '<', time() ] ,
            'found_end_time' => [ '>', time() ] ,
        ])->select();

        foreach ($data as $v) {
            $oneHourRemindTime = date('YmdH',$v['found_end_time'] - 3600) ;
            $halfRemindTime = date('YmdH',( $v['found_end_time'] + $v['found_time'] ) / 2);
            $nowTime = date('YmdH');

            if ($oneHourRemindTime == $nowTime || $halfRemindTime == $nowTime ) {
                $info['goods_name'] = Db::name('team_activity')->where('team_id',$v['team_id'])->getField('goods_name');
                $info['time_left'] = floor(( $v['found_end_time'] - time() ) / 3600);
                $info['needer_left'] = $v['need'] - $v['join'];
                $info['remark'] = '在规定时间内凑满人数才能拼团成功哦，马上分享>';

                $followUser = Db::name('team_follow')->field('follow_user_id,order_id')->where('found_id',$v['found_id'])->select();
                $followUser[] = ['follow_user_id' => $v['user_id'], 'order_id' => $v['order_id'] ];
                $info['follow_user'] = $followUser;

                //时间过半
                if ($halfRemindTime == $nowTime) {
                    $info['first_data'] = '您好，您的拼团人数不足，还需多多分享哟！';
                }

                //还剩一小时
                if ($oneHourRemindTime == $nowTime) {
                    $info['first_data'] = '您好，您的拼团人数不足，还有1小时就要拼团失败啦，赶紧去分享哟！';
                }

                (new WechatLogic())->sendTemplateMsgOnTeamRemind($info);

            }
        }


    }

}