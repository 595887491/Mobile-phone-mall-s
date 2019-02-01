<?php
/**
 * @Author: 陈静
 * @Date: 2018/09/05 10:34:57
 * @Description:
 */

namespace app\mobile\model;


use app\common\model\Goods;
use think\Model;

class VoteActivityModel extends Model
{
    protected $table = 'cf_vote_activity';
    protected $resultSetType = 'collection';

    public function getActivityInfo($activityId)
    {
        $activityInfo = $this->where('id',$activityId)->find()->toArray();
        $giftsInfo = json_decode($activityInfo['prize_setting'],true);

        $goodsId = array_column($giftsInfo,'goods_id');
        $goodsInfo = (new Goods())->where('is_on_sale',1)
            ->where('goods_id','in',$goodsId)->getField('goods_id,goods_name,goods_remark,shop_price,market_price,original_img',true);

        foreach ($giftsInfo as &$v) {
            $goodsInfo[$v['goods_id']]['shop_price'] = round($goodsInfo[$v['goods_id']]['shop_price'],2);
            $goodsInfo[$v['goods_id']]['market_price'] = round($goodsInfo[$v['goods_id']]['market_price'],2);
            $v['goods_info'] = $goodsInfo[$v['goods_id']];
        }

        $activityInfo['prize_setting'] = $giftsInfo;

        return $activityInfo;
    }

}