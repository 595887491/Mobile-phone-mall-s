<?php
/**
 * @Author: 陈静
 * @Date: 2018/04/19 16:49:02
 * @Description:
 */

namespace app\common\model;


use gmars\nestedsets\NestedSets;
use think\Db;
use think\Page;

class UserUserModel extends NestedsetsModel
{
    protected $table = 'cf_user_user';
    protected $pk = 'user_id';
    protected $resultSetType = 'collection';

    //nested配置
    public $nestedConfig = [
        'primaryKey' => 'user_id',
        'leftKey' => 'left_key',
        'rightKey' => 'right_key',
        'levelKey' => 'level',
        'parentKey' => 'parent_id',
    ];

    //查找用户partner角色
    public function partnerRelation()
    {
        return $this->hasOne('UserPartnerModel','user_id','user_id')
            ->field('user_id,level,partner_kpi,first_agent_id');
    }

    //查找用户agent角色
    public function agentRelation()
    {
        return $this->hasOne('UserAgentModel','user_id','user_id')
            ->field('user_id,level,agent_level');
    }

    //查找用户user角色
    public function userRelation()
    {
        return $this->hasOne('UserModel','user_id','user_id')
            ->field('user_id,first_partner_id,first_agent_id');
    }

    //查找tpshop原表信息
    //查找用户user角色
    public function originUserRelation()
    {
        return $this->hasOne('Users','user_id','user_id')
            ->field('user_id,reg_time,mobile,head_pic,nickname');
    }

    /**
     * @Author: 陈静
     * @Date: 2018/04/04 15:3:0
     * @Description: 获取整条树的数据
     */
    public function  getTreeByUserId($user_id)
    {
        try{
            //查找当前节点信息
            $currentUserInfo = $this->getCurrentUserInfo($user_id)->toArray();

            //查找整条链接的所有父节点
            $parentInfoDatas = $this
                ->with(['userRelation','partnerRelation','agentRelation'])
                ->field('user_id,parent_id,level')
                ->where('left_key','<=',$currentUserInfo['left_key'])
                ->where('right_key','>=',$currentUserInfo['right_key'])
                ->order('left_key')
                ->select()->toArray();
        }catch (\Throwable $exception){
            //记录日志
            $parentInfoDatas = [];
            \app\common\library\Logs::sentryLogs($exception);
        }

        return $parentInfoDatas;
    }

    //获取总的会员数
    public function getUserChild($user_id,$is_count = false)
    {
        $nestedObj = new NestedSets($this);
        $result = $nestedObj->getBranch($user_id);
        if ($is_count) {
            return count($result);
        }
        return $result;
    }

    //获取等级会员总数
    public function getLevelChild($user_id,$level = 0,$count = false)
    {
        $page = I('get.p',1);
        //查找当前节点信息
        $currentUserInfo = $this->getCurrentUserInfo($user_id);

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

            //层级大于4，返回缤纷会员（3级以后的所有会员）
            if ($level >= 4 && is_numeric($level)) {
                return $this->getOverTwoLevelMembers($user_id);
            }

            $result = $this->alias('a')
                ->where($where)
                ->field('a.user_id,a.be_user_start,a.be_user_end,a.user_kpi,(a.level-'.$currentUserInfo['level'].') as relative_level,b.reg_time,b.mobile,b.head_pic,b.nickname,cu.user_type')
                ->join(['tp_users' => 'b'],'a.user_id = b.user_id','LEFT')
                ->join(['cf_users' => 'cu'],'a.user_id = cu.user_id','LEFT')
                ->order('b.reg_time DESC,a.left_key');

            //分页
            if ($page && !defined('NO_P')) {
                $counts = (clone $result)->count();
                $pageObj = new Page($counts,10);
                $result->limit($pageObj->firstRow ,10);
            }

            //计数
            if ($result && $count) {
                return $result->count();
            }

            //返回结果
            if ($result) {
                return $result->select()->toArray();
            }
        }
        return null;
    }

    //获取缤纷会员列表
    public function getOverTwoLevelMembers($user_id)
    {
        $page = I('get.p',0);

        $currentUserInfo = $this->getCurrentUserInfo($user_id);

        if ($currentUserInfo) {
            //先获取第二级会员的列表
            $where = [
                'left_key' => ['>', $currentUserInfo['left_key'] ],
                'right_key' => ['<',$currentUserInfo['right_key']],
                'level' => 3
            ];

            $twoLevelUsers = $this->where($where)
                ->field('user_id,left_key,right_key,be_user_start,be_user_end,user_kpi')
                ->order('left_key')->select()->toArray();

            //查找缤纷会员
            foreach ($twoLevelUsers as $v) {
                $where = [
                    'a.left_key' => ['>', $v['left_key'] ],
                    'a.right_key' => ['<',$v['right_key']],
                ];
                $this->whereOr(function ($query) use ($where){
                    $query->where($where);
                });
            }

            $memberObj = $this->alias('a')->field('a.user_id,a.be_user_start,a.be_user_end,a.user_kpi,b.reg_time,b.mobile,b.head_pic,b.nickname')
                ->join(['tp_users' => 'b'],'a.user_id = b.user_id','LEFT')
                ->order('b.reg_time DESC,a.left_key');

            //分页
            if ($page) {
                $counts = (clone $memberObj)->count();
                $pageObj = new Page($counts,10);
                $memberObj->limit($pageObj->firstRow ,10);
            }

            $overTwoLevelMembers = $memberObj->select();

            if ($overTwoLevelMembers) {
                return $overTwoLevelMembers->toArray();
            }
            return false;
        }
    }
    //得到用户层级
    public function getUserLevel($user_id){
        return $this->where(['user_id'=>$user_id])->getField('level');
    }

    //得到用户的第一位以及会员的信息
    public function getFirstLevelUser($user_id){
        $result = $this->alias('a')
            ->join(['tp_users' => 'b'],'a.user_id = b.user_id','LEFT')
            ->field('b.user_id,b.reg_time,b.mobile,b.head_pic,b.nickname')
            ->order('b.reg_time ASC')
            ->where(['a.parent_id'=>$user_id])
            ->find();
        //返回结果
        if ($result) {
            return $result->toArray();
        }
        return [];
    }


    //获取用户下级会员订单总额
    public function getUserChildTotalOrderAmount($user_id)
    {
        $userUserModel = new UserUserModel();
        $firstChild = $userUserModel->getLevelChild($user_id,1);
        $secondChild = $userUserModel->getLevelChild($user_id,2);

        $childUserIdArr = array_column(array_merge($firstChild,$secondChild),'user_id');

        //查询订单（不含支付后取消，发起退款或退货的订单）
        $orderModel = new Order();

        //已完成
        $where1 = [
            'user_id' => ['in',$childUserIdArr],
            'prom_type' => ['<',5],
            'order_status' => 4
        ];

        //待收货
        $where2 = [
            'user_id' => ['in',$childUserIdArr],
            'prom_type' => ['<',5],
            'order_status' => 1 ,
            'shipping_status' => 1
        ];

        //待发货
        $where3 = [
            'user_id' => ['in',$childUserIdArr],
            'prom_type' => ['<',5],
            'order_status' => ['in',[0,1]] ,
            'shipping_status' => ['neq',1] ,
            'pay_status' => 1 ,
        ];

        $totalAmount = $orderModel
            ->whereOr(function ($query) use ($where1){
                $query->where($where1);
            })
            ->whereOr(function ($query) use ($where2){
                $query->where($where2);
            })
            ->whereOr(function ($query) use ($where3){
                $query->where($where3);
            })
            ->sum('total_amount');

        return $totalAmount;
    }

    public function getUserInfo($user_id){
        $userInfo = $this->alias('uu')
            ->field('uu.*,cu.user_type')
            ->join(['cf_users'=>'cu'],'cu.user_id', 'left')
            ->where(['cu.user_id'=>$user_id])
            ->find();
        if (empty($userInfo)) {
            return [];
        } else {
            return $userInfo->toArray();
        }
    }

}