<?php
/**
 * @Author: 陈静
 * @Date: 2018/04/19 17:59:40
 * @Description:
 */

namespace app\common\model;


use think\Model;
use think\Page;

class UserAgentModel extends Model
{
    protected $table = 'cf_user_agent';
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
    public function getAgentDivideDatas($result,$partnerArr)
    {
        //排序找最近的直接代理商
        $userIdentityArr = array_column($partnerArr,'user_relation','user_id');
        if (empty($userIdentityArr)) {
            return;
        }
        $firstAgentId = end($userIdentityArr)['first_agent_id'];

        //查询整条分支数据
        $parentInfoDatas = $this->getTreeByUserId($firstAgentId);

        //身份和分成比例对应
        $divideRatioConfig = tpCache('distribute','','cf_config');

        //根据身份来设置分成比例
        $agent[1][1] = $divideRatioConfig['agent_city_ratio1'];
        $agent[1][2] = $divideRatioConfig['agent_city_ratio2'];
        $agent[1][3] = $divideRatioConfig['agent_city_ratio3'];

        $agent[2][1] = $divideRatioConfig['agent_county_ratio1'];
        $agent[2][2] = $divideRatioConfig['agent_county_ratio2'];
        $agent[2][3] = $divideRatioConfig['agent_county_ratio3'];

        $agent[3][1] = $divideRatioConfig['agent_town_ratio1'];
        $agent[3][2] = $divideRatioConfig['agent_town_ratio2'];
        $agent[3][3] = $divideRatioConfig['agent_town_ratio3'];

        $i = count($parentInfoDatas);
        $arr = [];
        foreach ($parentInfoDatas as $k => $v){
            $arr[$k]['from_user_id'] = $result['user_id'];
            $arr[$k]['order_sn'] = $result['order_sn'];
            $arr[$k]['to_user_id'] = $v['user_id'];
            $arr[$k]['distribute_type'] = config('distribute')['distribute_type']['agent'];
            $arr[$k]['divide_ratio'] = $agent[$v['agent_level']][$i];
            $arr[$k]['divide_money'] = ($result['order_amount'] + $result['user_money']) * $arr[$k]['divide_ratio'] / 100;
            $arr[$k]['score'] = 0 ;
            $arr[$k]['is_divided'] = 0 ;
            $arr[$k]['add_time'] = time() ;
            if ($i == 1) {
                $arr[$k]['remarks'] = '代理商作为一级获得分成';
            }elseif ($i == 2){
                $arr[$k]['remarks'] = '代理商作为二级获得分成';
            }elseif ($i == 3){
                $arr[$k]['remarks'] = '代理商作为三级获得分成';
            }
            $i -= 1;
        }

        return $arr;
    }


    /**
     * @Author: 陈静
     * @Date: 2018/04/04 15:3:0
     * @Description: 获取整条树的数据
     */
    public function getTreeByUserId($user_id,$level = 0)
    {
        try{
            //查找当前节点信息
            $currentUserInfo = $this
                ->field('user_id,left_key,right_key,level')
                ->where('user_id','=',$user_id)
                ->find()->toArray();

            $parentInfoDatas = $this->alias('a')
                ->field('user_id,parent_id,level,agent_level')
                ->where('a.left_key','<=',$currentUserInfo['left_key'])
                ->where('a.right_key','>=',$currentUserInfo['right_key'])
                ->order('a.left_key');

            if ($level) {
                $parentInfoDatas = $parentInfoDatas->where('a.level',$level)->select()->toArray();
            }else{
                $parentInfoDatas = $parentInfoDatas->select()->toArray();
            }
        }catch (\Throwable $exception){
            $parentInfoDatas = [];
        }

        return $parentInfoDatas;
    }


    /*
     * @Author :赵磊
     * 2.3.2代管代理商
     * */
    public function manageAgent($userId,$userLevel)
    {
        $page = I('get.p',1);
            $condition1['parent_id'] = $userId;
            $condition1['agent_level'] = $userLevel + 1;
            $condition1['status'] = 1;
            $res1 = $this->where($condition1)->select();//市
            $res1 = $res1->toArray();
            $user1 = array_column($res1,'user_id');
            $condition2['parent_id'] = ['in',$user1];
            $condition2['agent_level'] = $userLevel + 2;
            $condition2['status'] = 1;
            $res2 = $this->where($condition2)->select()->toArray();//区
            $user2 = array_column($res2,'user_id');
            $condition3['parent_id'] = ['in',$user2];
            $condition3['agent_level'] = $userLevel + 3;
            $condition3['status'] = 1;
            $res3 = $this->where($condition3)->select()->toArray();//县
            $res = array_merge($res1,$res2,$res3);

        $userModel = new Users();
        for ($i=0;$i<count($res);$i++){
            $res[$i]['user'] = $userModel->getHeadpic($res[$i]['user_id']);
            $res[$i]['user']['mobile'] = phoneToStar($res[$i]['user']['mobile']);
            $res[$i]['nickname'] = $res[$i]['user']['nickname'];
            $res[$i]['head_pic'] = $res[$i]['user']['head_pic'];
            $res[$i]['mobile'] = $res[$i]['user']['mobile'];
            $res[$i]['reg_time'] = $res[$i]['user']['reg_time'];
        }
        if ($page && !defined('NO_P') && !empty($res)) {
            $counts = count($res);
            $pageObj = new Page($counts,20);
            $res = array_slice($res,$pageObj->firstRow ,20);
        }
        if (!empty($res)){
            $reg_time = array_column($res,'reg_time');
            array_multisort($reg_time, SORT_DESC, $res);
        }
        return $res;
    }

}