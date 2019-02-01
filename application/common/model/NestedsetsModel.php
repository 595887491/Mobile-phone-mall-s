<?php
/**
 * @Author: 陈静
 * @Date: 2018/04/24 15:07:48
 * @Description:
 */

namespace app\common\model;


use think\Model;

class NestedsetsModel extends Model
{
    /**
     * @Author: 陈静
     * @Date: 2018/04/04 15:3:0
     * @Description: 获取整条树的数据
     */
    public function getTreeByUserId($user_id)
    {
        try{
            //查找当前节点信息
            $currentUserInfo = $this->getCurrentUserInfo($user_id)->toArray();

            //查找整条链接的所有父节点
            $parentInfoDatas = $this
                ->field('user_id,parent_id,level')
                ->where('left_key','<=',$currentUserInfo['left_key'])
                ->where('right_key','>=',$currentUserInfo['right_key'])
                ->whereOr(function ($query)use($currentUserInfo){
                    $query->where('left_key','>=',$currentUserInfo['left_key'])
                        ->where('right_key','<=',$currentUserInfo['right_key']);
                })
                ->order('left_key')
                ->select()->toArray();
        }catch (\Throwable $exception){
            //记录日志
            $parentInfoDatas = [];
            \app\common\library\Logs::sentryLogs($exception);
        }

        return $parentInfoDatas;
    }

    public function getCurrentUserInfo($user_id)
    {
        $currentUserInfo = $this
            ->field('user_id,left_key,right_key,level')
            ->where('user_id','=',$user_id)
            ->find();

        if ($currentUserInfo) {
            return $currentUserInfo;
        }
        return false;

    }

}