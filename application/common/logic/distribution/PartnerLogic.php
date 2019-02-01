<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/27
 * Time: 15:38
 */

namespace app\common\logic\distribution;


use think\Model;

class PartnerLogic extends Model
{
    protected $table = 'cf_user_partner';
    protected $resultSetType = 'collection';

    public function getPartnerUserInfo($user_id){
        $agentUserInfo = $this->alias('up')
            ->field('up.*')
            ->where(['up.user_id'=>$user_id])
            ->find();
        if ($agentUserInfo) {
            $agentUserInfo = $agentUserInfo->toArray();
        }
        return $agentUserInfo;
    }
    public function getPartnerApplyLog($user_id){
        $agentApplyLog = $this->table('cf_apply_partner')
            ->alias('ap')
            ->join(['cf_user_partner'=>'up'],'ap.user_id = up.user_id', 'left')
            ->join(['tp_users'=>'agent'],'agent.user_id = ap.agent_id', 'left')
            ->field('ap.*,agent.nickname  as agentnickname')
            ->where(['up.user_id'=>$user_id])
            ->order('ap.apply_time', 'desc')
            ->select()->toArray();
        return $agentApplyLog;
    }
}