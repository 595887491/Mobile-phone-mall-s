<?php
/**
 * @Author: 陈静
 * @Date: 2018/08/28 10:12:38
 * @Description:
 */

namespace app\mobile\model;


use app\common\model\Goods;
use app\common\model\SpecGoodsPrice;
use think\Model;
use think\Page;

class BargainActivityModel extends Model
{
    protected $table = 'cf_bargain_activity';
    protected $resultSetType = 'collection';

    public function getBargainDetail($activityId,$itemId)
    {
        $activityInfo = $this->where('id',$activityId)
            ->where('status',1)
            ->find();

        if (empty($activityInfo)) {
            return null;
        }

        $goodInfo = Goods::get($activityInfo->goods_id,'',true);

        if ($itemId) {
            $specGoodsPrice = SpecGoodsPrice::get($itemId,'',false);
            if ($goodInfo->goods_id != $specGoodsPrice->goods_id) {
//                return $this->error('砍价商品信息有误');
            }
            //如果价格有变化就将市场价等于商品规格价。
            $goodInfo->market_price = $specGoodsPrice['price'];
            $goodInfo->shop_price = $specGoodsPrice['price'];
            $goodInfo->store_count = $specGoodsPrice['store_count'];
        }
        $goodInfo->shop_price = round($goodInfo->shop_price,2);
        $goodInfo->market_price = round($goodInfo->market_price,2);

        $goodInfo->activity_id = $activityInfo->id;
        $goodInfo->act_type = $activityInfo->act_type;
        $goodInfo->min_price = $activityInfo->min_price;
        $goodInfo->sales_sum = $activityInfo->sales_sum + $activityInfo->virtual_num;

        $goodInfo->share_title = $activityInfo->share_title;
        $goodInfo->share_desc = $activityInfo->share_title;
        $goodInfo->share_img = $activityInfo->share_title;

        //查询基本配置
        $baseConfig = (new BargainTemplateModel())->getBargainConfig($activityInfo->template_id);

        if (empty($goodInfo->share_title)) {
            $goodInfo->share_title = $baseConfig['default']['bargain_share_title_default'];
        }
        if (empty($goodInfo->share_img)) {
            $goodInfo->share_img = $baseConfig['default']['bargain_share_pic_default'];
        }
        if (empty($goodInfo->share_desc)) {
            $goodInfo->share_desc = $baseConfig['default']['bargain_share_desc_default'];
        }

        $goodInfo->team_priv = $activityInfo->team_priv;
        $goodInfo->is_free_shipping = $activityInfo->is_shipping;
        $goodInfo->template_id = $activityInfo->template_id;
        $goodInfo->item_id = $itemId;

        return $goodInfo;
    }




    /*
    * @Author:赵磊
    * 获取砍价活动列表数据
    * */
    public function getBargainList()
    {
        $count = $this->where('status',1)->count();
        $page = new Page($count,20);
        $list = $this->alias('a')
            ->join(['tp_goods'=>'b'],'a.goods_id=b.goods_id')
            ->field('a.*,b.goods_remark,b.shop_price,b.original_img,b.goods_name')
            ->where('a.status',1)
            ->limit($page->firstRow,$page->listRows)
            ->select()
            ->toArray();
        for ($i=0;$i<count($list);$i++){
            $list[$i]['salesNum'] = $list[$i]['sales_sum'] + $list[$i]['virtual_num'];//购买数量
        }
        return $list;
    }

}