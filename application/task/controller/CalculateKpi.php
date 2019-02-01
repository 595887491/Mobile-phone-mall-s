<?php
/**
 * @Author: 陈静
 * @Date: 2018/05/03 08:35:36
 * @Description: 计算合伙人成就者
 */

namespace app\task\controller;

use app\common\library\Logs;
use app\common\model\UserPartnerModel;
use think\Controller;
use think\Db;
use think\Exception;
use think\Request;

/**
合伙人的成就值（KPI）  =  (k1*40% + k2*20% +k3*20 + k4*20)* 100 / KPImax
其中：
k1= 我的一级会员总数/所有合伙人的一级会员总数;
k2= 我的购买过商品的一级会员数量/所有合伙人的一级会员总数 ;
k3 =  我的一级会员订单总额/所有合伙人的一级会员订单总额 ;
k4 =  我的自购订单总额/（所有合伙人的自购订单总额
KPImax = 为所有合伙人中最大的成就值。
 */
class CalculateKpi extends Controller
{
    //占比的定义
    public $first_child_num_percent = 40;//一级会员数量
    public $first_child_liveness_percent = 20;//一级会员活跃度
    public $first_child_sales_percent = 20;//一级会员销量
    public $self_buy_money_percent = 20;//自购金额
    public function __construct(Request $request = null)
    {
        parent::__construct($request);
        $week = date('w');// 0 1 2  3  4  5  6

        if ($week) {
            $mondayDays = -$week - 6;
            $sundayDays = - $week;
        }else{
            $mondayDays =  -6;
            $sundayDays =  0;
        }

        $this->monday = strtotime(   $mondayDays.' days' ) - date('s')-date('i')*60-date('H')*60*60;
        $this->sunday = strtotime(date('Y-m-d',strtotime(  $sundayDays .' days' ))) + 24 * 3600 -1;

        Logs::sentryLogs();
    }

    //插入数据库
    public function insertData()
    {
        $res = Db::table('cf_user_kpi_log')->where(['kpi_start'=> $this->monday,'kpi_end' => $this->sunday])->count();
        if ($res) {
            return;
        }
        $data = $this->calculateScore();
        $func = function (&$value) {
            unset($value['partner_kpi']);
        };
        Db::startTrans();
        try{
            (new UserPartnerModel())->isUpdate(true)->saveAll($data)->toArray();
            array_walk($data,$func);
            Db::table('cf_user_kpi_log')
                ->insertAll($data);
        }catch (Exception $e){
            Db::rollback();
            Logs::sentryLogs($e,['msg'=>'插入合伙人KPI数据失败']);
        }
        Db::commit();

    }

    /**
     * @Author: 陈静
     * @Date: 2018/05/13 10:04:24
     * @Description: 计算各项得分,组装插入数据库的值
     */
    public function calculateScore()
    {
        $firstChildNum = $this->findFirstChildNum();
        $firstChildLivenessAndSales = $this->findFirstChildLivenessAndSales();
        $selfBuyMoney = $this->findSelfBuyMoney();

        //计算一级会员得分
        //所有合伙人下级所有一级会员的总数
        $totalFirstChildNum = array_sum(array_column($firstChildNum,'child_nums')) ;

        //计算一级会员订单数和订单总额得分
        $totalFirstChildOrderNum = array_sum(array_column($firstChildLivenessAndSales,'child_order_nums')) ;
        $totalFirstChildOrderAmountNum = array_sum(array_column($firstChildLivenessAndSales,'child_order_amount')) ;

        //计算自购得分
        $totalSelfBuyMoney = array_sum(array_column($selfBuyMoney,'self_money')) ;

        $data = [];
        $maxKpi = 0;
        //计算各个得分
        foreach ($firstChildNum as $key => $value) {
            $arr = [];
            $data[$key]['user_id'] = $value['user_id'];
            //1.一级会员数量得分
            if ($totalFirstChildNum) {
                $arr['child_percent'] = round(($firstChildNum[$key]['child_nums'] / $totalFirstChildNum) * $this->first_child_num_percent,2);
            }else{
                $arr['child_percent'] = 0;
            }
            //2.一级会员订单数量得分
            if ($totalFirstChildOrderNum) {
                $arr['child_order_nums_percent'] = round(($firstChildLivenessAndSales[$key]['child_order_nums'] / $totalFirstChildOrderNum) * $this->first_child_liveness_percent,2);
            }else{
                $arr['child_order_nums_percent'] = 0;
            }

            //3.一级会员订单总额得分
            if ($totalFirstChildOrderAmountNum) {
                $arr['child_order_amount_percent'] = round(($firstChildLivenessAndSales[$key]['child_order_amount'] / $totalFirstChildOrderAmountNum) * $this->first_child_sales_percent,2);
            }else{
                $arr['child_order_amount_percent'] = 0;
            }

            //2.自购订单总额得分
            if ($totalSelfBuyMoney) {
                $arr['self_money_percent'] = round(($selfBuyMoney[$key]['self_money'] / $totalSelfBuyMoney) * $this->self_buy_money_percent,2);
            }else{
                $arr['self_money_percent'] = 0;
            }
            $data[$key]['partner_kpi'] = array_sum($arr);
            if ($data[$key]['partner_kpi'] > $maxKpi) {
                $maxKpi = $data[$key]['partner_kpi'];
            }
        }

        //分值过低，重新整理(等比计算一下,以最大的和100的比值等比运算)
        $ratio = $maxKpi / 100;
        $func = function (&$value) use ($ratio) {
            if ($ratio != 0) {
                $value['partner_kpi'] =  ceil($value['partner_kpi'] / $ratio);
                $value['kpi_value'] = $value['partner_kpi'];
            }else{
                $value['partner_kpi'] = 0;
                $value['kpi_value'] = 0;
            }
            $value['kpi_type'] = 2;
            $value['kpi_period'] = 1;
            $value['kpi_start'] = $this->monday;
            $value['kpi_end'] = $this->sunday;
            $value['stat_time'] = time();
        };
        array_walk($data,$func);
        return $data;
    }

    /**
     * @Author: 陈静
     * @Date: 2018/05/12 10:57:57
     * @Description: 查找一级会员数量
     */
    public function findFirstChildNum()
    {
        $parentChildInfoArr = (new UserPartnerModel())->alias('a')->field('a.user_id, count(b.user_id) as child_nums')
            ->join(['cf_user_user' => 'b'] , ' a.user_id = b.parent_id ','LEFT')
            ->join(['tp_users' => 'c'] , ' a.user_id = c.user_id ','LEFT')
            ->where('c.reg_time',['>',$this->monday],['<',$this->sunday],'and')
            ->group(' a.user_id ' )
            ->order('a.user_id')->select();

        if ($parentChildInfoArr) {
            $parentChildInfoArr = $parentChildInfoArr->toArray();
        }
        return $parentChildInfoArr;
    }

    /**
     * @Author: 陈静
     * @Date: 2018/05/12 10:59:10
     * @Description: 查找一级会员的活跃度（一级会员购买商品的人数） 和 一级会员的销量（一级会员购物的总金额）
     */
    public function findFirstChildLivenessAndSales()
    {
        $parentChildOrderInfoArr = (new UserPartnerModel())->alias('a')
            ->field('a.user_id, count(d.user_id) as child_order_nums ,SUM(d.order_amount) as child_order_amount')
            ->join(['cf_user_user' => 'b'] , ' a.user_id = b.parent_id ','LEFT')
            ->join(['tp_users' => 'c'] , ' a.user_id = c.user_id ','LEFT')
            ->join(['tp_order' => 'd'] , ' b.user_id = d.user_id ','LEFT')
            ->where('c.reg_time',['>',$this->monday],['<',$this->sunday],'and')
            ->group(' a.user_id ' )
            ->order('a.user_id')->select();
        if ($parentChildOrderInfoArr) {
            $parentChildOrderInfoArr = $parentChildOrderInfoArr->toArray();
        }
        return $parentChildOrderInfoArr;
    }

    /**
     * @Author: 陈静
     * @Date: 2018/05/12 11:02:02
     * @Description: 查找自购的金额
     */
    public function findSelfBuyMoney()
    {
        $parentChildOrderInfoArr = (new UserPartnerModel())->alias('a')
            ->field('a.user_id,SUM(b.order_amount) as self_money')
            ->join(['tp_order' => 'b'] , ' a.user_id = b.user_id ','LEFT')
            ->join(['tp_users' => 'c'] , ' a.user_id = c.user_id ','LEFT')
            ->where('c.reg_time',['>',$this->monday],['<',$this->sunday],'and')
            ->group(' a.user_id ' )
            ->order('a.user_id')->select();
        if ($parentChildOrderInfoArr) {
            $parentChildOrderInfoArr = $parentChildOrderInfoArr->toArray();
        }
        return $parentChildOrderInfoArr;
    }

}