<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/6/25
 * Time: 17:25
 */

namespace app\common\model;

use think\Model;

class PartnerRankActivity extends Model
{
    protected $table = 'cf_partner_rank_activity';
    /**
     * 活动列表
     * @param $pageObj
     * @param array $condition
     */
    public function getList($pageObj,$condition=[]){
        $lists = $this->where($condition)
            ->limit($pageObj->firstRow ,$pageObj->listRows)
            ->order('start_time','desc')
            ->select();
        foreach ($lists as $list){
            $this->activityStatus($list);
        }
        return $lists;
    }

    /**
     * 更新活动状态
     * @param $list
     */
    public function activityStatus(&$list){
        if ($list['status'] != 3 && $list['status'] != 4) {
            if (time() < $list['start_time']) {
                $status = 0;
            } elseif ($list['start_time'] <= time() && time() <= $list['end_time']) {
                $status = 1;
            } elseif (time() > $list['end_time']) {
                $status = 2;
            }
            if ($list['status'] != $status) {
                $this->where('id',$list['id'])->update(['status'=>$status]);
            }
            $list['status'] = $status;
        }
    }

    /**
     * @param $id
     * 活动详情
     */
    public function activityDetail($id){
        $activity = $this->where('id',$id)->find();
        if ($activity) $this->activityStatus($activity);
        return $activity;
    }

    public function activityData($activity,$count=false,$pageObj=[]){
        if ($activity['status'] == 3) {
            return $this->activityDataLog($activity,$count,$pageObj);
        } else {
            return $this->activityDataNow($activity,$count,$pageObj);
        }
    }
    /**
     * 活动参与的合伙人数据
     * @param $activity
     * @param bool $count 是否统计条数
     * @param $pageObj 分页对象
     */
    public function activityDataNow($activity,$count=false,$pageObj=[]){
        //新增消费用户
        $subQuery = $this->table('tp_order o')
            ->join(['tp_users'=>'u'],'o.user_id = u.user_id','left')
            ->join(['cf_user_user'=>'uu'], 'o.user_id = uu.user_id','left')//方便查parent_id
            ->field('u.user_id, uu.parent_id, sum(o.total_amount) as sub_total_order')
            ->where('o.add_time','between',[$activity['start_time'],$activity['end_time']])//活动时间下单
            ->where('u.reg_time','between',[$activity['start_time'],$activity['end_time']])//活动时间注册
            ->where("(pay_status=1 or pay_code='cod') and order_status in(0,1,2,4)")
            ->group('o.user_id')
            ->buildSql();
        //参与打榜的合伙人
        $query = $this->table($subQuery.' u')
            ->join(['cf_users'=>'cfu'],'u.parent_id = cfu.user_id','left')//方便查父级是否合伙人
            ->join(['tp_users'=>'tu'],'u.parent_id = tu.user_id','left')//查询合伙人nickname
            ->field('count(*) as sub_user, u.parent_id as partner_id, tu.nickname,tu.mobile,tu.head_pic, sum(u.sub_total_order) as total_order, round((count(*)*100+sum(u.sub_total_order))/100, 2) as contribution,
            (SELECT count(1) FROM cf_user_user WHERE parent_id = u.parent_id) as member_one')
            ->where('u.parent_id',['>',0])//有上级会员
            ->where('cfu.user_type&2= 2') //上级会员为合伙人
            ->group('u.parent_id');

        if ($count) return $query->count();
        if ($pageObj) {
            $rows = $query->order('contribution desc')//按贡献值排名
                ->limit($pageObj->firstRow ,$pageObj->listRows)
                ->select();
        } else {
            $rows = $query->order('contribution desc')//按贡献值排名
                ->select();
        }
        return $rows;
    }

    /**
     * 已经派奖的活动的活动数据
     */
    public function activityDataLog($activity,$count=false,$pageObj=[]){
        $query = $this->table('cf_partner_rank_record rr')
            ->join(['tp_users'=>'tu'],'rr.partner_id=tu.user_id','left')
            ->where('act_id',$activity['id']);
        if ($count) {
            return $query->count();
        }
        $field = 'partner_id,partner_name AS nickname,current_rank_no AS rank,current_rank_scale AS scale,current_rank_reward AS scale_amount,increased_user_num AS sub_user,increased_sale_amount AS total_order,contribution_value AS contribution,tu.mobile,tu.head_pic,(SELECT count(1) FROM cf_user_user WHERE parent_id = rr.partner_id) as member_one';
        if ($pageObj) {
            $rows = $query->table('cf_partner_rank_record rr')
                ->field($field)
                ->order('contribution desc')//按贡献值排名
                ->limit($pageObj->firstRow ,$pageObj->listRows)
                ->select();
        } else {
            $rows = $query->table('cf_partner_rank_record rr')
                ->field($field)
                ->order('contribution desc')//按贡献值排名
                ->select();
        }
        return $rows;
    }

    /**
     * WAP端->个人中心->打榜活动入口展示的活动
     */
    public function nowActivity(){
        $activity = $this->table('cf_partner_rank_activity')
            ->where('start_time', ['<',time()])
            ->where('status', ['<>',4])
            ->order('start_time','desc')
            ->find();
//        halt($this->getLastSql());
        if ($activity) $this->activityStatus($activity);
        return $activity;
    }
}