<?php
/**
 * 代理商分成
 */

namespace app\common\logic\profit;


use think\Model;

class ProfitLogLogic extends Model
{
    protected $table = 'cf_profit_log';
    protected $resultSetType = 'collection';

    // 发展合伙人收益
    public function getMemberEarnings($user_id){
        $p = input('get.p',1);
        $rows = $this->alias('pl')
            ->field('pl.*, u.nickname, u.mobile,tu.head_pic, uu.level as from_user_level,uu1.level as to_user_level, (uu.level - uu1.level) as relative_level')
            ->join('users u', 'pl.from_user_id = u.user_id','left')
            ->join(['tp_users'=>'tu'], 'pl.from_user_id = tu.user_id','left')
            ->join(['cf_user_user'=>'uu'], 'pl.from_user_id = uu.user_id','left')
            ->join(['cf_user_user'=>'uu1'], 'pl.to_user_id = uu1.user_id','left')
            ->where([
                'to_user_id'=>$user_id,
                'pl.profit_money' => ['<>',0]
            ])
            ->order('add_time', 'DESC')
            ->page($p, 12)
            ->select()->toArray();
        return $rows;
    }

    //获取用户某段时间的收益
    public function getUserOrderEarnsByFewTime($user_id , $everyDayOrderEarns)
    {
        $data = [];
        foreach ($everyDayOrderEarns as $k => $v){
            $data[$k] = $this->getUserTotalProfitEarnings($user_id,$k,$k + 24 * 3600 -1);
        }

        return $data;
    }

    //获取用户累计收益
    public function getUserTotalProfitEarnings($user_id, $start_time = 0, $end_time = 0): float
    {
        $where = [
            'to_user_id' => $user_id,
        ];
        if ($end_time) {
            $where['add_time'] = ['between', [$start_time, $end_time]];
        }
        $result = $this
            ->where($where)->sum('profit_money');

        return $result ?? 0 ;
    }

    public function getUserTotalProfitEarningsByTime($user_id, $start_time = 0, $end_time = 0)
    {
        $where = [
            'to_user_id' => $user_id,
        ];
        if ($end_time) {
            $where['add_time'] = ['between', [$start_time, $end_time]];
        }

        $data = [];
        $this->field('id,profit_money,add_time')
            ->where($where)
            ->chunk(100, function ($users) use (&$data) {
                $data = array_merge($data,collection($users)->toArray());
            }, 'id', 'desc');
        return $data;
    }
}