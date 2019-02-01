<?php
/**
 * @Author: 陈静
 * @Date: 2018/08/29 11:36:56
 * @Description:
 */

namespace app\mobile\model;

use app\common\logic\CartLogic;
use app\common\logic\GoodsLogic;
use app\common\logic\OrderLogic;
use app\common\model\OrderGoods;
use app\common\model\Users;
use think\Db;
use think\Model;

class BargainFollowModel extends Model
{
    protected $table = 'cf_bargain_follow';
    protected $resultSetType = 'collection';

    private $activityFoundInfo;
    private $activityInfo;
    private $bargainConfig;
    private $userId;

    //自动砍价
    public function autoBargain($activityInfo,$userId,$foundId = 0,$is_share = 0){
        $this->userId = $userId;
        $this->activityInfo = $activityInfo;
        if ($foundId) {//帮砍者
            $activityFoundInfo = (new BargainFoundModel())->where('found_id',$foundId)->find()->toArray();
        }else{
            $activityFoundInfo = (new BargainFoundModel())->getCurrentBargain($activityInfo,$userId);
        }
        if ($activityFoundInfo == false) {
            return false;
        }
        $this->activityFoundInfo = $activityFoundInfo;
        $this->bargainConfig = (new BargainTemplateModel())->getBargainConfig($activityInfo->template_id,$activityFoundInfo['reduce_price_percent']);

        //自己砍
        if ($this->userId == $this->activityFoundInfo['user_id']) {
            $res = $this->bargainBySelf();
            $this->activityFoundInfo['current_bargain_info'] = $this->getNextShareInfo($this->shareInfo,$this->activityFoundInfo['current_bargain_num']);
        }else{
            //好友砍
            $res = $this->bargainByFriend();
        }

        $this->activityFoundInfo['have_bargain_price'] = $this->activityFoundInfo['goods_price'] - $this->activityFoundInfo['price'];
        $this->activityFoundInfo['bargain_info'] = $res;

        //砍价成功标志（免费得和底价购）
        $this->activityFoundInfo['is_success'] = 0;
        if ($activityInfo->act_type == 0) { //免费得
            if ($this->activityFoundInfo['price'] == 0) {
                $this->activityFoundInfo['is_success'] = 1;
            }
            //自动下单
            if ($this->activityFoundInfo['status'] == 2) {
                $this->autoAddOrder();
            }
        }elseif ($activityInfo->act_type == 1){//底价购
            if ($this->activityFoundInfo['price'] == $this->activityInfo->min_price) {
                $this->activityFoundInfo['is_success'] = 1;
            }
        }

        return $this->activityFoundInfo;
    }

    //好友分享
    private function bargainByFriend(){
        //检测好友砍价次数
        $res1 = $this->cheakUserBargainNum();
        if ($res1['is_over_times']) {
            $data['is_over_times'] = $res1['is_over_times'];
            return $data;
        }elseif($res1['is_have_bargain']){
            $data['is_have_bargain'] = $res1['is_have_bargain'];
            return $data;
        }

        $isNew = $this->judgeNewUser($this->userId);
        if ($isNew) {
            $data['reduce_price'] = round($this->activityFoundInfo['goods_price'] * $this->bargainConfig['new_bargain'][$res1['today_bargain_num']] / 100,2) ;
        }else{
            $data['reduce_price'] = $this->dealOldUserPrice($this->bargainConfig['old_bargain'][$res1['today_bargain_num']],$res1['today_bargain_num']);
        }

        $data['follow_user_id'] = $this->userId;
        $data['follow_time'] = time();
        $data['found_id'] = $this->activityFoundInfo['found_id'];
        $data['found_user_id'] = $this->activityFoundInfo['user_id'];
        $data['bargain_id'] = $this->activityFoundInfo['bargain_id'];
        $data['this_price'] = $this->activityFoundInfo['price'];

        if ($this->activityInfo->act_type == 0) {
            $limitPrice = 0 ;
        }elseif ($this->activityInfo->act_type == 1){
            $limitPrice = $this->activityInfo->min_price;
        }

        if ($data['this_price'] <= $limitPrice) {
            return;
        }

        $data['is_new'] = $isNew;
        $data['cut_no'] = $res1['today_bargain_num'] + 1;
        switch ( $data['cut_no'] ) {
            case 1:
                $data['cut_desc'] = '当日首刀';
                break;
            case 2:
                $data['cut_desc'] = '当日第二刀';
                break;
            case 3:
                $data['cut_desc'] = '当日第三刀';
                break;
        }

        $nextPrice = $data['this_price'] - $data['reduce_price'];

        if ($nextPrice < $limitPrice) {
            $data['reduce_price'] = $data['this_price'] - $limitPrice;
            $nextPrice = $limitPrice ;
            $updateData['status'] = 2;
            $this->activityFoundInfo['status'] = 2;
        }

        $updateData['join'] = $this->activityFoundInfo['join'] + 1;
        $updateData['price'] = $nextPrice;

        $res = $this->save($data);
        if ($res) {
            (new BargainFoundModel())->where('found_id',$this->activityFoundInfo['found_id'])->update($updateData);
            $this->activityFoundInfo['price'] = $nextPrice;
        }
        return $data;
    }

    //处理老用户砍价问题
    private function dealOldUserPrice($percent,$times){
        switch ($times){
            case 0:
                $minPrice = $this->bargainConfig['old_bargain_money_limit'][0]['min'];
                $maxPrice = $this->bargainConfig['old_bargain_money_limit'][0]['max'];
                break;
            case 1:
                $minPrice = $this->bargainConfig['old_bargain_money_limit'][1]['min'];
                $maxPrice = $this->bargainConfig['old_bargain_money_limit'][1]['max'];
                break;
            case 2:
                $minPrice = $this->bargainConfig['old_bargain_money_limit'][2]['min'];
                $maxPrice = $this->bargainConfig['old_bargain_money_limit'][2]['max'];
                break;
        }

        $price = round($this->activityFoundInfo['goods_price'] * $percent / 100,2) ;

        if ($price < $minPrice) {
            $price = $minPrice;
        }

        if ($price > $maxPrice) {
            $price = $maxPrice;
        }
        return $price;
    }

    //检测好友砍价次数
    private function cheakUserBargainNum(){
        $todayStartTime = strtotime(date('Ymd'));
        $todayEndTime = $todayStartTime + 24 * 3600 - 1 ;
        //今天帮砍次数
        $bargainNum = $this->where('follow_time','>',$todayStartTime)
            ->where('follow_time','<',$todayEndTime)
            ->where('follow_user_id',$this->userId)
            ->where('follow_user_id','exp',' <> found_user_id')
            ->count();

        $data['today_bargain_num'] = $bargainNum;

        if ( $bargainNum >= $this->bargainConfig['default']['bargain_count_follow_day'] ) {
            $data['is_over_times'] = 1;
        }

        //是否砍过当前
        $currentBargainNum = $this->where('found_id',$this->activityFoundInfo['found_id'])
            ->where('follow_user_id',$this->userId)
            ->count();

        if ( $currentBargainNum ) {
            $data['is_have_bargain'] = 1;
        }
        return $data;
    }


    //自己砍价
    private function bargainBySelf(){
        //检测分享状态(第几刀)
        $shareInfo = (new BargainShareModel())->where('found_id',$this->activityFoundInfo['found_id'])
            ->where('found_user_id',$this->userId)->find();
        $this->shareInfo = $shareInfo;
        $times = $this->judgeSelfTimes($shareInfo);

        if (empty($times)) {
            return;
        }

        $data['follow_user_id'] = $this->userId;
        $data['follow_time'] = time();
        $data['found_id'] = $this->activityFoundInfo['found_id'];
        $data['found_user_id'] = $this->activityFoundInfo['user_id'];
        $data['bargain_id'] = $this->activityFoundInfo['bargain_id'];
        $data['this_price'] = $this->activityFoundInfo['price'];

        if ($this->activityInfo->act_type == 0) {
            $limitPrice = 0 ;
        }elseif ($this->activityInfo->act_type == 1){
            $limitPrice = $this->activityInfo->min_price;
        }

        if ($data['this_price'] <= $limitPrice) {
            return;
        }

        $data['reduce_price'] = round($this->activityFoundInfo['goods_price'] * $this->bargainConfig['self_bargain'][$times - 1] / 100,2) ;
        $data['is_new'] = 0;
        $data['cut_no'] = $times;
        switch ($times) {
            case 1:
                $data['cut_desc'] = '当日首刀';
                break;
            case 2:
                $data['cut_desc'] = '当日第二刀';
                break;
            case 3:
                $data['cut_desc'] = '当日第三刀';
                break;
            case 4:
                $data['cut_desc'] = '当日第四刀';
                break;
        }

        $nextPrice = $data['this_price'] - $data['reduce_price'];

        if ($nextPrice < $limitPrice) {
            $data['reduce_price'] = $data['this_price'] - $limitPrice;
            $nextPrice = $limitPrice ;
            $updateData['status'] = 2;
            $this->activityFoundInfo['status'] = 2;
        }

        $updateData['join'] = $this->activityFoundInfo['join'] + 1;
        $updateData['price'] = $nextPrice;

        $res = $this->save($data);
        if ($res) {
            (new BargainFoundModel())->where('found_id',$this->activityFoundInfo['found_id'])->update($updateData);
            $this->activityFoundInfo['price'] = $nextPrice;
        }
        $data['shareInfo'] = $this->getNextShareInfo($shareInfo,$times);

        return $data;
    }


    //获取下一次的分享信息
    public function getNextShareInfo($shareInfo,$currentTimes = 0)
    {
        $foundInfo = $this->alias('a')->field('b.reduce_price_percent,b.goods_price,c.template_id')
            ->join(['cf_bargain_found' => 'b'],'a.found_id = b.found_id','LEFT')
            ->join(['cf_bargain_activity' => 'c'],'b.bargain_id = c.id','LEFT')
            ->where('a.found_id',$shareInfo['found_id'])->find();

        $this->bargainConfig = (new BargainTemplateModel())->getBargainConfig($foundInfo->template_id,$foundInfo->reduce_price_percent);
        $data['current_share_num'] = 0;
        $data['current_share_status'] = 0;

        $data['next_bargain_price'] = round(($this->bargainConfig['self_bargain'][$currentTimes] * $foundInfo->goods_price) / 100,2);
        if ($shareInfo) {
            //分享状态为成功
            if ($shareInfo['step_status']){
                $data['current_share_status'] = 1;
                if ($shareInfo['step'] == 1) {
                    $data['next_share_user_num'] = $this->bargainConfig['default']['bargain_count_found_share2'];
                    $data['next_share_type'] = 3;
                    $data['current_share_type'] = 1;
                }elseif ($shareInfo['step'] == 3) {
                    $data['next_share_user_num'] = 0;
                    $data['next_bargain_price'] = 0;
                    $data['next_share_type'] = 3;
                    $data['current_share_type'] = 3;
                }
            }else{
                $data['current_share_status'] = 0;
                if ($shareInfo['step'] == 1) {
                    $data['next_share_user_num'] = $this->bargainConfig['default']['bargain_count_found_share1'];
                    $data['current_share_num'] = $this->bargainConfig['default']['bargain_count_found_share1'] - $shareInfo['share_count'];
                    $data['next_share_type'] = 1;
                    $data['current_share_type'] = 1;
                }elseif ($shareInfo['step'] == 3) {
                    $data['next_share_user_num'] = $this->bargainConfig['default']['bargain_count_found_share2'];
                    $data['current_share_num'] = $this->bargainConfig['default']['bargain_count_found_share1'] + $this->bargainConfig['default']['bargain_count_found_share2'] - $shareInfo['share_count'];
                    $data['next_share_type'] = 3;
                    $data['current_share_type'] = 3;
                }
            }
        }else{
            $data['next_share_user_num'] = $this->bargainConfig['default']['bargain_count_found_share1'];
            $data['next_share_type'] = 1;
            $data['current_share_type'] = 0;
        }

        return $data;
    }

    //判断自己砍的次数
    private function judgeSelfTimes($shareInfo){
        //判断第几刀
        if (empty($shareInfo)) {
            $times = 1 ;
        }else{
            if ($shareInfo->share_count >= $this->bargainConfig['default']['bargain_count_found_share2'] + $this->bargainConfig['default']['bargain_count_found_share1'] ) {
                $times = 3;
            }elseif($shareInfo->share_count >= $this->bargainConfig['default']['bargain_count_found_share1']){
                $times = 2;
            }
        }

        //检查好友是否有满足第四刀
        $shareUserNum = $this->where('found_id',$this->activityFoundInfo['found_id'])
            ->where('follow_time','>' , 0 )
            ->where('follow_user_id','<>' , $this->userId )
            ->count();

        if ( $shareUserNum >= $this->bargainConfig['default']['bargain_count_found_share3'] ) {
            $times = 4;
        }

        //检查是否砍过该刀
        $followInfo = $this->where('follow_user_id',$this->userId)
            ->where('found_id',$this->activityFoundInfo['found_id'])
            ->where('cut_no',$times)
            ->find();
        if ( $followInfo ) {
            $this->activityFoundInfo['current_bargain_num'] = $times;
            $this->activityFoundInfo['next_bargain_num'] = $times + 1;
        }

        if (empty($times) || !empty($followInfo)) {
            return false;
        }
        $this->activityFoundInfo['current_bargain_num'] = $times - 1;
        $this->activityFoundInfo['next_bargain_num'] = $times;

        return $times;
    }


    //判断砍价新用户
    private function judgeNewUser($userId){
        $regTime = (new Users())->where('user_id',$userId)->getField('reg_time');
        return $regTime > strtotime(date('Ymd')) ? 1 : 0 ;
    }
    
    //获得成功砍价的用户信息
    public function getBargainUserByFoundId($foundId)
    {
        $userDatas = $this->alias('a')
            ->join('users b','a.follow_user_id = b.user_id','LEFT')
            ->field('a.reduce_price,b.nickname,b.head_pic')
            ->where('a.found_id',$foundId)
            ->select()->toArray();

        foreach ($userDatas as &$v) {
            if (is_mobile($v['nickname'])) {
                $v['nickname']= phoneToStar($v['nickname']);
            }
        }
        return $userDatas;
    }
    
    //自动下单
    public function autoAddOrder()
    {
        $address = Db::name('UserAddress')->where("address_id", $this->activityFoundInfo['address_id'])->find();
        $data['order_sn'] = (new OrderLogic())->get_order_sn();
        $data['user_id'] = $this->activityFoundInfo['user_id'];
        $data['order_status'] = 1;
        $data['pay_status'] = 1;
        if ($this->activityFoundInfo['address_id'] == -1) {
            $data['shipping_code'] = 'ZITI';
            $data['shipping_name'] = '门店自提';
        }
        $data['consignee'] = $address['consignee'] ? $address['consignee'] : '';
        $data['province'] = $address['province'] ? $address['province'] : '';
        $data['city'] = $address['city'] ? $address['city'] : '';
        $data['district'] = $address['district'] ? $address['district'] : '';
        $data['address'] = $address['address'] ? $address['address'] : '';
        $data['mobile'] = $address['mobile'] ? $address['mobile'] : '';

        $data['goods_price'] = $this->activityFoundInfo['goods_price'];
        $data['order_amount'] = $this->activityFoundInfo['price'];
        $data['total_amount'] = $this->activityFoundInfo['goods_price'];
        $data['add_time'] = time();

        $res = Db::name('order')->insertGetId($data);
        if ($res) {
            //查询商品信息
            $data1['order_id'] = $res;
            $data1['goods_id'] = $this->activityInfo->goods_id;
            $data1['goods_name'] = $this->activityInfo->goods_name;
            $data1['goods_sn'] = $this->activityInfo->goods_sn;
            $data1['goods_num'] = 1;
            $data1['final_price'] = $this->activityInfo->shop_price;
            $data1['goods_price'] = $this->activityInfo->shop_price;
            $data1['member_goods_price'] = $this->activityInfo->shop_price;
            (new OrderGoods())->insert($data1);
        }
        (new BargainFoundModel())->where('found_id',$this->activityFoundInfo['found_id'])->update(['order_id' => $res]);
        $this->activityFoundInfo['order_id'] = $res;
    }
        

}