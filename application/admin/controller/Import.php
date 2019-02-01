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
 * Author: 聂晓克     
 * Date: 2017-12-12
 */
namespace app\admin\controller;
use app\admin\logic\GoodsLogic;
use think\Db;

class Import extends Base {

 	public function index(){
            
        $cat_list = Db::name('goods_category')->where("parent_id = 0")->select(); // 已经改成联动菜单
        $this->assign('cat_list', $cat_list);
 		return $this->fetch();	
                
  	}

  	//上传的csv文件及图片文件 返回数组结果
	public function upload_data(){
        
		$images = request()->file('images');//图片文件
		$file = request()->file('csv');//csv文件
		$data=I('post.');//表单数据

		//移动到框架应用根目录/public/uploads/csv目录下
		$path = UPLOAD_PATH.'csv/';
		$arrimg=array();
		if (!file_exists($path)){
            mkdir($path);
        }

        //暂未对tbi文件进行验证 默认合法
        $result = $this->validate(
            ['file2' => $file], 
            ['file2'=>'fileSize:30000000|fileExt:csv'],
            ['file2.fileSize' => '上传csv文件过大', 'file2.fileExt' => '仅可上传csv文件']                    
           );

        if (true !== $result ) {            
            $this->error($result, U('Seller/import/index'));
        }

	    if($file){
	        $info = $file->move($path);
	        if($info){
	        	//上传成功
		        $csv=$info->getSaveName();
	        }else{
	            //上传失败
	            $this->error($file->getError(), U('Admin/import/index'));
	        }
	    }else{
	    	$this->error("导入csv文件失败", U('Admin/import/index'));
	    }


	    if($images){
		    foreach ($images as $k => $v) {
				$res=$v->move($path,'');
				$arrimg[$k]=$res->getSaveName();
			}
	    }else{
	    	$this->error("导入图片文件失败", U('Admin/import/index'));
	    }

	   	/*
	   	*path 上传文件路径
	    *csv  上传后的csv文件路径
	    *img  上传后的图片文件路径数组
	   	*form 提交的表单数据
	    */
	    $arr=array('path'=>$path,'csv'=>$csv,'img'=>$arrimg,'form'=>$data);
	    return $arr;exit();
            
	}

	public function add_data(){
        
		$arr=$this->upload_data();
		$file=$arr['path'].$arr['csv'];
		$img=$arr['img'];
		$form=$arr['form'];

		$handle =$this->fopen_utf8($file);

		$str='';
		while(!feof($handle)){
			//csv文件若用户使用Excel编辑另存UTF8会导致此处报错 先隐藏之后对$str进行判断并返回错误信息
		    @$str.=fgets($handle);
		}
		if($str){
			$goods=$this->str_getcsv($str,"\t");
		}else{
			$this->error("csv文件编码出错,请重新解压淘宝数据并再次导入!", U('Seller/import/index'));
		}

		//20为淘宝导出数据的 商品介绍 字段,可能是html图片信息,此处对此字段进行html标签转义
		foreach ($goods as $k => $v){
			if($v['20']){
				$goods[$k]['20']=htmlspecialchars($v['20']);
			}
		}
		//$title=array_slice($goods,0,3);//淘宝csv头文件  0版本号 1淘宝字段名 2淘宝字段名对应中文名称
		$goods=array_slice($goods,3);//商品数据

		//csv数据转换
		$param=array();
		foreach ($goods as $k => $v) {
			//tpshop数据字段 = 淘宝csv数据字段
			$param[$k]['goods_name']=$v[0];		//商品名称
			$param[$k]['cat_id']=$form['cat_id_3'];			//单商家 商品分类
			$param[$k]['store_count']=$v[9];	//商品库存  
			$param[$k]['on_time']=time();		//商品上架时间
			$param[$k]['market_price']=$v[7];	//市场价
			$param[$k]['shop_price']=$v[7];		//本店价
			$param[$k]['goods_remark']=$v[57];	//商品简单描述
			$param[$k]['goods_content']=$v[20];	//商品详细描述
			$param[$k]['is_new']=$v[3];			//是否新品
			$param[$k]['images']=$v[28];        //相册图片 临时存储                        
		} 

		foreach ($param as $k => $v) {
			$param[$k]['images']=explode(';', $v['images'],-1);
		}
		
		//生成上传图片地址数组  图片名=>图片地址
		foreach ($img as $k => $v){
			$img[str_replace('.tbi','', $v)]='/'.$arr['path'].$v;//添加关联元素
			unset($img[$k]);//删除索引元素
		}

        foreach ($param as $k => $v){
            foreach ($v['images'] as $k2 => $v2) {
                //淘宝的图片存储格式替换为图片地址形式
                $param[$k]['images'][$k2]=$img[substr($v2,0,strpos($v2,':'))];
            }
        }
        
        //数据插入
        $add=0;
        foreach ($param as $k => $v) {
            if($v['images']){
                $v['original_img']=$v['images'][0];//没有主图时默认取相册图片第一张
            }
            $goods_id=M('goods')->add($v);//goods表插入主体数据
            if($goods_id){
                if($v['images']){
                    foreach ($v['images'] as $k2 => $v2) {
                        $res=M('goods_images')->add(array('goods_id'=>$goods_id,'image_url'=>$v2));//goods_image表插入商品图片
                        if(!$res) continue;
                    }
                }
            }else{
                $add+=1;//统计插入失败次数
                continue;//某次循环数据插入失败时跳出当前循环执行下一个
            }
        } 

        if($add==count($param)){
            $this->error("商品添加失败", U('Admin/import/index'));
        }else{
            $this->success("商品添加成功", U('Admin/Goods/goodsList'));
        }
        
	}

	/**
	 * csv文件转码为utf8
	 * @param  string 文件路径
	 * @return resource  打开文件后的资源类型
	 */
	private function fopen_utf8($filename){  
        $encoding='';  
        $handle = fopen($filename, 'r');  
        $bom = fread($handle, 2);  
    	//fclose($handle);  
        rewind($handle);  
       
        if($bom === chr(0xff).chr(0xfe)  || $bom === chr(0xfe).chr(0xff)){  
            // UTF16 Byte Order Mark present  
            $encoding = 'UTF-16';  
        } else {  
            $file_sample = fread($handle, 1000) + 'e'; //read first 1000 bytes  
            // + e is a workaround for mb_string bug  
            rewind($handle);  
            $encoding = mb_detect_encoding($file_sample , 'UTF-8, UTF-7, ASCII, EUC-JP,SJIS, eucJP-win, SJIS-win, JIS, ISO-2022-JP');  
        }  
        if ($encoding){  
            stream_filter_append($handle, 'convert.iconv.'.$encoding.'/UTF-8');  
        }  
        return ($handle);  
    } 

    //csv文件读取为数组形式返回
	private function str_getcsv($string, $delimiter=',', $enclosure='"'){ 
        $fp = fopen('php://temp/', 'r+');
        fputs($fp, $string);
        rewind($fp);
        while($t = fgetcsv($fp, strlen($string), $delimiter, $enclosure)) {
            $r[] = $t;
        }
        if(count($r) == 1) 
            return current($r);
        return $r;
    }

}