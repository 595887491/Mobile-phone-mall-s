<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/3/21 0021
 * Time: 上午 9:24
 */
namespace app\transfer\controller;

use app\transfer\common\DbClass;
use gmars\nestedsets\NestedSets;


define( 'SRC_DB', 'shangmeibf_test' )   ;   //源数据库 只能读
define( 'DIST_DB', 'tpshop_dev' ) ;     //目标数据库，可读写


//转移数据类
class TransferDatabase{

    //****************陈静-start***********************
    //转移用户
    public function TransferUser()
    {
        $tables = array(
            'tp_users','cf_users','cf_user_user','cf_user_partner','cf_user_agent','tp_user_address',
           'tp_coupon' , 'tp_coupon_list' , 'tp_goods_collect', 'tp_oauth_users' )  ;

        $this->clear( $tables ) ;


        $db= new DbClass() ;

        //导入 用户账号 到 users 表
        // $db->selectDatabase(DIST_DB) ;
        $sql = "insert into ".DIST_DB." .tp_users".
       "(    user_id, email,`password`, paypwd ,sex, birthday ,user_money,`frozen_money`,distribut_money,underling_number,pay_points ,address_id,".
       " reg_time,last_login,last_ip,qq,mobile,    mobile_validated ,oauth,openid,unionid,head_pic,province, city ,district ,email_validated ,nickname ,".
       "`level` ,discount ,total_amount,is_lock,is_distribut,first_leader ,second_leader ,third_leader ,token,message_mask,push_id ,distribut_level ,is_vip ) ".

     "select user_id,email,`password` , '' ,  sex , birthday ,user_money,frozen_money,          0 ,      0,              floor(pay_points/10) ,address_id, ".
       " reg_time,last_login,last_ip,qq,mobile_phone,is_validated ,'', wxid ,   '',    
                                 ( case when left( user_pic ,4)='http'   then user_pic  
                                  when left( user_pic ,1)='/'    then concat('http://cdn.cfo2o.com',user_pic) 
                                   when length( user_pic ) >2   then concat('http://cdn.cfo2o.com/',user_pic) 
                                 else 'http://cdn.cfo2o.com/data/avatar/user_head_default.png'   end )  , 
                            0,  0 ,    0   ,       0,          user_name ," .
       " (case 
            when pay_points>=0 and pay_points <1000  then  10
            when pay_points>=1000 and pay_points <5000 then  20
            when pay_points>=5000 and pay_points <20000 then  30
            when pay_points>=20000 and pay_points <100000 then  40
            when pay_points>=100000 and pay_points <500000 then  50
            when pay_points>=500000 and  pay_points <100000000   then  60
            else  0  end ) as `level` , ".
               "1.00 ,  (pay_points/10) ,  0,       0,            0 ,           0,               0 ,       access_token ,   63 ,      ''   , 0 ,  0   " .
            "from  ".SRC_DB." .ecs_users " ;

      //  var_dump( $sql) ; die ;
        $tp_rows=  $db->query($sql , 0) ;

        //向 ".DIST_DB." .cf_users 导入数据
        $sql = " insert into ".DIST_DB." .cf_users 
                       ( user_id ,  user_type ,channel_type ,id_card_num ,id_card_name ,user_qrcode_id ,agent_qrcode_id ,first_partner_id ,first_agent_id ,
                         wallet_user_income ,wallet_partner_income ,wallet_agent_income   )       " .
             "  select  u1.user_id , ( case when u2.user_id >0    then  3   
                                           when u3.user_id =1023    then  5   
                                           else  1  end ) as user_type  ,   ".

                                       "  ( case when u1.parent_id >0  and u1.wxid !=''  then  0   
                                                 when u1.parent_id >0  and u1.mobile_phone !=''  then  1   
                                                 when u1.parent_id =0  and u1.wxid !=''  then  2   
                                                 when u1.parent_id =0  and u1.mobile_phone !=''  then  3 
                                                    else  1  end ) as channel_type  ,    ".

                                        "    ( case when u2.user_id >0    then  u2.card   else  u1.card  end ) as id_card_num      , " .

                                        "   ( case  when u2.user_id >0    then  u2.card_name   when u1.card !=''    then   u1.true_name  end ) as id_card_name      ,    ".

                 "     ''     ,   ''          ,       ''         ,    ''        ,        ''          ,          ''           ,   ''                    
                from ".SRC_DB." .ecs_users u1 left join ".SRC_DB." .ecs_affiliate_card u2  on u1.user_id = u2.user_id   left join ".SRC_DB." .ecs_affiliate_agent u3 on u1.user_id = u3.user_id "   ;
        $tp_rows=  $db->query($sql , 0) ;


        //向 ".DIST_DB." .cf_user_user 导入数据
//        $sql = " insert into ".DIST_DB." .cf_user_user
//                       ( user_id ,  parent_id ,    left_key ,    right_key ,`level` ,be_user_start ,be_user_end ,user_kpi )       " .
//            "  select  u1.user_id , u1.parent_id ,      '' ,        ''    ,    ''  ,       ''     ,   ''       ,  ''
//                from ".SRC_DB." .ecs_users u1  "   ;
//        $tp_rows=  $db->query($sql , 0) ;
        $sql = " select  u1.user_id , u1.parent_id , u1.reg_time as be_user_start from ".SRC_DB." .ecs_users u1 order by u1.parent_id asc  " ;
        $rows=  $db->query($sql , 0) ;
        $data = array() ;
        $nested = new NestedSets(DIST_DB.'.cf_user_user','left_key' ,'right_key','parent_id' , 'level' ,'user_id');
        foreach (  $rows as $k => $v ) {
            $data['user_id']=  $v['user_id'] ;
            $data['be_user_start']=  $v['be_user_start'] ;
            $data['be_user_end']=  3999999999  ; //默认最大过期时间 UNIX
            $nested->insert( $v['parent_id'] , $data ) ;
         }

        //向 ".DIST_DB." .cf_user_partner 导入数据
//        $sql = " insert into ".DIST_DB." .cf_user_partner
//                       ( user_id ,  parent_id ,    left_key ,    right_key ,`level` ,be_partner_start ,be_partner_end ,partner_kpi )       " .
//            "  select  u1.user_id , ''       ,      ''     ,        ''    ,    ''  ,       ''       ,       ''       ,  ''
//                from ".SRC_DB." .ecs_affiliate_card u1  "      ;
//        $tp_rows=  $db->query($sql , 0) ;
        // $db->selectDatabase(SRC_DB) ;
        $sql = " select  u1.user_id , u1.pass_time as be_partner_start 
                 from ".SRC_DB." .ecs_affiliate_card u1 order by  u1.pass_time desc " ;
        $rows=  $db->query($sql , 0) ;

        $data = array() ;
        $nested = new NestedSets(DIST_DB.'.cf_user_partner','left_key' ,'right_key','parent_id' , 'level' ,'user_id');
        foreach (  $rows as $k => $v ) {
            $data['user_id']=  $v['user_id'] ;
            $data['be_partner_start']=  $v['be_partner_start'] ;
            $data['be_partner_end']=  $v['be_partner_start'] +  365*86400 ; //默认最大过期时间 UNIX
            $parent_id =  $this->getPartnerParentId(  $v['user_id'] ) ;  //
            @$nested->insert( $parent_id , $data ) ;
        }

        //向 ".DIST_DB." .cf_user_agent 导入数据
        $sql = " insert into ".DIST_DB." .cf_user_agent 
                       ( user_id ,  parent_id ,    left_key ,    right_key ,`level` ,be_agent_start ,be_agent_end ,agent_kpi ,agent_level  )       " .
            "  select  u1.user_id ,  ''       ,      ''     ,        ''    ,   ''  ,       ''       ,       ''       ,  ''   ,    1             
                from ".SRC_DB." .ecs_affiliate_agent u1  where u1.user_id = 1023" ;
        $tp_rows=  $db->query($sql , 0) ;

        //导入 用户账号 oauth_users 表
        $sql =" insert into ".DIST_DB." .tp_oauth_users(user_id,openid,oauth,unionid,oauth_child) select user_id,openid, 'weixin','','mp' from ".DIST_DB." .tp_users where openid !='' order by user_id " ;
        // $db->selectDatabase(DIST_DB) ;
        $tp_rows=  $db->query($sql , 0) ;

        //导入 会员等级 user_level 表    已通过SQL导入
//        $sql =" insert into ".DIST_DB." .tp_user_level(".
//            "level_id,  level_name,   amount,         discount,`describe` ) ".
//            "select r.rank_id ,r.rank_name, r.min_points/10 , 100   ,   r.rank_name  ".
//            "from ".SRC_DB." .ecs_user_rank  r  order by r.rank_id  " ;
//        $tp_rows=  $db->query($sql , 0) ;


        //导入 用户账号 user_address 表
        $sql =" insert into ".DIST_DB." .tp_user_address(".
              "address_id,user_id,consignee,email,country,province,city , district , twon ,address , zipcode ,mobile, is_default , is_pickup ) ".
      "select a.address_id,a.user_id, a.consignee,a.email,a.country,a.province,a.city , a.district ,''   ,a.address,  a.zipcode , if( tel !='' ,tel ,mobile)  as mobile ,  if( u.address_id = a.address_id ,1 ,0) as is_default ,         0 ".
            "from ".SRC_DB." .ecs_user_address a inner join  ".SRC_DB." .ecs_users u on a.user_id = u.user_id order by a.address_id  " ;
        // $db->selectDatabase(DIST_DB) ;
        $tp_rows=  $db->query($sql , 0) ;

        //导入 优惠券 类型 tp_coupon 表
        $sql =" insert into ".DIST_DB." .tp_coupon(".
                    "id,  `name`,   `type`, money,      `condition` ,       createnum , send_num , use_num , send_start_time  ,send_end_time , use_start_time ,use_end_time ,  add_time ,           status ,   use_type , valid_time) ".
       "select b2.type_id ,b2.type_name ,   ".
            "(case 
            when left( b2.type_name ,2)='注册'   then  4
            when left( b2.type_name ,2)='新注'   then  4  
            when left( b2.type_name ,2)='完善'   then  5  
            when left( b2.type_name ,2)='新完'   then  5    
            when left( b2.type_name ,2)='推荐'   then  6   
            when left( b2.type_name ,2)='新推'   then  6     
            else  1  end ) as `type` , ".

            "   b2.type_money , b2.min_goods_amount  ,    0       ,    
          ( SELECT count(*) from ".SRC_DB." .ecs_user_bonus b1 where b1.bonus_type_id = b2.type_id ) as send_num  , 
            ( SELECT count(*) from ".SRC_DB." .ecs_user_bonus b3 where b3.bonus_type_id = b2.type_id and b3.used_time>0 )  as use_num  ,
               b2.send_start_date  ,b2.send_end_date , b2.use_start_date , b2.use_end_date ,b2.send_start_date-86400 , 1 ,       0  , b2.is_num*30*86400 ".
            "from ".SRC_DB." .ecs_bonus_type b2 " ;
        // $db->selectDatabase(DIST_DB) ;
        $tp_rows=  $db->query($sql , 0) ;

        //导入 优惠券 列表 tp_coupon_list 表
        $sql =" insert into ".DIST_DB." .tp_coupon_list(".
               "id,        cid ,          `type`,        uid ,  order_id ,  get_order_id ,    use_time  , `code`  , send_time  , `status` ,use_start_time , use_end_time ) ".
       "select b.bonus_id , b.bonus_type_id , ( select   `type` from ".DIST_DB." .tp_coupon   where  id = b.bonus_type_id  ) ,b.user_id ,b.order_id ,      0         , b.used_time  , ''   ,  b.p_add_time,  ".
            "(case 
            when b.used_time > 0      then  1
            when b.used_time = 0 and  ( ( select   `use_end_time` from ".DIST_DB." .tp_coupon   where  id = b.bonus_type_id  ) < UNIX_TIMESTAMP() )  then  2           
            else  0  end ) as `status` ,".

            "  (case 
            when c.use_start_time >= b.p_add_time      then  c.use_start_time                   
            else  b.p_add_time  end ) as `use_start_time` ,"

            ."  (case 
            when c.use_end_time <= b.p_add_time + c.valid_time    then  c.use_end_time                   
            else  b.p_add_time + c.valid_time  end ) as `use_start_time`     ".

             "from ".SRC_DB." .ecs_user_bonus b inner join  ".DIST_DB." .tp_coupon c on b.bonus_type_id = c.id " ;
        // $db->selectDatabase(DIST_DB) ;
        $tp_rows=  $db->query($sql , 0) ;
        $sql = "update ".DIST_DB." .tp_coupon_list set status =2 where use_time =0 and  use_end_time < UNIX_TIMESTAMP() " ;
        $tp_rows=  $db->query($sql , 0) ;



  //导入 收藏 tp_goods_collect 表
        $sql =" insert into ".DIST_DB." .tp_goods_collect(".
               "collect_id, user_id,  goods_id, add_time ) ".
       "select  rec_id,    user_id, goods_id,  add_time ".
            "from ".SRC_DB." .ecs_collect_goods  " ;
        // $db->selectDatabase(DIST_DB) ;
        $tp_rows=  $db->query($sql , 0) ;


    }


    //转移订单
    public function TransferOrders()
    {
        $tables = array(  'tp_order','tp_order_goods'  , 'tp_deliver_doc' )  ;
        $this->clear( $tables ) ;

        $db= new DbClass() ;
        // $db->selectDatabase(SRC_DB) ;
        //向 ".DIST_DB." .tp_order 导入数据
        $sql = " insert into ".DIST_DB." .tp_order
           ( order_id ,  order_sn ,    user_id ,    order_status ,`shipping_status` ,pay_status ,consignee , country ,
              province ,  city ,       district ,      twon ,        address ,       zipcode ,    mobile  ,   email , 
          shipping_code , shipping_name , pay_code ,   pay_name ,      invoice_title ,  taxpayer ,  goods_price ,shipping_price ,
            user_money , coupon_price  , integral ,  integral_money , order_amount ,  total_amount , add_time ,  shipping_time ,
          confirm_time ,  pay_time ,  transaction_id ,prom_id ,       prom_type ,    order_prom_id , order_prom_amount , discount ,
            user_note  , admin_note ,   parent_sn ,  is_distribut ,   paid_money ,   deleted )       " .

    " select  u1.order_id , u1.order_sn , u1.user_id ,
                                                      ( case  when u1.order_status=0  then  0 
                                                        when u1.order_status=1  then  1 
                                                        when u1.order_status=2  then  3
                                                        when u1.order_status=3  then  5
                                                        when u1.order_status=4  then  2
                                                        when u1.order_status=5  then  2
                                                        when u1.order_status=6  then  2
                                                        when u1.order_status=7  then  4                                                         
                                                        end ) as  order_status ,
             
                                                                       (case  when u1.shipping_status=0  then  0 
                                                                              when u1.shipping_status=1  then  1
                                                                              when u1.shipping_status=2  then  1
                                                                              when u1.shipping_status=3  then  0
                                                                              when u1.shipping_status=4  then  2
                                                                              when u1.shipping_status=5  then  0
                                                                              when u1.shipping_status=6  then  1
                                                                        end ) as shipping_status ,
                                                                                        ( case  when u1.pay_status=0  then  0 
                                                                                                 when u1.pay_status=1  then  0
                                                                                                when u1.pay_status=2  then  1
                                                                                        end )  as pay_status  , u1.consignee ,  u1.country ,
            u1.province ,  u1.city ,   u1.district ,   ''     ,      u1.address ,    u1.zipcode ,    u1.tel  , u1.email , 
           ( case when  u1.shipping_name ='百世快递' then 'huitongkuaidi' else 'ZITI' end) ,
                        if ( u1.shipping_name ='百世快递' , '百世快递' , '到店自提' )  as shipping_name ,
                                       ''      ,  u1.pay_name  ,    IF(invoice_no ='', '', '个人') , '' , u1.goods_amount ,u1.shipping_fee ,
             u1.surplus,    u1.bonus ,        0   ,         0 ,           u1.order_amount ,u1.goods_amount + u1.shipping_fee , u1.add_time , u1.shipping_time ,        
        u1.confirm_time ,u1.pay_time , u1.wx_order_sn ,    0    ,        0 ,               0 ,            0 ,            u1.discount,
         u1.postscript , u1.to_buyer ,    ''        ,  u1.is_separate ,   u1.money_paid ,  0
       from ".SRC_DB." .ecs_order_info u1 where u1.is_delete=0 and ( u1.order_status in (1 ,3 ,4,5,6,7  , 2 )   )  "   ;
        // $db->selectDatabase(DIST_DB) ;
     //   var_dump(  $sql ) ;die ;
        $tp_rows=  $db->query($sql , 0) ;

        //向 ".DIST_DB." .tp_order 导入数据 使用tp自带的 region 数据
//        $sql = " insert into ".DIST_DB." .tp_region ( id ,  `name` ,  `level`  , parent_id  )
//                  select  region_id , region_name  , region_type  , parent_id from  ".SRC_DB." .ecs_region     " ;
//        $tp_rows=  $db->query($sql , 0) ;

        //向 ".DIST_DB." .tp_order_goods 导入数据
        $sql = " insert into ".DIST_DB." .tp_order_goods
           ( rec_id ,      order_id ,    goods_id , goods_name , `goods_sn` ,  goods_num ,      final_price ,    goods_price ,     cost_price ,   member_goods_price ,
            give_integral ,spec_key , spec_key_name ,bar_code , is_comment ,   prom_type ,      prom_id ,         is_send ,        delivery_id ,    sku     )       " .

 " select  u1.rec_id , u1.order_id , u1.goods_id ,u1.goods_name , u1.goods_sn ,u1.goods_number , u2.shop_price ,  u1.goods_price ,u2.promote_price ,u2.promote_price ,
            0 ,           ''        ,   ''       ,    ''        ,   0 ,             0  ,            0   ,             0    ,            0 ,         ''
     from ".SRC_DB." .ecs_order_goods u1 inner join  ".SRC_DB." .ecs_goods u2 on u1.goods_id = u2.goods_id"   ;
        // $db->selectDatabase(DIST_DB) ;
        $tp_rows=  $db->query($sql , 0) ;

        //修订订单的 自增长 ID
        $sql = " alter table ".DIST_DB." .tp_order AUTO_INCREMENT=5000 " ;
        $tp_rows=  $db->query($sql , 0) ;

          //向 ".DIST_DB." . tp_delivery_doc 导入数据
        $sql = " insert into ".DIST_DB." .tp_delivery_doc
                   ( id ,      order_id ,    order_sn ,    user_id ,    `admin_id` ,    consignee ,      zipcode ,     mobile , 
                    country ,  province ,    city     ,   district ,     address ,    shipping_code,  shipping_name , shipping_price ,
                    invoice_no ,tel ,        note ,       best_time ,     create_time ,   is_del ,      send_type      )       " .

   " select  u1.delivery_id , u1.order_id , u1.order_sn ,u1.user_id ,   ''         ,u1.consignee ,  u1.zipcode , if ( u1.tel !='' , u1.tel , u1.mobile) ,
                u1.country , u1.province , u1.city  ,   u1.district  , u1.address  , 
                                 ( select if (  left ( u1.shipping_name,4) = '百世汇通' or u1.shipping_name = '百世快递'  ,'huitongkuaidi', 'ZITI' ) as shipping_code   )    ,
                 ( select if ( left ( u1.shipping_name , 4) = '百世汇通' or u1.shipping_name = '百世快递'  ,'百世快递', '到店自提' ) as shipping_name   )   , 
                 u1.shipping_fee ,  u1.invoice_no, if ( u1.tel !='' , u1.tel , u1.mobile)    ,
                                      u1.postscript       , u1.best_time , u1.add_time ,    0  ,            0  
     from ".SRC_DB." .ecs_delivery_order u1 "   ;

      //  var_dump( $sql )  ;;die ;
            $tp_rows=  $db->query($sql , 0) ;



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

        $tables = array( 'tp_goods_images','tp_goods_attr', 'tp_goods_attribute', 'tp_goods'  )  ;

        $this->clear( $tables ) ;
       $db= new DbClass() ;
        // $db->selectDatabase(SRC_DB) ;
       $sql = "select * from ".SRC_DB." .ecs_goods  " ;
       $ecs_rows=  $db->query($sql) ;

        //导入 商品图片
        // $db->selectDatabase(SRC_DB) ;
        $sql = "select * from ".SRC_DB." .ecs_goods_gallery  " ;
        $gallery_rows=  $db->query($sql) ;

        // $db->selectDatabase(DIST_DB) ;
        foreach( $gallery_rows  as  $k => $v) {
          $img_id = $v['img_id'] ;
          $goods_id = $v['goods_id'] ;
          $image_url = $v['img_url'] ;

         $sql = "insert into ".DIST_DB." .tp_goods_images( img_id, goods_id , image_url ) ".
                                       "values( $img_id ,$goods_id,'$image_url' )";
         // $db->selectDatabase(DIST_DB) ;          //  echo $sql ; die ;
         $tp_rows=  $db->query($sql , 0) ;
        }


        //导入 商品规格
        // $db->selectDatabase(SRC_DB) ;
        $sql = "select * from  ".SRC_DB." .ecs_goods_attr  " ;
        $goods_attr_rows=  $db->query($sql) ;

        // $db->selectDatabase(DIST_DB) ;
        foreach( $goods_attr_rows  as  $k => $v) {
            $goods_attr_id = $v['goods_attr_id'] ;
            $goods_id = $v['goods_id'] ;
            $attr_id = $v['attr_id'] ;
            $attr_value = $v['attr_value'] ;
            $attr_price = $v['attr_price'] ;

            $sql = "insert into ".DIST_DB." .tp_goods_attr( goods_attr_id, goods_id , attr_id ,attr_value ,attr_price) ".
                "values( $goods_attr_id, $goods_id , $attr_id ,'$attr_value' ,'$attr_price' )";
            // $db->selectDatabase(DIST_DB) ;          //  echo $sql ; die ;
            $tp_rows=  $db->query($sql , 0) ;
        }
       //导入 商品属性表 ecs_attr 到 tp_goods_attribute
        $sql = "insert into ".DIST_DB." .tp_goods_attribute(attr_id, attr_name, type_id,attr_index,attr_type, attr_input_type,attr_values,`order` ) ".
                                                    "select attr_id, attr_name ,0  ,   attr_index ,attr_type ,attr_input_type,attr_values,sort_order  from  ".SRC_DB." .ecs_attribute " ;
        // $db->selectDatabase(DIST_DB) ;
        $tp_rows=  $db->query($sql , 0) ;


        //导入 商品数据
        // $db->selectDatabase(SRC_DB) ;
        //  $sql = "select * from ecs_goods where goods_desc !='' AND original_img !='/s01' and original_img !='' " ;
       $sql = "select * from ".SRC_DB." .ecs_goods where goods_desc !=''  " ;
        $ecs_rows=  $db->query($sql ) ;

        // $db->selectDatabase(DIST_DB) ;
        foreach( $ecs_rows  as  $k => $v) {
            $good_id = $v['goods_id'] ;            //ECS  有
            $cat_id = $v['cat_id'] ;              //ECS  有
            $extend_cat_id = 0 ;            //ECS  无
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
            $price_ladder = '' ;     // '价格阶梯', $v['goods_weight']
            $keywords =   $v['keywords'] ;            // '商品关键词',  //ECS  有
            $goods_remark = $v['goods_brief'] ;    // '商品简单描述', //ECS  有
            $goods_content = $v['goods_desc'] ;      //'商品详细描述', //ECS  有
            $mobile_content = $v['goods_desc'] ;    //  '手机端商品详情', //ECS  有
            $original_img = ( $v['goods_img']!='/s01')?( $v['goods_img']):(  $v['goods_thumb'] ) ;     // '商品上传原始图',   original_img //ECS  有
            $is_virtual =  ( $v['is_real'] ==1) ? 0:1 ;  // '是否为虚拟商品 1是，0否',
            $virtual_indate = 0 ;     // '虚拟商品有效期',
            $virtual_limit = 0 ;     // '虚拟商品购买上限',
            $virtual_refund = 1 ;      // '是否允许过期退款， 1是，0否',
            $is_on_sale = ( $v['shop_price'] >0 ) ? $v['is_on_sale'] : 0 ;     //COMMENT '是否上架', //ECS  有

            $is_free_shipping = $v['is_shipping'] ;    // COMMENT '是否包邮  0否  1是', //ECS  有
            $on_time = $v['add_time'] ;          // '商品上架时间',//ECS  有
            $sort =    $v['sort_order'] ;           // '商品排序', //ECS  有
            $is_recommend = $v['goods_weight'] ;      // '是否推荐',
            $is_new = $v['is_new'] ;        // '是否新品', //ECS  有
            $is_hot = $v['is_hot'] ;         //'是否热卖', //ECS  有
            $last_update = $v['last_update'] ;    // '最后更新时间',  //ECS  有
            $goods_type = $v['goods_type'] ;     // '商品所属类型id，取值表goods_type的cat_id', //ECS  有
            $spec_type = $v['goods_weight'] ;        // '商品规格类型，取值表goods_type的cat_id',
            $give_integral = $v['goods_weight'] ;     //  '购买商品赠送积分',
            $exchange_integral = 0 ;  //'积分兑换：0-不参与积分兑换，积分和现金的兑换比例见后台配置',
            $suppliers_id = 0 ;       // '供货商ID',
            $sales_sum =  0 ;    //'商品销量',
            $prom_type =  0  ;       // '0-默认  1-抢购  2-团购  3-优惠促销  4-预售  5-虚拟(5其实没用) 6-拼团',
            $prom_id =   0 ;        // '优惠活动id',
            $commission = 0 ;     // '佣金用于分销分成',
            $spu = '' ;           // 'SPU',
            $sku = '' ;             // 'SKU',
            $template_id = 57 ;     // '运费模板ID',
            $video =  ''  ;            // '视频'

            $sql ="insert into ".DIST_DB." .tp_goods(   goods_id , `cat_id`,`extend_cat_id` ,`goods_sn` ,`goods_name` ,`click_count` ,`brand_id` ,`store_count` ," . // '库存数量',
                "`comment_count`, `weight` , `volume` ,`market_price` , `shop_price` ,`cost_price` , `price_ladder` ,`keywords` ," . // '商品关键词',
                "`goods_remark`,`goods_content` , `mobile_content` ,`original_img` ,`is_virtual` , `virtual_indate` ,`virtual_limit` , " . // '虚拟商品购买上限',
                "`virtual_refund` ,`is_on_sale` ,`is_free_shipping` ,`on_time` , `sort` , `is_recommend` ,`is_new` , `is_hot` , " .//'是否热卖',
                "`last_update` ,`goods_type` ,`spec_type` ,`give_integral` ,`exchange_integral` ,`suppliers_id` ,`sales_sum` ,`prom_type` ,".  // '0默认1抢购2团购3优惠促销4预售5虚拟(5其实没用)6拼团',
                "`prom_id` ,`commission` ,`spu` ,`sku`,`template_id` ,`video` )   " .
                "VALUES ($good_id , $cat_id , $extend_cat_id , '$goods_sn' , '$goods_name' ,$click_count ,$brand_id ,$store_count ," . // '库存数量',
                " $comment_count ,$weight ,$volume, $market_price , $shop_price ,  $cost_price , '$price_ladder' , '$keywords' ," . // '商品关键词',
                " '$goods_remark', '$goods_content' , '$mobile_content' ,'$original_img' ,$is_virtual , $virtual_indate ,$virtual_limit , " . // '虚拟商品购买上限',
                "$virtual_refund , $is_on_sale , $is_free_shipping ,$on_time , $sort , $is_recommend , $is_new ,$is_hot , " .//'是否热卖',
                " $last_update , $goods_type ,$spec_type ,$give_integral ,$exchange_integral ,$suppliers_id ,$sales_sum ,$prom_type ,".  // '0默认1抢购2团购3优惠促销4预售5虚拟(5其实没用)6拼团',
                " $prom_id , $commission ,'$spu', '$sku' ,$template_id , '$video' ) " ;

            //// $db->selectDatabase(DIST_DB) ;          //  echo $sql ; die ;
            $tp_rows=  $db->query($sql , 0) ;

            $sql =  " INSERT INTO ".DIST_DB." .tp_goods (
               goods_id, cat_id, extend_cat_id, goods_sn, goods_name, click_count, brand_id, store_count, comment_count, weight, volume,
               market_price, shop_price, cost_price, price_ladder, keywords, goods_remark, goods_content, mobile_content, original_img, is_virtual, virtual_indate, virtual_limit,
                virtual_refund, is_on_sale, is_free_shipping, on_time, sort, is_recommend, is_new, is_hot, last_update, goods_type, spec_type, give_integral, exchange_integral,
                 suppliers_id, sales_sum, prom_type, prom_id, commission, spu, sku, template_id, video) 
              VALUES ('1000000', '708', '0', 'TP1000000', '申请成为合伙人', '0', '0', '65535', '0', '0', '0.0000', 
              '365.00', '365.00', '365.00', '', '', '申请成为合伙人', '', NULL, '', '0', '-28800', '0', 
                   '1', '0', '1', '1525844507', '50', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0.00', '', '', '0', '')  " ;
            $tp_rows=  $db->query($sql , 0) ;

        }
//

    }


    //转移商品品牌
    public function TransferBrand()
    {
        $tables = array(  'tp_brand'  )  ;
        $this->clear( $tables ) ;

        $db= new DbClass() ;
        // $db->selectDatabase(SRC_DB) ;
        $sql = "select * from ".SRC_DB." .ecs_brand " ;
        $ecs_rows=  $db->query($sql) ;

       // var_dump ($ecs_rows[0]) ;die ;
        // $db->selectDatabase(DIST_DB) ;
        foreach( $ecs_rows  as  $k => $v) {
            $id= $v['brand_id'] ;                 //ECS  有
            $name = $v['brand_name'] ;            //ECS  有
            $logo = $v['brand_logo'] ;            //ECS  有
            $desc = $v['brand_desc'] ;            //ECS  有
            $url = $v['site_url'] ;               //ECS  有
            $sort = $v['sort_order'] ;           //ECS  有
            $cat_name=  $v['brand_id'] ;          //ECS  有
            $parent_cat_id = 0 ; // $v['goods_number'] ;         //ECS  无
            $cat_id =  0;    //['goods_number'] ;                //ECS  无
            $is_hot =  0 ;  //['goods_number'] ;                //ECS  无

            $sql ="insert into ".DIST_DB." .tp_brand(`id`,`name`, logo,  `desc`,  `url`, `sort`,cat_name, parent_cat_id, cat_id, is_hot ) ".
                                " values($id,'$name','$logo','$desc','$url',$sort, $cat_name,$parent_cat_id,$cat_id,$is_hot)" ;

           // var_dump( $sql ) ;
               $tp_rows=  $db->query($sql,0) ;
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


    //递归 获取合伙人 链上的父合伙人  user_id
    public  function getPartnerParentId($parter_id){
        $partner_parent_id=  0 ;

        //  获取当前合伙人的 parent_id，并判断是否是合伙人
        // $db->selectDatabase(SRC_DB) ;
        $db= new DbClass() ;
        $sql = " select   u1.parent_id , u2.user_id as partner_parent_id  from ".SRC_DB." .ecs_users u1  
                 left join ".SRC_DB." .ecs_affiliate_card u2 on u1.parent_id = u2.user_id
                   where u1.user_id = $parter_id" ;
        $rows=  $db->query($sql , 0) ;

      //  var_dump(  $rows ) ;die ;
        $parent_id = 0 ;
        foreach ( $rows as $k => $v) {
            $partner_parent_id = $v['partner_parent_id'] ;
            $parent_id = $v['partner_parent_id'] ;
        }
        // 找完所有的上级
        if ( $parent_id == 0 )
            return  $partner_parent_id  ;

        //上级是合伙人
        if (  $partner_parent_id > 0 )
            return  $partner_parent_id  ;

        return  $this->getPartnerParentId( $parent_id )  ;
    }

  // 通过递归  获取 用户的  first_partner_id
    public  function getFirstPartnerId($user_id){
           if ( $user_id < 1 ) return 0 ;   //
           $db= new DbClass() ;
        //  $db->selectDatabase(DIST_DB) ;

            $sql = " select u1.parent_id ,( select  u4.user_type  from ".DIST_DB." .cf_users u4 where u4.user_id =  u1.parent_id  ) as parent_user_type ,
                       if( u3.user_id is null , 0 , u3.user_id ) as partner_id 
             from ".DIST_DB." .cf_user_user u1 
                      left join  ".DIST_DB." .cf_user_partner u3 on u1.parent_id = u3.user_id 
                       where u1.user_id = $user_id   " ;  // and u3.user_id > 0
         //   echo $sql ;
            $rows =  $db->query($sql , 0) ;
           // $rows = mysqli_fetch_all($result,1 );

            foreach ( $rows as $k => $v) {
                $parent_id = $v['parent_id'] ;
                $parent_user_type = $v['parent_user_type'] ;
                $partner_id = $v['partner_id']  ;
            }

            if ( $partner_id > 0  || $parent_id == 0 )  return  $parent_id ;

           return  $this->getFirstPartnerId( $parent_id , $parent_user_type ) ;

    }

    // 通过递归  获取 用户的  first_user_id
    public  function getFirstUserId($user_id){
        if ( $user_id < 1 ) return 0 ;   //
        $db= new DbClass() ;
        //  $db->selectDatabase(DIST_DB) ;

        $sql = " select u1.parent_id ,( select  u4.user_type  from ".DIST_DB." .cf_users u4 where u4.user_id =  u1.parent_id  ) as parent_user_type , u3.user_id as partner_id 
             from ".DIST_DB." .cf_user_user u1  inner join ".DIST_DB." .cf_users u2
                      on u1.user_id = u2.user_id  left join  ".DIST_DB." .cf_user_partner u3 on u1.parent_id = u3.user_id 
                       where u1.user_id = $user_id  and u3.user_id > 0  " ;
        $rows =  $db->query($sql , 0) ;
        // $rows = mysqli_fetch_all($result,1 );

        foreach ( $rows as $k => $v) {
            $parent_id = $v['parent_id'] ;
            $parent_user_type = $v['parent_user_type'] ;
            $partner_id = $v['partner_id']  ;
        }

        if ( $partner_id > 0  || $parent_id == 0 )  return  $parent_id ;

        return  $this->getFirstPartnerId( $parent_id , $parent_user_type ) ;

    }


    // 通过递归  获取 用户的  first_agent_id
    public  function getFirstAgentId($user_id ){
       return $user_id ;
    }

    public function  updateUser() {
        $db= new DbClass() ;
        // $db->selectDatabase(DIST_DB) ;

        //向 ".DIST_DB." .cf_users 更新first_partner_id  first_agent_id 数据
//        $sql =  " select  u1.user_id ,u1.user_type   from ".DIST_DB." .cf_users u1   " ;
//        $rows=  $db->query($sql , 0) ;
//      //  $rows = mysqli_fetch_all($result,1 );
//
//        foreach (  $rows as $k => $v ) {
//            $user_id =  $v["user_id"] ;
//            $first_partner_id =  $this->getFirstPartnerId( $user_id ) ;
//         //   $first_agent_id =  $this->getFirstAgentId( $user_id  );
//
//            if ( $first_partner_id    ) {
//                $sql =  " update  ".DIST_DB." .cf_users set first_partner_id = $first_partner_id    where user_id = $user_id " ;
//                $tp_rows=  $db->query($sql , 0) ;
//            }
//        }


        //向 ".DIST_DB." .cf_users 更新first_partner_id    数据
        $sql =  "   update ".DIST_DB." .cf_users u1 inner join ".DIST_DB." .cf_user_user u2 on u1.user_id = u2.user_id
                    left join ".DIST_DB." .cf_user_partner u3 on u2.parent_id = u3.user_id
                    set u1.first_partner_id = u2.parent_id 
                    where  u3.user_id > 0  " ;
        // $db->selectDatabase(DIST_DB) ;
        $result=  $db->query($sql , 0) ;

        //向 ".DIST_DB." .cf_users 更新 first_agent_id 数据 ,
        //  合伙人自己 属于代理商
        $sql =  " update ".DIST_DB." .cf_users u1  inner join ".DIST_DB." .cf_user_partner u2 on u1.user_id = u2.user_id
          set u1.first_agent_id =1023  " ;

        //  合伙人下属用户就属于代理商
        $sql =  "   update ".DIST_DB." .cf_users u1 inner join ".DIST_DB." .cf_user_user u2 on u1.user_id = u2.user_id
                    left join ".DIST_DB." .cf_user_partner u3 on u2.parent_id = u3.user_id                   
                    set u1.first_agent_id =  1023 
                    where  u3.user_id > 0 " ;
        $result=  $db->query($sql , 0) ;

      //代理商自己的一级以下会员 属于代理商
        $data = array() ;
        $nested = new NestedSets(DIST_DB.'.cf_user_user','left_key' ,'right_key','parent_id' , 'level' ,'user_id');
        $branch_id = 1023 ; //目前的唯一代理商 user_id
        $result = $nested->getBranch( $branch_id ) ;
        foreach ( $result as $v   ) {
            $sql =  " update ".DIST_DB." .cf_users u1 
                    set u1.first_agent_id = 1023  where  u1.user_id =  " .$v['user_id'] ;
            $result=  $db->query($sql , 0) ;
        }


    }

    public function  update_first_partner_id() {
        $db= new DbClass() ;
        // $db->selectDatabase(DIST_DB) ;

        //向 ".DIST_DB." .cf_users 更新first_partner_id  first_agent_id 数据
        $sql =  " select  u1.user_id ,u1.user_type   from ".DIST_DB." .cf_users u1   " ;
        $rows=  $db->query($sql , 0) ;
      //  $rows = mysqli_fetch_all($result,1 );
        $i = 0 ;
        foreach (  $rows as $k => $v ) {
            $user_id =  $v["user_id"] ;
            $first_partner_id =  $this->getFirstPartnerId( $user_id ) ;
         //   $first_agent_id =  $this->getFirstAgentId( $user_id  );

            if ( $first_partner_id    ) {
                $sql =  " update  ".DIST_DB." .cf_users set first_partner_id = $first_partner_id ,first_agent_id =1023   where user_id = $user_id " ;
                $tp_rows=  $db->query($sql , 0) ;
                $i++ ;
            }
        }
      echo "cf_users 更新first_partner_id  first_agent_id 数据 $i 条 成功" ;
    }



    //转移商品分类
    public function TransferCategoy()
    {
        $tables = array( 'tp_goods_category' )  ;
        $this->clear( $tables ) ;

        $db= new DbClass() ;
        // $db->selectDatabase(SRC_DB) ;

        $sql = "select * from ".SRC_DB." .ecs_category order by cat_id  " ;
        $ecs_rows=  $db->query($sql,0) ;
      //  $rows = mysqli_fetch_all($result,1 );

        $parent_id_rows = array() ;
        foreach( $ecs_rows  as  $k => $v) {
            $parent_id_rows[$v['cat_id']] = $v['parent_id'];
        }

        // $db->selectDatabase(DIST_DB) ;
        foreach( $ecs_rows  as  $k => $v) {
       //     var_dump( $v ) ;
            $id= $v['cat_id'] ;                 //ECS  有
            $name = $v['cat_name'] ;             //ECS  有
            $mobile_name =$v['cat_name'] ;       //ECS  有
            $parent_id = $v['parent_id'] ;        //ECS  有
            $parent_id_path = $this->getParentIdPath($parent_id_rows,$id ,$parent_id) ;  //ECS  无
          //  var_dump($parent_id_path ) ;
            $level =  substr_count( $parent_id_path , '_');                            //ECS  无
            $sort_order=  $v['sort_order'] ;       //ECS  有
            $is_show = $v['is_show'] ;             //ECS  有
            $image = ''. $v['cat_ico'] ;            //ECS  无
            $is_hot = 0            ;                //ECS  无
            $cat_group = 0            ;              //ECS  无
            $commission_rate = 0 ;                    //ECS  无

            $sql ="insert into ". DIST_DB ." .tp_goods_category(`id`,`name`, `mobile_name`,  `parent_id`,  `parent_id_path`, `level`, sort_order, is_show, image, is_hot, cat_group, commission_rate ) ".
                                        " values($id,'$name', '$mobile_name', '$parent_id','$parent_id_path', $level, $sort_order, $is_show,'$image',$is_hot,$cat_group,$commission_rate) ; " ;
             // $db->selectDatabase(DIST_DB) ;
           //  var_dump( $sql ) ; die   ;
            $tp_rows=  $db->query($sql,0) ;

        }
    }      //end  function TransferCategoy

     public function  TransferPoints() {
         $tables = array(  'tp_account_log'  )  ;

         $this->clear( $tables ) ;

        // $db->selectDatabase(SRC_DB) ;
         $db= new DbClass() ;
         $sql = "insert into ".DIST_DB." .tp_account_log(
              log_id , user_id , user_money ,  frozen_money  ,pay_points , change_time ,`desc` ,   `order_sn`  ,order_id ) ".
     "select log_id,  user_id , user_money  , frozen_money , floor( pay_points/10) , change_time, change_desc,  
              if (  left ( substring_index(substring_index(change_desc, ' ', 2 ), ' ' ,-1),3) !='201' , '' , 
                 left ( substring_index(substring_index(change_desc, ' ', 2 ), ' ' ,-1),13) )  as order_sn1  ,  
               (  select o.order_id from ecs_order_info o where o.order_sn = order_sn1 ) as order_id1
              from  ".SRC_DB." .ecs_account_log   " ;
         // $db->selectDatabase(DIST_DB) ;
         $tp_rows=  $db->query($sql , 0) ;

     }

    public function  TransferComment() {
        $tables = array(  'tp_comment'  )  ;
        $this->clear( $tables ) ;

        // $db->selectDatabase(SRC_DB) ;
        $db= new DbClass() ;
        $sql = "   insert into ".DIST_DB.".tp_comment(
                comment_id ,  goods_id , email ,        username  ,  content ,        add_time ,   `ip_address` ,  `is_show`  ,   parent_id ,  user_id ,   
               img ,   order_id  ,deliver_rank , goods_rank ,`service_rank` ,   `zan_num` ,  zan_userid  ,   is_anonymous , rec_id   )  

                SELECT     t1.comment_id ,  t1.id_value ,  t1.email  , t1.user_name ,   t1.content  ,   t1.add_time,   t1.ip_address ,  t1.`status`   , t1.parent_id   , if( t1.user_id >0 ,t1.user_id ,t1.user_id1 ) ,
                          t1.img1  ,    t1.order_id , t1.r1,    t1.r2  ,    t1.r3  ,     t1.zan_num  ,        t1.zan_userid       ,    t1.is_anonymous   ,   t1.rec_id 
                from   ( 
                    select c.comment_id ,  c.id_value ,  c.email  , c.user_name ,   c.content  ,   c.add_time,   c.ip_address ,  c.`status`   , c.parent_id   ,  
                               c.img1  ,    c.order_id , c.comment_rank as r1, c.comment_rank as r2  ,c.comment_rank as r3  ,     0  as zan_num ,          ''   as zan_userid    ,     1  as is_anonymous   ,   
                                  ( select  og.rec_id from ".SRC_DB.".ecs_order_goods  og where ( og.order_id = c.order_id )and og.goods_id = c.id_value  ) as rec_id ,
                                c.user_id ,   ( SELECT o1.user_id from ".SRC_DB.".ecs_order_info o1 where o1.order_id in  ( SELECT  g.order_id  from ".SRC_DB.".ecs_order_goods where g.goods_id = c.id_value ) ) as user_id1
                      from  ".SRC_DB.".ecs_comment c  left JOIN  ".SRC_DB.".ecs_order_goods g  on    c.id_value =g.goods_id               
                      group by c.comment_id   order by c.comment_id  
                ) as t1  where t1.user_id> 0  or t1.user_id1 >0   " ;

        // $db->selectDatabase(DIST_DB) ;
        $tp_rows=  $db->query($sql , 0) ;

    }

    public function  TransferWallet() {
        $tables = array( 'cf_distribute_divide_log' ,'cf_profit_log' )  ;
        $this->clear( $tables ) ;

        // $db->selectDatabase(SRC_DB) ;
        $db= new DbClass() ;

      // 更新可提现收益 ,待分利收益  总收益
        $sql = "update  ".DIST_DB." .cf_users u INNER JOIN  ".SRC_DB." .test t on u.user_id = t.user_id 
               inner join ".DIST_DB." .tp_users u1 on u.user_id = u1.user_id
                set u.wallet_accumulate_income = t.total_income ,
                u.wallet_accumulate_agent_income = if(u.user_type = 5,   t.total_income , 0),
                u.wallet_accumulate_partner_income = if(u.user_type = 3,   t.total_income , 0) ,
                u.wallet_accumulate_user_income = if( u.user_type = 1,   t.total_income , 0) ,
                u.wallet_agent_income   = if(u.user_type = 5,   t.withdrawable_income , 0) ,
                u.wallet_partner_income = if(u.user_type = 3,   t.withdrawable_income , 0),
                u.wallet_user_income    = if( u.user_type = 1 ,   0 , 0 ) ,
                u1.user_money   = if (  u.user_type = 1 , u1.user_money + t.withdrawable_income , u1.user_money  )  " ;

       // var_dump( $sql) ;
        // $db->selectDatabase(DIST_DB) ;
        $tp_rows=  $db->query($sql , 0) ;

        //导入 收益明细
        $sql = "insert into ".DIST_DB." .cf_distribute_divide_log(
                to_user_id ,    from_user_id ,   order_sn  ,  distribute_type ,   divide_ratio ,   `divide_money` ,   
                 score ,   is_divided  ,divide_time ,      remarks ,  `divide_remarks` ,   `add_time`    ) ".

        "select t.user_id ,    t.user_id ,  concat('u20180520',t.user_id,'00' )  ,
                                                      (  case when u.user_type = 1 then 1
                                                               when u.user_type = 3 then 2
                                                               when u.user_type = 5 then 4
                                                               end)  ,
                                                                                   5 ,            t.total_income  ,
                   0 ,    1  ,   1527064424 , '因升级到wap2,0汇总转入所有已分利' ,        '因升级到wap2,0汇总转入所有已分利'     ,  1527064424
              from  ".SRC_DB." .test t inner join ".DIST_DB." .cf_users u  on t.user_id = u.user_id  where t.total_income  >0       " ;
        // $db->selectDatabase(DIST_DB) ;
    //      var_dump( $sql) ;
        $tp_rows=  $db->query($sql , 0) ;


        $sql = "insert into ".DIST_DB." .cf_distribute_divide_log(
                to_user_id ,    from_user_id ,   order_sn  ,  distribute_type ,   divide_ratio ,   `divide_money` ,   
                 score ,   is_divided  ,divide_time ,      remarks ,  `divide_remarks` ,   `add_time`    ) ".

            "select t.user_id ,    t.user_id ,  concat('u20180520',t.user_id ,'01')  ,
                                                      (  case when u.user_type = 1 then 1
                                                               when u.user_type = 3 then 2
                                                               when u.user_type = 5 then 4
                                                               end) ,
                                                                                   5 ,            t.wait_income  ,
                   0 ,    0  ,       0     , '因升级到wap2,0汇总转入所有待分利' ,        '因升级到wap2,0汇总转入所有待分利'     ,  UNIX_TIMESTAMP()
             from  ".SRC_DB." .test t inner join ".DIST_DB." .cf_users u  on t.user_id = u.user_id  where t.wait_income >0      " ;
     //  var_dump( $sql) ; die ;
        // $db->selectDatabase(DIST_DB) ;
        $tp_rows=  $db->query($sql , 0) ;
    }


    public function  TransferUpdateCdn() {
        // $db->selectDatabase(SRC_DB) ;
        $db= new DbClass() ;

        //更新tp_goods表的original_img
        $sql = " update ".DIST_DB." .tp_goods set original_img = left(original_img,length(original_img) - 4 ) 
                 where original_img like '%s01' " ;
        // $db->selectDatabase(DIST_DB) ;
        $tp_rows=  $db->query($sql , 0) ;

        //
        $sql = " update ".DIST_DB." .tp_goods set original_img = left(original_img,length(original_img) - 4 ) 
                 where original_img like '%s01' " ;
        // $db->selectDatabase(DIST_DB) ;
        $tp_rows=  $db->query($sql , 0) ;

    }

    public function  TransferApply() {
        $tables = array( 'cf_apply_partner'  )  ;
        $this->clear( $tables ) ;

        // $db->selectDatabase(SRC_DB) ;
        $db= new DbClass() ;

        // 插入合伙人申请信息
        $sql = "insert into ".DIST_DB." .cf_apply_partner(
            user_id , agent_id ,        channel_type , apply_content ,                `apply_time` ,       status ,     deal_time ,    deal_result  ) 
   select  u1.user_id , 1023 ,    0   ,   ' {\"performance_value\":80 }' , u1.be_partner_start ,  1  ,  u1.be_partner_start , '同意'       
       from  ".DIST_DB." .cf_user_partner u1  inner join  ".DIST_DB." .cf_users u2  on u1.user_id = u2.user_id     "  ;
        $tp_rows=  $db->query($sql , 0) ;

    }


    public function clear( $tables=array() )
    {
        // $db->selectDatabase(DIST_DB) ;
          $dist_db = DIST_DB ;
          if (  $dist_db =="tpshop" ||   $dist_db =="shangmeibf" ) {
              echo "目标数据库是 正式数据库，不能执行清空。" ;
              return 0 ;
          }

//          if ( empty( $tables) ) {
//              $tables = array( 'tp_goods_category' , 'tp_brand' ,
//                  'tp_goods_images','tp_goods_attr', 'tp_goods_images','tp_goods_attribute','tp_goods',
//                  'tp_users','cf_users','cf_user_user','cf_user_partner','cf_user_agent','tp_user_address',
//                  'tp_order','tp_order_goods'  , 'tp_comment' , 'tp_account_log' ,'cf_distribute_divide_log' ,'cf_profit_log' )  ;
//          } ;

        $db= new DbClass() ;
        //  清空 要导入的表   ;
         foreach( $tables as $t ) {
             $sql = "truncate ".DIST_DB .".". $t ;
             $tp_rows=  $db->query($sql , 0) ;
         }



    }

        public function transfer()
    {
           //   $tables =    array('cf_distribute_divide_log')  ;
           //  $this->clear( $tables ) ;
          //   echo "step1: clear  ok! \n" ;

//             $this->TransferUser() ;
//             echo "step2: TransferUser ok ! \n" ;

//            $this->TransferCategoy() ;
//            echo "step3: TransferCategoy ok ! \n" ;
//
//            $this->TransferBrand() ;
//            echo "step4: TransferBrand ok ! \n" ;
//
//            $this->TransferGoods() ;
//             echo "step5: TransferBrand ok ! \n" ;

//            $this->TransferOrders() ;
//            echo "step6: TransferOrders ok ! \n" ;
//
//            $this->updateUser() ;
//            echo "step7: updateUser ok ! \n" ;
//
//           $this->TransferPoints() ;
//           echo "step7: updateUser ok ! \n" ;
//
//            $this->transferComment() ;
//            echo "step8: transferComment ok ! \n" ;
//
//            $this->transferWallet() ;
//            echo "step9: transferWallet ok ! \n" ;
//
//            $this->TransferUpdateCdn() ;
//            echo "step10: TransferUpdateCdn ok ! \n" ;
//
//            $this->TransferApply() ;
//             echo "step11: TransferApply ok ! \n" ;

     //         $this->update_first_partner_id() ;
           echo  $this->getFirstPartnerId( 4049 ) ;
           echo " success! \n" ;
    }

    //****************bluce-end***********************

}








