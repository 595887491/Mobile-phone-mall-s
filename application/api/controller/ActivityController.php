<?php
/**
 * @Author: 陈静
 * @Date: 2018/03/26 08:50:35
 * @Description: 一些通用的Api方法
 */

namespace app\api\controller;

use app\admin\model\Goods;
use app\common\model\FlashRemindModel;

class ActivityController extends BaseController
{
    public function flashRemindUser()
    {
        $userId = $this->validateToken();

        $remindDatas = $this->getPostParams();

        if ($remindDatas['flash_start_time'] < time()) {
            return outPut(-1,'秒杀时间有误');
        }
        $goodInfo = (new Goods())->where('goods_id',$remindDatas['goods_id'])->find();

        if (empty($goodInfo)) {
            return outPut(-1,'商品有误');
        }

        $data = [
            'user_id' => $userId,
            'goods_id' => $remindDatas['goods_id'],
            'flash_start_time' => $remindDatas['flash_start_time']
        ];

        $flashGoodModel = new FlashRemindModel();

        $remindInfo = $flashGoodModel->where($data)->find();
        $data = [
            'user_id' => $userId,
            'goods_id' => $remindDatas['goods_id'],
            'flash_start_time' => $remindDatas['flash_start_time'],
            'status' => $remindDatas['status'],
            'add_time' => time(),
        ];

        if ($remindInfo) {
            $res = $flashGoodModel->where('id',$remindInfo->id)->update($data);
        }else{
            $res = $flashGoodModel->insert($data);
        }

        $msg = $remindDatas['status'] ? '取消' : '添加';

        if ($res) {
            return outPut(1,$msg.'提醒成功');
        }else{
            return outPut(-1,$msg.'秒杀提醒失败，请联系客服');
        }
    }
}