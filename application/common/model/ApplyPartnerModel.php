<?php
/**
 * @Author: 陈静
 * @Date: 2018/04/19 14:21:00
 * @Description:
 */

namespace app\common\model;


use app\admin\logic\DistributionLogic;
use app\common\library\Logs;
use app\common\logic\WechatLogic;
use think\Db;
use think\Exception;
use think\Model;

class ApplyPartnerModel extends Model
{
    protected $table = 'cf_apply_partner';
    protected $pk = 'id';
    protected $resultSetType = 'collection';

    //插入支付日志
    public function addApply($result)
    {
        //获取订单信息
        $orderInfo = (new Order())->getOrderInfoByTradeNo($result['out_trade_no']);

        //查询邀请码
        $agentId = Db::table('cf_user_agent')->where('invite_partner_code',$orderInfo->user_note)->getField('user_id');
        $applySwitch = tpCache('distribute.partner_apply_switch','','cf_config');
        $status = $applySwitch ? 0 : 1;
        try{
            if ($status) {
                $data['user_id'] = $orderInfo->user_id;
                $data['agent_user_id'] = $agentId;
                $data['start_time'] = time();
                $data['end_time'] = strtotime('+1 year');
                $distributeLogic = new DistributionLogic();
                $res = $distributeLogic->addPartnerUser($data);
                if ($res['status'] != 1) {
                    throw new Exception('插入申请合伙人信息失败:'.$res['msg']);
                }
                $data['agent_id'] = $data['agent_user_id'];

                $agentData = $distributeLogic->dealAgentAccumulate($data);

                foreach ($agentData as $v) {
                    //发送模板消息通知给代理商
                    $msgResult = (new WechatLogic())->sendTemplateMsgOnAgentDistribute($v);
                    if ($msgResult['status'] != 1) {
                        Logs::sentryLogs('发送合伙人申请收益模板消息通知给代理商失败:'.$msgResult['msg']);
                    }
                }
            }

            $this->insert([
                'user_id' => $orderInfo->user_id,
                'channel_type' => 0,
                'agent_id' => $agentId ?? 0,
                'apply_content' => json_encode([ 'order_sn' => $result['out_trade_no']]),
                'apply_time' => time(),
                'status' => $status,
                'deal_time' => time(),
                'deal_result' => '自动审核通过'
            ]);
        }catch (Exception $exception){
            //记录日志
            \app\common\library\Logs::sentryLogs($exception,['msg' => '插入申请合伙人信息失败']);
        }

    }

    //判断申请状态
    /**
     * -2 不允许申请
     * -1 未申请
     * 0 待处理
     * 1 同意
     * 2 拒绝
     */
    public function judgeApplyStatus($user_id,$identity)
    {
        if(!$identity['partner'] && !$identity['agent']){
            //普通用户查询是否扫码
            if ($_SESSION['openid']) {
                $openid = $_SESSION['openid'];
            }else{
                $openid = (new Users())->alias('a')
                    ->join(['tp_oauth_users' => 'b'],'a.user_id = b.user_id','left')
                    ->where('a.user_id' , $user_id)
                    ->where('b.oauth_child' , 'mp')
                    ->getField('b.openid');
            }
            $result = (new UserScanModel())->where('open_id',$openid)
                ->where('scan_type',1)->order('scan_time DESC')->find();

            if (time() > $result->expire_time) {
                return -2;
            }
        }

        if ($identity['partner']) {
            return -2;
        }

        $res = $this->where('user_id',$user_id)->order('apply_time DESC')->find();

        if (empty($res)) {
            return -1;
        }
        return $res->status;
    }

}