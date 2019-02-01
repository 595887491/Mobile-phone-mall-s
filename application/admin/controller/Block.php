<?php
/**
 * tpshop
 * ============================================================================
 * 版权所有 2015-2027 深圳搜豹网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.tp-shop.cn
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 如果商业用途务必到官方购买正版授权, 以免引起不必要的法律纠纷.
 * ============================================================================
 * Author: 聂晓克      
 * Date: 2017-12-14
 */
namespace app\admin\controller;
use app\admin\logic\GoodsLogic;
use app\common\logic\GoodsActivityLogic;
use app\common\logic\ActivityLogic;

use think\Db;

class Block extends Base{

	public function index(){
        
        $id=I('get.id');
        if($id){
	        $arr=M('mobile_template')->where('id='.$id)->field('template_html,block_info,template_name')->find();
		    $html=htmlspecialchars_decode($arr['template_html']);

	       	$this->assign('html',$html);
	       	$this->assign('info',$arr['block_info']);
	       	$this->assign('template_name',$arr['template_name']);
	       	$this->assign('id',$id);
        }

        $page_list=M('mobile_template')->field('id,template_name,add_time,is_index')->select();
		
       	$cat_list = Db::name('goods_category')->where("parent_id = 0 and is_show=1")->select(); // 联动菜单第一级

       	$cat_tree=$cat_list;
       	foreach ($cat_tree as $k => $v) {
       		$cat_tree[$k]['son'] = Db::name('goods_category')->where("parent_id =".$v['id']."  and is_show=1")->select(); // 菜单第二级
       	}

       	//商品列表数据
       	$goodsList = M('Goods')->where('is_on_sale=1')->order($order_str)->page(1,10)->select();
       	$count=M('Goods')->where('is_on_sale=1')->count();
        $count=ceil($count/10);

       	//优惠券列表数据
       	$atype = I('atype', 1);
        $user = session('user');
        $p = I('p', '');
        $activityLogic = new ActivityLogic();
        $result = $activityLogic->getCouponCenterList($cat_id, $this->user_id, $p);

        $this->assign('coupon_list',$result);
       	$this->assign('page_list',$page_list);
       	$this->assign('cat_list',$cat_list);
       	$this->assign('cat_tree',$cat_tree);
       	$this->assign('goodsList',$goodsList);
       	$this->assign('count',$count);
		return $this->fetch();
          
	}

	//自定义页面列表页
	public function pageList(){
            
		$list=M('mobile_template')->field('id,template_name,add_time,is_index')->select();
		$this->assign('list',$list);
		return $this->fetch();
                
	}

	public function ajaxGoodsList(){
            
        $page=I('page');
        $where = 'is_on_sale=1'; // 搜索条件                
        // 关键词搜索               
        $key_word = I('keywords') ? trim(I('keywords')) : '';
        if($key_word){
            $where = "$where and (goods_name like '%$key_word%' or goods_sn like '%$key_word%')" ;
        }
        
        $count = M('Goods')->where($where)->count();
        $goodsList = M('Goods')->where($where)->order($order_str)->page($page,8)->select();

        $html='';
        foreach ($goodsList as $k => $v) {
        	$html.='<ul class="p-goods-item">';
        	$html.='<li class="pi-li0"><input type="checkbox" value="'.$v[goods_id].'" /></li>';
        	$html.='<li class="pi-li1">'.$v[goods_id].'</li>';
        	$html.='<li class="pi-li2">'.$v[goods_name].'</li>';
        	$html.='<li class="pi-li3"><img src="'.$v[original_img].'" alt="" /></li>';
        	$html.='<li class="pi-li4">'.$v[cat_id].'</li>';
        	$html.='<li class="pi-li4">'.$v[shop_price].'</li>';
        	$html.='<li class="pi-li4">'.$v[store_count].'</li>';
        	$html.='</ul>';
        }
        $result['html']=$html;
        $result['count']=ceil($count/10);

        $this->ajaxReturn(['status' => 1, 'msg' => '成功', 'result' =>$result]);
        
    }

    //商品列表板块参数设置
    public function goods_list_block(){
        
    	$data=I('post.');
    	$count=I('post.num',4);	

    	if($data['ids']){
    		$ids = substr($data['ids'],0,strlen($data['ids'])-1); 	//ids是前台传递过来的商品2级分类
    	}
    	
    	if($ids){
    		$ids="(".$ids.")";
    		//此处前台传递的是2级分类id 需要获取它的3级分类
    		$cat_ids=Db::name('goods_category')->where("parent_id in".$ids." and is_show=1")->getField('id',true);	
    	}
    	if($cat_ids){
    		$str="(".implode(",",$cat_ids).")";
    	}
    	
    	$where='is_on_sale=1';
    	if($cat_ids){
    		$where.=" and cat_id in".$str;
    	}
    	if($data['label']){
    		$where.=" and ".$data['label']."=1";
    	}
    	if($data['min_price']){
    		$where.=" and shop_price>".$data['min_price'];
    	}
    	if($data['max_price']){
    		$where.=" and shop_price<".$data['max_price'];
    	}
    	if($data['goods']){
    		$goods_id = substr($data['goods'],0,strlen($data['goods'])-1);
    		$goods_id = "(".$goods_id.")";
    		$where.=" and goods_id in".$goods_id;
    	}


    	switch ($data['order']) {
    		case '0':
    			$order_str="sales_sum DESC";
    			break;
    		
    		case '1':
    			$order_str="sales_sum ASC";
    			break;

    		case '2':
    			$order_str="shop_price DESC";
    			break;

    		case '3':
    			$order_str="shop_price ASC";
    			break;

    		case '4':
    			$order_str="last_update DESC";
    			break;

    		case '5':
    			$order_str="last_update ASC";
    			break;
    	}

    	$goodsList = M('Goods')->where($where)->order($order_str)->limit(0,$count)->select();

        $html='';
        foreach ($goodsList as $k => $v) {
            $html.='<li>';
            $html.='<a class="tpdm-goods-pic" href="/Mobile/Goods/goodsInfo/id/'.$v["goods_id"].'"><img src="'.$v["original_img"].'" alt="" /></a>';
            $html.='<a href="/Mobile/Goods/goodsInfo/id/'.$v["goods_id"].'" class="tpdm-goods-name">'.$v["goods_name"].'</a>';
            $html.='<div class="tpdm-goods-des">';
            $html.='<div class="tpdm-goods-price">￥'.$v['shop_price'].'</div>'; 
            $html.='<a class="tpdm-goods-like" href="/Mobile/Goods/goodsList/id/'.$v["cat_id3"].'">看相似</a>'; 
            $html.='</div>';
            $html.='</li>';
        }
    	$this->ajaxReturn(['status' => 1, 'msg' => '成功', 'result' =>$html]);
         
    }

	/*
	*保存编辑完成后的信息
	*/
	public function add_data(){
            
 		$param=I('post.');
 		//print_r($param['info']);exit();
 		$html=$param['html'];
 		$html=str_replace("\n"," ",$html);

 		$data['add_time']=time();
 		$data['template_html']=$html;
 		$data['block_info']=$param['info'];
 		$data['template_name']=$param['template_name'];

 		$id=I('post.edit_id');
 		if($id){	//若传递过来的有id则作更新操作
 			$res=M('mobile_template')->where('id='.$id)->save($data);
 		}else{
 			$res=M('mobile_template')->add($data);
 		}

 		//传递id回去防止重复添加 
 		if($res){
 			if($id){
 				echo $id;
 			}else{
 				echo $res;
 			}
 		}else{
 			echo 0;
 		}
            
	}

	//设置首页
	public function set_index(){
            
		$data=I('post.');
		if($data['status']==0){
			$sql='update tp_mobile_template set is_index=if(id='.$data['id'].', 1, 0)';
		}
		if($data['status']==1){
			$sql='update tp_mobile_template set is_index=0 where id='.$data['id'];
		}
		$result=DB::execute($sql);
		if($result){
			$this->ajaxReturn(['status' => 1, 'msg' => '成功','result' => 1]);
		}else{
			$this->ajaxReturn(['status' => 0, 'msg' => '失败','result' => 0]);
		}
                
	}

	//删除页面
	public function delete(){
		$id=I('post.id');
		if($id){
			$r = D('mobile_template')->where('id', $id)->delete();
    		exit(json_encode(1));
		}
	}

	
	//获取秒杀活动数据
	public function get_flash(){
            
        //秒杀商品
        $now_time = time();  //当前时间
        if(is_int($now_time/7200)){      //双整点时间，如：10:00, 12:00
            $start_time = $now_time;
        }else{
            $start_time = floor($now_time/7200)*7200; //取得前一个双整点时间
        }
        $end_time = $start_time+7200;   //结束时间
        $flash_sale_list = M('goods')->alias('g')
            ->field('g.goods_id,f.price,s.item_id')
            ->join('flash_sale f','g.goods_id = f.goods_id','LEFT')
            ->join('__SPEC_GOODS_PRICE__ s','s.prom_id = f.id AND g.goods_id = s.goods_id','LEFT')
            ->where("start_time = $start_time and end_time = $end_time")
            ->limit(3)->select();
        //dump($flash_sale_list);exit();
        
	}

}
?>