<?php
/**
 * @Author: 赵磊
 * @Date: 2018/08/28 10:12:38
 * @Description:
 */

namespace app\mobile\model;

use think\Db;
use think\Model;
use think\Page;

class BargainFoundModel extends Model
{
    protected $table = 'cf_bargain_found';
    protected $resultSetType = 'collection';



    /*
     * @Author:赵磊
     * 获取我的砍价数据
     * */
    public function getMyBargain($userId)
    {
        $time = time();
        $list = $this->alias('a')
            ->join(['cf_bargain_activity'=>'b'],'a.bargain_id=b.id')
            ->join(['tp_goods'=>'c'],'b.goods_id=c.goods_id')
            ->field('a.*,b.goods_id,b.is_shipping,b.act_name,b.time_limit,b.id,b.item_id,c.goods_name,b.act_type,b.min_price,c.goods_remark,c.original_img')
            ->where('a.user_id',$userId)
            ->order('a.found_time desc')
            ->select()
            ->toArray();
        for ($i=0;$i<count($list);$i++){
            $list[$i]['cut'] = $list[$i]['goods_price'] - $list[$i]['price'];
            $list[$i]['time'] = $time;
            $list[$i]['pay_status'] = Db::table('tp_order')->where('order_id',$list[$i]['order_id'])->getField('pay_status');
            if ($list[$i]['pay_status']='')$list[$i]['pay_status'] = -1;//未下单
        }
        $page = new Page(count($list),20);
        $list = array_slice($list,$page->firstRow,20);
        return $list;
    }

    //获取用户当前砍价（没有则创建）
    public function getCurrentBargain($bargianActivityInfo,$userId)
    {
        $activityFoundInfo = $this->where('user_id',$userId)
            ->where('found_end_time','>',time())
            ->where('bargain_id',$bargianActivityInfo->activity_id)
            ->where('status',1)
            ->find();

        if (empty($activityFoundInfo)) {
            $activityInfo = (new BargainActivityModel())->where('id',$bargianActivityInfo->activity_id)
                ->where('end_time','>',time())
                ->where('status',1)
                ->find();
            if (empty($activityInfo)) {
                return false;
            }

            $bargainConfig = (new BargainTemplateModel())->getBargainConfig($bargianActivityInfo->template_id);

            $data['found_time'] = time();
            $data['address_id'] = 0;
            $data['found_end_time'] = time() + $activityInfo->time_limit * 3600;
            $data['user_id'] = $userId;
            $data['bargain_id'] = $bargianActivityInfo->activity_id;
            $data['price'] = $bargianActivityInfo->shop_price;
            $data['goods_price'] = $bargianActivityInfo->shop_price;
            $data['status'] = 1;
            $data['is_auto_confirm'] = $activityInfo->is_auto_confirm;
            //生成砍价的价格
            $data['reduce_price_percent'] = join(',',$bargainConfig['self_bargain']);
            $data['found_id'] = $this->insertGetId($data);
            $activityFoundInfo = $data;
            $activityFoundInfo['is_first'] = 1;
        }else {
            $activityFoundInfo = $activityFoundInfo->toArray();
        }

        return $activityFoundInfo;
    }


}