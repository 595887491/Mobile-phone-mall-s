<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/3/21 0021
 * Time: 上午 9:24
 */

define( 'SRC_DB', 'shangmeibf_test' )   ;   //源数据库 只能读
define( 'DIST_DB', 'tpshop_test' ) ;     //目标数据库，可读写

class DbClass
{
    public $config = [
        'host' => 'test.cfo2o.com',  //47.100.19.77
        'user_name' => 'root',
        'password' => "shangmei&bf&0314",
        'db_name' => 'shangmeibf_test'
    ];

    public function __construct()
    {
        $this->mysqli = new mysqli(
            $this->config['host'],
            $this->config['user_name'],
            $this->config['password'],
            $this->config['db_name']
        );
    }

    /**
     * 选择数据库
     * @param $db_name 数据库名
     */
    public function selectDatabase($db_name)
    {
        mysqli_select_db($this->mysqli,$db_name);
    }

    /**
     * 执行sql语句
     * @param $sql 要执行的SQL语句
     * @param int $type 代表SQL语句的类型，0代表增删改 1代表查询
     * @return bool|mixed|mysqli_result
     */
    public function query($sql, $type = 1)
    {

        $result =  $this->mysqli->query($sql);
        if ($type) {
            //如果是查询，返回数据
            return $result->fetch_all(MYSQLI_ASSOC);
        } else {//如果是增删改，返回true或false
            return $result;
        }
    }

    /**
     * @param $table 表名
     * @param null $field 字段名
     * @param null $where where条件
     * @return int
     */
    public function select($table, $field = null, $where = null)
    {
        $sql = "SELECT * FROM {$table}";
        if (!empty($field)) {
            $field = '`' . implode('`,`', $field) . '`';
            $sql = str_replace('*', $field, $sql);
        }
        if (!empty($where)) {
            $sql = $sql . ' WHERE ' . $where;
        }
        $this->result = $this->mysqli->query($sql);
        return $this->result->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * 插入数据
     * @param $table 数据表
     * @param $data 数据数组
     * @return mixed 插入ID
     */
    public function insert($table, $data)
    {
        foreach ($data as $key => $value) {
            $data[$key] = $this->mysqli->real_escape_string($value);
        }
        $keys = '`' . implode('`,`', array_keys($data)) . '`';
        $values = '\'' . implode("','", array_values($data)) . '\'';
        $sql = "INSERT INTO {$table}( {$keys} )VALUES( {$values} )";
        $this->mysqli->query($sql);
        return $this->mysqli->insert_id;
    }
    /**
     * 更新数据
     * @param $table 数据表
     * @param $data 数据数组
     * @param $where 过滤条件
     * @return mixed 受影响记录
     */
    public function update($table, $data, $where)
    {
        foreach ($data as $key => $value) {
            $data[$key] = $this->mysqli->real_escape_string($value);
        }
        $sets = array();
        foreach ($data as $key => $value) {
            $kstr = '`' . $key . '`';
            $vstr = '\'' . $value . '\'';
            array_push($sets, $kstr . '=' . $vstr);
        }
        $kav = implode(',', $sets);
        $sql = "UPDATE {$table} SET {$kav} WHERE {$where}";
        $this->mysqli->query($sql);
        return $this->mysqli->affected_rows;
    }
    /**
     * 删除数据
     * @param $table 数据表
     * @param $where 过滤条件
     * @return mixed 受影响记录
     */
    public function delete($table, $where)
    {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        $this->mysqli->query($sql);
        return $this->mysqli->affected_rows;
    }
}

//转移数据类
class TransferDatabase{

    //****************陈静-start***********************
    //转移用户
    public function TransferUser()
    {

        $db= new DbClass() ;

        //导入 用户账号 到 users 表
        $db->selectDatabase(SRC_DB) ;
        $sql = "insert into tpshop_test.tp_users".
       "(    user_id, email,`password`, paypwd ,sex, birthday ,user_money,`frozen_money`,distribut_money,underling_number,pay_points ,address_id,".
       " reg_time,last_login,last_ip,qq,mobile,    mobile_validated ,oauth,openid,unionid,head_pic,province, city ,district ,email_validated ,nickname ,".
       "`level` ,discount ,total_amount,is_lock,is_distribut,first_leader ,second_leader ,third_leader ,token,message_mask,push_id ,distribut_level ,is_vip ) ".

     "select user_id,email,`password` , '' ,  sex , birthday ,user_money,frozen_money,          0 ,      0,              pay_points ,address_id, ".
       " reg_time,last_login,last_ip,qq,mobile_phone,is_validated ,'', wxid ,   '',     user_pic ,   '',   '' ,    ''   ,       0,          user_name ," .
       " (case 
            when pay_points>=0 and pay_points <100  then  10
            when pay_points>=100 and pay_points <500 then  20
            when pay_points>=500 and pay_points <2000 then  30
            when pay_points>=2000 and pay_points <10000 then  40
            when pay_points>=10000 and pay_points <50000 then  50
            when pay_points>=50000 and  pay_points <100000000   then  60
            else  0  end ) as `level` , ".
               "1.00 ,  (pay_points/10) ,  0,       0,            0 ,           0,               0 ,       access_token ,   63 ,      ''   , 0 ,  0   " .
            "from  shangmeibf_test.ecs_users " ;


        $tp_rows=  $db->query($sql , 0) ;

        //导入 用户账号 oauth_users 表
        $sql =" insert into tpshop_test.tp_oauth_users(user_id,openid,oauth,unionid,oauth_child) select user_id,openid, 'wexin','','mp' from tpshop_test.tp_users where openid !='' order by user_id" ;
        $tp_rows=  $db->query($sql , 0) ;

        //导入 会员等级 user_level 表    已通过SQL导入
//        $sql =" insert into tpshop_test.tp_user_level(".
//            "level_id,  level_name,   amount,         discount,`describe` ) ".
//            "select r.rank_id ,r.rank_name, r.min_points/10 , 100   ,   r.rank_name  ".
//            "from shangmeibf_test.ecs_user_rank  r  order by r.rank_id  " ;
//        $tp_rows=  $db->query($sql , 0) ;


        //导入 用户账号 user_address 表
        $sql =" insert into tpshop_test.tp_user_address(".
              "address_id,user_id,consignee,email,country,province,city , district , twon ,address , zipcode ,mobile, is_default , is_pickup ) ".
      "select a.address_id,a.user_id, a.consignee,a.email,a.country,a.province,a.city , a.district ,''   ,a.address,  a.zipcode , if( tel !='' ,tel ,mobile)  as mobile ,  if( u.address_id = a.address_id ,1 ,0) as is_default ,         0 ".
            "from shangmeibf_test.ecs_user_address a inner join  shangmeibf_test.ecs_users u on a.user_id = u.user_id order by a.address_id  " ;

        $tp_rows=  $db->query($sql , 0) ;








    }

    //转移合伙商
    public function TransferPartners()
    {

    }

    //转移其他
    public function TransferOthers()
    {

    }

    //****************陈静-end***********************


    //****************龙信红-start***********************
    //转移订单
    public function TransferOrders()
    {

    }

    //转移优惠券
    public function TransferDiscountCoupon()
    {

    }

    //转移拼团
    public function TranferGroupBooking()
    {

    }

    //转移积分
    public function TransferIntegral()
    {

    }
    //****************龙信红-end***********************


    //****************bluce-start***********************
    //转移商品
    public function TransferGoods()
    {

       $db= new DbClass() ;



      // $db->selectDatabase(SRC_DB) ;
       $sql = 'select * from ecs_goods limit 5' ;
       $ecs_rows=  $db->query($sql) ;



        //导入 商品图片
        $db->selectDatabase(SRC_DB) ;
        $sql = "select * from ecs_goods_gallery  " ;
        $gallery_rows=  $db->query($sql) ;
        foreach( $gallery_rows  as  $k => $v) {
          $img_id = $v['img_id'] ;
          $goods_id = $v['goods_id'] ;
          $image_url = $v['img_url'] ;

           $sql = "insert into tp_goods_images( img_id, goods_id , image_url ) ".
                                       "values( $img_id ,$goods_id,'$image_url' )";
            $db->selectDatabase(DIST_DB) ;          //  echo $sql ; die ;
            $tp_rows=  $db->query($sql , 0) ;
        }


        //导入 商品规格
        $db->selectDatabase(SRC_DB) ;
        $sql = "select * from ecs_goods_attr  " ;
        $goods_attr_rows=  $db->query($sql) ;
        foreach( $goods_attr_rows  as  $k => $v) {
            $goods_attr_id = $v['goods_attr_id'] ;
            $goods_id = $v['goods_id'] ;
            $attr_id = $v['attr_id'] ;
            $attr_value = $v['attr_value'] ;
            $attr_price = $v['attr_price'] ;


            $sql = "insert into tp_goods_attr( goods_attr_id, goods_id , attr_id ,attr_value ,attr_price) ".
                "values( $goods_attr_id, $goods_id , $attr_id ,'$attr_value' ,'$attr_price' )";
            $db->selectDatabase(DIST_DB) ;          //  echo $sql ; die ;
            $tp_rows=  $db->query($sql , 0) ;
        }
       //导入 商品属性表 ecs_attr 到 tp_goods_attribute
        $sql = "insert into tpshop_test.tp_goods_attribute(attr_id, attr_name, type_id,attr_index,attr_type, attr_input_type,attr_values,`order` ) ".
                                                    "select attr_id, attr_name ,0  ,   attr_index ,attr_type ,attr_input_type,attr_values,sort_order  from  shangmeibf_test.ecs_attribute " ;
       $tp_rows=  $db->query($sql , 0) ;


        //导入 商品数据
        $db->selectDatabase(SRC_DB) ;
       $sql = "select * from ecs_goods where goods_desc !='' AND original_img !='/s01' and original_img !='' " ;
       $ecs_rows=  $db->query($sql) ;

        foreach( $ecs_rows  as  $k => $v) {
            var_dump( $k) ;

            var_dump( $v) ;
            $good_id = $v['goods_id'] ;            //ECS  有
            $cat_id = $v['cat_id'] ;              //ECS  有
            $extend_cat_id = '' ;            //ECS  无
            $goods_sn = $v['goods_sn'] ;       //ECS  有
            $goods_name = $v['goods_name'] ;     //ECS  有
            $click_count = $v['click_count'] ;   //ECS  有
            $brand_id =  $v['brand_id'] ;          //ECS  有
            $store_count = $v['goods_number'] ;         //ECS  有
            $comment_count = $v['comments_number'] ;         //ECS  有
            $weight = $v['goods_weight']*1000 ;         //ECS  有,以千克为单位， TP以克为单位
            $volume = 0  ;    //ECS  无
            $market_price = $v['market_price'] ;

            $shop_price = $v['shop_price'] ;        // '本店价',    //ECS  有
            $cost_price = $v['goods_weight'] ;    // '商品成本价',

            $price_ladder = $v['goods_weight'] ;     // '价格阶梯',
            $keywords = $v['keywords'] ;            // '商品关键词',  //ECS  有

            $price_ladder = 'a:0:{}'  ;           //$v['goods_weight'] ;     // '价格阶梯',
            $keywords =   $v['keywords'] ;            // '商品关键词',  //ECS  有

            $goods_remark = $v['goods_brief'] ;    // '商品简单描述', //ECS  有
            $goods_content = $v['goods_desc'] ;      //'商品详细描述', //ECS  有
            $mobile_content = $v['goods_desc'] ;    //  '手机端商品详情', //ECS  有
            $original_img = $v['original_img'] ;     // '商品上传原始图',  //ECS  有
            $is_virtual =  ( $v['is_real'] ==1) ? 0:1 ;  // '是否为虚拟商品 1是，0否',
            $virtual_indate = 0 ;     // '虚拟商品有效期',
            $virtual_limit = 0 ;     // '虚拟商品购买上限',
            $virtual_refund = 1 ;      // '是否允许过期退款， 1是，0否',
            $is_on_sale = $v['is_on_sale'] ;     //COMMENT '是否上架', //ECS  有
            $is_free_shipping = $v['is_shipping'] ;    // COMMENT '是否包邮  0否  1是', //ECS  有
            $on_time = $v['add_time'] ;          // '商品上架时间',//ECS  有
            $sort = $v['sort_order'] ;           // '商品排序', //ECS  有
            $is_recommend = $v['goods_weight'] ;      // '是否推荐',
            $is_new = $v['is_new'] ;        // '是否新品', //ECS  有
            $is_hot = $v['is_hot'] ;         //'是否热卖', //ECS  有
            $last_update = $v['last_update'] ;    // '最后更新时间',  //ECS  有
            $goods_type = $v['goods_type'] ;     // '商品所属类型id，取值表goods_type的cat_id', //ECS  有
            $spec_type = $v['goods_weight'] ;        // '商品规格类型，取值表goods_type的cat_id',
            $give_integral = $v['goods_weight'] ;     //  '购买商品赠送积分',
            $exchange_integral = 0 ;  //'积分兑换：0-不参与积分兑换，积分和现金的兑换比例见后台配置',
            $suppliers_id = 0 ;       // '供货商ID',
            $sales_sum = 0 ;    //'商品销量',
            $prom_type =  0  ;       // '0-默认  1-抢购  2-团购  3-优惠促销  4-预售  5-虚拟(5其实没用) 6-拼团',
            $prom_id = 0 ;        // '优惠活动id',
            $commission = 0 ;     // '佣金用于分销分成',
            $spu = '' ;           // 'SPU',
            $sku = '' ;             // 'SKU',
            $template_id = 57 ;     // '运费模板ID',
            $video =  ''  ;            // '视频'


            $sql ="insert into tp_goods(   goods_id , ". // '商品id'
                "`cat_id`,".    // '分类id'
                "`extend_cat_id` ," .  //'扩展分类id',
                "`goods_sn` ,"  .//'商品编号',
                "`goods_name` ,". // '商品名称',
                "`click_count` ," . // '点击数',
                "`brand_id` ," . // '品牌id',
                "`store_count` ," . // '库存数量',
                "`comment_count` ," .  // '商品评论数',
                "`weight` ,"  . //'商品重量克为单位',
                " `volume` ," .// '商品体积。单位立方米',
                "`market_price` ," . // '市场价',
                " `shop_price` ," . // '本店价',
                "`cost_price` ," . // '商品成本价',
                " `price_ladder` ," . // '价格阶梯',
                "`keywords` ," . // '商品关键词',
                "`goods_remark`, " .// '商品简单描述',
                "`goods_content` , " .//'商品详细描述',
                " `mobile_content` ," .//  '手机端商品详情',
                "`original_img` ," .  // '商品上传原始图',
                "`is_virtual` , " . // '是否为虚拟商品 1是，0否',
                "`virtual_indate` ," . // '虚拟商品有效期',
                "`virtual_limit` , " . // '虚拟商品购买上限',
                "`virtual_refund` ," .  // '是否允许过期退款， 1是，0否',
                "`is_on_sale` , " . //COMMENT '是否上架',
                "`is_free_shipping` ," .// COMMENT '是否包邮0否1是',
                "`on_time` , " . // '商品上架时间',
                "`sort` , ".  // '商品排序',
                "`is_recommend` ," .   // '是否推荐',
                "`is_new` ," . // '是否新品',
                " `is_hot` , " .//'是否热卖',
                "`last_update` ," .// '最后更新时间',
                "`goods_type` ," . // '商品所属类型id，取值表goods_type的cat_id',
                "`spec_type` ," . // '商品规格类型，取值表goods_type的cat_id',
                "`give_integral` ," . //  '购买商品赠送积分',
                "`exchange_integral` ,"  . //'积分兑换：0不参与积分兑换，积分和现金的兑换比例见后台配置',
                "`suppliers_id` ,"  . // '供货商ID',
                "`sales_sum` ," .   //'商品销量',
                "`prom_type` ,".  // '0默认1抢购2团购3优惠促销4预售5虚拟(5其实没用)6拼团',
                "`prom_id` ," . // '优惠活动id',
                "`commission` ," . // '佣金用于分销分成',
                "`spu` ,".  // 'SPU',
                "`sku` ." . // 'SKU',
                "`template_id` ," .  // '运费模板ID',
                "`video`" .  // '视频'
                " )    " .

                "VALUES (".
                "'$good_id' , ". // '商品id'
                "`cat_id`,".    // '分类id'
                "`extend_cat_id` ," .  //'扩展分类id',
                "`goods_sn` ,"  .//'商品编号',
                "`goods_name` ,". // '商品名称',
                "`click_count` ," . // '点击数',
                "`brand_id` ," . // '品牌id',
                "`store_count` ," . // '库存数量',
                "`comment_count` ," .  // '商品评论数',
                "`weight` ,"  . //'商品重量克为单位',
                " `volume` ," .// '商品体积。单位立方米',
                "`market_price` ," . // '市场价',
                " `shop_price` ," . // '本店价',
                "`cost_price` ," . // '商品成本价',
                " `price_ladder` ," . // '价格阶梯',
                "`keywords` ," . // '商品关键词',
                "`goods_remark`, " .// '商品简单描述',
                "`goods_content` , " .//'商品详细描述',
                " `mobile_content` ," .//  '手机端商品详情',
                "`original_img` ," .  // '商品上传原始图',
                "`is_virtual` , " . // '是否为虚拟商品 1是，0否',
                "`virtual_indate` ," . // '虚拟商品有效期',
                "`virtual_limit` , " . // '虚拟商品购买上限',
                "`virtual_refund` ," .  // '是否允许过期退款， 1是，0否',
                "`is_on_sale` , " . //COMMENT '是否上架',
                "`is_free_shipping` ," .// COMMENT '是否包邮0否1是',
                "`on_time` , " . // '商品上架时间',
                "`sort` , ".  // '商品排序',
                "`is_recommend` ," .   // '是否推荐',
                "`is_new` ," . // '是否新品',
                " `is_hot` , " .//'是否热卖',
                "`last_update` ," .// '最后更新时间',
                "`goods_type` ," . // '商品所属类型id，取值表goods_type的cat_id',
                "`spec_type` ," . // '商品规格类型，取值表goods_type的cat_id',
                "`give_integral` ," . //  '购买商品赠送积分',
                "`exchange_integral` ,"  . //'积分兑换：0不参与积分兑换，积分和现金的兑换比例见后台配置',
                "`suppliers_id` ,"  . // '供货商ID',
                "`sales_sum` ," .   //'商品销量',
                "`prom_type` ,".  // '0默认1抢购2团购3优惠促销4预售5虚拟(5其实没用)6拼团',
                "`prom_id` ," . // '优惠活动id',
                "`commission` ," . // '佣金用于分销分成',
                "`spu` ,".  // 'SPU',
                "`sku` ," . // 'SKU',
                "`template_id` ," .  // '运费模板ID',
                "  " .  // '视频'
                " ) " ;
            $db->selectDatabase(DIST_DB) ;
             $tp_rows=  $db->query($sql) ;

        }

//

    }


    //转移商品品牌
    public function TransferBrand()
    {
        $db= new DbClass() ;


        // $db->selectDatabase(SRC_DB) ;
        $sql = 'select * from ecs_brand limit 5' ;
        $ecs_rows=  $db->query($sql) ;

        foreach( $ecs_rows  as  $k => $v) {
           var_dump( $v ) ;

            $id= $v['brand_id'] ;                 //ECS  有
            $name = $v['brand_name'] ;            //ECS  有
            $logo = $v['brand_logo'] ;            //ECS  有
            $desc = $v['brand_desc'] ;            //ECS  有
            $url = $v['site_url'] ;               //ECS  有
            $sort = $v['sort_order'] ;           //ECS  有
            $cat_name=  $v['brand_id'] ;          //ECS  有
            $parent_cat_id = $v['goods_number'] ;         //ECS  无
            $cat_id = $v['goods_number'] ;                //ECS  无
            $is_hot = $v['goods_number'] ;                //ECS  无

            $sql ="insert into tp_goods(`id`,`name`, logo,  `desc`,  `url`, `sort`,cat_name, parent_cat_id, cat_id, is_hot ) ".
                                " values($id,'$name','$logo','$desc','$url',$sort, $cat_name,$parent_cat_id,$cat_id,$is_hot)" ;
               $db->selectDatabase(DIST_DB) ;
               $tp_rows=  $db->query($sql) ;
        }

    }

//递归 生成  分类路径的 家族图谱
    public  function getParentIdPath($cat_array,$id,$parent_id){
        $parent_id_path= '' ;
        if(  empty($cat_array) || $id <1 ||  $parent_id <0 )
            return $parent_id_path ;

        if ( $parent_id == 0 )   {
            $parent_id_path = '0_'.$id ;
            return $parent_id_path ;
        }

        $id_old = $id ;
        $id = $parent_id ;
        $parent_id = $cat_array[$id] ;

       // echo "$parent_id==" .$parent_id . '\n';
        return  $this->getParentIdPath($cat_array,$id,$parent_id).'_'.$id_old ;
    }

    //转移商品分类
    public function TransferCategoy()
    {
        $db= new DbClass() ;

        $db->selectDatabase(SRC_DB) ;
        $sql = 'select * from ecs_category order by cat_id  ' ;
        $ecs_rows=  $db->query($sql) ;
        $parent_id_rows = array() ;
        foreach( $ecs_rows  as  $k => $v) {
            $parent_id_rows[$v['cat_id']] = $v['parent_id'];
        }

        foreach( $ecs_rows  as  $k => $v) {
       //     var_dump( $v ) ;
            $id= $v['cat_id'] ;                 //ECS  有
            $name = $v['cat_name'] ;             //ECS  有
            $mobile_name =$v['cat_name'] ;       //ECS  有
            $parent_id = $v['parent_id'] ;        //ECS  有
            $parent_id_path = $this->getParentIdPath($parent_id_rows,$id ,$parent_id) ;  //ECS  无
          //  var_dump($parent_id_path ) ;
            $level = 0 ;                            //ECS  无
            $sort_order=  $v['sort_order'] ;       //ECS  有
            $is_show = $v['is_show'] ;             //ECS  有
            $image = ''. $v['cat_ico'] ;                //ECS  无
            $is_hot = 0            ;                //ECS  无
            $cat_group = 0            ;              //ECS  无
            $commission_rate = 0 ;                    //ECS  无

            $sql ="insert into tp_goods_category(`id`,`name`, `mobile_name`,  `parent_id`,  `parent_id_path`, `level`, sort_order, is_show, image, is_hot, cat_group, commission_rate ) ".
                                        " values($id,'$name', '$mobile_name', '$parent_id','$parent_id_path', $level, $sort_order, $is_show,'$image',$is_hot,$cat_group,$commission_rate) ; " ;
             $db->selectDatabase(DIST_DB) ;
          //   var_dump( $sql ) ;  echo "\n"   ;
             $tp_rows=  $db->query($sql,0) ;

        }
    }      //end  function TransferCategoy




    //****************bluce-end***********************

}
  $t = new TransferDatabase() ;
 // echo 'ddddd' ; die ;
  //  $t->TransferGoods() ;
   // $t->TransferBrand() ;
 //  $t->TransferCategoy() ;

  // $t->TransferCategoy() ;  // step 1s
  // $t->TransferBrand() ;  //step 2
//   $t->TransferGoods() ;  //  step 3
   $t->TransferUser() ;




