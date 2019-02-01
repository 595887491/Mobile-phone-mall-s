<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/5/2
 * Time: 15:25
 */

namespace app\admin\logic;
use app\common\logic\distribution\DistributionDevideLogLogic;
use app\common\model\UserAgentModel;
use app\common\model\UserModel;
use app\common\model\UserPartnerModel;
use app\common\model\Users;
use app\common\model\UserUserModel;
use gmars\nestedsets\NestedSets;
use think\Db;
use think\Model;
use think\Page;


class DistributionLogic extends Model
{
    protected $resultSetType = 'collection';
    /**
     * @param bool $count 是否为统计条数
     */
    public function getPartnerList($count = false, $condition = '', $pageObj = '',$order = ''){
        $field = ' up.user_id,tu.reg_time,tu.last_login,tu.mobile , tu.nickname ,tu.head_pic , cu.id_card_name, cu.id_card_num , cu.wallet_accumulate_income as  total_earns  ,
 up.`status` ,up.be_partner_start ,cuu.id_card_name AS agent_real_name,
 (SELECT count(*) from cf_user_user  where parent_id = up.user_id) as firstUserNum ,
 (SELECT count(*) from cf_user_user  where left_key >= d.left_key  and right_key <= d.right_key  and `level`= d.`level`+2 ) as secondUserNum ,
 (SELECT count(*) from cf_user_user  where left_key >= d.left_key  and right_key <= d.right_key  and `level`>=d.`level`+3 ) as overSecondUserNum ,
 (SELECT if( sum(divide_money )>0 , sum(divide_money ) , 0) from cf_distribute_divide_log d 
where to_user_id = up.user_id and ( ceiling( if(divide_time>0 ,divide_time ,add_time)/86400) = CEIL( UNIX_TIMESTAMP()/86400))) as today_earns ,
 (SELECT sum(divide_money ) from cf_distribute_divide_log where from_user_id =up.user_id and to_user_id = up.first_agent_id ) 
 as divide_total,
 (SELECT sum(profit_money) FROM cf_profit_log WHERE from_user_id = up.user_id AND to_user_id = up.first_agent_id)
  as profit_total
 ';

        if ($count) {
            $field = ' count(up.user_id) as total_num  ';
        }
        $sql = ' SELECT '.$field.' from cf_user_partner up 
INNER JOIN  cf_user_user d on d.user_id = up.user_id  
INNER JOIN cf_users cu on cu.user_id = up.user_id 
INNER JOIN tp_users tu  on tu.user_id = up.user_id
LEFT JOIN `tp_users` `p_tu` ON `p_tu`.`user_id` = `cu`.`first_agent_id` 
INNER JOIN `cf_users` `cuu` ON `cu`.`first_agent_id` = `cuu`.`user_id` ';

        if ($condition) {
            $sql .= $condition;
        }

        if ($order) {
            $sql .= ' order by '.$order.' ';
        }

        if ($pageObj) {
            $sql .= ' limit '.$pageObj->firstRow.', '.$pageObj->listRows;
        }

        $rows = Db::query($sql);

        if ($count) {
            return $rows[0]['total_num'];
        } else {
            $status = [
                1 => '正常',
                2 => '已过期',
                3 => '已解除'
            ];
            foreach ($rows as &$v){
                if ($v['id_card_num']) {
                    $v['id_card_num'] = substr_replace($v['id_card_num'],str_repeat('*',strlen($v['id_card_num']) - 8),4,-4);
                }
                $v['status'] = $status[$v['status']];
                $v['share_money'] = $v['divide_total'] + $v['profit_total'];
            }

            return $rows;
        }
    }
    /**
     * @param $user_id int
     */
    public function getApplyTime($user_id){
        if (is_numeric($user_id)) {
            return $this->table('cf_apply_partner')
                ->where('user_id', $user_id)
                ->order('apply_time', 'DESC')
                ->limit(1)
                ->getField('apply_time');
        }
    }

    public function getAgentList($count = false, $condition = '', $pageObj = '',$order = ''){

        $field = 'up.user_id , tu.mobile ,tu.head_pic, tu.nickname , tu.reg_time , tu.last_login , cu.id_card_name, cu.id_card_num , cu.wallet_accumulate_income as total_earns,
 up.`status` ,up.be_agent_start ,up.parent_id ,up.agent_level , cuu.id_card_name as parent_name,
(SELECT count(*) from cf_user_partner  where first_agent_id = up.user_id) as partner_num , 
 (SELECT if( sum(divide_money )>0 , sum(divide_money ) , 0) from cf_distribute_divide_log d where to_user_id = up.user_id 
   and ( ceiling( if(divide_time>0 ,divide_time ,add_time)/86400) = CEIL( UNIX_TIMESTAMP()/86400 ) ) ) as today_earns ';

        if ($count) {
            $field = ' count(up.user_id) as total_num ';
        }

        $sql = ' SELECT '.$field.' from cf_user_agent up 
INNER JOIN cf_users cu on cu.user_id = up.user_id 
LEFT JOIN cf_users cuu on cuu.user_id = up.parent_id
LEFT JOIN tp_users tuu on tuu.user_id = up.parent_id
INNER JOIN tp_users tu  on tu.user_id = up.user_id  ';

        if ($condition) {
            $sql .= $condition;
        }

        if ($order) {
            $sql .= ' order by '.$order.' ';
        }

        if ($pageObj) {
            $sql .= ' limit '.$pageObj->firstRow.', '.$pageObj->listRows;
        }
//dump($sql);
        $rows = Db::query($sql);

        if ($count) {
            return $rows[0]['total_num'];
        } else {
            $status = [
                1 => '正常',
                2 => '已过期',
                3 => '已解除'
            ];
            foreach ($rows as &$v){
                if ($v['id_card_num']) {
                    $v['id_card_num'] = is_id_card($v['id_card_num'])?substr_replace($v['id_card_num'],str_repeat('*',strlen($v['id_card_num']) - 8),4,-4):$v['id_card_num'];
                }
                $v['status'] = $status[$v['status']];
            }

            return $rows;
        }
    }

    /**
     * @param $data $data['user_id'] = $postData['user_id'];
    $data['agent_user_id'] = $postData['agent_user_id'];
    $data['start_time'] = strtotime($postData['start_time']);
    $data['end_time'] = strtotime($postData['end_time']);
     * @return array
     */
    public function addPartnerUser($data){
        $userUserModel = new UserUserModel();
        // 代理商用户
        $agentUser = $userUserModel->getUserInfo($data['agent_user_id']);
        if (empty($agentUser) || ($agentUser['user_type']&4) != 4) {
            return ['status'=>0,'msg'=>'你选择的代理商身份不正确'];
        }

        $userTree = $userUserModel->getTreeByUserId($data['user_id']);
        $userTree = array_reverse($userTree);
        // 判断是否已经是合伙人
        if (!empty($userTree[0]['partner_relation'])) {
            return ['status'=>0,'msg'=>'目标用户已经是合伙人'];
        }
        if (!empty($userTree[0]['agent_relation'])) {
            return ['status'=>0,'msg'=>'目标用户是代理商'];
        }
        $parentId = 0;
        if (count($userTree) == 1) {//没有上级用户
            $parentId = 0;
        } else {
            foreach ($userTree as $key => $val){
                if (!empty($val['partner_relation'])) {
                    $parentId = $val['user_id'];
                    break;
                }
            }
        }
        $userPartnerModel = new UserPartnerModel();
        $nestedsetsModel = new NestedSets($userPartnerModel);
        $res = $nestedsetsModel->insert($parentId,[
            'user_id'=>$data['user_id'],
            'be_partner_start' => $data['start_time'],
            'be_partner_end' => $data['end_time'],
            'first_agent_id'    =>$data['agent_user_id']
            ]);
        if ($res) {
            //修改用户信息 cf_users ,和下级的 first_partner_id
            $this->changeUserType($data, 2);
            return ['status'=>1, 'msg'=>'新增成功'];
        }
        return ['status'=>0,'msg'=>"写入数据失败"];
    }

    public function getPartnerApply($count = false, $condition=[], $firstRow = 0, $listRows = 20){
        $apply = $this->table('cf_apply_partner')->alias('ap')
            ->join(['tp_users'=>'tu'],'tu.user_id = ap.user_id', 'left')
            ->where($condition);
        if ($count) {
            return $apply->count();
        } else {
            $rows = $apply->field('ap.*, tu.nickname,tu.mobile')
                ->order('apply_time','DESC')
                ->limit($firstRow, $listRows)
                ->select()->toArray();
            return $rows;
        }
    }
    public function getPartnerApplyDetail($id){
        $apply = $this->table('cf_apply_partner')->alias('ap')
            ->field('ap.*, tu.nickname,tu.mobile')
            ->join(['tp_users'=>'tu'],'tu.user_id = ap.user_id', 'left')
            ->where('ap.id', $id)
            ->find();
        return !empty($apply) ? $apply->toArray() : [];
    }

    /**
     * 根据合伙人查询代理商信息
     */
    public function getAgentInfoByApply($partnerId){
        // 查询最近的一条扫码记录
        $scanCodeLog = $this->table('cf_user_scan')->alias('us')
            ->field('us.*')
            ->join(['tp_oauth_users'=>'ou'], 'us.open_id = ou.openid','left')
            ->where('ou.user_id',$partnerId)
            ->order('us.scan_time','DESC')
            ->find();
        if (!empty($scanCodeLog)) {
            $scanCodeLog = $scanCodeLog->toArray();
            $agentUserInfo = $this->table('cf_user_agent')->alias('ua')
                ->field('ua.*,tu.nickname,tu.mobile')
                ->join(['tp_users'=>'tu'], 'tu.user_id = ua.user_id', 'left')
                ->where('ua.user_id', $scanCodeLog['parent_id'])
                ->find();
            return !empty($agentUserInfo) ? $agentUserInfo->toArray() : [];
        }
        return [];
    }

    public function dealPartnerApply($data){
        $this->table('cf_apply_partner')
            ->where('id',$data['id'])
            ->update(['deal_time'=>time(), 'status'=>$data['status'], 'deal_result'=>$data['deal_result']]);
    }

    public function deletePartnerApply($id){
        $this->table('cf_apply_partner')
            ->where('id',$id)
            ->delete();
    }

    public function addAgentUser($data){
        $userUserModel = new UserUserModel();

        $userTree = $userUserModel->getTreeByUserId($data['user_id']);
        $userTree = array_reverse($userTree);
        // 判断是否已经是代理商
//        if (!empty($userTree[0]['partner_relation'])) {
//            return ['status'=>0,'msg'=>'目标用户已经是合伙人'];
//        }
        if (!empty($userTree[0]['agent_relation'])) {
            return ['status'=>0,'msg'=>'目标用户是代理商'];
        }
        $parentId = 0;
        $array = [
            '1'=>$data['city_id']??0,
            '2'=>$data['area_id']??0,
            '3'=>$data['town_id']??0,
            '4'=>$data['school_id']??0,
        ];
        $region_id = $array[$data['agent_level']];
//父级区域是否有代理商, 如添加武侯区代理商，要检查是否有成都市代理商
        $parent_area_agent = Db::table('cf_user_agent ua')
            ->field('ua.*')
            ->join(['tp_region'=>'r'],'ua.region_id=r.parent_id','left')
            ->where('r.id',$region_id)
            ->find();
        if ($parent_area_agent) {
            $parentId = $parent_area_agent['user_id'];
        }
        $userAgentModel = new UserAgentModel();
        $nestedsetsModel = new NestedSets($userAgentModel);
        $res = $nestedsetsModel->insert($parentId,[
            'user_id'=>$data['user_id'],
            'be_agent_start' => $data['start_time'],
            'be_agent_end' => $data['end_time'],
            'agent_level'=>$data['agent_level'],
            'status'        => 1,
            'region_id'     => $region_id,
            'invite_partner_code' => generateInviteCode('agent')
        ]);
        if ($res) {
            //修改用户信息 cf_users
            $this->changeUserType($data, 4);
            return ['status'=>1, 'msg'=>'新增成功'];
        }
        return ['status'=>0,'msg'=>"写入数据失败"];
    }

    /**
     * 改变用户的身份 和 下级用户的 first_agent_id 或者 first_partner_id
     * @param $data
     * @param $user_type
     */
    public function changeUserType($data, $user_type){

        if ($user_type == 2) {
            Db::table('cf_users')
                ->where(['user_id'=>$data['user_id']])
                ->update(['user_type'=> ['exp',"user_type | $user_type"] ,'first_agent_id'=>$data['agent_user_id']]);
            Db::query("update  cf_users u1 inner join  cf_user_user u2 on u1.user_id = u2.user_id  left join  cf_user_partner u3 on u2.parent_id = u3.user_id
                    set u1.first_partner_id = u2.parent_id
                    where  u3.user_id > 0");
        } elseif ($user_type == 4) {
            Db::table('cf_users')
                ->where(['user_id'=>$data['user_id']])
                ->update(['user_type'=> ['exp',"user_type | $user_type"]]);
            Db::query("update  cf_users u1 inner join  cf_user_user u2 on u1.user_id = u2.user_id  left join  cf_user_agent u3 on u2.parent_id = u3.user_id
                    set u1.first_agent_id = u2.parent_id
                    where  u3.user_id > 0");
        }
//        $userUserModel = new UserUserModel();
//        $tree = $userUserModel->getTreeByUserId($data['user_id']);

        // 改变下级会员树的first_agent_id 或者 first_partner_id TODO

    }

    /**
     *
     * @param $apply
     */
    public function dealAgentAccumulate($apply){
        //获取代理商用户的信息
        $agentUserInfo = Db::table('cf_user_agent')
            ->where('user_id',$apply['agent_id'])
            ->find();
        $agentData = [];
        if ($agentUserInfo) {
            $agent_level = $agentUserInfo['agent_level'];
            if ($agent_level == 1) {
                $data['profit_type'] = 1;
                $data['profit_money'] = 90;
                $data['score'] = 0;//发展分暂为0
            } elseif($agent_level == 2) {
                $data['profit_type'] = 2;
                $data['profit_money'] = 60;
                $data['score'] = 0;//发展分暂为0
            } elseif($agent_level == 3) {
                $data['profit_type'] = 3;
                $data['profit_money'] = 60;
                $data['score'] = 0;//发展分暂为0
            }
            $data['to_user_id'] = $apply['agent_id'];
            $data['from_user_id'] = $apply['user_id'];
            $data['is_pofited'] = 1;
            $data['pofit_time'] = time();
            $data['add_time'] = time();
            Db::table('cf_profit_log')->save($data);
            agentAccountChange($agentUserInfo['user_id'], $data['profit_money']);
            $agentData[] = $data;
            unset($data);

            if ($agent_level == 2) {
                $parentAgentUser = Db::table('cf_user_agent')
                    ->where('user_id',$agentUserInfo['parent_id'])
                    ->find();
                $data['profit_type'] = 2;
                $data['profit_money'] = 30;
                $data['score'] = 0;//发展分暂为0
                $data['to_user_id'] = $parentAgentUser['user_id'];
                $data['from_user_id'] = $apply['user_id'];
                $data['is_pofited'] = 1;
                $data['pofit_time'] = time();
                $data['add_time'] = time();
                Db::table('cf_profit_log')->save($data);
                agentAccountChange($parentAgentUser['user_id'], $data['profit_money']);
                $agentData[] = $data;
                unset($data);
            }
            if ($agent_level == 3) {
                $parentAgentUser = Db::table('cf_user_agent')//区县代理
                    ->where('user_id',$agentUserInfo['parent_id'])
                    ->find();
                $data['profit_type'] = 3;
                $data['profit_money'] = 20;
                $data['score'] = 0;//发展分暂为0
                $data['to_user_id'] = $parentAgentUser['user_id'];
                $data['from_user_id'] = $apply['user_id'];
                $data['is_pofited'] = 1;
                $data['pofit_time'] = time();
                $data['add_time'] = time();
                Db::table('cf_profit_log')->save($data);
                agentAccountChange($parentAgentUser['user_id'], $data['profit_money']);
                $agentData[] = $data;
                unset($data);

                $parentParentAgentUser = Db::table('cf_user_agent')//市代理
                    ->where('user_id',$parentAgentUser['parent_id'])
                    ->find();
                $data['profit_type'] = 3;
                $data['profit_money'] = 10;
                $data['score'] = 0;//发展分暂为0
                $data['to_user_id'] = $parentParentAgentUser['user_id'];
                $data['from_user_id'] = $apply['user_id'];
                $data['is_pofited'] = 1;
                $data['pofit_time'] = time();
                $data['add_time'] = time();
                Db::table('cf_profit_log')->save($data);
                $agentData[] = $data;
                agentAccountChange($parentParentAgentUser['user_id'], $data['profit_money']);
                unset($data);
            }
        }
        return $agentData;
    }

    //获取合伙人信息
    public function getPartnerInfo($partnerId)
    {
        $userModel = new UserModel();
        //获取用户基本数据和身份
        $data = $userModel->getUserRelationIdentity($partnerId);
        $distributionLogic = new DistributionDevideLogLogic();
        //1.待返利
        $data['wait_earnings'] = round($distributionLogic->where(['to_user_id' => $partnerId,'is_divided' => 0])->sum('divide_money'),2);
        //2.已返利收益
        $data['have_earnings'] = round($data['partner_earnings'] + $data['user_earnings'] + $data['agent_earnings'],2) ?? 0;
        //3.可提现收益
        $data['can_pick_money'] = round($data['partner_earnings_residue'] + $data['agent_earnings_residue'] + $data['user_earnings_residue'],2) ?? 0;
        //5.已提现
        $data['have_pick_money'] = M('withdrawals')->where('user_id',$partnerId)->where('status',2)->sum('money');
        //4.累计收益(代表的是代理商和合伙人的收益)
        $data['total_earnings'] = round($data['agent_partner_earnings']+$data['wait_earnings'],2) ?? 0;
        return $data;
    }


    //获取等级会员总数
    /*public function getLevelChild($currentUserInfo,$level = 0,$count = false,$condition=[],$pageObj = '',$sort_order = 'b.reg_time DESC')
    {
        if ($currentUserInfo) {
            if ($level && is_numeric($level)) {
                //数据库的层级比正常理解的多一级
                $level +=1;
                $where = [
                    'a.left_key' => ['>', $currentUserInfo['left_key'] ],
                    'a.right_key' => ['<',$currentUserInfo['right_key']],
                    'a.level' => $level
                ];
            }else{
                $where = [
                    'a.left_key' => ['>', $currentUserInfo['left_key'] ],
                    'a.right_key' => ['<',$currentUserInfo['right_key']],
                ];
                if (is_array($level)) {
                    $where = array_merge($where, $level);
                }
            }
            $where = array_merge($where,$condition);

            $result = $this->table('cf_user_user')->alias('a')
                ->where($where)
                ->field('a.user_id,a.be_user_start,a.be_user_end,a.user_kpi,a.level,(a.level-'.$currentUserInfo['level'].') as relative_level,b.reg_time,b.mobile,b.head_pic,b.nickname,b.last_login,cu.user_type')
                ->join(['tp_users' => 'b'],'a.user_id = b.user_id','LEFT')
                ->join(['cf_users' => 'cu'],'a.user_id = cu.user_id','LEFT')
                ->order($sort_order.',a.left_key');

            //计数
            if ($result && $count) {
                return $result->count();
            }

            //返回结果
            if ($result) {
                return $result->limit($pageObj->firstRow ,$pageObj->listRows)
//                    ->select(false);
                    ->select()->toArray();
            }
        }
        return null;
    }*/
    public function getLevelChild($currentUserInfo,$userType,$count = false,$condition = '',$pageObj = '',$sort_order = 'b.reg_time DESC')
    {
        $field = 'u1.user_id ,b.head_pic, b.mobile , b.nickname , b.reg_time , b.last_login , 
 (SELECT count(*) from cf_user_user  where parent_id = u1.user_id) as firstUserNum ,
 (SELECT count(*) from cf_user_user  where left_key >= u1.left_key  and right_key <= u1.right_key  and `level`= u1.`level`+2 ) as secondUserNum ,
 (SELECT count(*) from cf_user_user  where left_key >= u1.left_key  and right_key <= u1.right_key  and `level`>=u1.`level`+3 ) as overSecondUserNum  ,
 (SELECT if( sum(divide_money )>0 , sum(divide_money ) , 0) from cf_distribute_divide_log  where from_user_id =u1.user_id and  to_user_id = '.$currentUserInfo->user_id.'  )  as share_money';

        if ($count) {
            $field = 'count(u1.user_id) as total_num';
        }

        if ($userType >2) {
            $judgeSymbol = '>=';
        }else{
            $judgeSymbol = '=';
        }

        $where = 'where  u1.left_key >= '.$currentUserInfo->left_key.'  and u1.right_key <= '.$currentUserInfo->right_key.'  and u1.`level`'.$judgeSymbol.' '.$currentUserInfo->level.' + '.$userType;

        if ($condition) {
            $where .= $condition;
        }

        $sql = 'SELECT '.$field.' from cf_user_user u1 INNER JOIN tp_users b  on b.user_id = u1.user_id '.$where;

        if ($sort_order) {
            $sql .= ' order by '.$sort_order;
        }

        if ($pageObj) {
            $sql .= ' limit '.$pageObj->firstRow.', '.$pageObj->listRows;
        }

        $result = Db::query($sql);

        //计数
        if ($result && $count) {
            return $result[0]['total_num'];
        }

        return $result;
    }

    //收益详情页
    public function getEarnsDetails($partnerId,$count = false,$condition = [],$pageObj = '',$sort_order = '',$totalEarns = false)
    {
        $where = [
            'to_user_id'=>$partnerId,
            'd.divide_money'=> ['<>',0],
        ];

        if ($condition){
            $where = array_merge($where,$condition);
        }

        $distributionLogic = new DistributionDevideLogLogic();






        $order_earnings_rows = $distributionLogic->alias('d')
            ->field('d.*, u.nickname, u.mobile,u.head_pic, (uu.level - uu1.level) as relative_level')
            ->join('users u', 'd.from_user_id = u.user_id','left')
            ->join(['cf_user_user'=>'uu'], 'd.from_user_id = uu.user_id','left')
            ->join(['cf_user_user'=>'uu1'], 'd.to_user_id = uu1.user_id','left')
            ->where($where)
            ->order($sort_order);

        if ($count) {
            return $order_earnings_rows->count();
        }

        if ($totalEarns) {
            return $order_earnings_rows->sum('divide_money');
        }

        return $order_earnings_rows->limit($pageObj->firstRow ,$pageObj->listRows)
            ->select()->toArray();
    }

    public function withDrawDetail($count = false,$condition = [],$pageObj = '',$sort_order = '',$totalWithdraw = false)
    {
        if ($count) {
            return M('withdrawals')
                ->where($condition)
                ->count();
        }

        if ($totalWithdraw) {
            return M('withdrawals')
                ->where($condition)
                ->where('status',2)
                ->sum('money');
        }

        return M('withdrawals')
            ->field('taxfee,pay_code,error_code',true)
            ->where($condition)
            ->limit($pageObj->firstRow ,$pageObj->listRows)
            ->order($sort_order)
            ->select();
    }
    
    
    //取消合伙人身份
    public function canclePartnerIdentity($partnerId)
    {
        $partnerInfo = $this->table('cf_user_partner')->where('user_id',$partnerId)->find()->toArray();

        $nestedsetsModel = new NestedSets((new UserPartnerModel()));
        Db::startTrans();
        $res1 = $nestedsetsModel->delete($partnerId);
        if (!$res1) {
            return false;
        }

        $res2 = $nestedsetsModel->insert(0,[
            'user_id' => $partnerId,
            'be_partner_start' => $partnerInfo['be_partner_start'],
            'be_partner_end' => $partnerInfo['be_partner_end'],
            'partner_kpi' => $partnerInfo['partner_kpi'],
            'status' => 3,
        ]);

        if ($res2) {
            Db::commit();
            return true;
        }else{
            Db::rollback();
            return false;
        }
    }


    public function getAgentEarnsDetails($partnerId,$count = false,$condition = [],$pageObj = '',$sort_order = '',$totalEarns = false)
    {
        $field = ' * ';
        if ($count) {
            $field = ' count(*) as total_num ';
        }

        if ($totalEarns) {
            $field = ' SUM(divide_money) as total_earns ';
        }

        $sql = 'select '.$field.' FROM (SELECT
	a.id,
	a.to_user_id,
	a.from_user_id,
	a.distribute_type,
	a.divider_type,
	a.divide_money,
	a.divide_ratio,
	a.is_divided,
	a.add_time,
	b.nickname,
	b.mobile,
	b.head_pic,
	0 as agent_level,
	(c.`level` - d.`level`) as level
FROM
	`cf_distribute_divide_log` a
LEFT JOIN tp_users b ON a.from_user_id = b.user_id
LEFT JOIN cf_user_user c ON a.from_user_id = c.user_id
LEFT JOIN cf_user_user d ON a.to_user_id = d.user_id
UNION ALL
SELECT
	a.id,
	a.to_user_id,
	a.from_user_id,
	a.profit_type + 100,
	0,
	a.profit_money,
	a.profit_money,
	a.is_pofited,
	a.add_time,
	b.nickname,
	b.mobile,
	b.head_pic,
	e.agent_level,
	(c.level- c.level + 0.5)
FROM
	`cf_profit_log` a
LEFT JOIN tp_users b ON a.from_user_id = b.user_id
LEFT JOIN cf_user_user c ON a.from_user_id = c.user_id
LEFT JOIN cf_user_partner d ON d.user_id = a.from_user_id
LEFT JOIN cf_user_agent e ON e.user_id = d.first_agent_id
) tmp ';

        if ($condition) {
            $sql .= $condition;
        }


        if ($sort_order) {
            $sql .= ' order by '.$sort_order;
        }



        if ($pageObj) {
            if ($totalEarns){
                $sql .= ' limit '. 0 .', '.$pageObj->listRows;
            }else{
                $sql .= ' limit '.$pageObj->firstRow.', '.$pageObj->listRows;
            }
        }

        $order_earnings_rows = Db::query($sql);
        if ($count) {
            return $order_earnings_rows[0]['total_num'];
        }

        if ($totalEarns) {
            return $order_earnings_rows[0]['total_earns'];
        }
        return $order_earnings_rows;
    }

}