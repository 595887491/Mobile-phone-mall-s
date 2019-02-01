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
 * Author: 当燃
 * 拼团控制器
 * Date: 2016-06-09
 */

namespace app\admin\controller;

use app\admin\logic\OrderLogic;
use app\common\model\DistributeDivideLog;
use app\common\model\Order;
use app\common\model\TeamActivity;
use app\common\model\TeamFollow;
use app\common\model\TeamFound;
use think\AjaxPage;
use think\Loader;
use think\Db;
use think\Page;

class Team extends Base
{
	public function index()
	{
	
		$TeamActivity = new TeamActivity();
		$count = $TeamActivity->where('')->count();
		$Page = new Page($count, 10);
		$list = $TeamActivity->append(['team_type_desc','time_limit_hours','status_desc'])->with('spec_goods_price')->where('')->limit($Page->firstRow . ',' . $Page->listRows)->select();

		foreach ($list as &$v) {
		    $teamId = $v->team_id;
            $v->order_num = Db::name('team_found')
                ->where([
                    'team_id' => $teamId ,
                    'status' => 1,
                    'found_end_time' => ['>',time()],
                    'join' => ['exp',' < need '],
                ])
                ->count();
        }

		$this->assign('page', $Page);
		$this->assign('list', $list);
		return $this->fetch();
		
	}

	/**
	 * 拼团详情
	 * @return mixed
	 */
	public function info()
	{
	
		$team_id = input('team_id');
		if ($team_id) {
			$TeamActivity = new TeamActivity();
			$teamActivity = $TeamActivity->append(['time_limit_hours'])->with('specGoodsPrice,goods')->where(['team_id'=>$team_id])->find();
			if(empty($teamActivity)){
				$this->error('非法操作');
			}
			$this->assign('teamActivity', $teamActivity);
		}
		return $this->fetch();
		
	}

	/**
	 * 保存
	 * @throws \think\Exception
	 */
	public function save(){
	
		$data = input('post.');
		if (!empty($data['team_id'])){
            $isAuto = I('is_auto_confirm',1);//是否自动拼团;0否,1是,默认自动
            $res = Db::table('tp_team_activity')->where('team_id',$data['team_id'])->update(['is_auto_confirm'=>$isAuto]);//更改是否自动拼团
            if($res) $this->ajaxReturn(['data'=>1,'msg'=>'是否自动拼团修改成功']);
        }
        //上架状态的拼团商品
		$teamGoods = Db::table('tp_team_activity')->field('goods_id')->where('status',1)->select();
		$teamGoods = array_column($teamGoods,'goods_id');
		if (in_array($data['goods_id'],$teamGoods)) $this->ajaxReturn(['data'=>-1,'msg'=>'有相同的商品正在进行拼团，请下架后重试']);
		$teamValidate = Loader::validate('Team');
		if (!$teamValidate->batch()->check($data)) {
			$this->ajaxReturn(['status' => 0, 'msg' => '操作失败', 'result' => $teamValidate->getError()]);
		}
		if($data['team_id']){
			$teamActivity = TeamActivity::get(['team_id' => $data['team_id']]);
			if(empty($teamActivity)){
				$this->ajaxReturn(array('status' => 0, 'msg' => '非法操作','result'=>''));
			}
		}else{
			$teamActivity = new TeamActivity();
		}
		$teamActivity->data($data, true);
		$row = $teamActivity->allowField(true)->save();
		if($data['item_id'] > 0){
			Db::name('spec_goods_price')->where(['item_id'=>$teamActivity->item_id])->update(['prom_id'=>$teamActivity->team_id,'prom_type'=>6]);
			Db::name('goods')->where(['goods_id'=>$teamActivity->goods_id])->update(['prom_type'=>6,'prom_id'=>0]);
		}else{
			Db::name('goods')->where(['goods_id'=>$teamActivity->goods_id])->update(['prom_id'=>$teamActivity->team_id,'prom_type'=>6]);
		}
		if($row !== false){
			$this->ajaxReturn(['status' => 1, 'msg' => '操作成功', 'result' => '']);
		}else{
			$this->ajaxReturn(['status' => 0, 'msg' => '操作失败', 'result' => '']);
		}
		
	}

	/**
	 * 删除拼团
	 */
	public function delete(){
	
		$team_id = input('team_id');
		if($team_id){
			$order_goods = Db::name('order_goods')->where(['prom_type' => 6, 'prom_id' => $team_id])->find();
			if($order_goods){
				$this->ajaxReturn(['status' => 0, 'msg' => '该活动有订单参与不能删除!', 'result' => '']);
			}
			$teamActivity = TeamActivity::get(['team_id'=>$team_id]);
			if($teamActivity){
				if($teamActivity['item_id']){
					Db::name('spec_goods_price')->where('item_id', $teamActivity['item_id'])->save(['prom_type' => 0, 'prom_id' => 0]);
					$goodsPromCount = Db::name('spec_goods_price')->where('goods_id', $teamActivity['goods_id'])->where('prom_type','>',0)->count('item_id');
					if($goodsPromCount == 0){
						Db::name('goods')->where("goods_id", $teamActivity['goods_id'])->save(['prom_type' => 0, 'prom_id' => 0]);
					}
				}else{
					Db::name('goods')->where("goods_id", $teamActivity['goods_id'])->save(['prom_type' => 0, 'prom_id' => 0]);
				}
				$row = $teamActivity->delete();
				if($row !== false){
					$this->ajaxReturn(['status' => 1, 'msg' => '删除成功', 'result' => '']);
				}else{
					$this->ajaxReturn(['status' => 0, 'msg' => '删除失败', 'result' => '']);
				}
			}else{
				$this->ajaxReturn(['status' => 0, 'msg' => '参数错误', 'result' => '']);
			}
		}else{
			$this->ajaxReturn(['status' => 0, 'msg' => '参数错误', 'result' => '']);
		}
		
	}

	/**
	 * 确认拼团
	 * @throws \think\Exception
	 */
	public function confirmFound(){
	
		$found_id = input('found_id');
		if(empty($found_id)){
			$this->ajaxReturn(['status'=>0,'msg'=>'参数错误','result'=>'']);
		}
		$TeamFound = new TeamFound();
		$teamFound = $TeamFound::get(['found_id'=>$found_id]);
		if(empty($teamFound)){
			$this->ajaxReturn(['status'=>0,'msg'=>'找不到拼单','result'=>'']);
		}
		if(empty($teamFound->order)){
			$this->ajaxReturn(['status'=>0,'msg'=>'找不到拼单的订单','result'=>'']);
		}
		if($teamFound->Surplus > 0){
			$this->ajaxReturn(['status'=>0,'msg'=>'不满足确认拼团条件，还缺'.$teamFound->Surplus,'result'=>'']);
		}
		if($teamFound->order->order_status > 0){
			$this->ajaxReturn(['status'=>0,'msg'=>'拼单已经确认','result'=>'']);
		}
		$follow_order_id = Db::name('team_follow')->where(['found_id' => $found_id, 'status' => 2])->getField('order_id', true);
		$follow_confirm = Db::name('order')->where('order_id', 'IN', $follow_order_id)->where(['prom_type' => 6])->update(['order_status' => 1]);
		if($follow_confirm !== false){
			$teamFound->order->order_status = 1;
			$found_confirm = $teamFound->order->save();
			if($found_confirm !== false){
                Db::table('tp_team_found')->where('found_id',$teamFound->found_id)->update(['is_auto_confirm'=>0]);//手动确认拼团
                $orderSnArr = Db::name('order')->where('order_id', 'IN', $follow_order_id)->getField('order_sn', true);
			    //生成待分成订单
                array_push($orderSnArr,$teamFound->order['order_sn']);
                foreach ($orderSnArr as $v) {
                    $result['out_trade_no'] = $v;
                    (new DistributeDivideLog())->createWaitDistribut($result);
                }
				$this->ajaxReturn(['status'=>1,'msg'=>'拼单确认成功','result'=>'']);
			}else{
				$this->ajaxReturn(['status'=>0,'msg'=>'拼单确认失败','result'=>'']);
			}
		}else{
			$this->ajaxReturn(['status'=>0,'msg'=>'拼单确认失败','result'=>'']);
		}
		
	}

	/**
	 * 拼团退款
	 */
	public function refundFound(){
	
		$found_id = input('found_id');
		if(empty($found_id)){
			$this->ajaxReturn(['status'=>0,'msg'=>'参数错误','result'=>'']);
		}
		$teamFound = TeamFound::get(['found_id'=>$found_id]);
		$TeamActivityLogic = new \app\admin\logic\TeamActivityLogic();
		$TeamActivityLogic->setTeamFound($teamFound);
		$result = $TeamActivityLogic->refundFound();
		$this->ajaxReturn($result);
		
	}

	/**
	 * 拼团抽奖
	 */
	public function lottery(){
	
		$team_id = input('team_id/d');
		if(empty($team_id)){
			$this->ajaxReturn(['status'=>0,'msg'=>'参数错误','result'=>'']);
		}
		$team = TeamActivity::get(['team_id'=>$team_id]);
		$TeamActivityLogic = new \app\admin\logic\TeamActivityLogic();
		$TeamActivityLogic->setTeam($team);
		$result = $TeamActivityLogic->lottery();
		$this->ajaxReturn($result);
		
	}

	/**
	 * 拼团订单
	 */
	public function team_list()
	{
	
		$add_time_begin = input('add_time_begin',date('Y-m-d', strtotime("-3 month")+86400));
		$add_time_end = input('add_time_end',date('Y-m-d', strtotime('+1 days')));
		$status = input('status');
		$team_id = input('team_id');
		$order_sn = input('order_sn');//拼主订单编号
		$found_where = [];
		$begin_time = strtotime($add_time_begin);
		$end_time = strtotime($add_time_end);
		if ($begin_time!='' && $end_time!='') {
			$found_where['found_time'] = array('between', [$begin_time,$end_time]);
		}
		if($status != ''){
			$found_where['status'] = $status;
		}
		if($team_id != ''){
			$found_where['team_id'] = $team_id;
		}
		if($order_sn != ''){
			$order_id = Db::name('order')->where(['prom_type'=>6,'order_sn'=>$order_sn])->getField('order_id');
			(empty($order_id)) ? $found_where['order_id'] = 0 : $found_where['order_id'] = $order_id;
		}
		$TeamFound = new TeamFound();
		$found_count = $TeamFound->where($found_where)->count('found_id');
		$page = new Page($found_count, 20);
		$TeamFound = $TeamFound->with('order,orderGoods,teamActivity,teamFollow.order,teamFollow.orderGoods')->where($found_where)->limit($page->firstRow, $page->listRows)->select();
		$this->assign('page', $page);
		$this->assign('teamFound', $TeamFound);
		$this->assign('add_time_begin',date('Y-m-d H:i',$begin_time));
		$this->assign('add_time_end',date('Y-m-d H:i',$end_time));
		return $this->fetch();
		
	}

	/**
	 * 拼团订单详情
	 * @return mixed
	 */
	public function team_info()
	{
	
		$order_id = input('order_id');
		$Order = new Order();
		$orderLogic = new OrderLogic();
		$order_where = ['prom_type' => 6, 'order_id' => $order_id];
		$order = $Order::get($order_where);
		if (empty($order)) {
			$this->error('非法操作');
		}
		$teamActivity = $order->teamActivity;
		$orderTeamFound = $order->teamFound;
		$TeamFollow = new TeamFollow();
		$TeamFound = new TeamFound();
		if ($orderTeamFound) {
			//团长的单
			$teamFollows = $TeamFollow->where(['found_id' => $orderTeamFound['found_id'], 'status' => ['gt', 0]])->select();
			$this->assign('orderTeamFound', $orderTeamFound);//团长
			$this->assign('teamFollows', $teamFollows);//参团的人
		} else {
			//团员的单
			$orderTeamFollow = $order->teamFollow;
			$this->assign('orderTeamFollow', $orderTeamFollow);
			//去找团长
			$teamFound = $TeamFound::get(['found_id' => $orderTeamFollow['found_id']]);
			$this->assign('orderTeamFound', $teamFound);//团长
			$teamFollows = $TeamFollow->where(['found_id' => $orderTeamFound['found_id'], 'status' => ['gt', 0], 'follow_id' => ['<>', $orderTeamFollow['follow_id']]])->select();
			$this->assign('teamFollows', $teamFollows);//参团的人
		}
		$button = $orderLogic->getOrderButton($order);
		$action_log = Db::name('order_action')->where(['order_id' => $order_id])->order('log_time desc')->select();
		$this->assign('action_log', $action_log);
		$this->assign('teamActivity', $teamActivity);
		$this->assign('button', $button);
		$this->assign('order', $order);
		return $this->fetch();
		
	}

	//拼团订单
	public function order_list(){
	
		$team_id = input('team_id');
		$found_id = input('found_id');
		$order_status = input('order_status',-1);
		$pay_status = input('pay_status');
		$shipping_status = input('shipping_status',-1);
		$pay_code = input('pay_code');
		$key_type = input('key_type');
		$user_id = input('user_id');
		$order_by = input('order_by');
		$sort = input('sort','DESC');
        $begin = $this->begin;
        $end = $this->end;
		// 搜索条件
		$condition = ['prom_type'=>6];
		$keywords = I('keywords','','trim');
		if($key_type != '' && $keywords != ''){
			if($key_type == 'order_sn'){
				$condition['order_sn'] = $keywords;
			}elseif($key_type == 'consignee'){
				$condition['consignee'] = $keywords;
			}
		}
		if($begin && $end){
			$condition['add_time'] = array('between',"$begin,$end");
		}
		if($order_status > -1){
			$condition['order_status'] = $order_status;
		}
		if($pay_status != ''){
			$condition['pay_status'] = $pay_status;
		}
		if($pay_code != ''){
			$condition['pay_code'] = $pay_code;
		}
		if($shipping_status > -1){
			$condition['shipping_status'] = $shipping_status;
		}
		if($user_id != ''){
			$condition['user_id'] = $user_id;
		}
		$TeamOrderIds = [];
		if($team_id != ''){
			$TeamFoundOrderId = Db::name('team_found')->where('team_id',$team_id)->getField('order_id',true);
			$TeamFollowOrderId = Db::name('team_follow')->where('team_id',$team_id)->getField('order_id',true);
			$TeamOrderIdByTeamId = array_merge($TeamFoundOrderId,$TeamFollowOrderId);
			$TeamOrderIds = array_merge($TeamOrderIdByTeamId,$TeamOrderIds);
		}
		if($found_id != ''){
			$TeamFollowOrderId = Db::name('team_follow')->where('found_id',$found_id)->getField('order_id',true);
			$TeamFoundOrderId = Db::name('team_found')->where('found_id',$found_id)->getField('order_id',true);
			$TeamOrderIdByFoundId = array_merge($TeamFoundOrderId,$TeamFollowOrderId);
			$TeamOrderIds = array_merge($TeamOrderIdByFoundId,$TeamOrderIds);
		}
		if(count($TeamOrderIds) > 0){
			$condition['order_id'] = ['in',$TeamOrderIds];
		}
		if ($order_by != '') {
			$orderBy = [$order_by => $sort];
		} else {
			$orderBy = ['order_id' => $sort];
		}
		$order = new Order();
		$count = $order->where($condition)->count();
		$page  = new Page($count);
		//获取订单列表
		$orderList = $order->with('teamActivity,teamFollow,teamFound')->where($condition)->limit($page->firstRow,$page->listRows)->order($orderBy)->select();
		$this->assign('orderList',$orderList);
		$this->assign('page',$page);
        $this->assign('shipping_status', $shipping_status);
        $this->assign('pay_status', $pay_status);
        $this->assign('order_status', $order_status);
        $this->assign('pay_code', $pay_code);
		return $this->fetch();
	}

	public function team_found(){
		$found_id = input('found_id');
		if (empty($found_id)) {
			$this->error('非法操作');
		}
		$found_where = ['found_id'=>$found_id];
		$TeamFound = new TeamFound();
		$found_count = $TeamFound->where($found_where)->count('found_id');
		$page = new Page($found_count, 20);
		$TeamFound = $TeamFound->with('order,orderGoods,teamActivity,teamFollow.order,teamFollow.orderGoods')->where($found_where)->limit($page->firstRow, $page->listRows)->find();
		if (empty($TeamFound)) {
			$this->error('该拼单记录不存在或已被删除');
		}
		$this->assign('page', $page);
		$this->assign('teamFound', $TeamFound);
		return $this->fetch();
		
	}

	/**
	 * 团长佣金
	 */
	public function bonus(){
	
		$found_id = input('found_id');
		if(empty($found_id)){
			$this->error('参数错误');
		}
		$teamFound = TeamFound::get($found_id);
		if(empty($teamFound)){
			$this->error('拼主记录不翼而飞啦~');
		}
		if($teamFound['status'] != 2){
			$this->error('拼团未成功，请确认拼团~');
		}
		$this->assign('teamFound',$teamFound);
		return $this->fetch();
		
	}

	public function doBonus(){
	
		$desc = input('desc','拼团佣金');
		$found_id = input('found_id');
		if(empty($found_id)){
			$this->ajaxReturn(['status'=>0,'msg'=>'参数错误']);
		}
		$teamFound = TeamFound::get($found_id);
		if(empty($teamFound)){
			$this->error('拼主记录不翼而飞啦~');
		}
		if($teamFound['status'] != 2){
			$this->error('拼团未成功，请确认拼团~');
		}
		if($teamFound['bonus_status'] == 1){
			$this->ajaxReturn(['status'=>0,'msg'=>'团长已领取佣金']);
		}
		$doBonus = accountLog($teamFound['user_id'], $teamFound->teamActivity->bonus, 0, $desc, 0, $teamFound->order->order_id, $teamFound->order->order_sn);
		if($doBonus !== false){
			$teamFound->bonus_status = 1;
			$teamFound->save();
			$this->ajaxReturn(['status'=>1,'msg'=>'操作成功','result'=>'']);
		}else{
			$this->ajaxReturn(['status'=>0,'msg'=>'操作失败']);
		}
		
	}



}
