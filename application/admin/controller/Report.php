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
 * Date: 2015-12-21
 */

namespace app\admin\controller;

use app\admin\logic\GoodsLogic;
use app\admin\model\Order;
use app\common\model\Users;
use think\AjaxPage;
use think\Db;
use think\Page;

class Report extends Base{
	public $begin;
	public $end;
	public function _initialize(){
        parent::_initialize();
        $start_time = I('start_time');
		if(I('start_time')){
           $begin = urldecode($start_time);
            $end_time = I('end_time');
           $end = urldecode($end_time);
		}else{
           $begin = date('Y-m-d 00:00:00', strtotime("-7 day"));//7天前
           $end = date('Y-m-d 00:00:00');
            $this->assign('days',7);
		}
		$this->assign('start_time',$begin);
		$this->assign('end_time',$end);
		$this->begin = strtotime($begin);
		$this->end = strtotime($end)+86399;
	}
	
	public function index(){
		$now = strtotime(date('Y-m-d'));
		$today['today_amount'] = M('order')->where("add_time>$now AND (pay_status=1 or pay_code='cod') and order_status in(1,2,4)")->sum('total_amount');//今日销售总额
		$today['today_order'] = M('order')->where("add_time>$now and (pay_status=1 or pay_code='cod')")->count();//今日订单数
		$today['cancel_order'] = M('order')->where("add_time>$now AND order_status=3")->count();//今日取消订单
		if ($today['today_order'] == 0) {
			$today['sign'] = round(0, 2);
		} else {
			$today['sign'] = round($today['today_amount'] / $today['today_order'], 2);
		}
		$this->assign('today',$today);
        $select_year = $this->select_year;
        $res = Db::name("order".$select_year)
            ->field(" COUNT(*) as tnum,sum(total_amount) as amount, FROM_UNIXTIME(add_time,'%Y-%m-%d') as gap ")
            ->where(" add_time >$this->begin and add_time < $this->end AND (pay_status=1 or pay_code='cod') and order_status in(1,2,4) ")
            ->group('gap')
            ->select();
        $tnum = $tamount = 0;
		foreach ($res as $val){
			$arr[$val['gap']] = $val['tnum'];
			$brr[$val['gap']] = $val['amount'];
			$tnum += $val['tnum'];
			$tamount += $val['amount'];
		}

		for($i=$this->begin;$i<=$this->end;$i=$i+24*3600){
			$tmp_num = empty($arr[date('Y-m-d',$i)]) ? 0 : $arr[date('Y-m-d',$i)];
			$tmp_amount = empty($brr[date('Y-m-d',$i)]) ? 0 : $brr[date('Y-m-d',$i)];
			$tmp_sign = empty($tmp_num) ? 0 : round($tmp_amount/$tmp_num,2);						
			$order_arr[] = $tmp_num;
			$amount_arr[] = $tmp_amount;			
			$sign_arr[] = $tmp_sign;
			$date = date('Y-m-d',$i);
			$list[] = array('day'=>$date,'order_num'=>$tmp_num,'amount'=>$tmp_amount,'sign'=>$tmp_sign,'end'=>date('Y-m-d',$i+24*60*60));
			$day[] = $date;
		}
		rsort($list);
		$this->assign('list',$list);
		$result = array('order'=>$order_arr,'amount'=>$amount_arr,'sign'=>$sign_arr,'time'=>$day);
		$this->assign('result',json_encode($result));
		return $this->fetch();
	}
//销售统计
	public function saleReport(){
	    $days = input('days');
	    $agent = Db::table('cf_user_agent au')->join(['tp_users'=>'u'],'u.user_id=au.user_id','left')->field('au.user_id,au.parent_id,u.nickname')->where('agent_level = 1')->select();
	    $new_agent = [['value'=>0,'label'=>'全部']];
	    foreach ($agent as $v){
            $new_agent[] = ['value'=>$v['user_id'],'label'=>$v['nickname']];
        }
        $this->assign('daysAgo_7',date("Y-m-d 00:00:00",strtotime('-1 week')));
        $this->assign('daysAgo_30',date("Y-m-d 00:00:00",strtotime('-1 month')));
        $this->assign('yestodayS',date("Y-m-d 00:00:00",strtotime('-1 day')));
        $this->assign('todayS',date("Y-m-d 00:00:00"));
        $this->assign('agentArr',json_encode($new_agent));
        $a_user = input('a_user/d',0);
        if ($a_user){
            //代理商用户所有会员
            $where = '';
        }
        $todayS = strtotime(date('Y-m-d'));
        $today['today_amount'] = M('order')->where("add_time>$todayS AND (pay_status=1 or pay_code='cod') and order_status in(0,1,2,4)")->sum('total_amount');//今日销售总额
        $today['today_order'] = M('order')->where("add_time>$todayS AND (pay_status=1 or pay_code='cod')")->count();//今日订单数
        $today['cancel_order'] = M('order')->where("add_time>$todayS AND order_status=3")->count();//今日取消订单
        $today['cancel_amount'] = M('order')->where("add_time>$todayS AND order_status=3")->sum('total_amount');//今日取消订单金额
        $today['nopay'] = M('order')->where("add_time>$todayS AND pay_status=0")->count();//今日未付款单
        $today['sended'] = M('order')->where("add_time>$todayS AND shipping_status=1")->count();//今日已发货
        if ($today['today_order'] == 0) {
            $today['sign'] = round(0, 2);
        } else {
            $today['sign'] = round($today['today_amount'] / $today['today_order'], 2);//今日客单价
        }
        /** @var  $yestodayS昨日 */
        $yestodayS = $todayS - 86400;
        $yestoday['today_amount'] = M('order')->where("add_time>$yestodayS AND add_time<$todayS AND (pay_status=1 or pay_code='cod') and order_status in(0,1,2,4)")->sum('total_amount');//昨日销售总额
        $yestoday['today_order'] = M('order')->where("add_time>$yestodayS AND add_time<$todayS and (pay_status=1 or pay_code='cod')")->count();//昨日订单数
        $yestoday['cancel_order'] = M('order')->where("add_time>$yestodayS AND add_time<$todayS AND order_status=3")->count();//昨日取消订单数量
        $yestoday['cancel_amount'] = M('order')->where("add_time>$yestodayS AND add_time<$todayS AND order_status=3")->sum('total_amount');//昨日取消订单金额
        $yestoday['nopay'] = M('order')->where("add_time>$yestodayS AND add_time<$todayS AND pay_status=0")->count();//昨日未付款单
        if ($yestoday['today_order'] == 0) {
            $yestoday['sign'] = round(0, 2);
        } else {
            $yestoday['sign'] = round($yestoday['today_amount'] / $yestoday['today_order'], 2);//昨日客单价
        }

        $total['total_amount'] = M('order')->where("(pay_status=1 or pay_code='cod') and order_status in(0,1,2,4)")->sum('total_amount');//累计销售额
        $total['total_order'] = M('order')->where("(pay_status=1 or pay_code='cod')")->count();//累计订单数
        $total['cancel_order'] = M('order')->where("order_status=3")->count();//累计退货数量
        $total['cancel_amount'] = M('order')->where("order_status=3")->sum('total_amount');//累计退货金额
        if ($total['total_order'] == 0) {
            $total['sign'] = round(0, 2);
        } else {
            $total['sign'] = round($total['total_amount']/$total['total_order'], 2);//总平均客单价
        }
        $add = [
            'today_amount' => $yestoday['today_amount']> 0 ? round(abs($today['today_amount']-$yestoday['today_amount'])/$yestoday['today_amount'],4) * 100 ."%":'--',
            'today_order' => $yestoday['today_order']> 0 ? round(abs($today['today_order']-$yestoday['today_order'])/$yestoday['today_order'],4) * 100 ."%":'--',
            'sign' => $yestoday['sign']> 0 ? round(abs($today['sign']-$yestoday['sign'])/$yestoday['sign'],4) * 100 ."%":'--',
            'nopay' => $yestoday['nopay']> 0 ? round(abs($today['nopay']-$yestoday['nopay'])/$yestoday['nopay'],4) * 100 ."%":'--',
            'cancel_amount' => $yestoday['cancel_amount']> 0 ? round(abs($today['cancel_amount']-$yestoday['cancel_amount'])/$yestoday['cancel_amount'],4) * 100 ."%":'--',
        ];
        //今日未付款订单数
        $today['nopay_order'] = M('order')->where("add_time>$todayS AND pay_status=0")->count();

        $res = Db::query("SELECT FROM_UNIXTIME(add_time,'%Y-%m-%d') as gap,COUNT(if((pay_status=1 or pay_code='cod') and order_status in(0,1,2,4),1,null)) as tnum,SUM(if((pay_status=1 or pay_code='cod') and order_status in(0,1,2,4),total_amount,0)) as amount,COUNT(if(pay_status=0,1,null)) AS nopay, COUNT(if(order_status=3 AND pay_status=3,1,null)) AS cancel,SUM(if(order_status=3 AND pay_status=3,total_amount,0)) AS cancel_amount FROM tp_order WHERE add_time >$this->begin and add_time < $this->end GROUP BY gap");
        foreach ($res as $val){
            $arr[$val['gap']] = $val['tnum'];
            $brr[$val['gap']] = $val['amount'];
            $crr[$val['gap']] = $val['nopay'];
            $drr[$val['gap']] = $val['cancel'];
            $err[$val['gap']] = $val['cancel_amount'];
        }
        for($i=$this->begin;$i<=$this->end;$i=$i+24*3600){
            $tmp_num = empty($arr[date('Y-m-d',$i)]) ? 0 : $arr[date('Y-m-d',$i)];
            $tmp_amount = empty($brr[date('Y-m-d',$i)]) ? 0 : $brr[date('Y-m-d',$i)];
            $tmp_nopay = empty($crr[date('Y-m-d',$i)]) ? 0 : $crr[date('Y-m-d',$i)];
            $tmp_cancel = empty($drr[date('Y-m-d',$i)]) ? 0 : $drr[date('Y-m-d',$i)];
            $tmp_cancel_amount = empty($err[date('Y-m-d',$i)]) ? 0 : $err[date('Y-m-d',$i)];
            $tmp_sign = empty($tmp_num) ? 0 : round($tmp_amount/$tmp_num,2);
            $order_arr[] = $tmp_num;
            $amount_arr[] = $tmp_amount;
            $nopay_arr[] = $tmp_nopay;
            $cancel_arr[] = $tmp_cancel;
            $sign_arr[] = $tmp_sign;
            $date = date('Y-m-d',$i);
            $list[] = array('day'=>$date,'order_num'=>$tmp_num,'amount'=>$tmp_amount,'nopay'=>$tmp_nopay,'cancel'=>$tmp_cancel,'cancel_amount'=>$tmp_cancel_amount,'sign'=>$tmp_sign,'end'=>date('Y-m-d',$i+24*60*60));
            $day[] = $date;
        }
        rsort($list);
        $this->assign('list',$list);
        $t_order_num = $t_amount = $t_nopay = $t_cancel = $t_cancel_amount = 0;//总单数、总销售额、总未支付、总取消单数、总取消订单金额
        array_walk($list, function($value)use(&$t_order_num,&$t_amount,&$t_nopay,&$t_cancel,&$t_cancel_amount){
            $t_order_num += $value['order_num'];
            $t_amount += $value['amount'];
            $t_nopay += $value['nopay'];
            $t_cancel += $value['cancel'];
            $t_cancel_amount += $value['cancel_amount'];
        });
        $this->assign('subTotal',array('day_count'=>count($list),'order_num'=>$t_order_num,'amount'=>$t_amount,'nopay'=>$t_nopay,'cancel'=>$t_cancel,'cancel_amount'=>$t_cancel_amount,'sign'=>$t_order_num>0?round($t_amount/$t_order_num, 2):0));
        $result = array('order'=>$order_arr,'amount'=>$amount_arr,'sign'=>$sign_arr,'nopay'=>$nopay_arr,'cancel'=>$cancel_arr,'time'=>$day);
//        halt($result);
        $this->assign('agent',json_encode($agent));
        $this->assign('result',json_encode($result));

        $this->assign('yestoday',$yestoday);
        $this->assign('add',$add);
        $this->assign('today',$today);
        $this->assign('total',$total);
        return $this->fetch();
    }
    public function memberReport(){
        $todayS = strtotime(date('Y-m-d'));
        $yestodayS = $todayS - 86400;
        $this->assign('daysAgo_7',date("Y-m-d 00:00:00",strtotime('-1 week')));
        $this->assign('daysAgo_30',date("Y-m-d 00:00:00",strtotime('-1 month')));
        $this->assign('yestodayS',date("Y-m-d 00:00:00",strtotime('-1 day')));
        $this->assign('todayS',date("Y-m-d 00:00:00"));
        $total['count'] = Db::name('users')->count();
        $total['p_count'] = Db::table('cf_users')->where('user_type&2 = 2')->count();
        $total['a_count'] = Db::table('cf_users')->where('user_type&4 = 4')->count();//累计代理商
        $total['order_user'] = Db::name('order')->where("(pay_status=1 or pay_code='cod') and order_status in(0,1,2,4)")->group('user_id')->count();
        $total['float'] = $total['count'] > 0 ?round($total['order_user']/$total['count'],4)*100 .'%':'0.00%';
        $today = [
            'count'=>Db::name('users')->where("reg_time > $todayS")->count(),
            'p_count'=>Db::table('cf_user_partner')->where("be_partner_start > $todayS")->count(),
            'a_count'=>Db::table('cf_user_agent')->where("be_agent_start > $todayS")->count(),
            'order_user'=>Db::name('order')->where("(pay_status=1 or pay_code='cod') and order_status in(0,1,2,4) AND add_time > $todayS")->group('user_id')->count()
        ];
        $today['float'] = $today['count'] > 0 ?round($today['order_user']/$total['count'],4)*100 .'%':'0.00%';
//        var_dump($today['order_user'], $total['count']);die;
        $yestoday = [
            'count'=>Db::name('users')->where("$yestodayS < reg_time AND reg_time < $todayS")->count(),
            'p_count'=>Db::table('cf_user_partner')->where("be_partner_start > $todayS")->count(),
            'a_count'=>Db::table('cf_user_agent')->where("be_agent_start > $todayS")->count(),
            'order_user'=>Db::name('order')->where("(pay_status=1 or pay_code='cod') and order_status in(0,1,2,4) AND add_time > $yestodayS AND add_time < $todayS")->group('user_id')->count(),//昨日新增会员下单率
        ];
        $add = [
            'count'=>$yestoday['count'] == 0 ? '--':round(($today['count']-$yestoday['count'])/$yestoday['count'],4)*100 .'%',
            'p_count'=>$yestoday['p_count'] == 0 ? '--':round(($today['p_count']-$yestoday['p_count'])/$yestoday['p_count'],4)*100 .'%',
            'a_count'=>$yestoday['a_count'] == 0 ? '--':round(($today['a_count']-$yestoday['a_count'])/$yestoday['a_count'],4)*100 .'%',
            'order_user'=>$yestoday['order_user'] == 0 ? '--':round(($today['order_user']-$yestoday['order_user'])/$yestoday['order_user'],4)*100 .'%',
        ];

        $weekS = $todayS - (86400 * 7);
        $monthS = $todayS - (86400*30);
        $lastWeekOrderUserCount = Db::name('order')->where("add_time > $weekS")->where("add_time < $todayS")->group('user_id')->count();//近一周（不含今天）下单人数
        $lastMonthOrderUserCount = Db::name('order')->where("add_time > $monthS")->where("add_time < $todayS")->group('user_id')->count();
        $lastWeekFloat = round($lastWeekOrderUserCount/$total['count'],4) * 100 .'%';
        $lastMonthFloat = round($lastMonthOrderUserCount/$total['count'],4) * 100 .'%';

        $user = Db::query("SELECT FROM_UNIXTIME(reg_time,'%Y-%m-%d') as gap,COUNT(1) as num FROM tp_users WHERE reg_time >$this->begin and reg_time < $this->end GROUP BY gap");
        $p_user = Db::table('cf_user_partner')->field("FROM_UNIXTIME(be_partner_start,'%Y-%m-%d') as gap,COUNT(1) as num")->where("be_partner_start >$this->begin and be_partner_start < $this->end")->group('gap')->select();
        $a_user = Db::table('cf_user_agent')->field("FROM_UNIXTIME(be_agent_start,'%Y-%m-%d') as gap,COUNT(1) as num")->where("be_agent_start >$this->begin and be_agent_start < $this->end")->group('gap')->select();
//        $order = Db::query("SELECT FROM_UNIXTIME(add_time,'%Y-%m-%d') as gap,COUNT(1) as num FROM tp_order WHERE reg_time >$this->begin and reg_time < $this->end GROUP BY gap");
        foreach ($user as $val){
            $a[$val['gap']] = $val['num'];
        }
        foreach ($p_user as $val){
            $b[$val['gap']] = $val['num'];
        }
        foreach ($a_user as $val){
            $c[$val['gap']] = $val['num'];
        }
        for($i=$this->begin;$i<=$this->end;$i=$i+24*3600){
            $date = date('Y-m-d',$i);
            $userArr[] = isset($a[$date]) ? $a[$date] : 0;
            $p_userArr[] = isset($b[$date]) ? $b[$date] : 0;
            $a_userArr[] = isset($c[$date]) ? $c[$date] : 0;
            $time[] = $date;
            $lists[] = ['date'=>$date,'user'=>isset($a[$date]) ? $a[$date] : 0,'p_user'=>isset($b[$date]) ? $b[$date] : 0,'a_user'=>isset($c[$date]) ? $c[$date] : 0];
        }
        rsort($lists);
        $this->assign('lists',$lists);
        $result = json_encode(array('time'=>$time,'user'=>$userArr,'p_user'=>$p_userArr,'a_user'=>$a_userArr));

        $this->assign('total',$total);
        $this->assign('today',$today);
        $this->assign('yestoday',$yestoday);
        $this->assign('add',$add);
        $this->assign('lastWeekFloat',$lastWeekFloat);//最近一周购买率
        $this->assign('lastMonthFloat',$lastMonthFloat);
        $this->assign('result',$result);
	    return $this->fetch();
    }

    /**
     * 销量排行
     * @return mixed
     */
	public function saleTop(){
		$goods_name = I('goods_name');
		$where = [
            'od.pay_time'    => ['Between',"$this->begin,$this->end"],
            'og.is_send'    => 1,
        ];
        if(!empty($goods_name))$where['og.goods_name'] =['like', '%$goods_name%'];
         $count = Db::name('order_goods')->alias('og')
             ->field('sum(og.goods_num) as sale_num,sum(og.goods_num*og.goods_price) as sale_amount ')
             ->join('order od','og.order_id=od.order_id','LEFT')
             ->where($where)->group('og.goods_id')->count();
         $Page = new Page($count,$this->page_size);
         $res = Db::name('order_goods')->alias('og')
             ->field('og.goods_name,og.goods_id,og.goods_sn,sum(og.goods_num) as sale_num,sum(og.goods_num*og.goods_price) as sale_amount ')
             ->join('order od','og.order_id=od.order_id','LEFT')
             ->where($where)->group('og.goods_id')->order('sale_num DESC')
             ->limit($Page->firstRow,$Page->listRows)->cache(true,3600)->select();
		$this->assign('list',$res);
        $this->assign('page',$Page);
        $this->assign('p',I('p/d',1));
        $this->assign('page_size',$this->page_size);
		return $this->fetch();
	}

    /**
     * 统计报表 - 会员排行
     * @return mixed
     */
	public function userTop(){

		$mobile = I('mobile');
		$email = I('email');
        $order_where = [
            'o.add_time'=>['Between',"$this->begin,$this->end"],
            'o.pay_status'=>1
        ];
		if($mobile){
			$user_where['mobile'] =$mobile;
		}		
		if($email){
            $user_where['email'] = $email;
		}
        if($user_where){   //有查询单个用户的条件就去找出user_id
            $user_id = Db::name('users')->where($user_where)->getField('user_id');
            $order_where['o.user_id']=$user_id;
        }

        $count = Db::name('order')->alias('o')->where($order_where)->group('o.user_id')->count();  //统计数量
        $Page = new Page($count,$this->page_size);
        $list = Db::name('order')->alias('o')
            ->field('count(o.order_id) as order_num,sum(o.order_amount) as amount,o.user_id,u.mobile,u.email,u.nickname')
            ->join('users u','o.user_id=u.user_id','LEFT')
            ->where($order_where)
            ->group('o.user_id')
            ->order('amount DESC')
            ->limit($Page->firstRow,$Page->listRows)
            ->cache(true)->select();   //以用户ID分组查询
        $this->assign('page',$Page);
        $this->assign('p',I('p/d',1));
        $this->assign('page_size',$this->page_size);
        $this->assign('list',$list);
		return $this->fetch();
	}

    /**
     * 用户订单
     * @return mixed
     */
    public function userOrder(){
        $orderModel = new \app\common\model\Order();
        $user_id = trim(I('user_id'));
        // 搜索条件
        $condition=[
            'add_time'=>['Between',"$this->begin,$this->end"],
            'pay_status'=>1,
            'user_id' => $user_id,
        ];
        $keyType = I("keytype");
        $keywords = I('keywords','','trim');

        $pay_code = input('pay_code');
        $order_sn = ($keyType && $keyType == 'order_sn') ? $keywords : I('order_sn') ;
        $order_sn ? $condition['order_sn'] = trim($order_sn) : false;
        $pay_code != '' ? $condition['pay_code'] = $pay_code : false;   //支付方式
        $count = $orderModel->where($condition)->count();
        $Page  = new Page($count,$this->page_size);
        $orderList = $orderModel->where($condition)
            ->limit("{$Page->firstRow},{$Page->listRows}")->order('add_time desc')->select();

        $this->assign('orderList',$orderList);
        $this->assign('user_id',$user_id);
        $this->assign('keywords',$keywords);
        $this->assign('page',$Page);// 赋值分页输出
        return $this->fetch();
    }

    public function saleOrder(){
        $end_time = $this->begin+24*60*60;
        $order_where = "o.add_time>$this->begin and o.add_time<$end_time";  //交易成功的有效订单
        $order_count = Db::name('order')->alias('o')->where($order_where)->whereIn('order_status','1,24')->count();
        $Page = new Page($order_count,20);
        $order_list = Db::name('order')->alias('o')
            ->field('o.order_id,o.order_sn,o.goods_price,o.shipping_price,o.total_amount,o.add_time,u.user_id,u.nickname')
            ->join('users u','u.user_id = o.user_id','left')
            ->where($order_where)->whereIn('order_status','1,2,4')
            ->limit($Page->firstRow,$Page->listRows)->select();
        $this->assign('order_list',$order_list);
        $this->assign('page',$Page);
        return $this->fetch();
    }

    /**
     * 销售明细列表
     */
	public function saleList(){
        $cat_id = I('cat_id',0);
        $brand_id = I('brand_id',0);
        $goods_id = I('goods_id',0);
        $where = "o.add_time>$this->begin and o.add_time<$this->end and order_status in(1,2,4) ";  //交易成功的有效订单
        if($cat_id>0){
            $where .= " and (g.cat_id=$cat_id or g.extend_cat_id=$cat_id)";
            $this->assign('cat_id',$cat_id);
        }
        if($brand_id>0){
            $where .= " and g.brand_id=$brand_id";
            $this->assign('brand_id',$brand_id);
        }

        if($goods_id >0){
        	$where .= " and og.goods_id=$goods_id";
        }
        $count = Db::name('order_goods')->alias('og')
            ->join('order o','og.order_id=o.order_id ','left')
            ->join('goods g','og.goods_id = g.goods_id','left')
            ->where($where)->count();  //统计数量
        $Page = new Page($count,20);
        $show = $Page->show();

        $res = Db::name('order_goods')->alias('og')->field('og.*,o.user_id,o.order_sn,o.shipping_name,o.pay_name,o.add_time,og.spec_key_name')
            ->join('order o','og.order_id=o.order_id ','left')
            ->join('goods g','og.goods_id = g.goods_id','left')
            ->where($where)->limit($Page->firstRow,$Page->listRows)
            ->order('o.add_time desc')->select();
        $this->assign('list',$res);
        $this->assign('pager',$Page);
        $this->assign('page',$show);

        $GoodsLogic = new GoodsLogic();
        $brandList = $GoodsLogic->getSortBrands();  //获取排好序的品牌列表
        $categoryList = $GoodsLogic->getSortCategory(); //获取排好序的分类列表
        $this->assign('categoryList',$categoryList);
        $this->assign('brandList',$brandList);
        return $this->fetch();
	}
	
	public function user(){
		$today = strtotime(date('Y-m-d'));
		$month = strtotime(date('Y-m-01'));
		$user['today'] = D('users')->where("reg_time>$today")->count();//今日新增会员
		$user['month'] = D('users')->where("reg_time>$month")->count();//本月新增会员
		$user['total'] = D('users')->count();//会员总数
		$user['user_money'] = D('users')->sum('user_money');//会员余额总额
		$res = M('order')->cache(true)->distinct(true)->field('user_id')->select();
		$user['hasorder'] = count($res);
		$this->assign('user',$user);
		$sql = "SELECT COUNT(*) as num,FROM_UNIXTIME(reg_time,'%Y-%m-%d') as gap from __PREFIX__users where reg_time>$this->begin and reg_time<$this->end group by gap";
		$new = DB::query($sql);//新增会员趋势
		foreach ($new as $val){
			$arr[$val['gap']] = $val['num'];
		}
		
		for($i=$this->begin;$i<=$this->end;$i=$i+24*3600){
			$brr[] = empty($arr[date('Y-m-d',$i)]) ? 0 : $arr[date('Y-m-d',$i)];
			$day[] = date('Y-m-d',$i);
		}		
		$result = array('data'=>$brr,'time'=>$day);
		$this->assign('result',json_encode($result));					
		return $this->fetch();
	}

	public function expense_log(){
		$map = array();
        $add_time_begin = I('add_time_begin');
        $add_time_end = I('add_time_end');
		$begin = strtotime($add_time_begin);
		$end = strtotime($add_time_end);
		$admin_id = I('admin_id');
		if($begin && $end){
			$map['addtime'] = array('between',"$begin,$end");
		}
		if($admin_id){
			$map['admin_id'] = $admin_id;
		}
		$count = M('expense_log')->where($map)->count();
		$page = new Page($count);
		$lists  = M('expense_log')->where($map)->limit($page->firstRow.','.$page->listRows)->select();
		$this->assign('page',$page->show());
		$this->assign('total_count',$count);
		$this->assign('add_time_begin',$add_time_begin);
		$this->assign('add_time_end',$add_time_end);
		$this->assign('list',$lists);
		$admin = M('admin')->getField('admin_id,user_name');
		$this->assign('admin',$admin);
		$typeArr = array('','会员提现','订单退款','其他');//数据库设计问题
		$this->assign('typeArr',$typeArr);
		return $this->fetch();
	}

    //财务统计
    public function finance(){
        $begin = $this->begin;
        $end_time = $this->end;
        $order = Db::name('order')->alias('o')
            ->where(['o.pay_status'=>1,'o.shipping_status'=>1])->whereTime('o.add_time', 'between', [$begin, $end_time])
            ->order('o.add_time asc')->getField('order_id,o.*');  //以时间升序
        $order_id_arr = get_arr_column($order,'order_id');
        $order_ids = implode(',',$order_id_arr);            //订单ID组
        $order_goods = Db::name('order_goods')->where(['is_send'=>['in','1,2'],'order_id'=>['in',$order_ids]])->group('order_id')
            ->order('order_id asc')->getField('order_id,sum(goods_num*cost_price) as cost_price,sum(goods_num*member_goods_price) as goods_amount');  //订单商品退货的不算
        $frist_key = key($order);  //第一个key
        $sratus_date = strtotime(date('Y-m-d',$order["$frist_key"]['add_time']));  //有数据那天为循环初始时间，大范围查询可以避免前面输出一堆没用的数据
        $key = array_keys($order);
        $lastkey = end($key);//最后一个key
        $end_date = strtotime(date('Y-m-d',$order["$lastkey"]['add_time']))+24*3600;  //数据最后时间为循环结束点，大范围查询可以避免前面输出一堆没用的数据
        for($i=$sratus_date;$i<=$end_date;$i=$i+24*3600){   //循环时间
            $date = $day[] = date('Y-m-d',$i);
            $everyday_end_time = $i+24*3600;
            $goods_amount=$cost_price =$shipping_amount=$coupon_amount=$order_prom_amount=$total_amount=0.00; //初始化变量
            foreach ($order as $okey => $oval){   //循环订单
                $for_order_id = $oval['order_id'];
                if (!isset($order_goods["$for_order_id"])){
                    unset($order[$for_order_id]);           //去掉整个订单都了退货后的
                }
                if($oval['add_time'] >= $i && $oval['add_time']<$everyday_end_time){      //统计同一天内的数据
                    $goods_amount      += $oval['goods_price'];
                    $total_amount      += $oval['total_amount'];
                    $cost_price        += $order_goods["$for_order_id"]['cost_price']; //订单成本价
                    $shipping_amount   += $oval['shipping_price'];
                    $coupon_amount     += $oval['coupon_price'];
                    $order_prom_amount += $oval['order_prom_amount'];
                    unset($order[$okey]);  //省的来回循环
                }
            }
            //拼装输出到图表的数据
            $goods_arr[]    = $goods_amount;
            $total_arr[]    = $total_amount;
            $cost_arr[]     = $cost_price ;
            $shipping_arr[] = $shipping_amount;
            $coupon_arr[]   = $coupon_amount;

            $list[] = [
                'day'=>$date,
                'goods_amount'      => $goods_amount,
                'total_amount'      => $total_amount,
                'cost_amount'       => $cost_price,
                'shipping_amount'   => $shipping_amount,
                'coupon_amount'     => $coupon_amount,
                'order_prom_amount' => $order_prom_amount,
                'end'=>$everyday_end_time,
            ];  //拼装列表
        }
        rsort($list);
        $this->assign('list',$list);
        $result = ['goods_arr'=>$goods_arr,'cost_arr'=>$cost_arr,'shipping_arr'=>$shipping_arr,'coupon_arr'=>$coupon_arr,'time'=>$day];
        $this->assign('result',json_encode($result));
        return $this->fetch();
    }
    
  /**
     * 运营概况详情
     * @return mixed
     */
    public function financeDetail(){
        $begin = $this->begin;
        $end_time = $this->begin+24*60*60;
        $order_where = [
            'o.pay_status'=>1,
            'o.shipping_status'=>1,
            'og.is_send'=>['in','1,2']];  //交易成功的有效订单
        $order_count = Db::name('order')->alias('o')
            ->join('order_goods og','o.order_id = og.order_id','left')->join('users u','u.user_id = o.user_id','left')
            ->whereTime('o.add_time', 'between', [$begin, $end_time])->where($order_where)
            ->group('o.order_id')->count();
        $Page = new Page($order_count,50);

        $order_list = Db::name('order')->alias('o')
            ->field('o.*,u.user_id,u.nickname,SUM(og.cost_price) as coupon_amount')
            ->join('order_goods og','o.order_id = og.order_id','left')->join('users u','u.user_id = o.user_id','left')
            ->where($order_where)->whereTime('o.add_time', 'between', [$begin, $end_time])
            ->group('o.order_id')->limit($Page->firstRow,$Page->listRows)->select();
        $this->assign('order_list',$order_list);
        $this->assign('page',$Page);
        return $this->fetch();
    }

    //余额统计
    public function balance()
    {
        $orderModel = new Order();
        
        $startTime = I('start_time');
        $endTime = I('end_time');
        
        // 搜索条件
        $condition = array();
        $condition['a.pay_status'] = 1;
        $condition['a.user_money'] = ['>',0];

        $haveCondition = '';
        $pageParams = array();
        if (I('mobile')) {
            $condition['b.mobile'] = I('mobile');
            $pageParams['mobile'] = I('mobile');
        }
        if (I('user_id')) {
            $condition['a.user_id'] = I('user_id');
            $pageParams['user_id'] = I('user_id');
        }

        if ($startTime) {
            $haveCondition = "add_times >= '$startTime'";
            $condition['a.add_time'] = [['>=',strtotime($startTime)]];
            $pageParams['start_time'] = $startTime;
            $showTime = $startTime;
            if ($endTime) {
                $haveCondition .= " and add_times <= '$endTime'";
                $condition['a.add_time'] = [['>=',strtotime($startTime)],['<=',strtotime($endTime) + 86399],'and'];
                $pageParams['end_time'] = $endTime;
                $showTime = $startTime.' ~ '.$endTime;
            }
        }else{
            if ($endTime) {
                $haveCondition = "add_times <= '$endTime'";
                $condition['a.add_time'] = ['<=',strtotime($endTime) + 86399];
                $pageParams['end_time'] = $endTime;
                $showTime = $endTime;
            }
        }


        if ($times = I('time')) {
            if ($times == 'today') {
                $start_time = date('Y-m-d');
                $haveCondition = "add_times = '$start_time'";
                $condition['a.add_time'] = [['>=',strtotime($start_time)],['<=',time()],'and'];
                $pageParams['time'] = I('time');
            }
            if ($times == 'yestoday') {
                $start_time = date('Y-m-d',strtotime('-1 day'));
                $haveCondition = "add_times = '$start_time'";
                $condition['a.add_time'] = [['>=',strtotime($start_time)],['<=',strtotime($start_time) + 86399],'and'];
                $pageParams['time'] = I('time');
            }
            $showTime = $start_time;
        }

        I('order_by') ? $sort_order = I('order_by').' '.I('sort') : false;

        if (IS_AJAX) {
            $count = Db::name('order')->alias('a')
                ->field("	a.user_id,count(a.user_id) as order_nums,sum(a.user_money) AS user_moneys,`b`.`nickname`,`b`.`mobile`,
	FROM_UNIXTIME(`a`.`add_time`, '%Y-%m-%d') AS add_times")
                ->join('users b','a.user_id = b.user_id','LEFT')->group('a.user_id')
                ->where($condition)
                ->having($haveCondition)
                ->count();

            $Page  = new AjaxPage($count,10);
            //  搜索条件下 分页赋值
            foreach($pageParams as $key=>$val) {
                $Page->parameter[$key]   =   urlencode($val);
            }

            $balanceList = $orderModel->getOrderList()
                ->where($condition)
                ->order($sort_order)
                ->having($haveCondition)
                ->limit($Page->firstRow.','.$Page->listRows)
                ->select();

            $balanceList = collection($balanceList)->toArray();
            foreach ($balanceList as &$value) {
                if ($startTime) {
                    $value['add_times'] = $startTime.' ~ '.date('Y-m-d');
                    if ($endTime) {
                        $value['add_times'] = $startTime.' ~ '.$endTime;
                        if ($startTime == $endTime) {
                            $value['add_times'] = $startTime;
                        }
                    }
                }else{
                    if ($endTime) {
                        $value['add_times'] = ' ~ '.$endTime;
                    }
                }
            }

            $show = $Page->show();

            $orderCount = Db::name('order')->alias('a')
                ->field("count(a.user_id) as order_nums,
	FROM_UNIXTIME(`a`.`add_time`, '%Y-%m-%d') AS add_times")
                ->join('users b','a.user_id = b.user_id','LEFT')
                ->where($condition)
                ->having($haveCondition)
                ->find();

            $totalBalance = Db::name('order')->alias('a')
                ->join('users b','a.user_id = b.user_id','LEFT')
                ->where($condition)
                ->where('a.user_money','>',0)
                ->sum('a.user_money');

            $this->assign('times',$showTime);
            $this->assign('total_balance',$totalBalance);
            $this->assign('order_count',$orderCount['order_nums']);
            $this->assign('user_count',$count);
            $this->assign('balance_list',$balanceList);
            $this->assign('page',$show);// 赋值分页输出
            $this->assign('pager',$Page);
            return $this->fetch('ajaxBalance');
        }
        return $this->fetch();
    }

    //余额明细统计
    public function balanceDetail()
    {
        $userId = I('user_id');
        $orderModel = new Order();

        $startTime = I('start_time');
        $endTime = I('end_time');

        // 搜索条件
        $condition = array();
        $condition['a.user_id'] = $userId;
        $condition['a.pay_status'] = 1;
        $condition['a.user_money'] = ['>',0];

        $pageParams = array();

        if ($startTime) {
            $condition['a.add_time'] = [['>=',strtotime($startTime)]];
            $pageParams['start_time'] = $startTime;
            $showTime = $startTime;
            if ($endTime) {
                $condition['a.add_time'] = [['>=',strtotime($startTime)],['<=',strtotime($endTime) + 86399],'and'];
                $pageParams['end_time'] = $endTime;
                $showTime = $startTime.' ~ '.$endTime;
            }
        }else{
            if ($endTime) {
                $condition['a.add_time'] = ['<=',strtotime($endTime) + 86399];
                $pageParams['end_time'] = $endTime;
                $showTime = $endTime;
            }
        }

        if ($times = I('time')) {
            if ($times == 'today') {
                $start_time = date('Y-m-d');
                $condition['a.add_time'] = [['>=',strtotime($start_time)],['<=',time()],'and'];
                $pageParams['time'] = I('time');
            }

            if ($times == 'yestoday') {
                $start_time = date('Y-m-d',strtotime('-1 day'));
                $condition['a.add_time'] = [['>=',strtotime($start_time)],['<=',strtotime($start_time) + 86399],'and'];
                $pageParams['time'] = I('time');
            }
            $showTime = $start_time;
        }

        I('order_by') ? $sort_order = I('order_by').' '.I('sort') : false;

        if (IS_AJAX) {
            $count = Db::name('order')->alias('a')
                ->field('a.order_id,a.order_sn,a.user_id,a.province,a.city,a.district,a.address,a.total_amount,a.order_amount,a.user_money,a.pay_name,a.order_status,a.add_time,a.pay_time')
                ->where($condition)
                ->count();

            $Page  = new AjaxPage($count,10);

            foreach($pageParams as $key=>$val) {
                $Page->parameter[$key]   =   urlencode($val);
            }

            $balanceList = $orderModel->alias('a')
                ->field('a.order_id,a.order_sn,a.mobile,a.consignee,a.user_id,a.province,a.city,a.district,a.address,a.total_amount,a.order_amount,a.user_money,a.pay_name,a.order_status,a.add_time,a.pay_time')
                ->where($condition)
                ->order($sort_order)
                ->limit($Page->firstRow.','.$Page->listRows)
                ->select();


            $balanceList = collection($balanceList)->toArray();
            $provinceArr = array_column($balanceList,'province');
            $cityArr = array_column($balanceList,'city');
            $districtArr = array_column($balanceList,'district');
            $regionDatas = Db::name('region')
                ->where('id','in',array_unique(array_merge($provinceArr,$cityArr,$districtArr)))
                ->select();

            $addressArr = array_column($regionDatas,'name','id');

            foreach ($balanceList as &$value) {
                if (!empty($value['address'])) {
                    $value['address'] = '<b>'.$value['consignee'].'</b>：'.$value['mobile'].'<br/>'.$addressArr[$value['province']].$addressArr[$value['city']].$addressArr[$value['district']].$value['address'];
                }else{
                    $value['address'] = '自提';
                }
                if ($value['total_amount'] != $value['user_money']) {
                    $value['pay_name'] = '余额+'.$value['pay_name'];
                }
                $value['order_status'] = C('ORDER_STATUS')[$value['order_status']];
            }

            $totalBalance = $orderModel->alias('a')
                ->field('sum(`a`.`user_money`) as total_balance')
                ->where($condition)
                ->order($sort_order)
                ->limit($Page->firstRow.','.$Page->listRows)
                ->find();

            $this->assign('totalBalance',$totalBalance->total_balance);
            $this->assign('count',$count);
            $this->assign('balance_list',$balanceList);
            $this->assign('page',$Page->show());// 赋值分页输出
            $this->assign('pager',$Page);
            return $this->fetch('ajaxBalanceDetail');
        }
        $userInfo = (new Users())->where('user_id',$userId)->field('mobile,nickname')->find();


        $this->assign('userInfo',$userInfo);
        $this->assign('showTime',$showTime);
        $this->assign('times',$times);
        $this->assign('startTime',$startTime);
        $this->assign('endTime',$endTime);
        $this->assign('user_id',$userId);
        return $this->fetch();
    }

    //导出余额
    public function exportBalance(){
        $strTable ='<table width="500" border="1">';
        $strTable .= '<tr>';
        $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">时间</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="100">用户</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">消费余额</td>';
        $strTable .= '<td style="text-align:center;font-size:12px;" width="*">订单数量</td>';
        $strTable .= '</tr>';
        //如果需要加条件，使用ajax提交
        $count = Db::name('order')->alias('a')
            ->field('a.user_id,count(a.user_id) as order_nums,sum(a.user_money) as user_moneys,a.add_time,b.nickname,b.mobile')
            ->join('users b','a.user_id = b.user_id','LEFT')->group('a.user_id')
            ->where('a.user_money','>',0)
            ->count();

        $p = ceil($count/5000);
        for($i=0;$i<$p;$i++){
            $start = $i*5000;
            $end = ($i+1)*5000;
            $balanceList = (new Order())->getOrderList()
                ->order('user_moneys desc')
                ->select();
            $balanceList = collection($balanceList)->toArray();
            if(is_array($balanceList)){
                foreach($balanceList as $k=>$val){
                    $strTable .= '<tr>';
                    $strTable .= '<td style="text-align:center;font-size:12px;">'.$val['add_times'].'</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['nickname'].' </td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['user_moneys'].'</td>';
                    $strTable .= '<td style="text-align:left;font-size:12px;">'.$val['order_nums'].'</td>';
                    $strTable .= '</tr>';
                }
                unset($balanceList);
            }
        }
        $strTable .='</table>';
        downloadExcel($strTable,'balance'.$i);
        exit();
    }

}