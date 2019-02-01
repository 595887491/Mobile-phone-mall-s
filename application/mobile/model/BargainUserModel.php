<?php
/**
 * @Author: 陈静
 * @Date: 2018/08/29 10:06:10
 * @Description:
 */

namespace app\mobile\model;


use think\Db;
use think\Model;

class BargainUserModel extends Model
{
    protected $table = 'cf_bargain_user';
    protected $resultSetType = 'collection';

    public function getSuccessUser($activityId)
    {
        $foundSuccessUser = (new BargainFoundModel())->alias('a')
            ->distinct('b.user_id')
            ->field('b.nickname,b.head_pic')
            ->join('users b','a.user_id = b.user_id')
            ->where('a.status',2)
            ->where('a.bargain_ok_time','>',0)
            ->select()->toArray();

        $virtualSuccessUserId = $this->where('bargain_id',$activityId)->getField('user_id');
        $virtualSuccessUser = Db::table('cf_bargain_user_default')
            ->field('nickname,head_pic')
            ->where('user_id' , 'in' , $virtualSuccessUserId)
            ->limit(20 - count($foundSuccessUser))
            ->select();
        $successUser = array_merge($foundSuccessUser,$virtualSuccessUser);

        foreach ($successUser as &$v) {
            if (is_mobile($v['nickname'])) {
                $v['nickname'] =  phoneToStar($v['nickname']);
            }
        }
        return $successUser;
    }
}