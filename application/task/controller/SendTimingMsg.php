<?php
/**
 * @Author: 陈静
 * @Date: 2018/05/08 20:24:43
 * @Description: 发送短信提醒
 */

namespace app\task\controller;

use app\common\library\Logs;
use app\common\logic\SmsLogic;
use app\common\logic\WechatLogic;
use app\common\model\FlashRemindModel;
use app\common\model\FlashSale;

class SendTimingMsg
{
    public function sendMsg()
    {
        $datas = $this->getFlashRemindDatas();

        if (empty($datas['datas'])) {
            return;
        }

        foreach ($datas['datas'] as $v) {
            if ($v['openid']) {
                //发送模板消息
                $res = (new WechatLogic())->sendTemplateMsgOnFlashSale($v);
                if ($res['status'] == -1) {
                    Logs::sentryLogs('发送模板消息失败',$v);
                }
            }else{
                //发送短信
                $msg = $v['goods_num'] > 1 ? mb_substr($v['goods_name'],0,15).' ...等' : mb_substr($v['goods_name'],0,15).' ...';
                $res = (new SmsLogic())->sendSms(3,$v['mobile'],['goods_name'=> $msg]);
                if ($res['status'] == -1) {
                    Logs::sentryLogs('发送模板消息失败',$v);
                }
            }
            sleep(1);
        }

        //改变状态
        $result = (new FlashRemindModel())->where('id','in',$datas['remindDatas'])->update(['status' => 1]);
        if (!$result) {
            Logs::sentryLogs('修改秒杀提醒状态失败');
        }
    }

    public function getFlashRemindDatas()
    {
        $timeSpaceArr = $this->getFlashTimeSpace();
        $timeSpaceArr[0]['font'] = '10:00';
        $timeSpaceArr[0]['start_time'] = '1527732000';
        $timeSpaceArr[0]['end_time'] = '1527739200';

        $startTime = current($timeSpaceArr)['start_time'];

        if ($startTime < time()) {
            array_shift($timeSpaceArr);
            $startTime = current($timeSpaceArr)['start_time'];
        }

        if (empty($startTime)) {
            return;
        }

        $remindTime = tpCache('flash.flash_ahead_time','','cf_config') ?? 300 ;

        if ( ($startTime - time()) >= $remindTime ) {
            return;
        }

        $flashRemindDatas = new FlashRemindModel();

        $where = [
            'status' => 0,
            'a.flash_start_time' => $startTime,
            'c.start_time' => $startTime,
        ];

        $datas = $flashRemindDatas->alias('a')
            ->field('a.id,a.user_id,a.flash_start_time,b.openid,c.goods_name,count(a.goods_id) as goods_num,d.mobile')
            ->join(['tp_oauth_users' => 'b'],'a.user_id = b.user_id','LEFT')
            ->join(['tp_flash_sale' => 'c'],'a.goods_id = c.goods_id','LEFT')
            ->join(['tp_users' => 'd'],'a.user_id = d.user_id','LEFT')
            ->where($where)
            ->group('a.user_id')
            ->select();

        $datas = collection($datas)->toArray();

        $datas1 = collection($flashRemindDatas->alias('a')->field('a.id')->where($where)
            ->join(['tp_flash_sale' => 'c'],'a.goods_id = c.goods_id','LEFT')->select())->toArray();

        $remindDatas = array_column($datas1,'id');

        return [
            'datas' => $datas,
            'remindDatas' => $remindDatas
        ];
    }


    //获取秒杀时间段
    public function getFlashTimeSpace()
    {
        $time_space = flash_sale_time_space();
        $current_row = $time_space[1];
        $space = 7200;
        $time_space_arr = [];
        $i = 1;
        do{
            $start_time = $current_row['start_time'];
            $end_time = $current_row['end_time'];
            $where = array(
                'fl.start_time'=>array('egt',$start_time),
                'fl.end_time'=>array('elt',$end_time),
                'g.is_on_sale'=>1
            );
            $flash_sale_goods_num = (new FlashSale())->alias('fl')
                ->join('__GOODS__ g', 'g.goods_id = fl.goods_id')->with(['specGoodsPrice','goods'])
                ->field('*,100*(FORMAT(buy_num/goods_num,2)) as percent')
                ->where($where)
                ->count();
            if ($flash_sale_goods_num > 0) {
                $key = count($time_space_arr) + 1;
                $time_space_arr[$key] = $current_row;
            }
            $current_row = [
                "font"=>date("H",$start_time + $space).":00",
                "start_time"=>$start_time + $space,
                "end_time"=>$end_time + $space
            ];
            $i++;
        } while(count($time_space_arr) < 4 && $i<84);

        if (empty($time_space_arr)) {
            return array_slice($time_space,0,4);
        }
        return $time_space_arr;
    }

}