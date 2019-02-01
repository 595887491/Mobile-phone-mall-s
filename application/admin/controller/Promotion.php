<?php
/**
 * tpshop
 * ============================================================================
 * 版权所有 2015-2027 深圳搜豹网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.tp-shop.cn
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用最新Thinkphp5助手函数特性实现单字母函数M D U等简写方式
 * ============================================================================
 * Author: 当燃
 * 专题管理
 * Date: 2016-03-09
 */

namespace app\admin\controller;

use app\admin\model\FlashSale;
use app\admin\model\Goods;
use app\admin\model\GoodsActivity;
use app\admin\model\GroupBuy;
use app\common\model\GoodsTopic;
use app\common\model\PromGoods;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use think\AjaxPage;
use think\Page;
use app\admin\logic\GoodsLogic;
use think\Loader;
use think\Db;

class Promotion extends Base
{

    public function index()
    {
        return $this->fetch();
    }

    /**
     * 商品活动列表
     */
    public function prom_goods_list()
    {
        $PromGoods = new PromGoods();
        $count = $PromGoods->count();
        $Page = new Page($count, 10);
        $prom_list = $PromGoods->order('start_time desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $this->assign('page',$Page);
        $this->assign('prom_list', $prom_list);
        return $this->fetch();
    }

    public function prom_goods_info()
    {
        $level = M('user_level')->select();
        $this->assign('level', $level);
        $prom_id = I('id');
        $info['start_time'] = date('Y-m-d H:i:s');
        $info['end_time'] = date('Y-m-d H:i:s', time() + 3600 * 60 * 24);
        if ($prom_id > 0) {
            $info = M('prom_goods')->where("id=$prom_id")->find();
            $info['start_time'] = date('Y-m-d H:i:s', $info['start_time']);
            $info['end_time'] = date('Y-m-d H:i:s', $info['end_time']);
            //获取活动商品
            $prom_goods = Db::name('prom_goods_list li')
                ->join('goods g','li.goods_id=g.goods_id','left')
                ->join('spec_goods_price p','li.item_id=p.item_id','left')
                ->field('li.*,g.goods_name,g.shop_price,g.original_img,g.store_count,p.key_name,p.price spec_price,p.store_count spec_store_count')
                ->where('promote_id',$prom_id)->select();
        }
        $coupon_list = M('coupon')->where(['type'=>0,'status'=>1,'use_start_time'=>['lt',time()],'use_end_time'=>['gt',time()]])->select();
        $this->assign('prom_goods', isset($prom_goods)?$prom_goods:[]);
        $this->assign('coupon_list',$coupon_list);
        $this->assign('info', $info);
        $this->assign('min_date', date('Y-m-d H:i:s'));
        $this->initEditor();
        return $this->fetch();
    }

    public function prom_goods_save()
    {
        $prom_id = I('id/d');
        $data = I('post.');
        $promGoodsValidate = Loader::validate('PromGoods');
        if(!$promGoodsValidate->batch()->check($data)){
            $error = '';
            foreach ($promGoodsValidate->getError() as $value){
                $error .= $value.'！';
            }
            $this->ajaxReturn(['status' => 0,'msg' =>$error,'token'=>\think\Request::instance()->token()]);
        }
        $data['start_time'] = strtotime($data['start_time']);
        $data['end_time'] = strtotime($data['end_time']);

        $action = $prom_id>0 ? 'update':'insert';
        $idArr = array_column($data['goods'],'goods_id');
        $goodsArr = Db::name('goods')->where('goods_id',['in',$idArr])->select();
        $newGoodsArr = [];
        array_walk($goodsArr,function($value)use(&$newGoodsArr){
            $newGoodsArr[$value['goods_id']] = $value;
        });
        unset($goodsArr);
        if ($action =='insert') {
            $prom_id = Db::name('prom_goods')->insertGetId($data);
        } else {
            Db::name('prom_goods_list')->where('promote_id',$prom_id)->delete();
            Db::name('prom_goods')->where('id',$prom_id)->update($data);
        }
        $unique = [];//去重
        foreach ($data['goods'] as $inputItem) {
            if (in_array($inputItem['goods_id'].'_'.$inputItem['item_id'],$unique)) {
                continue;
            } else {
                $unique[] = $inputItem['goods_id'].'_'.$inputItem['item_id'];
            }
            $addData[] = [
                'goods_id'  =>$inputItem['goods_id'],
                'promote_id'=>$prom_id,
                'item_id'   =>isset($inputItem['item_id']) ? $inputItem['item_id']:0,
                'promote_price'     =>$this->getPromPrice($inputItem['shop_price'],$data),
                'goods_num' =>$inputItem['num'],
            ];
        }
        Db::name('prom_goods_list')->insertAll($addData);
        $this->ajaxReturn(['status'=>1,'msg'=>$action=='insert'?'新增成功':'修改成功']);
    }
    /**
     * 计算促销价格。
     * @param $Price|原价或者规格价格
     * @return float
     */
    public function getPromPrice($Price,$prom){
        switch ($prom['type']) {
            case 0:
                $promotionPrice = round($Price * $prom['expression'] / 100,2);//打折优惠
                break;
            case 1:
                $promotionPrice = $Price - $prom['expression'];//减价优惠
                break;
            case 2:
                $promotionPrice = $prom['expression'];//固定金额优惠
                break;
            default:
                $promotionPrice = $Price;//原价
                break;
        }
        $promotionPrice = ($promotionPrice >0 ? $promotionPrice : 0); //防止出现负数
        return round($promotionPrice,2);
    }

    public function prom_goods_del()
    {
        $prom_id = I('id');
        Db::name('prom_goods')->where('id',$prom_id)->delete();
        Db::name('prom_goods_list')->where('promote_id',$prom_id)->delete();
        $this->ajaxReturn(['status'=>1,'msg'=>'删除活动成功']);
    }


    /**
     * 活动列表
     */
    public function prom_order_list()
    {
        $parse_type = array('0' => '满额打折', '1' => '满额优惠金额', '2' => '满额送积分', '3' => '满额送优惠券');
        $level = M('user_level')->select();
        if ($level) {
            foreach ($level as $v) {
                $lv[$v['level_id']] = $v['level_name'];
            }
        }
        $count = M('prom_order')->count();
        $Page = new Page($count, 10);
        $show = $Page->show();
        $prom_list = M('prom_order')->limit($Page->firstRow . ',' . $Page->listRows)->select();
//        if ($res) {  //获得适用范围（用户等级）
//            foreach ($res as $val) {
//                if (!empty($val['group']) && !empty($lv)) {
//                    $val['group'] = explode(',', $val['group']);
//                    foreach ($val['group'] as $v) {
//                        $val['group_name'] .= $lv[$v] . ',';
//                    }
//                }
//                $prom_list[] = $val;
//            }
//        }
        $this->assign('pager', $Page);// 赋值分页输出
        $this->assign('page', $show);// 赋值分页输出
        $this->assign("parse_type", $parse_type);
        $this->assign('prom_list', $prom_list);
        return $this->fetch();
    }

    public function prom_order_info()
    {
        $this->assign('min_date', date('Y-m-d'));
        $level = M('user_level')->select();
        $this->assign('level', $level);
        $prom_id = I('id');
        $info['start_time'] = date('Y-m-d');
        $info['end_time'] = date('Y-m-d', time() + 3600 * 24 * 60);
        if ($prom_id > 0) {
            $info = M('prom_order')->where("id=$prom_id")->find();
            $info['start_time'] = date('Y-m-d H:i:s', $info['start_time']);
            $info['end_time'] = date('Y-m-d H:i:s', $info['end_time']);
        }
        $this->assign('info', $info);
        $this->assign('min_date', date('Y-m-d'));
        $this->initEditor();
        return $this->fetch();
    }

    public function prom_order_save()
    {
        $prom_id = I('id');
        $data = I('post.');
        $data['start_time'] = strtotime($data['start_time']);
        $data['end_time'] = strtotime($data['end_time']);
        $data['group'] = $data['group'] ? implode(',', $data['group']) : '';
        if ($prom_id) {
            M('prom_order')->where("id=$prom_id")->save($data);
            adminLog("管理员修改了商品促销 " . I('name'));
        } else {
            M('prom_order')->add($data);
            adminLog("管理员添加了商品促销 " . I('name'));
        }
        $this->success('编辑促销活动成功', U('Promotion/prom_order_list'));
    }

    public function prom_order_del()
    {
        $prom_id = I('id');
        $order = M('order')->where("order_prom_id = $prom_id")->find();
        if (!empty($order)) {
            $this->error("该活动有订单参与不能删除!");
        }

        M('prom_order')->where("id=$prom_id")->delete();
        $this->success('删除活动成功', U('Promotion/prom_order_list'));
    }

    public function group_buy_list()
    {
        $GroupBuy = new GroupBuy();
        $count = $GroupBuy->where('')->count();
        $Page = new Page($count, 10);
        $list = $GroupBuy->where('')->order('id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $this->assign('list', $list);
        $this->assign('page', $Page);
        return $this->fetch();
    }

    public function group_buy()
    {
        $act = I('GET.act', 'add');
        $groupbuy_id = I('get.id/d');
        $group_info = array();
        $group_info['start_time'] = date('Y-m-d');
        $group_info['end_time'] = date('Y-m-d', time() + 3600 * 365);
        if ($groupbuy_id) {
            $GroupBy = new GroupBuy();
            $group_info = $GroupBy->with('specGoodsPrice,goods')->find($groupbuy_id);
            $group_info['start_time'] = date('Y-m-d H:i', $group_info['start_time']);
            $group_info['end_time'] = date('Y-m-d H:i', $group_info['end_time']);
            $act = 'edit';
        }
        $this->assign('min_date', date('Y-m-d'));
        $this->assign('info', $group_info);
        $this->assign('act', $act);
        return $this->fetch();
    }

    public function groupbuyHandle()
    {
        $data = I('post.');
        $data['groupbuy_intro'] = htmlspecialchars(stripslashes($this->request->param('groupbuy_intro')));
        $data['start_time'] = strtotime($data['start_time']);
        $data['end_time'] = strtotime($data['end_time']);
        if ($data['act'] == 'del') {

            $spec_goods = Db::name('spec_goods_price')->where(['prom_type' => 2, 'prom_id' => $data['id']])->find();
            //有活动商品规格
            if($spec_goods){
                Db::name('spec_goods_price')->where(['prom_type' => 2, 'prom_id' => $data['id']])->save(array('prom_id' => 0, 'prom_type' => 0));
                //商品下的规格是否都没有活动
                $goods_spec_num = Db::name('spec_goods_price')->where(['prom_type' => 2, 'goods_id' => $spec_goods['goods_id']])->find();
                if(empty($goods_spec_num)){
                    //商品下的规格都没有活动,把商品回复普通商品
                    Db::name('goods')->where(['goods_id' => $spec_goods['goods_id']])->save(array('prom_id' => 0, 'prom_type' => 0));
                }
            }else{
                //没有商品规格
                Db::name('goods')->where(['prom_type' => 2, 'prom_id' => $data['id']])->save(array('prom_id' => 0, 'prom_type' => 0));
            }
            $r = D('group_buy')->where(['id' => $data['id']])->delete();
            if ($r) exit(json_encode(1));
        }
        $groupBuyValidate = Loader::validate('GroupBuy');
        if($data['item_id'] > 0){
            $spec_goods_price = Db::name("spec_goods_price")->where(['item_id'=>$data['item_id']])->find();
            $data['goods_price'] = $spec_goods_price['price'];
            $data['store_count'] = $spec_goods_price['store_count'];
        }else{
            $goods = Db::name("goods")->where(['goods_id'=>$data['goods_id']])->find();
            $data['goods_price'] = $goods['shop_price'];
            $data['store_count'] = $goods['store_count'];
        }
        if(!$groupBuyValidate->batch()->check($data)){
            $return = ['status' => 0,'msg' =>'操作失败','result' => $groupBuyValidate->getError() ];
            $this->ajaxReturn($return);
        }
        $data['rebate'] = number_format($data['price'] / $data['goods_price'] * 10, 1);
        if ($data['act'] == 'add') {
            $r = Db::name('group_buy')->insertGetId($data);
            if($data['item_id'] > 0){
                //设置商品一种规格为活动
                Db::name('spec_goods_price')->where('item_id',$data['item_id'])->update(['prom_id' => $r, 'prom_type' => 2]);
                Db::name('goods')->where("goods_id", $data['goods_id'])->save(array('prom_id' => 0, 'prom_type' => 2));
            }else{
                Db::name('goods')->where("goods_id", $data['goods_id'])->save(array('prom_id' => $r, 'prom_type' => 2));
            }
        }
        if ($data['act'] == 'edit') {
            $r = Db::name('group_buy')->where(['id' => $data['id']])->update($data);
            if($data['item_id'] > 0){
                //设置商品一种规格为活动
                Db::name('spec_goods_price')->where(['prom_type' => 2, 'prom_id' => $data['id']])->update(['prom_id' => 0, 'prom_type' => 0]);
                Db::name('spec_goods_price')->where('item_id', $data['item_id'])->update(['prom_id' => $data['id'], 'prom_type' => 2]);
                M('goods')->where("goods_id", $data['goods_id'])->save(array('prom_id' => 0, 'prom_type' => 2));
            }else{
                M('goods')->where("goods_id", $data['goods_id'])->save(array('prom_id' => $data['id'], 'prom_type' => 2));
            }
        }
        if ($r !== false) {
            $this->ajaxReturn(['status' => 1,'msg' =>'操作成功','result' => '']);
        } else {
            $this->ajaxReturn(['status' => 0,'msg' =>'操作失败','result' =>'']);
        }
    }

    public function get_goods()
    {
        $case = input('get.case/s','');
        if (empty($case)) {
            $prom_id = I('id/d');
            $prom_where = ['promote_id'=>$prom_id];
            $count = Db::name('prom_goods_list')->where($prom_where)->count();
            $Page = new Page($count, 10);
            $goodsList = Db::name('prom_goods_list li')
                ->join('goods g','g.goods_id=li.goods_id','left')
                ->join('spec_goods_price gp','gp.goods_id=li.goods_id and gp.item_id=li.item_id','left')
                ->field('li.*,g.goods_name,g.store_count,g.shop_price,gp.store_count as spec_store_count,gp.price as spec_price')
                ->where($prom_where)->order('g.goods_id DESC')
                ->limit($Page->firstRow . ',' . $Page->listRows)->select();
            $show = $Page->show();
            $this->assign('page', $show);
            $this->assign('goodsList', $goodsList);
        } elseif ($case == 'topic') {
            $topic_id = input('get.topic_id/d');
            $goodsTopic = new GoodsTopic();
            $info = $goodsTopic->topicInfo($topic_id);
//            halt( $info);
            $count = count(explode(',', $info['goods_id']));
            $Page = new Page($count, 10);
            $goodsList = Db::table('tp_goods')->where('goods_id', 'in', $info['goods_id'])->order('goods_id DESC')->limit($Page->firstRow . ',' . $Page->listRows)->select();

            $show = $Page->show();
            $this->assign('page', $show);
            $this->assign('goodsList', $goodsList);
        }
        return $this->fetch();
    }

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
        $Goods = new Goods();
        /*后台拼团管理添加已下架拼团商品 --start--  @Author:赵磊*/
        if ($prom_type == 6){
            $condition['prom_type'] = 0;
            $normal_goods = Db::table('tp_goods')->field('goods_id')->where($condition)->select();
            $normal_goods = array_column($normal_goods,'goods_id');
            $team_goods = Db::table('tp_team_activity')->field('goods_id')->where('status',0)->select();
            $team_goods = array_column($team_goods,'goods_id');
            $team_goods = array_merge($normal_goods,$team_goods);
            $where['goods_id'] = ['in',$team_goods];//拼团未上架商品+正常商品
        }
        /*后台拼团管理添加已下架拼团商品 --end--  @Author:赵磊*/
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
                $query->where('prom_type','in' ,[0,$prom_type]);
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
                $query->where('prom_type','in' ,[0,$prom_type]);
            }else if($prom_type = 'article'){
                //从文章中过来的查询，显示所有商品
                $query->where('prom_type','in' ,[0,1,2,3,4,5,6]);
            }
            else{
                $query->where('prom_type',0);
            }
        })->order('goods_id DESC')->limit($Page->firstRow . ',' . $Page->listRows)->select();

        $GoodsLogic = new GoodsLogic;
        $brandList = $GoodsLogic->getSortBrands();
        $categoryList = $GoodsLogic->getSortCategory();
        $this->assign('brandList', $brandList);
        $this->assign('categoryList', $categoryList);
        $this->assign('page', $Page);
        $this->assign('goodsList', $goodsList);
        return $this->fetch($tpl);
    }

    //限时抢购
    public function flash_sale()
    {
        $FlashSale = new FlashSale();
        $count = $FlashSale->flashList(true);
        $Page = new Page($count, 10);
        $show = $Page->show();
        $prom_list = $FlashSale->flashList(false,$Page->firstRow,$Page->listRows);
        foreach ($prom_list as &$value){
            $value['status_desc'] = $FlashSale->getStatusDesc($value);
        }
        $this->assign('prom_list', collection($prom_list)->toArray());
        $this->assign('page', $show);// 赋值分页输出
        $this->assign('pager', $Page);
        return $this->fetch();
    }
    //秒杀
    public function flash_sale_info()
    {
        if (IS_POST) {
            $data = I('post.');
            $data['start_time'] = strtotime($data['start_time']);
            $data['end_time'] = strtotime($data['end_time']);
            $flashSaleValidate = Loader::validate('FlashSale');
            if (!$flashSaleValidate->batch()->check($data)) {
                $return = ['status' => 0, 'msg' => '操作失败', 'result' => $flashSaleValidate->getError()];
                $this->ajaxReturn($return);
            }
            $idArr = array_column($data['goods'],'goods_id');
            $goodsArr = Db::name('goods')->where('goods_id',['in',$idArr])->select();
            $newGoodsArr = [];
            array_walk($goodsArr,function($value)use(&$newGoodsArr){
                $newGoodsArr[$value['goods_id']] = $value;
            });
            unset($goodsArr);

            //如果此开始时间在数据库已经存在，则为修改；否则为新增
            $action = (Db::name('flash_sale')->where('start_time',$data['start_time'])->count())>0?'update':'insert';
            if($action== 'update') {
                Db::name('flash_sale')->where('start_time',$data['start_time'])->delete();//删掉原来的数据
            }
            $unique = [];//去重
            foreach ($data['goods'] as $inputItem) {
                if (in_array($inputItem['goods_id'].'_'.$inputItem['item_id'],$unique)) {
                    continue;
                } else {
                    $unique[] = $inputItem['goods_id'].'_'.$inputItem['item_id'];
                }
                $addData[] = [
                    'title'=>$data['title'],
                    'start_time'=>$data['start_time'],
                    'end_time'  =>$data['end_time'],
                    'goods_id'  =>$inputItem['goods_id'],
                    'item_id'   =>isset($inputItem['item_id']) ? intval($inputItem['item_id']):0,
                    'price'     =>$inputItem['price'],
                    'goods_num' =>$inputItem['goods_num'],
                    'buy_limit' =>$inputItem['limit'],
                    'goods_name'=>$newGoodsArr[$inputItem['goods_id']]['goods_name'],
                    'flash_order'=>$inputItem['flash_order'],
                    'flash_desc'    => $data['flash_desc'],
                ];
            }
            if ($action == 'update') {
                adminLog('修改秒杀活动：'.date('Y-m-d H:i:s',$data['start_time']).'场，商品数量：'.count($unique));
            } else {
                adminLog('新增秒杀活动：'.date('Y-m-d H:i:s',$data['start_time']).'场，商品数量：'.count($unique));
            }
            Db::name('flash_sale')->insertAll($addData);
            $this->ajaxReturn(['status'=>1,'msg'=>$action=='insert'?'新增成功':'修改成功']);
        }

        $start_time = input('start_time/d');
        if ($start_time) {
            if (input('act')=='copy') {
                $now_time = date('H');
                if ($now_time % 2 == 0) {
                    $flash_now_time = $now_time;
                } else {
                    $flash_now_time = $now_time - 1;
                }
                $flash_sale_time = strtotime(date('Y-m-d') . " " . $flash_now_time . ":00:00");
                $info['start_time'] = date("Y-m-d H:i:s", $flash_sale_time);
                $info['end_time'] = date("Y-m-d H:i:s", $flash_sale_time + 7200);
            } else {
                $info = Db::name('flash_sale')->where('start_time',$start_time)->find();
                $info['start_time'] = date('Y-m-d H:i:s', $info['start_time']);
                $info['end_time'] = date('Y-m-d H:i:s', $info['end_time']);
            }
            $goodsList = Db::name('flash_sale f')
                ->join('goods g','f.goods_id=g.goods_id','left')
                ->join('spec_goods_price s','f.goods_id=s.goods_id and f.item_id=s.item_id','left')
                ->field('f.*,g.store_count,g.shop_price,g.original_img,s.key,s.key_name,s.store_count as spec_store_count, s.price as spec_price')->where('start_time',$start_time)->order('f.id', 'ASC')->select();
        } else {
            $now_time = date('H');
            if ($now_time % 2 == 0) {
                $flash_now_time = $now_time;
            } else {
                $flash_now_time = $now_time - 1;
            }
            $flash_sale_time = strtotime(date('Y-m-d') . " " . $flash_now_time . ":00:00");
            $info['start_time'] = date("Y-m-d H:i:s", $flash_sale_time);
            $info['end_time'] = date("Y-m-d H:i:s", $flash_sale_time + 7200);
            $goodsList = [];
        }
//        halt($goodsList);
        $this->assign('goodsList',$goodsList);
        $this->assign('info', $info);
        $this->assign('min_date', date('Y-m-d'));
        return $this->fetch();
    }

    public function flash_sale_del()
    {
        $time = input('start_time/d');
        if ($time) {
            Db::name('flash_sale')->where('start_time',$time)->delete();
            adminLog('删除秒杀活动：开始时间 '.date('Y-m-d H:i:s',$time).'场');
            exit(json_encode(1));
        } else {
            exit(json_encode(0));
        }
    }


    private function initEditor()
    {
        $this->assign("URL_upload", U('Admin/Ueditor/imageUp', array('savepath' => 'promotion')));
        $this->assign("URL_fileUp", U('Admin/Ueditor/fileUp', array('savepath' => 'promotion')));
        $this->assign("URL_scrawlUp", U('Admin/Ueditor/scrawlUp', array('savepath' => 'promotion')));
        $this->assign("URL_getRemoteImage", U('Admin/Ueditor/getRemoteImage', array('savepath' => 'promotion')));
        $this->assign("URL_imageManager", U('Admin/Ueditor/imageManager', array('savepath' => 'promotion')));
        $this->assign("URL_imageUp", U('Admin/Ueditor/imageUp', array('savepath' => 'promotion')));
        $this->assign("URL_getMovie", U('Admin/Ueditor/getMovie', array('savepath' => 'promotion')));
        $this->assign("URL_Home", "");
    }

    /**
     * 商品预售列表
     *
     */
    public function pre_sell_list()
    {
    
        $condition = array('act_type' => 1);
        I('keywords') && $condition['goods_name'] = I('keywords');
        $model = D('goods_activity');
        $count = $model->where($condition)->count();
        $Page = new Page($count, 10);
        $show = $Page->show();
        $pre_sell_list = $model->where($condition)->order("act_id desc")->limit($Page->firstRow . ',' . $Page->listRows)->select();
        foreach ($pre_sell_list as $key => $val) {
            $pre_sell_list[$key] = array_merge($pre_sell_list[$key]->toArray(), unserialize($pre_sell_list[$key]['ext_info']));
            $pre_sell_list[$key]['act_status'] = $model->getPreStatusAttr($pre_sell_list[$key]);
            $pre_count_info = $model->getPreCountInfo($pre_sell_list[$key]['act_id'], $pre_sell_list[$key]['goods_id']);
            $pre_sell_list[$key] = array_merge($pre_sell_list[$key], $pre_count_info);
            $pre_sell_list[$key]['price'] = $model->getPrePrice($pre_sell_list[$key]['total_goods'], $pre_sell_list[$key]['price_ladder']);
        }
        $this->assign('pre_sell_list', $pre_sell_list);
        $this->assign('page', $show);// 赋值分页输出
        $this->assign('pager', $Page);
        return $this->fetch();
    	
    }

    /**
     * 预售商品商品详情页
     */
    public function pre_sell_info()
    {
        
        if (IS_POST) {
            $data = I('post.');
            $goods_logic = new GoodsLogic();
            $save = $goods_logic->savePreSell($data);
            if ($save['status']) {
                $this->success($save['msg'], U('Promotion/pre_sell_list'));
                exit();
            } else {
                $this->error($save['msg']);
            }
        }
        $id = I('id');
        $default_time['start_time'] = date('Y-m-d H:i:s');
        $default_time['end_time'] = date('Y-m-d 23:59:59', time() + 3600 * 24 * 7);
        if ($id > 0) {
            $goods_activity_model = new GoodsActivity();
            $goods_activity = $goods_activity_model->with('goods')->where(array('act_id' => $id, 'act_type' => 1))->find();
            $info = $goods_activity->toArray();
            $pre_count_info = $goods_activity_model->getPreCountInfo($info['act_id'], $info['goods_id']);
            if (empty($info)) {
                $this->error('该预售商品活动已被删除或者不存在', U('Promotion/pre_sell_list'));
            }
            $info['start_time'] = date('Y-m-d H:i', $info['start_time']);
            $info['end_time'] = date('Y-m-d H:i', $info['end_time']);
            $info = array_merge($info, unserialize($info['ext_info']));
            if (!empty($info['retainage_start']) || !empty($info['retainage_start'])) {
                $info['retainage_start'] = date('Y-m-d H:i', $info['retainage_start']);
                $info['retainage_end'] = date('Y-m-d H:i', $info['retainage_end']);
            }
            $this->assign('pre_count_info', $pre_count_info);//预售商品的订购数量和订单数量
            $this->assign('info', $info);
        }
        $this->assign('default_time', $default_time);
        $this->assign('min_date', date('Y-m-d'));
        return $this->fetch();
    	
    }

    /**
     * 预售商品删除处理
     */
    public function pre_sell_del()
    {
        
        $id = I('del_id');
        if ($id) {
            $goods_activity = M('goods_activity')->where(array('act_id' => $id, 'act_type' => 1))->find();
            if (empty($goods_activity)) {
                exit(json_encode(array('status' => 0, 'msg' => '删除的商品不存在')));
            }
            $goods_activity = array_merge($goods_activity, unserialize($goods_activity['ext_info']));
            if ($goods_activity['is_finished'] == 0) {
                if (($goods_activity['start_time'] <= time() && $goods_activity['end_time'] > time()) && ($goods_activity['act_count'] < $goods_activity['restrict_amount'])) {
                    exit(json_encode(array('status' => 0, 'msg' => '该预售商品正在预售中不能删除，请先结束活动，并编辑活动失败')));
                }
                if ($goods_activity['end_time'] < time()) {
                    exit(json_encode(array('status' => 0, 'msg' => '该预售商品结束未处理，请先编辑活动失败')));
                }
            }
            $pre_sell_order_count_where = array(
                'prom_type' => 4,
                'prom_id' => $id,
//					'order_status' => array('neq', 5)
            );
            $pre_sell_order_count = M('order')->where($pre_sell_order_count_where)->count();
            if ($pre_sell_order_count > 0) {
                exit(json_encode(array('status' => 0, 'msg' => '该预售商品已有' . $pre_sell_order_count . '个订单,不能删除')));
            } else {
                M('goods_activity')->where("act_id=$id")->delete();
                M('goods')->where(array('prom_type' => 4, 'goods_id' => $goods_activity['goods_is']))->save(array('prom_id' => 0, 'prom_type' => 0, 'is_on_sale' => 0));
            }
            exit(json_encode(array('status' => 1, 'msg' => '删除成功,并下架了该商品')));
        } else {
            exit(json_encode(array('status' => 0, 'msg' => '非法操作')));
        }
	    
    }

    /**
     * 预售活动成功
     */
    public function pre_sell_success()
    {
        
        $act_id = I('id');
        if (empty($act_id)) {
            $this->error('非法操作');
        }
        $goods_activity = M('goods_activity')->where(array('act_id' => $act_id, 'act_type' => 1))->find();
        $goods_activity = array_merge($goods_activity, unserialize($goods_activity['ext_info']));
        if (empty($goods_activity)) {
            $this->error('该预售商品不存在');
        }
        if ($goods_activity['is_finished'] != 0) {
            $this->error('该预售商品已经结束');
        }
        if (($goods_activity['start_time'] <= time() && $goods_activity['end_time'] > time()) && ($goods_activity['act_count'] < $goods_activity['restrict_amount'])) {
            $this->error('该预售商品正在预售中，请先结束活动');
        }
        //获取预售商品最后的价格
        $pre_count_info = D('goods_activity')->getPreCountInfo($goods_activity['act_id'], $goods_activity['goods_id']);
        $pre_sell_final_price = D('goods_activity')->getPrePrice($pre_count_info['total_goods'], $goods_activity['price_ladder']);
        //获取购买预售商品的订单id数组
        $pre_sell_order_id_where = array(
            'prom_type' => 4,
            'prom_id' => $goods_activity['act_id'],
            'order_status' => 0
        );
        $pre_sell_order_id_list = M('order')->where($pre_sell_order_id_where)->getField('order_id', true);
        if (count($pre_sell_order_id_list) > 0) {
            //更新所有预售商品的订单的订单商品的金额
            M('order_goods')->where(array('order_id' => array('IN', $pre_sell_order_id_list)))->save(array('member_goods_price' => $pre_sell_final_price));
            //获取所有更新后的订单商品的商品总价
            $pre_sell_order_goods = M('order_goods')
                ->field('order_id,SUM(goods_num*member_goods_price) as goods_amount')
                ->where(array('order_id' => array('IN', $pre_sell_order_id_list)))
                ->group('order_id')
                ->select();
            //更新订单的价格
            foreach ($pre_sell_order_goods as $key => $val) {
                $able_message = false;//是否需要通知用户
                $message = '';
                $pre_sell_order = M('order')->field('order_sn,user_id,order_id,user_id,paid_money,pay_status,order_amount')->where(array('order_id' => $pre_sell_order_goods[$key]['order_id']))->find();
                //如果订单未支付的将其作废
                if ($pre_sell_order['pay_status'] == 0) {
                    M('order')->where(array('order_id' => $pre_sell_order_goods[$key]['order_id']))->save(array('order_status' => 5));
                }
                //如果是支付定金的
                if ($pre_sell_order['paid_money'] > 0 && $pre_sell_order['pay_status'] == 2) {
                    $save_data = array(
                        'goods_price' => $pre_sell_order_goods[$key]['goods_amount'],
                        'total_amount' => $pre_sell_order_goods[$key]['goods_amount'],
                        'order_amount' => $pre_sell_order_goods[$key]['goods_amount'] - $pre_sell_order['paid_money']//需要支付的尾款
                    );
                    M('order')->where(array('order_id' => $pre_sell_order_goods[$key]['order_id']))->save($save_data);
                    $able_message = true;
                    $message = '您的预售订单需要支付尾款，订单号为' . $pre_sell_order['order_sn'];
                }
                //如果是支付全款的
                if ($pre_sell_order['paid_money'] == 0 && $pre_sell_order['pay_status'] == 1) {
                    //如果需要退还差价的
                    if ($pre_sell_order['order_amount'] > $pre_sell_order_goods[$key]['goods_amount']) {
                        $save_data2 = array(
                            'goods_price' => $pre_sell_order_goods[$key]['goods_amount'],
                            'total_amount' => $pre_sell_order_goods[$key]['goods_amount'],
                            'order_amount' => $pre_sell_order_goods[$key]['goods_amount']
                        );
                        M('order')->where(array('order_id' => $pre_sell_order_goods[$key]['order_id']))->save($save_data2);
                        $cha_amount = $pre_sell_order['order_amount'] - $pre_sell_order_goods[$key]['goods_amount'];
                        accountLog($pre_sell_order['user_id'], $cha_amount, 0, '退还预售商品' . $goods_activity['act_name'] . '的差价，订单ID为' . $pre_sell_order['order_id'], $pre_sell_order['order_sn']);
                    }
                }
                //通知用户订单处理
                if ($able_message == true) {
                    $user_info = M('users')->where('user_id = ' . $pre_sell_order['user_id'])->find();
                    if (!empty($user_info)) {
                        if (!empty($user_info['email'])) {
                            //send_email($user_info['email'], '预售订单处理', $message);
                        }
                    }
                }
            }
        }

        M('goods_activity')->where(array('act_id' => $act_id, 'act_type' => 1))->save(array('is_finished' => 1));
        M('goods')->where(array('prom_type' => 4, 'goods_id' => $goods_activity['goods_id']))->save(array('prom_id' => 0, 'prom_type' => 0, 'is_on_sale' => 0));
        $this->success('该预售商品成功结束,并下架了该商品', U('Admin/Promotion/pre_sell_list'));
    }

    /**
     * 预售活动失败
     */
    public function pre_sell_fail()
    {
        $act_id = I('id');
        if (empty($act_id)) {
            $this->error('非法操作');
        }
        $goods_activity = M('goods_activity')->where(array('act_id' => $act_id, 'act_type' => 1))->find();
        $goods_activity = array_merge($goods_activity, unserialize($goods_activity['ext_info']));
        if (empty($goods_activity)) {
            $this->error('该预售商品不存在');
        }
        if ($goods_activity['is_finished'] != 0) {
            $this->error('该预售商品已经结束');
        }
        if (($goods_activity['start_time'] <= time() && $goods_activity['end_time'] > time()) && ($goods_activity['act_count'] < $goods_activity['restrict_amount'])) {
            $this->error('该预售商品正在预售中，请先结束活动');
        }
        //获取购买预售商品的并且已经支付的订单id
        $pre_sell_order_where = array(
            'prom_type' => 4,
            'prom_id' => $goods_activity['act_id'],
            'order_status' => 0,
            'pay_status' => array(array('eq', 1), array('eq', 2), 'or')
        );
        $pre_sell_order_list = M('order')->field('user_id,order_id,order_sn,pay_status,goods_price,total_amount,order_amount,paid_money')->where($pre_sell_order_where)->select();
        foreach ($pre_sell_order_list as $key => $val) {
            //如果是支付定金的
            if ($pre_sell_order_list[$key]['paid_money'] > 0 && $pre_sell_order_list[$key]['pay_status'] == 2) {
                //退还订金
                accountLog($pre_sell_order_list[$key]['user_id'], $pre_sell_order_list[$key]['paid_money'], 0, '退还预售商品' . $goods_activity['act_name'] . '的定金，订单ID为：' . $pre_sell_order_list[$key]['order_id'], 0, $pre_sell_order_list[$key]['order_id'], $pre_sell_order_list[$key]['order_sn']);
            }
            //如果是支付全款的
            if ($pre_sell_order_list[$key]['paid_money'] == 0 && $pre_sell_order_list[$key]['pay_status'] == 1) {
                //退还全款
                accountLog($pre_sell_order_list[$key]['user_id'], $pre_sell_order_list[$key]['order_amount'], 0, '退还预售商品' . $goods_activity['act_name'] . '的全款，订单ID为：' . $pre_sell_order_list[$key]['order_id'], 0, $pre_sell_order_list[$key]['order_id'], $pre_sell_order_list[$key]['order_sn']);
            }
        }
        //最后把该预售商品的订单标记已作废
        $pre_sell_order_cancel_where = array(
            'prom_type' => 4,
            'prom_id' => $goods_activity['act_id'],
            'order_status' => 0,
        );
        M('order')->where($pre_sell_order_cancel_where)->save(array('order_status' => 5));
        M('goods_activity')->where(array('act_id' => $act_id, 'act_type' => 1))->save(array('is_finished' => 2));
        M('goods')->where(array('prom_type' => 4, 'goods_id' => $goods_activity['goods_id']))->save(array('prom_id' => 0, 'prom_type' => 0, 'is_on_sale' => 0));
        $this->success('该预售商品失败结束,并下架了该商品', U('Admin/Promotion/pre_sell_list'));
	    
    }

    public function goodsTopicList(){
        $goodsTopic = new GoodsTopic();
        $p = I('p/d',1);
        $sort= I('sort/s','asc');
        $type= I('type/s',0);
        $status = I('status/d');
        $keywords = trim(I('keywords/s'));

        $where = [
            'is_select' => $type,
        ];
        if ($status !== '') {
            $where['status'] = $status;
        }
        if ($keywords) {
            $where['topic_id|topic_name'] = ['like','%'.$keywords.'%'];
        }
        $count = $goodsTopic->where($where)->count();
        $Page = new Page($count, 10);
        $topicList = $goodsTopic
            ->order('topic_id '.$sort)
            ->where($where)
            ->limit($Page->firstRow . ',' . $Page->listRows)
            ->select();
        foreach ($topicList as &$v) {
            $v['goods_num'] = count(explode(',',$v['goods_id']));
        }
        $this->assign('page',$Page);
        $this->assign('p',$p);
        $this->assign('sort',$sort);
        $this->assign('type',$type);
        $this->assign('status',$status);
        $this->assign('keywords',$keywords);
        $this->assign('url',U('Admin/Promotion/goodsTopicList',[
//            'p' => $p,
//            'sort' => $sort,
            'type' => $type,
//            'status' => $status,
//            'keywords' => $keywords,
        ]));
        $this->assign('topic_list', $topicList);
        return $this->fetch();
    }

    public function changeGoodsTopicStatus()
    {
        $topicId = I('topic_id/d');
        $status = I('status/d',1);
        $res = Db::table('cf_goods_topic')->where('topic_id',$topicId)->update([
            'status' => $status
        ]);

        if ($res) {
            return outPut(1,'操作成功');
        }
        return outPut(-1,'操作失败');
    }

    public function changeGoodsTopicSort()
    {
        $topicId = I('topic_id/d');
        $showOrder = I('show_order/d',0);
        $res = Db::table('cf_goods_topic')->where('topic_id',$topicId)->update([
            'show_order' => $showOrder
        ]);

        if ($res) {
            return outPut(1,'操作成功');
        }
        return outPut(-1,'操作失败');
    }

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

    public function goodsTopicSave(){
        $data = input("post.");
        if (empty($data['topic_name'])) {
            $this->ajaxReturn(['status'=>0, 'msg'=>'专题标题不能为空']);
        }
        if (empty($data['topic_img'])) {
            $this->ajaxReturn(['status'=>0, 'msg'=>'专题图片不能为空']);
        }
        if (empty($data['goods'])) {
            $this->ajaxReturn(['status'=>0, 'msg'=>'请选择专题商品']);
        }
        $goods_arr = array_keys($data['goods']);
        $data['goods_id'] = join(',',array_unique($goods_arr));
        $topic_id = $data['topic_id'];
        if ($topic_id) {
            Db::table('cf_goods_topic')->where("topic_id=$topic_id")->save($data);
            adminLog("管理员修改了商品专题 " . $data['topic_name']);
        } else {
            Db::table('cf_goods_topic')->add($data);
            adminLog("管理员添加了商品专题 " . $data['topic_name']);
        }
        $this->ajaxReturn(['status'=>1, 'msg'=>'操作专题成功']);
    }

    public function delGoodsTopic(){
        $topic_id = input("get.topic_id/d");
        $res = Db::table('cf_goods_topic')->where('topic_id',$topic_id)->delete();
        if ($res) {
            $this->ajaxReturn(['status'=>1, 'msg'=>'删除成功']);
        } else {
            $this->ajaxReturn(['status'=>0, 'msg'=>'删除失败']);
        }
    }

    /**
     * 导入Excel文件
     */
    public function dealFlashFile(){
        $file = request()->file('file');
        //移动到框架应用根目录/public/uploads/excel 目录下
        $path = ROOT_PATH . 'public' . DS . 'upload'. DS .'excel';
        $info = $file->move($path,mt_rand(0,99999));
        if ($info) {
            $spreadsheet = IOFactory::load($path . DS . $info->getFilename());
            $sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
            unlink($path . DS . $info->getFilename());//删除文件

            unset($sheetData[1]);
            $goodsRes = [];
//            halt($goodsRes);
            $Goods = new Goods();
            foreach ($sheetData as $value) {
                $goods_sn = trim($value['A']);
                $price  = sprintf("%.2f",$value['D']);
                $goods_num   = intval($value['E']);
                $buy_limit  = intval($value['F']);
                $flash_order = !empty($value['G'])? intval($value['G']) : 99;
                if ($goods_sn) {
                    $goods = $Goods->with('specGoodsPrice')->field('goods_id,original_img as goods_image,goods_name, shop_price as goods_price,store_count')->where('goods_sn',$goods_sn)->find();
                    if ($goods) {
                        $goods['price'] = $price;
                        $goods['goods_num'] = $goods_num;
                        $goods['buy_limit'] = $buy_limit;
                        $goods['flash_order'] = $flash_order;
                        if (!empty($goods['spec_goods_price'])) {
                            $goods['spec'] = $goods['spec_goods_price'];
                        }
                        $goodsRes[] = $goods->toArray();
                    }
                }
            }
            $this->ajaxReturn(['status'=>1,'msg'=>'','data'=>$goodsRes]);
        } else {
            //上传失败获取错误信息
            $this->error(['status'=>0,'msg'=>$file->getError()]);
        }
    }
    public function downloadExcel () {
        $file_dir  = ROOT_PATH . 'public' . DS . 'upload'. DS .'excel'.DS;
        $file_name = 'flash_template.xlsx';
        //以只读和二进制模式打开文件
        $file = fopen ( $file_dir . $file_name, "rb" );

        //告诉浏览器这是一个文件流格式的文件
        Header ( "Content-type: application/octet-stream" );
        //请求范围的度量单位
        Header ( "Accept-Ranges: bytes" );
        //Content-Length是指定包含于请求或响应中数据的字节长度
        Header ( "Accept-Length: " . filesize ( $file_dir . $file_name ) );
        //用来告诉浏览器，文件是可以当做附件被下载，下载后的文件名称为$file_name该变量的值。
        Header ( "Content-Disposition: attachment; filename=秒杀商品导入模板.xlsx" );

        //读取文件内容并直接输出到浏览器
        echo fread ( $file, filesize ( $file_dir . $file_name ) );
        fclose ( $file );
        exit ();
//        readfile(ROOT_PATH . 'public' . DS . 'upload'. DS .'excel'.DS.'flash_template.xlsx');

//        $spreadsheet = new Spreadsheet();
//        $sheet = $spreadsheet->getActiveSheet();
//        $sheet->setCellValue('A1','商品条码');
//        $sheet->setCellValue('B1','商品名称');
//        $sheet->setCellValue('C1','平台原售价');
//        $sheet->setCellValue('D1','秒杀价格');
//        $sheet->setCellValue('E1','秒杀库存');
//        $sheet->setCellValue('F1','限购数量');
//        $sheet->setCellValue('G1','排序');
//        $sheet->setCellValue('A2','671302011287');
//        $sheet->setCellValue('B2','加拿大阿莎娜 特惠装（日用10p+夜用8p）');
//        $sheet->setCellValue('C2','24.5');
//        $sheet->setCellValue('D2','23.5');
//        $sheet->setCellValue('E2','23');
//        $sheet->setCellValue('F2','23');
//        $sheet->setCellValue('G2','');
//        $writer = new Xlsx($spreadsheet);
//        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');//告诉浏览器输出07Excel文件
////header(‘Content-Type:application/vnd.ms-excel‘);//告诉浏览器将要输出Excel03版本文件
//        header('Content-Disposition: attachment;filename="import.xlsx"');//告诉浏览器输出浏览器名称
//        header('Cache-Control: max-age=0');//禁止缓存
//        $writer->save('php://output');
    }
}