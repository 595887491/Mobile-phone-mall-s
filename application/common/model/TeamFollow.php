<?php
/**
 * tpshop
 * ============================================================================
 * 版权所有 2015-2027 深圳搜豹网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.tp-shop.cn
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * Author: IT宇宙人
 * Date: 2015-09-09
 */
namespace app\common\model;

use think\Db;
use think\Exception;
use think\Model;

class TeamFollow extends Model
{
    public function teamActivity()
    {
        return $this->hasOne('TeamActivity', 'team_id', 'team_id');
    }
    public function teamFound(){
        return $this->hasOne('TeamFound','found_id','found_id');
    }
    public function order(){
        return $this->hasOne('Order','order_id','order_id');
    }
    public function orderGoods(){
        return $this->hasOne('OrderGoods','order_id','order_id');
    }

    //状态描述
    public function getStatusDescAttr($value, $data)
    {
        $status = config('TEAM_FOLLOW_STATUS');
        return $status[$data['status']];
    }

    /*************      zhaolei-----我的拼团                ******/

    static $teamStatus = [
        0 => '待拼团',//表示已下单但是未支付
        1 => '拼团中',//已支付
        2 => '拼团成功',
        3 => '拼团失败',
    ];

    /*
     * 根据用户获取用户 拼团的商品信息;
     * */
    public function getTeamInfoList($userId)
    {
        $followTeam = Db::table('tp_team_follow')->field('team_id,found_id,order_id,follow_id')->where("follow_user_id = $userId AND status > 0")->select();
        $foundTeam = Db::table('tp_team_found')->field('team_id,status,found_time,found_end_time,found_id,order_id,join')
            ->where("user_id = $userId AND status > 0")->select();
//        halt(Db::getLastSql());

//        $foundTeam = Db::table('tp_team_found')
//            ->alias('a')
//            ->distinct('found_id')
//            ->join('tp_team_follow b','a.found_id = b.found_id','left')
//            ->where("a.user_id = $userId OR b.follow_user_id = $userId")
//            ->where('a.status > 0 OR b.status>0')
//            ->select();
//halt($foundTeam);
        for ($i=0;$i<count($foundTeam);$i++){
            $foundTeam[$i]['statusStr'] = self::$teamStatus[$foundTeam[$i]['status']];//获取拼团状态转换成字符串
//            $this->fail($foundTeam[$i]['found_end_time'],$foundTeam[$i]['team_id']);  //拼团时间判断
            $foundTeam[$i]['info'] = $this->teamInfo($foundTeam[$i]['team_id']);//获取拼团信息
            $foundTeam[$i]['orderStatus'] = $this->getOrderStatus($foundTeam[$i]['order_id']);//获取拼团订单状态
            $foundTeam[$i]['prices'] = Db::table('tp_team_found')->field('price,goods_price,order_id')
                ->where('found_id',$foundTeam[$i]['found_id'])
                ->find();//获取拼团价格

            $this->EndTime($foundTeam[$i]['found_id']);

        }
        for ($i=0;$i<count($followTeam);$i++){
            $followTeam[$i]['statusStr'] = self::$teamStatus[$followTeam[$i]['status']];//获取拼团状态转换成字符串
            $followTeam[$i]['found_end_time'] = $this->followEndTime($followTeam[$i]['found_id']);  //拼团时间判断
            $followTeam[$i]['info'] = $this->teamInfo($followTeam[$i]['team_id']);//获取拼团信息
            $followTeam[$i]['orderStatus'] = $this->getOrderStatus($followTeam[$i]['order_id']);//获取拼团订单状态
            $followTeam[$i]['prices'] = Db::table('tp_team_found')->field('price,goods_price,join,status')
                ->where('found_id',$followTeam[$i]['found_id'])
                ->find();//获取拼团价格
            $followTeam[$i]['join'] = $followTeam[$i]['prices']['join'];//获取该团已参团人数
            $followTeam[$i]['status'] = $followTeam[$i]['prices']['status'];//获取该团状态
            $this->EndTime($followTeam[$i]['found_id']);
        }


//        $arr = array_column($foundTeam,'status');
//        array_multisort($arr,SORT_ASC,$foundTeam); //结果数组按拼团状态顺序排序
//        return $foundTeam;
        $res = array_merge($followTeam,$foundTeam);
        $nowTime = time();
        for ($i=0;$i<count($res);$i++){
            $res[$i]['startTime'] = $nowTime;//拼团倒计时开始时间
            $res[$i]['goodsNum'] = $this->getGoodsNum($res[$i]['order_id']);//获取拼团订单商品数量
            $res[$i]['add_time'] = $res[$i]['orderStatus']['add_time'];//获取拼团订单下单时间
            $res[$i]['recId']  = M('order_goods')->where("order_id", $res[$i]['order_id'])->select()[0]['rec_id'];//获取rec_id,评论自增id
        }

        $arr = array_column($res,'add_time');
        array_multisort($arr,SORT_DESC,$res); //结果数组按拼团时间倒叙排序

        return $res;
    }

    /*
     * 根据拼团活动id获取拼团信息
     *
     * */
    public function teamInfo($teamId){
        $field = 'a.is_shipping,a.goods_name,a.needer,a.team_price,a.act_name,a.item_id,a.sales_sum
                  ,b.shop_price,b.original_img,b.goods_id';
        $res = Db::table('tp_team_activity')
            ->alias('a')
            ->join('tp_goods b','a.goods_id = b.goods_id','LEFT')
            ->field($field)
            ->where('team_id',$teamId)
            ->find();

        $res['item_id'] = Db::table('tp_spec_goods_price')
            ->field('key_name')
            ->where('item_id',$res['item_id'])
            ->find();
        return $res;
    }

    /*
     * 参团的拼团结束时间
     * */
    public function followEndTime($founId)
    {
        $res = Db::table('tp_team_found')->field('found_end_time,team_id,status')->where('found_id',$founId)->find();
//        if ($res['status']==1 && time()>$res['found_end_time'])  $this->fail($res['found_end_time'],$res['team_id']);
        return $res['found_end_time'];
    }

    /*
   * 参团的拼团结束时间
   * */
    public function EndTime($founId)
    {
        $res = Db::table('tp_team_found')->field('found_end_time,team_id,status')->where('found_id',$founId)->find();
        if (time()>=$res['found_end_time'] and $res['status']==1){
            $result = Db::table('tp_team_found')->where('found_id',$founId)->update(['status'=>3]);
            //发送模板消息
            try{
                (new \app\common\logic\WechatLogic())->sendTemplateMsgOnTeamFail($founId);
            }catch (Exception $e){
                \app\common\library\Logs::sentryLogs('发送拼团失败模板消息失败:'.$e->getMessage(),$res);
            }
        }
        return $result;
    }

    /*
     * 支付状态为大于零时加入拼团
     * join字段加一
     * */
    public function addJoin($payStatus,$foundId,$need)
    {
        $model = new TeamFound();
        $join = $model->field('join')->where('found_id',$foundId)->find();
        if ($join < $need) {
            if ($payStatus == 1) $model->where('found_id',$foundId)->update(['join'=>$join+1,'status'=>1]);
        }
    }


    /*
     * 拼团超时未成功,处理为拼团失败
     * */
    public function fail($endTime,$teamId)
    {
        if (time() > $endTime){
            return Db::table('tp_team_found')->where('team_id',$teamId)->update(['status'=>3]);
        }
    }


    /*
     * 查询订单状态
     * */
    public function getOrderStatus($orderId)
    {
        $res =Db::table('tp_order')
            ->alias('a')
            ->field('a.order_status,a.shipping_status,a.pay_status,a.add_time,b.goods_num')
            ->join('tp_order_goods b',"a.order_id = b.order_id")
            ->where('a.order_id',$orderId)
            ->find();
        return $res;
    }


    /*
     * 查询订单商品数量信息
     * */
    public function getGoodsNum($orderId)
    {
        $res =Db::table('tp_order_goods')
            ->field('goods_num')
            ->where('order_id',$orderId)
            ->find();
        return $res['goods_num'];
    }


    /*
     * 获取拼团信息详情
     * */
    public function getTeamInfoMain($teamId)
    {
//        halt($teamId);
        $field ='a.team_price,a.is_shipping,a.needer,
                 c.goods_name,c.goods_remark,c.shop_price,c.original_img';
        $res = Db::table('tp_team_activity')
            ->alias('a')
//            ->join('tp_team_follow b','a.team_id = b.team_id','LEFT')
            ->join('tp_goods c','a.goods_id = c.goods_id','LEFT')
            ->field($field)
            ->where('team_id',$teamId)
            ->select();
        return $res;
    }




    /*
     * 拼团+订单详情数据
     * */
    public function getTeamInfo($foundId,$userId)
    {
        $field = 'a.goods_name,a.goods_id,a.is_shipping,a.needer,team_price ,a.item_id,a.team_id,a.share_img,a.needer_type    
                  ,b.follow_user_head_pic,b.found_id
                  ,c.found_time,c.order_id,c.found_end_time,c.user_id,c.join,c.need,c.price,c.goods_price,c.status,c.found_id,c.nickname
                  ';
        $teamInfo = Db::table('tp_team_found')
            ->alias('c')
            ->field($field)
            ->join('tp_team_activity a','a.team_id = c.team_id')
            ->join('tp_team_follow b','c.found_id = b.found_id','left')
            ->where('c.found_id',$foundId)
            ->find();
//        halt($teamInfo);
        //拼团商品品牌参数
        $teamInfo['goods'] = Db::table('tp_goods')
            ->alias('a')
            ->field('a.goods_name,a.original_img,a.goods_remark,a.mobile_content,a.goods_content,a.brand_id')
            ->where('goods_id',$teamInfo['goods_id'])
            ->find();

        $teamInfo['head_pic'] = Db::table('tp_users')->field('head_pic')->where('user_id',$teamInfo['user_id'])->find()['head_pic'];

        $fid = $teamInfo['found_id'];
        $where = "found_id = $fid and status > 0";
        $followUserInfo = Db::table('tp_team_follow')
            ->distinct(true)
            ->field('follow_user_nickname,follow_user_head_pic,follow_user_id')
            ->where($where)
            ->select(); //除团长外参团人

        $foundUser = Db::table('tp_team_found')->field('user_id')->where('found_id',$teamInfo['found_id'])->select();

        $followUser = array_column($followUserInfo,'follow_user_id');//参团用户
        $foundUser = array_column($foundUser,'user_id');//开团用户
        $inTeam = array_merge($foundUser,$followUser);//在团中的用户

        $teamId = $teamInfo['team_id'];
        $condition = "team_id=$teamId and status != 3 and status != 0";
        $foundNum = Db::table('tp_team_found')->where($condition)->count();//开团量
        $activeNum = Db::table('tp_team_activity')->field('virtual_num')->where('team_id',$teamId)->find();//虚拟量
        $followNum = $this->where($condition)->count();//参团量
        $teamInfo['teamed'] = $foundNum+$followNum+$activeNum['virtual_num']; //已下单的人数作为已拼团的人数

        $teamInfo['goods']['goods_content'] = htmlspecialchars_decode($teamInfo['goods']['goods_content']);//商品详情
        $teamInfo['goods']['brand_id'] = Db::table('tp_brand')->field('name')->where('id',$teamInfo['goods']['brand_id'])->find()['name'];//商品品牌
        $rec = new TeamFollow();

        $parameter = Db::table('tp_goods_attr')
            ->alias('a')
            ->field('a.attr_value,b.attr_name')
            ->where('a.goods_id',$teamInfo['goods_id'])
            ->join('tp_goods_attribute b','a.attr_id = b.attr_id')
            ->order('a.attr_id asc')
            ->select(); //获取产品添加的属性参数

        //订单详情
        $model = new TeamFollow();
        $order = new Order();
        $order_id = $this->getOrderId($userId,$teamInfo['found_id']);
        $teamInfo['order_id'] = $order_id;
        $teamInfo['orderStatus'] = $model->getOrderStatus($order_id);
        $orderInfo = $order->orderDetail($order_id);
//        halt($orderInfo);
//        if($orderInfo)$orderInfo = $orderInfo->toArray();
//        else throw new Exception('拼团购买商品');

        $order_goods = M('order_goods')->where("order_id", $order_id)->select();
//        if(empty($order_goods) || empty($order_id)){
//            throw new Exception('没有获取到订单信息');
//        }
        $delivery = M('delivery_doc')->where("order_id", $order_id)->find();

//        if ( empty($delivery)) {
//            $delivery = M('order')->where("order_id", $order_id)->find();
//            if( empty($delivery['shipping_code'])){
//                throw new Exception('运单号有误');
//            }
//        }
        $res =  array('order_goods'=>$order_goods,
            'delivery'=>$delivery,//物流信息
            'TeamOrderStatus'=>$teamInfo['orderStatus'], //拼团信息
            'orderInfo'=>$orderInfo,//拼团订单信息
            'teamInfo'=>$teamInfo,//拼团信息
            'inTeam'=>$inTeam,//在团中的
            'followUserInfo'=>$followUserInfo,//参团人员信息
            'parameter'=>$parameter);//拼团商品参数;
//        halt($res);
        return $res;
    }


    /*
     * 根据userid 获取订单id
     * */
    public function getOrderId($userId,$foundId)
    {
        $found = Db::table('tp_team_found')->field('order_id')->where(['user_id'=>$userId,'found_id'=>$foundId])->find();
        if (empty($found)){
            $found = Db::table('tp_team_follow')->field('order_id')->where(['follow_user_id'=>$userId,'found_id'=>$foundId])->find();
        }
        return $found['order_id'];
    }



    /*************      zhaolei-----我的拼团END             ******/
}
