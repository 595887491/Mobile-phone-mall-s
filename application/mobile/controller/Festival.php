<?php
/**
 * Created by PhpStorm.
 * User: 李楠
 * Date: 2018/9/20
 * Time: 上午10:53
 */
namespace app\mobile\controller;

use think\AjaxPage;
use app\common\model\GoodsTopic;
use think\Cache;
use think\Db;
use think\Page;
use think\Session;

class Festival extends MobileBase
{
    private $topicId;//商品专题Id

    public function _initialize()
    {
        parent::_initialize();
//        $nologin = [
//            'getFestivalGoods','checkActivityStatus','autumn','ajaxAutumn'
//        ];
//        $this->checkUserLogin($nologin);
    }


    /**
     * @author:李楠
     * @time:2018-9-20
     * @return string 专题商品商品列表
     */
    public function getFestivalGoods(){
        //获取专题下的商品列表id
        $modal=new GoodsTopic();
        $topicInfo = $modal->topicInfo($this->topicId);
        $goodsId=explode(",",$topicInfo['goods_id']);
        $total=count($goodsId);
        //分页
        $page = new AjaxPage($total,20);
        $a=array_splice($goodsId,$page->firstRow,$page->listRows);
        $data=[];
        foreach($a as $k => $v){
            $data[]=Db::table('tp_goods')
                ->field('goods_id,goods_name,original_img,goods_remark,shop_price,market_price,is_virtual')
                ->where('goods_id',$v)
                ->find();
        }
        return json_encode($data);
    }

    /**
     * @author:李楠
     * @time:2018-9-20
     * @return mixed 中秋专题活动页面
     */
    public function autumn(){
        return $this->fetch('festival/autumn');
    }

    /*
     * @author:李楠
     * @time:2018-9-20
     * @return mixed中秋活动专题商品分页
     */
    public function ajax_autumn(){
        $this->topicId=input('get.topicId')?input('get.topicId'):56;
        $data=$this->getFestivalGoods();
        $data=json_decode($data,true);
        $this->assign('goodsList',$data);
        return $this->fetch('festival/ajax_autumn');
    }

    /**
     * @author:李楠
     * @time:2018-9-20
     * 中秋活动活动规则
     */
    public function autumnRule(){
        return $this->fetch('festival/autumnRule');
    }

}