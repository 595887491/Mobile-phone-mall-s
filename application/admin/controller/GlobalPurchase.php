<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/8/6
 * Time: 9:23
 */

namespace app\admin\controller;


use app\admin\logic\GoodsLogic;
use app\common\model\GoodsGlobal;
use app\common\model\GoodsTopic;
use think\AjaxPage;
use think\Db;
use think\Page;

class GlobalPurchase extends Base
{
    /*
     * @Author : 赵磊
     * 全球购管理首页
     * */
    public function index(GoodsGlobal $global)
    {
        $search = I('post.');
        $search['sort'] = I('sort','desc');
        $list = $global->hallList($search);//列表所有数据

        $count = count($list);
        $Page  = new AjaxPage($count,15);
        $show = $Page->show();
        $list = array_slice($list,$Page->firstRow,15);
        $this->assign('page',$show);
        $this->assign('count',$count);//数据数量
        $this->assign('list',$list);//列表数据
        $this->assign('search',$search);//搜索数据

        if (IS_AJAX){
            return $this->fetch('ajax_index');
        }
        return $this->fetch();
    }

    /*
     * @Author : 赵磊
     * 删除操作
     * @params: $id 场馆自增id
     * */
    public function delete()
    {
        $id = I('id');
        $res = (new GoodsGlobal())->where('id',$id)->delete();
        if($res){
            return json(['data'=>1,'code'=> 1,'message'=>'删除成功']);
        }else{
            return json(['data'=>0,'code'=> -1,'message'=>'删除失败']);
        }
    }

    /*
     * @Author : 赵磊
     * 新增/编辑 场馆
     * */
    public function addEdit()
    {
        $data = input("post.");
        $id = I('id');
        $country = Db::table('cf_country')->field('name,country_id')->where('status',1)->select();
        if ($id){
            $global = new GoodsGlobal();
            $info = $global->where('id',$id)->find();//编辑时查询已有内容
            $goodsId = $global->field('goods_id')->where('id',$id)->find();//场馆内商品id
            if ($goodsId->goods_id != '') $goodsId = explode(',',$goodsId->goods_id);//转换数组
            $goodsList = Db::table('tp_goods')->where('goods_id', 'in', $goodsId)->order('goods_id DESC')->select();
            $this->assign('global_goods', $goodsList);
            $this->assign('info',$info);
        }
        if (IS_POST){
            if (empty($data['hall_name'])) {
                $this->ajaxReturn(['status'=>0, 'msg'=>'场馆名不能为空']);
            }
            if (empty($data['banner_img'])) {
                $this->ajaxReturn(['status'=>0, 'msg'=>'Banner图片不能为空']);
            }
            if (empty($data['goods'])) {
                $this->ajaxReturn(['status'=>0, 'msg'=>'请选择专题商品']);
            }
            $goods_arr = array_keys($data['goods']);
            $data['goods_id'] = join(',',array_unique($goods_arr));//选择商品id字符串
            $data['add_time'] = time();
            if ($id) {
                Db::table('cf_goods_global')->where('id',$id)->save($data);
                adminLog("管理员修改了全球购场馆 " . $data['hall_name']);
            } else {
                Db::table('cf_goods_global')->add($data);
                adminLog("管理员添加了管理员修改了全球购场馆 " . $data['hall_name']);
            }
            $this->ajaxReturn(['status'=>1, 'msg'=>'操作场馆成功']);
        }

        $this->assign('country',$country);//有商品的国家下拉框准备数据
        return $this->fetch();
    }

    //商品选择
    public function search_goods()
    {
        $goods_id = input('goods_id');
        $intro = input('intro');
        $cat_id = input('cat_id');
        $brand_id = input('brand_id');
        $keywords = input('keywords');
        $prom_id = input('prom_id');
        $tpl = input('tpl', 'search_goods');
        $where = ['is_on_sale' => 1, 'store_count' => ['gt', 0],'is_virtual'=>0,'exchange_integral'=>0];
        $prom_type = input('prom_type/d');
        if($goods_id){
            $where['goods_id'] = ['notin',trim($goods_id,',')];
        }
        if($intro){
            $where[$intro] = 1;
        }
        if($cat_id){
            $grandson_ids = getCatGrandson($cat_id);
            $where['cat_id'] = ['in',implode(',', $grandson_ids)];
        }
        if ($brand_id) {
            $where['brand_id'] = $brand_id;
        }
        if($keywords){
            $where['goods_name|keywords|goods_id|goods_sn'] = ['like','%'.$keywords.'%'];
        }
        $Goods = new \app\admin\model\Goods();
        $count = $Goods->where($where)->where(function ($query) use ($prom_type, $prom_id) {
            if($prom_type == 3){
                //优惠促销
                if ($prom_id) {
                    $query->where(['prom_id' => $prom_id, 'prom_type' => 3])->whereor('prom_id', 0);
                } else {
                    $query->where('prom_type', 0);
                }
            }else if(in_array($prom_type,[1,2,6])){
                //抢购，团购
                $query->where('prom_type','in' ,[0,$prom_type])->where('prom_type',0);
            }else{
                $query->where('prom_type',0);
            }
        })->count();
        $Page = new Page($count, 10);
        $goodsList = $Goods->with('specGoodsPrice')->where($where)->where(function ($query) use ($prom_type, $prom_id) {
            if($prom_type == 3){
                //优惠促销
                if ($prom_id) {
                    $query->where(['prom_id' => $prom_id, 'prom_type' => 3])->whereor('prom_id', 0);
                } else {
                    $query->where('prom_type', 0);
                }
            }else if(in_array($prom_type,[1,2,6])){
                //抢购，团购
                $query->where('prom_type','in' ,[0,$prom_type])->where('prom_id',0);
            }else if($prom_type = 'article'){
                //从文章中过来的查询，显示所有商品
                $query->where('prom_type','in' ,[0,1,2,3,4,5,6]);
            }
            else{
                $query->where('prom_type',0);
            }
        })->order('goods_id DESC')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $GoodsLogic = new GoodsLogic();
        $brandList = $GoodsLogic->getSortBrands();
        $categoryList = $GoodsLogic->getSortCategory();

        $this->assign('brandList', $brandList);
        $this->assign('categoryList', $categoryList);
        $this->assign('page', $Page);
        $this->assign('goodsList', $goodsList);
        return $this->fetch($tpl);
    }

    //已选择的商品
    public function addGoodsTopic(){
        $topic_id = input('get.topic_id/d',0);
        $type = I('type/d',0);
        $info = [];
        if ($topic_id > 0) {
            // 活动商品
            $goodsTopic = new GoodsTopic();
            $info = $goodsTopic->topicInfo($topic_id);
            $goodsList = Db::table('tp_goods')->where('goods_id', 'in', $info['goods_id'])->order('goods_id DESC')->select();
            $this->assign('prom_goods', $goodsList);
        }
        $this->assign('info', $info);
        $this->assign('type', $type);
        return $this->fetch();
    }

}