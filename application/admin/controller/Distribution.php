<?php
/**
 * 后台smbf分销
 */

namespace app\admin\controller;

use app\common\model\PartnerRankActivity;
use app\common\logic\distribution\DistributionDevideLogLogic;
use app\common\model\UserUserModel;
use think\AjaxPage;
use app\admin\logic\DistributionLogic;
use think\Db;
use think\Loader;

class Distribution extends Base {
    
    /*
     * 初始化操作
     */
    public function _initialize() {
       parent::_initialize();
    }

    /**
     * 合伙人列表
     */
    public function partnerList(){
        $startTime = I('start_time');
        $endTime = I('end_time');

        // 搜索条件
        $condition = ' where up.user_id > 0 ';
        $pageParams = array();

        if (I('status')) {
            $condition .= ' and up.status = '.I('status').' ';
            $pageParams['status'] = I('status');
        }

        if ($startTime) {
            $condition .= ' and up.be_partner_start >= '.strtotime($startTime).' ';
            $pageParams['start_time'] = $startTime;
            if ($endTime) {
                $endTimeStamp = strtotime($endTime) + 86399;
                $condition .= ' and up.be_partner_start <= '.$endTimeStamp.' ';
                $pageParams['end_time'] = $endTime;
            }
        }else{
            if ($endTime) {
                $endTimeStamp = strtotime($endTime) + 86399;
                $condition .= ' and up.be_partner_start <= '.$endTimeStamp.' ';
                $pageParams['end_time'] = $endTime;
            }
        }

        if (I('keywords')) {
            $identity = I('identity');
            $pageParams['identity'] = $identity;
            $pageParams['keywords'] = I('keywords');
            if ($identity == 1) {
                //合伙人
                $condition .= ' and (cu.id_card_name like "%'.I('keywords').'%" or tu.mobile like "%'.I('keywords').'%") ';
            }else{
                $condition .= ' and (cuu.id_card_name like "%'.I('keywords').'%" or p_tu.mobile like "%'.I('keywords').'%") ';
            }
        }

        $orderBy = I('order_by');

        if ($orderBy == 'be_partner_start' || $orderBy == 'user_id') {
            $sort_order = 'up.'.I('order_by').' '.I('sort');
        }else{
            $sort_order = I('order_by').' '.I('sort');
        }

        if (IS_AJAX) {
            $partnerModel = new DistributionLogic();
            $count = $partnerModel->getPartnerList(true,$condition);

            $Page  = new AjaxPage($count,10);
            $show = $Page->show();
            //  搜索条件下 分页赋值
            foreach($pageParams as $key=>$val) {
                $Page->parameter[$key] = urlencode($val);
            }

            $partnerList = $partnerModel->getPartnerList(false,$condition,$Page,$sort_order);

            $this->assign('count',$count);
            $this->assign('partner_list',$partnerList);
            $this->assign('page',$show);// 赋值分页输出
            return $this->fetch('partnerList_ajax');
        }
        return $this->fetch();
    }


    public function agentList(){
        $startTime = I('start_time');
        $endTime = I('end_time');

        // 搜索条件
        $condition = ' ';
        $pageParams = array();

        if (I('agent_level')) {
            $condition .= ' where up.agent_level = '.I('agent_level');
            $pageParams['agent_level'] = I('agent_level');
        }

        if (I('status')) {
            $condition .= ' and up.status = '.I('status').' ';
            $pageParams['status'] = I('status');
        }

        if ($startTime) {
            $condition .= ' and up.be_agent_start >= '.strtotime($startTime).' ';
            $pageParams['start_time'] = $startTime;
            if ($endTime) {
                $endTimeStamp = strtotime($endTime) + 86399;
                $condition .= ' and up.be_agent_start <= '.$endTimeStamp.' ';
                $pageParams['end_time'] = $endTime;
            }
        }else{
            if ($endTime) {
                $endTimeStamp = strtotime($endTime) + 86399;
                $condition .= ' and up.be_agent_start <= '.$endTimeStamp.' ';
                $pageParams['end_time'] = $endTime;
            }
        }

        if (I('keywords')) {
            $identity = I('identity');
            $pageParams['identity'] = $identity;
            $pageParams['keywords'] = I('keywords');
            if ($identity == 1) {
                //合伙人
                $condition .= ' and (cu.id_card_name like "%'.I('keywords').'%" or tu.mobile like "%'.I('keywords').'%") ';
            }else{
                $condition .= ' and (cuu.id_card_name like "%'.I('keywords').'%" or tuu.mobile like "%'.I('keywords').'%") ';
            }
        }

        $orderBy = I('order_by');

        if ($orderBy == 'be_agent_start' || $orderBy == 'user_id') {
            $sort_order = 'up.'.I('order_by').' '.I('sort');
        }else{
            $sort_order = I('order_by').' '.I('sort');
        }

        if (IS_AJAX) {
            $partnerModel = new DistributionLogic();
            $count = $partnerModel->getAgentList(true,$condition);

            $Page  = new AjaxPage($count,10);
            $show = $Page->show();
            //  搜索条件下 分页赋值
            foreach($pageParams as $key=>$val) {
                $Page->parameter[$key] = urlencode($val);
            }

            $agentList = $partnerModel->getAgentList(false,$condition,$Page,$sort_order);

            $this->assign('count',$count);
            $this->assign('agent_list',$agentList);
            $this->assign('page',$show);// 赋值分页输出
            return $this->fetch('agentList_ajax');
        }

        return $this->fetch();
    }

    public function addPartner(){
        $info = ['start_time'=>date("Y-m-d H:i:s", time()), 'end_time'=>date("Y-m-d H:i:s",time()+365*86400)];
        $this->assign('info',$info);
        return $this->fetch();
    }

    public function savePartner(){
        $postData = input("post.");
        $data['user_id'] = $postData['user_id'];
        $data['agent_user_id'] = $postData['agent_user_id'];
        $data['start_time'] = strtotime($postData['start_time']);
        $data['end_time'] = strtotime($postData['end_time']);
        $flashSaleValidate = Loader::validate('Distribution');
        if (!$flashSaleValidate->batch()->scene('partner')->check($data)) {
            $return = ['status' => 0, 'msg' => '操作失败', 'result' => $flashSaleValidate->getError()];
            $this->ajaxReturn($return);
        }
        $distributionLogic = new DistributionLogic();
        $res = $distributionLogic->addPartnerUser($data);
        $this->ajaxReturn($res);
    }

    /**
     * 合伙人申请
     */
    public function partnerApply(){
        if(IS_POST){
            $post = input('post.');
            $condition = [];
            if (!empty($post['keywords'])) {
                $condition['tu.nickname|tu.mobile'] = ['like', "%{$post['keywords']}%"];
            }
            if (is_numeric($post['status'])) {
                $condition['ap.status'] = $post['status'];
            }
            $post['start_time'] = empty($post['start_time']) ? 0 : strtotime($post['start_time']);
            $post['end_time'] = empty($post['end_time']) ? time() : strtotime($post['end_time']);
            $condition['ap.apply_time'] = [['>',$post['start_time']],['<',$post['end_time']],'and'];

            $distributionLogic = new DistributionLogic();
            $count = $distributionLogic->getPartnerApply(1,$condition);
            $Page  = new AjaxPage($count,20);
            $show = $Page->show();
            $applyList = $distributionLogic->getPartnerApply(0,$condition,$Page->firstRow,$Page->listRows);
            $status = [0=>'待处理',1=>'同意',2=>'不同意'];
            array_walk($applyList, function (&$value, $key) use($status){
                $value['apply_time'] = date("Y.m.d H:i", $value['apply_time']);
                $value['status_desc'] = $status[$value['status']];
                $value['deal_time'] = $value['deal_time'] == 0 ? '--' : date('Y.m.d H:i',$value['deal_time']);
            });
            if (input('action') == 'export') {//导出
                $strTable ='<table width="" border="1">';
                $strTable .= '<tr>';
                $strTable .= '<td style="text-align:center;font-size:12px;width:50px;">会员ID</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;width:200px;">昵称</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;width:120px;" >手机号</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;width:50px;" >申请时间</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;width:50px;" >状态</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;width:150px;" >审核时间</td>';
                $strTable .= '<td style="text-align:center;font-size:12px;width:120px;" >备注</td>';
                $strTable .= '</tr>';
                foreach ($applyList as $val){
                    $strTable .= '<tr>';
                    $strTable .= '<td style="text-align:center;font-size:12px;width:50px;">'.$val['user_id'].'</td>';
                    $strTable .= '<td style="text-align:center;font-size:12px;width:200px;">'.$val['nickname'].'</td>';
                    $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">'.$val['mobile'].'</td>';
                    $strTable .= '<td style="text-align:center;font-size:12px;width:50px;">'.$val['apply_time'].'</td>';
                    $strTable .= '<td style="text-align:center;font-size:12px;width:50px;">'.$val['status_desc'].'</td>';
                    $strTable .= '<td style="text-align:center;font-size:12px;width:150px;">'.$val['deal_time'].'</td>';
                    $strTable .= '<td style="text-align:center;font-size:12px;width:120px;">'.$val['deal_result'].'</td>';
                    $strTable .= '</tr>';
                }
                $strTable .='</table>';
                downloadExcel($strTable,'partnerApply');
                exit();
            }
            $this->assign('apply_list',$applyList);
            $this->assign('page',$show);// 赋值分页输出
            $this->assign('pager',$Page);
            return $this->fetch('partnerApply_ajax');
        }
        return $this->fetch();
    }

    /**
     * 该方法如果前台用的上，可移到
     * @return mixed
     */
    public function partnerApplyDetail(){
        $id = input('get.id',0);
        $distributionLogic = new DistributionLogic();
        $applyDetail = $distributionLogic->getPartnerApplyDetail($id);
        //数据处理
        $channel_type = [0=>'微信端扫码加入',1=>'点击微信分享链接',2=>'微信直接登录注册',3=>'微信端手机号直接登录',16=>'WAP端扫码',32=>'小程序扫码',48=>'PC端扫码',64=>'APP扫码',80=>'后台添加'];
        $applyDetail['channel_type'] = $channel_type[$applyDetail['channel_type']];
        $status = [0=>'待处理',1=>'同意',2=>'不同意'];
        $applyDetail['status_desc'] = $status[$applyDetail['status']];
        $applyDetail['apply_time'] = date("Y.m.d H:i",$applyDetail['apply_time']);
        $applyDetail['deal_time'] = !empty($applyDetail['deal_time']) ? date("Y.m.d H:i",$applyDetail['deal_time']): '--';
        $otherInfo = json_decode($applyDetail['apply_content'], true);

        //查询用户对应的代理商信息
        $agentInfo = Db::table('tp_users')->field('user_id,nickname,mobile')->where('user_id',$applyDetail['agent_id'])->find();

        // == 有可能存在查询不到的情况
        $this->assign('agent_user', $agentInfo);
//        halt($agentInfo);
        $this->assign('data', array_merge($applyDetail,$otherInfo));
        return $this->fetch();
    }

    /**
     * 处理合伙人申请
     */
    public function dealPartnerApply(){
        $postData = input('post.');
        $id = $postData['id'];
        $distributionLogic = new DistributionLogic();
        if ($postData['status'] ==1) { //同意
            $applyDetail = $distributionLogic->getPartnerApplyDetail($id);
            if (empty($applyDetail)) {
                $this->ajaxReturn(['status' => 0, 'msg' => '操作失败,扫码信息错误']);
            }
            //查询用户对应的代理商信息
            $agentInfo = Db::table('tp_users')->field('user_id,nickname,mobile')->where('user_id',$applyDetail['agent_id'])->find();
            if (empty($agentInfo)) {
                $this->ajaxReturn(['status' => 0, 'msg' => '操作失败,代理商信息不正确']);
            }

            $data['user_id'] = $postData['user_id'];
            $data['agent_user_id'] = $postData['agent_user_id'];
            $data['start_time'] = time();
            $data['end_time'] = strtotime('+1 year');
            $res = $distributionLogic->addPartnerUser($data);
            if ($res['status'] == 1) {
                $distributionLogic->dealPartnerApply($postData);// 修改申请记录
                $distributionLogic->dealAgentAccumulate($applyDetail);
                $this->ajaxReturn(['status' => 1, 'msg' => '操作成功']);
            } else {
                $this->ajaxReturn($res);
            }
        } elseif ($postData['status'] == 2) { //拒绝
            $distributionLogic->dealPartnerApply($postData);
            $this->ajaxReturn(['status' => 1, 'msg' => '操作成功']);
        }
    }

    public function deletePartnerApply(){
        $id = input("post.id", 0);
        $distributionLogic = new DistributionLogic();
        $distributionLogic->deletePartnerApply($id);
        $this->ajaxReturn(['status' => 1, 'msg' => '删除成功']);
    }

    /**
     * 增加代理商
     */
    public function addAgent(){
        $info = ['start_time'=>date("Y-m-d H:i:s", time()), 'end_time'=>date("Y-m-d H:i:s",time()+365*86400)];
        $province = Db::table('tp_region')->where('parent_id',0)->select();
        $this->assign('province',$province);
        $this->assign('info',$info);
        return $this->fetch();
    }
    public function saveAgent(){
        $data = input("post.");
        $data['start_time'] = strtotime($data['start_time']);
        $data['end_time'] = strtotime($data['end_time']);
        $flashSaleValidate = Loader::validate('Distribution');
        if (!$flashSaleValidate->batch()->scene('agent')->check($data)) {
            $return = ['status' => 0, 'msg' => '操作失败', 'result' => $flashSaleValidate->getError()];
            $this->ajaxReturn($return);
        }
        $distributionLogic = new DistributionLogic();
        $res = $distributionLogic->addAgentUser($data);
        $this->ajaxReturn($res);
    }

    public function exportPartner(){
        halt(input('post.'));
    }

    //start------合伙人统计列表详情
    public function partnerReportDetail()
    {
        $partnerId = I('partner_id');
        $distObj = new DistributionLogic();
        $partnerInfo = $distObj->getPartnerInfo($partnerId);

        if (empty($partnerInfo['id_card_num']) || empty($partnerInfo['id_card_name'])) {
            $this->error('实名制信息为空');
        }

        if(IS_AJAX){
            $act = I('act');
            switch ($act){
                case 'user_detail':
                    $this->userDetail();
                    return $this->fetch('user_list_ajax');
                    break;
                case 'earns_detail':
                    $this->earnsDetail();
                    return $this->fetch('earns_detail_ajax');
                    break;
                case 'withdraw_detail':
                    $this->withdrawDetail($partnerId);
                    return $this->fetch('withdraw_detail_ajax');
                    break;
            }

            return $this->fetch('partnerReportDetail_ajax');
        }

        $this->assign('partner_id',$partnerId);
        $this->assign('partner_info',$partnerInfo);
        return $this->fetch();
    }

    //用户列表tab页
    public function userDetail()
    {
        $userType = I('user_type');
        $partnerId = I('partner_id');
        $keywords = I('keywords');
        $condition = '';
        $pageParams = [];
        if ($keywords) {
            $condition = ' and (b.user_id like "%'.$keywords.'%" or b.mobile like "%'.$keywords.'%" or b.nickname like "%'.$keywords.'%") ';
            $pageParams['keywords'] = $keywords;
        }

        $orderBy = I('order_by');

        if ($orderBy) {
            if ( $orderBy == 'user_id' or $orderBy == 'reg_time' or $orderBy == 'last_login'){
                $sort_order = 'b.'.$orderBy.' '.I('sort');
            }else{
                $sort_order = $orderBy.' '.I('sort');
            }
        }

        $currentUserInfo = (new UserUserModel())->getCurrentUserInfo($partnerId);

        $distModel = new DistributionLogic();
        $count = $distModel->getLevelChild($currentUserInfo,$userType,true,$condition);

        $Page  = new AjaxPage($count,10);
        $show = $Page->show();
        //  搜索条件下 分页赋值
        foreach($pageParams as $key=>$val) {
            $Page->parameter[$key]   =   urlencode($val);
        }

        $userData = $distModel->getLevelChild($currentUserInfo,$userType,false,$condition,$Page,$sort_order);

        $this->assign('count',$count);
        $this->assign('page',$show);// 赋值分页输出
        $this->assign('user_data',$userData);
    }

    //收益tab页
    public function earnsDetail()
    {
        $partnerId = I('partner_id');

        $startTime = I('start_time');
        $endTime = I('end_time');

        // 搜索条件
        $condition = [];
        $pageParams = array();

        if (I('member_level')) {
            $currentPartnerInfo = (new UserUserModel())->where('user_id', $partnerId)->find()->toArray();
            $condition['uu.level'] = $currentPartnerInfo['level'] + I('member_level');
            $pageParams['member_level'] = I('member_level');
        }

        if ($startTime) {
            $condition['d.add_time'] = [['>=', strtotime($startTime)]];
            $pageParams['start_time'] = $startTime;
            if ($endTime) {
                $condition['d.add_time'] = [['>=', strtotime($startTime)], ['<=', strtotime($endTime) + 86399], 'and'];
                $pageParams['end_time'] = $endTime;
            }
        } else {
            if ($endTime) {
                $condition['d.add_time'] = ['<=', strtotime($endTime) + 86399];
                $pageParams['end_time'] = $endTime;
            }
        }


        if (I('keywords')) {
            $pageParams['keywords'] = I('keywords');
            $condition['u.user_id|u.mobile|u.nickname'] = ['like', '%' . I('keywords') . '%'];
        }


        $order_by ? $sort_order = 'd.'.$order_by.' '.I('sort') : false;





        $distModel = new DistributionLogic();

        $count = $distModel->getEarnsDetails($partnerId,true,$condition,'','');

        $Page  = new AjaxPage($count,10);
        $show = $Page->show();
        //  搜索条件下 分页赋值
        foreach($pageParams as $key=>$val) {
            $Page->parameter[$key]   =   urlencode($val);
        }

        $res = $distModel->getEarnsDetails($partnerId,false,$condition,$Page,$sort_order);

        $totalEarns = $distModel->getEarnsDetails($partnerId,false,$condition,$Page,$sort_order,true);

        $this->assign('count',$count);
        $this->assign('total_earns',round($totalEarns,2));
        $this->assign('page',$show);// 赋值分页输出
        $this->assign('earns_data',$res);
    }

    //提现明细tab页
    public function withdrawDetail($partnerId)
    {
        I('order_by') ? $sort_order = I('order_by').' '.I('sort') : false;

        // 搜索条件
        $condition = [];
        $condition['user_id'] = $partnerId;
        $pageParams = array();
        $startTime = I('start_time');
        $endTime = I('end_time');

        if ($startTime) {
            $condition['create_time'] = [['>=',strtotime($startTime)]];
            $pageParams['start_time'] = $startTime;
            if ($endTime) {
                $condition['create_time'] = [['>=',strtotime($startTime)],['<=',strtotime($endTime) + 86399],'and'];
                $pageParams['end_time'] = $endTime;
            }
        }else{
            if ($endTime) {
                $condition['create_time'] = ['<=',strtotime($endTime) + 86399];
                $pageParams['end_time'] = $endTime;
            }
        }

        $distModel = new DistributionLogic();
        $count = $distModel->withDrawDetail(true,$condition,'','');

        $Page  = new AjaxPage($count,10);
        $show = $Page->show();
        //  搜索条件下 分页赋值
        foreach($pageParams as $key=>$val) {
            $Page->parameter[$key]   =   urlencode($val);
        }

        $data = $distModel->withDrawDetail(false,$condition,$Page,$sort_order);


        if ($data) {
            $func = function (&$value){
                $value['create_time'] = date('Y-m-d H:i',$value['create_time']);
                $value['bank_card'] = substr($value['bank_card'],-4);
            };
            array_walk($data,$func);
        }

        $totalWithdraw = $distModel->withDrawDetail(false,$condition,$Page,$sort_order,true);
        $this->assign('count',$count);
        $this->assign('total_withdraw',round($totalWithdraw,2));
        $this->assign('page',$show);// 赋值分页输出
        $this->assign('withdraw_data',$data);
    }

    //解除合伙人身份
    public function canclePartnerIdentity()
    {
        $partnerId = I('partner_id');
        $result = (new DistributionLogic())->canclePartnerIdentity($partnerId);
        if ($result) {
            return outPut(1,'success');
        }
        return outPut(-1,'error');
    }
    //end------合伙人统计列表详情

    //代理商统计列表详情
    public function agentReportDetail()
    {
        $agentId = I('agent_id');
        $distObj = new DistributionLogic();
        $agentInfo = $distObj->getPartnerInfo($agentId);

        if (empty($agentInfo['id_card_num']) || empty($agentInfo['id_card_name'])) {
            $this->error('实名制信息为空');
        }

        $this->assign('agent_info',$agentInfo);
        if(IS_AJAX){
            $act = I('act');
            switch ($act){
                case 'user_detail':
                    $this->memberList();
                    return $this->fetch('user_list_ajax_agent');
                    break;
                case 'earns_detail':
                    $this->agentEarnsDetail($agentId);
                    return $this->fetch('agent_earns_detail_ajax');
                    break;
                case 'withdraw_detail':
                    $this->withdrawDetail($agentId);
                    return $this->fetch('withdraw_detail_ajax');
                    break;
            }
            return $this->fetch('agentReportDetail_ajax');
        }

        $this->assign('agent_id',$agentId);
        return $this->fetch();
    }

    public function memberList () {
        $user_type = input('user_type');
        $agent_id = input('agent_id');
        $keywords = input('keywords/s','');
        $where = ['first_agent_id'=>$agent_id,'up.user_id'=>['>',0]];
        if ($keywords) {
            $where['u.user_id|u.mobile|u.nickname'] = ['like','%'.$keywords.'%'];
        }
        $partnerIdArr = Db::table('cf_user_partner')
            ->alias('up')
            ->join('tp_users u','up.user_id = u.user_id','left')
            ->where($where)->getField('up.user_id',true);
        $partnerIdStr = join(',',$partnerIdArr);
        $condition = empty($partnerIdStr) ? " and up.user_id in ('') " :" and up.user_id in ($partnerIdStr) ";
        $Page  = new AjaxPage(count($partnerIdArr),10);
        $show = $Page->show();

        $orderBy = input('order_by');
        if ($orderBy) {
            if ( $orderBy == 'user_id' or $orderBy == 'reg_time' or $orderBy == 'last_login'){
                $sort_order = 'tu.'.$orderBy.' '.I('sort');
            }else{
                $sort_order = $orderBy.' '.I('sort');
            }
        }
        if ($user_type == 0) {//合伙人列表
            $distModel = new DistributionLogic();
            $partnerList = $distModel->getPartnerList($count = false, $condition, $Page,$sort_order);
            $this->assign('count',count($partnerIdArr));
            $this->assign('user_data',$partnerList);
            $this->assign('page',$show);// 赋值分页输出
        } else {
            $userType = I('user_type');
            $userId = I('agent_id');
            $keywords = I('keywords');
            $condition = '';
            $pageParams = [];
            if ($keywords) {
                $condition = ' and (b.user_id like "%'.$keywords.'%" or b.mobile like "%'.$keywords.'%" or b.nickname like "%'.$keywords.'%") ';
                $pageParams['keywords'] = $keywords;
            }

            $orderBy = I('order_by');

            if ($orderBy) {
                if ( $orderBy == 'user_id' or $orderBy == 'reg_time' or $orderBy == 'last_login'){
                    $sort_order = 'b.'.$orderBy.' '.I('sort');
                }else{
                    $sort_order = $orderBy.' '.I('sort');
                }
            }

            $currentUserInfo = (new UserUserModel())->getCurrentUserInfo($userId);

            $distModel = new DistributionLogic();
            $count = $distModel->getLevelChild($currentUserInfo,$userType,true,$condition);

            $Page  = new AjaxPage($count,10);
            $show = $Page->show();
            //  搜索条件下 分页赋值
            foreach($pageParams as $key=>$val) {
                $Page->parameter[$key]   =   urlencode($val);
            }

            $userData = $distModel->getLevelChild($currentUserInfo,$userType,false,$condition,$Page,$sort_order);

            $this->assign('count',$count);
            $this->assign('page',$show);// 赋值分页输出
            $this->assign('user_data',$userData);
        }
    }
    //收益tab页
    public function agentEarnsDetail($partnerId)
    {
        $startTime = I('start_time');
        $endTime = I('end_time');

        // 搜索条件
        $condition = ' where to_user_id = '.$partnerId.' and divide_money > 0 ';

        $pageParams = array();

        if ($level = I('member_level')) {
            $pageParams['member_level'] = $level;

            if ($level <= 3) {
                $condition .= ' and level = '.$level.'';
            }
            if ($level == 4) {
                $condition .= ' and agent_level > 0 ';
            }
        }

        if ($startTime) {
            $condition .= ' and add_time >= '.strtotime($startTime).' ';
            $pageParams['start_time'] = $startTime;
            if ($endTime) {
                $endTimeStamp = strtotime($endTime) + 86399;
                $condition .= ' and add_time <= '.$endTimeStamp.' ';
                $pageParams['end_time'] = $endTime;
            }
        }else{
            if ($endTime) {
                $endTimeStamp = strtotime($endTime) + 86399;
                $condition .= ' and add_time <= '.$endTimeStamp.' ';
                $pageParams['end_time'] = $endTime;
            }
        }

        if (I('keywords')) {
            $pageParams['keywords'] = I('keywords');
            $condition .= ' and (from_user_id like "%'.I('keywords').'%" or mobile like "%'.I('keywords').'%" or nickname like "%'.I('keywords').'%") ';
        }

        I('order_by') ? $sort_order = I('order_by').' '.I('sort') : false;

        $distModel = new DistributionLogic();

        $count = $distModel->getAgentEarnsDetails($partnerId,true,$condition,'','');

        $Page  = new AjaxPage($count,10);
        $show = $Page->show();
        //  搜索条件下 分页赋值
        foreach($pageParams as $key=>$val) {
            $Page->parameter[$key] = urlencode($val);
        }

        $res = $distModel->getAgentEarnsDetails($partnerId,false,$condition,$Page,$sort_order);

        $totalEarns = $distModel->getAgentEarnsDetails($partnerId,false,$condition,$Page,$sort_order,true);

        $this->assign('count',$count);
        $this->assign('total_earns',round($totalEarns,2));
        $this->assign('page',$show);// 赋值分页输出
        $this->assign('earns_data',$res);
    }
    //合伙人排行榜
    public function partnerRank(){
        if (IS_POST) {
            $data = input('post.');
            $PartnerRankActivity = new PartnerRankActivity();
            $count = $PartnerRankActivity->count();
            $Page  = new AjaxPage($count,10);
            $show = $Page->show();
            $lists = $PartnerRankActivity->getList($Page);
            $this->assign('count',$count);
            $this->assign('page',$show);
            $this->assign('lists',$lists);
            return $this->fetch('partnerRank_ajax');
        }
        return $this->fetch();
    }
    //排行榜活动
    public function rankActivity(){
        if (IS_POST) {
            $data = input('post.');
            if (isset($data['id']) && $data['id'] >0) {
                $PartnerRankActivity = new PartnerRankActivity();
                $activity = $PartnerRankActivity->activityDetail($data['id']);
                if ($activity['status'] == 3) {
                    $this->ajaxReturn(['status' => 0, 'msg' => '操作失败，该活动已派奖']);
                }
            }
            $data['start_time'] = strtotime($data['start_time']);
            $data['end_time'] = strtotime($data['end_time']);
            $flashSaleValidate = Loader::validate('Distribution');
            if (!$flashSaleValidate->batch()->scene('rankActivity')->check($data)) {
                $return = ['status' => 0, 'msg' => '操作失败', 'result' => $flashSaleValidate->getError()];
                $this->ajaxReturn($return);
            }
            $time = time();
            $PartnerRankActivity = new PartnerRankActivity();
            $data['reward_scale'] = str_replace('，',',',$data['reward_scale']);//中文逗号换成英文逗号
            if ($data['id']) {
                $data['update_time'] = $time;
            } else {
                $data['update_time'] = $time;
                $data['add_time'] = $time;
            }
            if ($data['start_time']>$time) {
                $data['status'] = 0;
            } elseif ($data['start_time'] < $time && $data['end_time'] > $time) {
                $data['add_time'] = 1;
            }
            $id = isset($data['id']) ? $data['id'] :0;
            unset($data['id']);
            if ($id > 0) {
                $PartnerRankActivity->where('id',$id)->update($data);
            } else {
                $PartnerRankActivity->insert($data);
            }
            $this->ajaxReturn(['status' => 1, 'msg' => '操作成功']);
        }
        return $this->fetch();
    }
    //关闭活动
    public function closeActivity(){
        $id = input('id');
        if ($id) {
            $activity = Db::table('cf_partner_rank_activity')->where('id',$id)->find();
            if ($activity['status']==3) {
                $this->ajaxReturn(['status' => 0, 'msg' => '该活动已派奖，无法关闭']);
            }
            if ( $activity['status']== 4) {
                if (time() < $activity['start_time']) {
                    $status = 0;
                } elseif ($activity['start_time'] < time() && time() < $activity['end_time']) {
                    $status = 1;
                } elseif (time() > $activity['end_time']) {
                    $status = 2;
                }
                $activity['status'] = $status;
            } else {
                $activity['status'] = 4;
            }
            Db::table('cf_partner_rank_activity')->where('id',$id)->save($activity);
            $this->ajaxReturn(['status' => 1, 'msg' => '操作成功']);
        }
        $this->ajaxReturn(['status' => 0, 'msg' => '操作失败']);
    }
    //排名活动详情
    public function rankActivityDetail(){
        $id = input('id');
        $PartnerRankActivity = new PartnerRankActivity();
        $activity = $PartnerRankActivity->activityDetail($id);
        $rows = $PartnerRankActivity->activityData($activity);

        $saleSum = array_sum(array_column($rows,'total_order'));
        $this->assign('partner_num',count($rows));//参与打榜的合伙人数量
        $this->assign('sub_user_count',array_sum(array_column($rows,'sub_user')));//新增消费用户数
        $this->assign('sale_sum',$saleSum);//合伙人新邀请的用户产生的订单金额
        $this->assign('reward',round(($saleSum*$activity['sale_reward_scale'])/100, 2));//打榜期间平台总销售额 * 奖励金比例
        $this->assign('activity',$activity);
        return $this->fetch();
    }

    public function rankActivityDetail_ajax(){
        $id = input('id');
        $order_by = input('order_by');
        $sort = input('sort');
        $PartnerRankActivity = new PartnerRankActivity();
        $activity = $PartnerRankActivity->activityDetail($id);
        $count = $PartnerRankActivity->activityData($activity, true);
        $Page  = new AjaxPage($count,10);
        $show = $Page->show();
        $rows = $PartnerRankActivity->activityData($activity, false);//查询到所有数据
        $scale = explode(',',$activity['reward_scale']);//奖金分配数组
        $saleSum = array_sum(array_column($rows,'total_order'));//有效的销售额
        $reward = max(round(($saleSum*$activity['sale_reward_scale'])/100, 2), $activity['init_reward']);//（销售额*奖金比例）和 初始奖金，谁大取谁
        $func = function (&$list,$key) use ($scale,$reward){
            $list['scale_amount'] = round(((isset($scale[$key]) ? $scale[$key] : 0)*$reward) /100, 2);//奖金
            $list['scale']      = (isset($scale[$key]) ? $scale[$key] : 0).'%';//瓜分比例
            $list['rank']       = $key+1;
        };
        array_walk($rows,$func);
        $lists = array_sort($rows,$order_by,$sort);
        $lists = array_slice($lists,$Page->firstRow,$Page->listRows,true);

//        halt($lists);
        $this->assign('page',$show);
        $this->assign('count',$count);
        $this->assign('lists',$lists);
        return $this->fetch();
    }

    /**
     * 派奖
     */
    public function award(){
        $id = input('id');
        $PartnerRankActivity = new PartnerRankActivity();
        $activity = $PartnerRankActivity->activityDetail($id);
        if ($activity['status'] != 2) {
            return $this->ajaxReturn(['status'=>0,'msg'=>'该活动不能派奖']);
        }
        $rows = $PartnerRankActivity->activityData($activity);//查询到所有数据
        $scale = explode(',',$activity['reward_scale']);//奖金分配数组
        $insertUserNum = array_sum(array_column($rows,'sub_user'));//新增下单的用户数量
        $saleSum = array_sum(array_column($rows,'total_order'));//有效的销售额
        $reward = max(round(($saleSum*$activity['sale_reward_scale'])/100, 2), $activity['init_reward']);//（销售额*奖金比例）和 初始奖金，谁大取谁
        $insertData = [];
        $func = function ($list,$key) use ($scale,$reward,$activity,&$insertData){
            if (!isset($scale[$key])) return;
            $rank = $key + 1;
            $scale_amount = round(((isset($scale[$key]) ? $scale[$key] : 0)*$reward) /100, 2);//奖金
            if ($activity['reward_type'] == 0) {//发放到余额
                accountLog($list['partner_id'], $scale_amount,$pay_points = 0, $desc = '合伙人打榜活动派奖，ID：'.$activity['id'],$distribut_money = 0,$order_id = 0 ,$order_sn = '');
            } elseif ($activity['reward_type'] == 1) {//发放到收益
                partnerAccountLog($list['partner_id'], $scale_amount,$desc = '合伙人打榜活动派奖，ID：'.$activity['id']);
            }
            //新增的有效订单
            $order = Db::name('order o')
                ->field('o.order_id,o.user_id')
                ->join(['cf_user_user'=> 'uu'],'o.user_id= uu.user_id','left')
                ->where('uu.parent_id',$list['partner_id'])
                ->where("(pay_status=1 or pay_code='cod') and order_status in(0,1,2,4)")
                ->select();
            $insertData[] = [
                'act_id'        => $activity['id'],
                'partner_id'    => $list['partner_id'],
                'partner_name'  => $list['nickname'],
                'current_rank_no'=>$rank,
                'current_rank_scale'=> $scale[$key].'%',
                'current_rank_reward' => $scale_amount,
                'increased_user_num'    => $list['sub_user'],
                'increased_sale_amount' => $list['total_order'],
                'increased_sale_reward' => round(($list['total_order'] * $activity['sale_reward_scale'])/100),
                'contribution_value'    => $list['contribution'],
                'increased_user_ids'    => join(',',array_column($order,'user_id')),
                'increased_order_ids'   => join(',',array_column($order,'order_id')),
                'add_time'              => time()
            ];
        };
        array_walk($rows,$func);
        if (!empty($insertData)) {
            Db::table('cf_partner_rank_record')->insertAll($insertData);
        }
        Db::table('cf_partner_rank_activity')->where('id',$id)
            ->update([
                'status'    => 3,
                'increased_user_num'    => $insertUserNum,
                'increased_sale_amount' => $saleSum,
                'increased_sale_reward' => round(($saleSum * $activity['sale_reward_scale'])/100, 2),
                'partner_num'           => count($rows)
            ]);
        $this->ajaxReturn(['status'=>1,'msg'=>'操作成功']);
    }
}