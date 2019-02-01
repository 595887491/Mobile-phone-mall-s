<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/8/3
 * Time: 8:50
 */

namespace app\common\model;


use think\Db;
use think\Model;

class GoodsGlobal extends Model
{
    public $table = 'cf_goods_global';

    /*
     * @Author : 赵磊
     * 2.3.0 全球购商品
     * */
    public function hallInfo($data)
    {
        $hallId = $data['id'];
        $sort = $data['sort']; // 排序名
        $sort_asc = $data['sort_asc']; // 排序顺序
        $free_post = $data['free_post']; // 是否包邮
        if ($free_post) $condition['is_free_shipping'] = 1;//包邮
        $store_count = $data['store_count']; // 是否有货
        if ($store_count) $condition['store_count'] = array('>',0);//有货
        $promotion = $data['promotion']; // 是否促销
        if ($promotion) $condition['prom_type'] = 3;//促销

        $hallInfo = $this
            ->where('id',$hallId)
            ->find();
        $info['banner_img'] = $hallInfo->banner_img;//馆内banner图
        $info['hall_name'] = $hallInfo->hall_name;//馆名
        $info['hall_name_en'] = $hallInfo->hall_name_en;//英文名

        $goodsIds = $hallInfo->goods_id;//id字符串
        if ($goodsIds) $goodsIds = explode(',',$goodsIds);//馆内商品数组
        $condition['goods_id'] = array('in',$goodsIds);
        //商品列表
        $goods = M('goods')
            ->field('goods_name,original_img,market_price,shop_price,goods_id,goods_remark')
            ->where($condition)
            ->order("$sort $sort_asc")
            ->select();
        $info['goods'] = $goods;//商品列表

        //热门品牌
        $hotBrandId = $hallInfo->hot_brand_id;//热门品牌id字符串
        if ($hotBrandId) $hotBrandId = explode(',',$hotBrandId);//热门品牌id数组
//        $condition2['is_hot'] = 1;
        $condition2['id'] = array('in',$hotBrandId);//专题馆内热门品牌id
        $hotBrand = M('brand')
            ->distinct(true)
            ->field('hot_logo,id,name,name_en,logo,country_id')
            ->where($condition2)
            ->order('sort desc')
            ->limit(20)
            ->select();
        $info['hotBrand'] = $hotBrand;//热门品牌列表
        return $info;

    }


    /*
     * @Author : 赵磊
     * 全球购场馆列表页------后台管理
     * */
    public function hallList($search)
    {
        $condition = [];
        $sort = $search['sort'];//id排序
        $searchVal = $search['keywords'];
        if ($searchVal){
            $countryId = $this->searchCountry($searchVal);
            if ($countryId){
                $condition['country_id'] = array('in',$countryId);
            }else{
                $condition['hall_name'] = ['like',"%$searchVal%"];
            }
        }
        $list = Db::table('cf_goods_global')
            ->where($condition)
            ->order("id $sort")
            ->select();
        for ($i=0;$i<count($list);$i++){
            $list[$i]['hot_brand'] = $this->getCount('hot_brand_id',$list[$i]['id']);//热门品牌数量
            $list[$i]['hot_goods'] = $this->getCount('goods_id',$list[$i]['id']);//商品数量
            $list[$i]['country'] = $this->getCountry($list[$i]['id']);//所属国家
        }
        return $list;
    }

    //热门品牌,商品数量
    public function getCount($field,$id)
    {
        $info = $this->field($field)->where('id',$id)->find();
        $str = $info->$field;//该场馆热门品牌或商品字符串
        if ($str != ''){
            $count = explode(',',$str);//转数组
            $count = count($count);
        }else{
            $count = 0;
        }
        return $count;
    }

    //获取商品所属国家
    public function getCountry($Id)
    {
        $countryId = Db::table('cf_goods_global')->field('country_id')->where('id',$Id)->find();
        if (!empty($countryId))$countryId = explode(',',$countryId['country_id']);
        $codition['country_id'] = array('in',$countryId);
        $country = Db::table('cf_country')->where($codition)->select();
        if (!empty($country))$country = array_column($country,'name');
        if (!empty($country)) $country = implode('、',$country);
        return $country;
    }

    //搜索国家
    public function searchCountry($search)
    {
        $condition['name'] = ['like',"%$search%"];
        $country = Db::table('cf_country')->field('country_id')->where($condition)->select();
        $countryId = array_column($country,'country_id');
        return $countryId;
    }


}