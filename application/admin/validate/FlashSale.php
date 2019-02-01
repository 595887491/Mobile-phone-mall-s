<?php
namespace app\admin\validate;
use think\Validate;
use think\Db;
class FlashSale extends Validate
{
    // 验证规则
    protected $rule = [
        ['id','checkId'],
        ['title', 'require'],
        ['goods', 'checkGoods'],
        ['start_time','require'],
        ['end_time','require|checkEndTime'],
        ['flash_order','number'],
    ];
    //错误信息
    protected $message  = [
        'title.require'         => '抢购标题必须',
        'goods.checkGoods'      => '商品设置有误',
        'start_time.require'    => '请选择开始时间',
        'end_time.require'      => '请选择结束时间',
        'end_time.checkEndTime' => '结束时间不能早于开始时间',
        'flash_order.number'    => '排序必须是数字',
    ];
//检验价格、数量、限购数量
    protected function checkGoods($value, $rule ,$data){
        $idArr = array_column($value,'goods_id');
        $goodsArr = Db::name('goods')->where('goods_id',['in',$idArr])->select();
        $newGoodsArr = [];
        array_walk($goodsArr,function($value)use(&$newGoodsArr){
            $newGoodsArr[$value['goods_id']] = $value;
        });
        unset($goodsArr);
        foreach ($value as $inputItem) {
            $goods = $newGoodsArr[$inputItem['goods_id']];
            if(empty($inputItem['price']) || !is_numeric($inputItem['price'])) return "商品 ".$goods['goods_name']." 秒杀价格为空";
            if(empty($inputItem['goods_num']) || !is_numeric($inputItem['goods_num'])) return "商品 ".$goods['goods_name']." 秒杀数量为空";
            if(isset($inputItem['limit']) && !is_numeric($inputItem['limit'])) return "商品 ".$goods['goods_name']." 限购数量不是数字";
            if(isset($inputItem['item_id']) && $inputItem['item_id'] > 0){
                $price = Db::name("spec_goods_price")->where(['goods_id'=>$inputItem['goods_id'],'item_id'=>$inputItem['item_id']])->getField('price');
            }else{
                $price = Db::name('goods')->where('goods_id',$inputItem['goods_id'])->getField('shop_price');
            }
            if ($inputItem['price'] > $price) return "商品 ".$goods['goods_name']." 秒杀价格不得大于商品原价".$price;
            if ($inputItem['goods_num'] > $inputItem['store_count']) return "商品 ".$goods['goods_name']." 秒杀数量".$inputItem['goods_num']."不得大于库存".$inputItem['store_count'];
            if ($inputItem['limit'] > $inputItem['goods_num']) return "商品 ".$goods['goods_name']." 限购数量不得大于秒杀数量";
        }
        return true;
    }
    /**
     * 检查限购数量
     * @param $value|验证数据
     * @param $rule|验证规则
     * @param $data|全部数据
     * @return bool|string
     */
    protected function checkLimit($value, $rule ,$data)
    {
        if($value > $data['goods_num']){
            return '限购数量不能超过抢购数量';
        }
        $goods = Db::name("goods")->where(['goods_id'=>$data['goods_id']])->find();
        if($goods['is_virtual'] == 1 && $value > $goods['virtual_limit']){
            return '限购数量不能超过虚拟商品购买上限';
        }
        return true;
    }
    /**
     * 检查结束时间
     * @param $value|验证数据
     * @param $rule|验证规则
     * @param $data|全部数据
     * @return bool|string
     */
    protected function checkEndTime($value, $rule ,$data)
    {
        return ($value < $data['start_time']) ? false : true;
    }
    /**
     * 检查抢购价格
     * @param $value|验证数据
     * @param $rule|验证规则
     * @param $data|全部数据
     * @return bool|string
     */
    protected function checkPrice($value, $rule ,$data)
    {
        if($data['item_id'] > 0){
            //商品规格
            $price = Db::name("spec_goods_price")->where(['item_id'=>$data['item_id']])->getField('price');
        }else{
            $price = Db::name('goods')->where('goods_id',$data['goods_id'])->getField('shop_price');
        }
        return ($value >= $price) ? false : true;
    }
    /**
     * 检查参与抢购数量
     * @param $value|验证数据
     * @param $rule|验证规则
     * @param $data|全部数据
     * @return bool|string
     */
    protected function checkGoodsNum($value, $rule ,$data)
    {
        if($value == 0){
            return '抢购数量不能为零';
        }
        if($data['item_id'] > 0){
            //商品规格
            $store_count = Db::name("spec_goods_price")->where(['item_id'=>$data['item_id']])->getField('store_count');
        }else{
            $store_count = Db::name("goods")->where(['goods_id'=>$data['goods_id']])->getField('store_count');
        }
        return ($value > $store_count) ? '抢购数量不能大于库存数量' : true;
    }
    /**
     * 该活动是否可以编辑
     * @param $value|验证数据
     * @param $rule|验证规则
     * @param $data|全部数据
     * @return bool|string
     */
    protected function checkId($value, $rule ,$data)
    {
        $isHaveOrder = Db::name('order_goods')->where(['prom_type'=>1,'prom_id'=>$value])->find();
        if($isHaveOrder){
            return '该活动已有用户下单购买不能编辑';
        }else{
            return true;
        }
    }
}