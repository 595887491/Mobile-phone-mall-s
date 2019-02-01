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
use think\Model;

class UserModel extends Model
{
    protected $table = 'cf_users';
    protected $pk = 'user_id';
    protected $resultSetType = 'collection';

    //查找用户partner角色
    public function partnerRelation()
    {
        return $this->hasOne('UserPartnerModel','user_id','user_id')
            ->field('user_id,level,status,partner_kpi,be_partner_start,be_partner_end');
    }

    //查找用户agent角色
    public function agentRelation()
    {
        return $this->hasOne('UserAgentModel','user_id','user_id')
            ->field('user_id,level,agent_level,be_agent_start,be_agent_end,status');
    }

    //查找用户tp_users数据
    public function usersRelation()
    {
        return $this->hasOne('Users','user_id','user_id')
            ->field('tp_users.user_money,tp_users.head_pic,tp_users.mobile,tp_users.user_id,tp_users.nickname,a.level_name')
            ->join('user_level a','tp_users.level = a.level_id','LEFT');
    }

    //获取用户信息
    public function getUserInfo($user_id)
    {
        return $this->where('user_id',$user_id)->find();
    }


    /**
     * @Author: 陈静
     * @Date: 2018/04/25 10:24:06
     * @Description: 获取用户身份
     * @param $user_id
     * @return array|bool|false|\PDOStatement|string|Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getUserRelationIdentity($user_id)
    {
        $result = $this->with(['partnerRelation','agentRelation','usersRelation'])
            ->where('user_id',$user_id)->find();
        if ($result) {
            $data['user_id'] = $user_id;
            $data['user_name'] = $result->users_relation->nickname;
            $data['level_name'] = $result->users_relation->level_name;
            $data['head_pic'] = $result->users_relation->head_pic;
            $data['mobile'] = $result->users_relation->mobile;
            $data['id_card_num'] = $result->id_card_num;
            $data['id_card_name'] = $result->id_card_name;
            $data['status'] = $result->partnerRelation->status;
            $data['be_partner_start'] = $result->partnerRelation->be_partner_start;
            $data['be_partner_end'] = $result->partnerRelation->be_partner_end;
            $data['be_agent_start'] = $result->agentRelation->be_agent_start;
            $data['be_agent_end'] = $result->agentRelation->be_agent_end;
            $data['agent_status'] = $result->agentRelation->status;
            $data['agent_level'] = $result->agentRelation->agent_level;

            $data['identity']['partner'] = 0;
            if ($result->partner_relation) {
                $data['identity']['partner'] = (new UserPartnerModel())->judgePartnerStatus($user_id);
            }
            $data['identity']['agent'] = 0;
            if ($result->agent_relation) {
                $data['identity']['agent'] = 1;
            }
            //会员累计收益
            $data['user_earnings'] = (float)$result->wallet_accumulate_user_income;
            //合伙人累计收益
            $data['partner_earnings'] = (float)$result->wallet_accumulate_partner_income;
            //代理商累计收益
            $data['agent_earnings'] = (float)$result->wallet_accumulate_agent_income;
            //代理商/合伙人累计收益
            $data['agent_partner_earnings'] = (float)($data['partner_earnings'] + $data['agent_earnings'] + $data['user_earnings']);

            //普通身份收益剩余
            $data['user_earnings_residue'] = (float)$result->wallet_user_income;

            //合伙人收益剩余
            $data['partner_earnings_residue'] = (float)$result->wallet_partner_income;

            //代理商收益剩余
            $data['agent_earnings_residue'] = (float)$result->wallet_agent_income;

            //用户待返利收益
            $data['wait_income'] = Db::table('cf_distribute_divide_log')->where([
                'to_user_id' => $user_id,
                'is_divided' => 0
            ])->sum('divide_money');

            //合伙人特有身份信息
            if ($result->partner_relation) {
                //合伙人kpi
                $data['partner_kpi'] = $result->partner_relation->partner_kpi;
                $data['remain_day'] = floor(( $result->partner_relation->be_partner_end - strtotime(date('Ymd',time())) ) / 86400);
            }

            return $data;
        }
        return false;
    }


    /*
 * @Author:赵磊
 * 2018年9月27日17:32:57
 * 自提码验证错误次数超标
 * */
    public function errNumbers($user)
    {
        $max1 = tpCache('order.order_pickup_error_max1','','cf_config');
        $max2 = tpCache('order.order_pickup_error_max2','','cf_config');
        $num = $this->where('user_id',$user)->getField('order_pickup_verify_error');
//        if ($num >= $max1 && $num < $max2){
        $err = '';
        if ($num == $max1){
            //输验证码
            $err = 1;
        }
        if ($num >= $max2){
            //手机验证
            $err = 2;
        }
        return $err;
    }


}
