<?php
/**
 * @Author: 陈静
 * @Date: 2018/06/13 14:16:55
 * @Description:
 */

namespace app\admin\model;


use app\common\model\VrOrderCode;
use think\Db;
use think\Model;

class Order extends Model
{

    //后台订单类型
    const ORDER_TYPE = [
        1=>1,//未消费
        2=>2,//待评价
        3=>3,//已完成
        4=>4,//待付款
        5=>5,//已退款
        6=>6 //已取消
    ];

    //订单状态
    const ORDER_STATUS = [
        0 => 0,//"待确认"
        1 => 1,//"已确认"
        2 => 2,//"已收货"
        3 => 3,//"已取消"
        4 => 4,//"已完成"
        5 => 5,//"已作废"
    ];

    //支付状态
    const PAY_STATUS = [
        0 => 0,//"未支付"
        1 => 1,//"已支付"
        2 => 2,//"部分支付"
        3 => 3,//"已退款"
        4 => 4,//"拒绝退款"
    ];

    public function getOrderList()
    {
        $res = $this->alias('a')
            ->field("	a.user_id,count(a.user_id) as order_nums,sum(a.user_money) AS user_moneys,`b`.`nickname`,`b`.`mobile`,
	FROM_UNIXTIME(`a`.`add_time`, '%Y-%m-%d') AS add_times")
            ->join('users b','a.user_id = b.user_id','LEFT')->group('a.user_id');
        return $res;
    }


    /*
     * @Author : 赵磊
     * 后台卡券订单列表查询
     * */
    public function getVrOrderInfoList($data)
    {
        $consume = new VrOrderCode();
        $fields = 'a.order_sn,a.order_id,a.user_id,a.pay_status,a.order_status,a.pay_time,a.add_time
                    ,a.pay_name,a.coupon_price,a.total_amount,a.integral_money,a.user_money,a.order_amount
                    ,b.goods_num,b.goods_name,b.is_comment
                    ,c.nickname,c.mobile,c.head_pic'; //查询字段

        if ($data['pay_status'] == 4) $condition['pay_status'] =0;//支付状态;0未支付;1已支付;2为默认全部
        if ($data['pay_status'] == 1) $condition['pay_status'] =1;//支付状态;0未支付;1已支付;2为默认全部
        $condition['a.prom_type'] = 5;//虚拟订单类型
        $startTime = strtotime($data['startTime']);//开始时间
        $endTime = strtotime($data['endTime'])+24*3600;//结束时间为当天的0点,加上一天


        //订单类型条件搜索 ;默认全部;1未消费;2待评价;3已完成;4待付款;5已退款;6已取消
        $orderType = $data['typeTab'];
        if ($orderType == self::ORDER_TYPE[1]){
            $userNum = M('vr_order_code')
                ->alias('a')
                ->distinct(true)
                ->join('order b','a.order_id=b.order_id')
                ->field('a.order_id')
                ->where('a.vr_state=0 and b.prom_type = 5')
                ->select();//含有未消费的订单
            $userNum = array_column($userNum,'order_id');
            $condition['a.order_id'] = array('in',$userNum);
        }//未消费
        if ($orderType == self::ORDER_TYPE[2]){
            $comment = M('order_goods')
                ->alias('a')
                ->join('order b','a.order_id = b.order_id')
                ->field('a.order_id')
                ->where('a.is_comment=0 and b.prom_type = 5 and b.order_status = 2')
                ->select();
            $comment = array_column($comment,'order_id');
            $condition['a.order_id'] = array('in',$comment);
        };//待评价
        //已完成
        if ($orderType == self::ORDER_TYPE[3]){
            $condition['a.order_status'] = self::ORDER_STATUS[4];
        }
        //待付款
        if ($orderType == self::ORDER_TYPE[4]){
            $condition['a.pay_status'] = self::PAY_STATUS[0];
            $condition['a.order_status'] = self::ORDER_STATUS[0];
        }
        if ($orderType == self::ORDER_TYPE[5]) $condition['a.pay_status'] = self::PAY_STATUS[3];//已退款
        if ($orderType == self::ORDER_TYPE[6]) $condition['a.order_status'] = self::ORDER_STATUS[3];;//已取消

        //用户类型搜索
        $searchValue = $data['keywords'];
        if ($data['keytype'] == 'user' && $searchValue != ''){
            $userId = M('users')
                ->field('user_id')
                ->where('user_id|nickname|mobile','like',"%$searchValue%")
                ->select();
            $userId = array_column($userId,'user_id');
            $condition['a.user_id'] = array('in',$userId);
        }
        //商品类型搜索
        if ($data['keytype'] == 'goods' && $searchValue != ''){
            $orderId = M('order_goods')
                ->field('order_id')
                ->where('goods_id|goods_name','like',"%$searchValue%")
                ->select();
            $orderId = array_column($orderId,'order_id');
            $condition['a.order_id'] = array('in',$orderId);
        }
        $orderInfo = M('order')
            ->alias('a')
            ->field($fields)
            ->join('order_goods b','a.order_id=b.order_id')
            ->join('users c','a.user_id=c.user_id')
            ->where($condition)
            ->where('a.add_time', 'between', [$startTime,$endTime])
            ->order('order_id desc')
            ->select();
        for ($i=0;$i<count($orderInfo);$i++){
            $orderInfo[$i]['is_consume'] = $consume->listConsumeStaus($orderInfo[$i]['order_id']);//是否已消费
            $orderInfo[$i]['isFillIn'] = $consume->fillIn($orderInfo[$i]['order_id']);//是否已填写
            $orderInfo[$i]['pay'] =  $orderInfo[$i]['total_amount']-$orderInfo[$i]['coupon_price']-$orderInfo[$i]['integral_money'];//实际应付
        }
        return $orderInfo;
    }

    /*
     * @Author : 赵磊
     * 卡券数组排序
     * */
    public function VrOrderInfoListSort($data,$orderList)
    {
        $sort = array_column($orderList,$data['order_by']);
        if ($data['sort'] == 'asc')array_multisort($sort,SORT_ASC,$orderList);
        if ($data['sort'] == 'desc')array_multisort($sort,SORT_DESC,$orderList);
        return $orderList;
    }

    /*
     * @Author :赵磊
     * 导出数据
     * */
    public function export($data)
    {
        $export = $data['export'];
        if($export == 1){
            $order_ids = $data['order_ids'];
            if($order_ids){
                $condition['a.order_id'] = array(in,$order_ids);
            }
            $condition['prom_type'] = 5;

            $fields = 'a.order_sn,a.pay_time,a.order_id
                       ,b.user_id,nickname,b.mobile
                       ,c.vr_code';
            $orderList = M('order')
                ->alias('a')
                ->join('users b','a.user_id=b.user_id')
                ->join('vr_order_code c','a.order_id = c.order_id')
                ->field($fields)
                ->where($condition)
                ->select();
            $info = $this->getTestInfo($order_ids);

            for($i=0;$i<count($info);$i++){
                $orderList[$i]['content'] = $info[$i]['content'];
                $orderList[$i]['add_time'] = $info[$i]['add_time'];
            }

            $strTable ='<table width="500" border="1">';
            $strTable .= '<tr>';
            $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">订单编号</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="100">用户id</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">用户昵称</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">用户手机号</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">缤纷券密码</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">支付时间</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">体检人姓名</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">体检人性别</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">体检人身份证号</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">期望体检分院</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">期望体检日期</td>';
            $strTable .= '<td style="text-align:center;font-size:12px;" width="*">体检信息提交时间</td>';
            $strTable .= '</tr>';

            foreach($orderList as $k=>$val){
                $strTable .= '<tr>';
                $strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$val['order_sn'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['user_id'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['nickname'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['mobile'].'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">`'.$val['vr_code'].'</td>';
                if ($val['pay_time']){
                    $strTable .= '<td style="text-align:left;font-size:12px;">'.date('Y-m-d',$val['pay_time']).' </td>';
                }else{
                    $strTable .= '<td style="text-align:left;font-size:12px;">'.'未填' .' </td>';
                }
                $content = json_decode($val['content']);
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$content->full_name.'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$content->sex.'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">`'.$content->id_no.'</td>';
                $strTable .= '<td style="text-align:left;font-size:12px;">'.$content->hostpital.'</td>';
                if ($content->input_date){
                    $strTable .= '<td style="text-align:left;font-size:12px;">'.$content->input_date.'</td>';
                }else{
                    $strTable .= '<td style="text-align:left;font-size:12px;">'.'未填' .'</td>';
                }

                if ($info[$k]['add_time']){
                    $strTable .= '<td style="text-align:left;font-size:12px;">'.date('Y-m-d',$val['add_time']).'</td>';
                }else{
                    $strTable .= '<td style="text-align:left;font-size:12px;">'.'未填' .'</td>';
                }

                $strTable .= '</tr>';
            }
            $strTable .='</table>';
            unset($orderList);

            downloadExcel($strTable,'virtualOrder');
            exit();
        }
    }

    /*
     * @Author : 赵磊
     * 获取体检人信息
     * */
    public function getTestInfo($orderId)
    {
        $condition['order_id'] = array('in',$orderId);
        $res = Db::table('cf_form_data')
            ->field('content,add_time')
            ->where($condition)
            ->select();
        return $res;
    }


    /*
     * @Author : 赵磊
     * 后台卡券订单详情
     * */
    public function getVrOrderInfo($orderId)
    {
        //订单信息
        $fields = 'a.order_sn,a.order_status,a.pay_status,a.pay_name,a.transaction_id,a.add_time,a.pay_time,a.user_id,a.total_amount
                    ,a.user_money,a.order_amount
                    ,b.goods_price,b.goods_num,b.is_comment
                    ,c.goods_id,c.goods_name,c.original_img
                    ,d.nickname,d.mobile,d.head_pic';
        $orderInfo = $this
            ->alias('a')
            ->join('order_goods b','a.order_id = b.order_id')
            ->join('goods c','b.goods_id = c.goods_id')
            ->join('users d','a.user_id = d.user_id')
            ->field($fields)
            ->where('a.order_id',$orderId)
            ->find();
        if ($orderInfo) $orderInfo = $orderInfo->toArray();
        $result['orderInfo'] = $orderInfo;

        //卡券列表
        $fid = 'a.vr_code,a.vr_state,a.rec_id,b.pay_status,b.order_status,a.vr_indate,a.vr_usetime,a.refund_lock';
        $vrCode = M('vr_order_code')
            ->alias('a')
            ->join('order b','a.order_id=b.order_id')
            ->field($fid)
            ->where('a.order_id',$orderId)
            ->select();
//        for ($i=0;$i<count($vrCode);$i++){
//            $cardList[$i]['consumption'] = (new VrOrderCode())->listConsumeStaus($orderId);
//        }
        $result['vrCode'] = $vrCode;
        //订单消费状态
        $result['consumption'] = (new VrOrderCode())->listConsumeStaus($orderId);

        //体检人信息
        $fid2 = 'add_time,content';
        $testUserInfo = Db::table('cf_form_data')
            ->field($fid2)
            ->where('order_id',$orderId)
            ->select();
        for ($i=0;$i<count($testUserInfo);$i++){
            $testUserInfo[$i]['content'] = \GuzzleHttp\json_decode($testUserInfo[$i]['content']);
        }
        $result['testUserInfo'] = $testUserInfo;

        //操作记录
        $fid3 = 'a.user_name,c.role_name';
        $user = M('order_action')->where('order_id',$orderId)->select();//使用者id

        for($i=0;$i<count($user);$i++){
            $log[$i] = $user[$i];
            if ($user[$i]['action_user']==0){//0时使用者为用户
                $log[$i]['role_name'] = '用户';
                $log[$i]['user_name'] = mb_substr(M('order_goods')->field('goods_name')->where('order_id',$orderId)->find()['goods_name'],0,5);
            }else{
                $log[$i]['user_name'] = $this->getLog($user[$i]['action_user'])['user_name'];//管理院名
                $log[$i]['role_name'] = $this->getLog($user[$i]['action_user'])['role_name'];//身份名
            }
        }
        $result['log'] = $log;

        return $result;
    }

    /*
     * @Author :赵磊
     * 查询兑换码是否使用
     * */
    public function vrCodeUse($recId)
    {
        $status = M('vr_order_code')->field('vr_state')->where('rec_id',$recId)->find();
        return $status['vr_state'];
    }

    public function getLog($action_user)
    {

        $log = M('admin')
            ->alias('a')
            ->join('admin_role c','a.role_id = c.role_id')
            ->field('a.user_name,c.role_name')
            ->where('a.admin_id',$action_user)
            ->find();
        return $log;
    }



}