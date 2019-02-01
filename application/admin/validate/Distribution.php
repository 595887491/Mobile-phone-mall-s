<?php
namespace app\admin\validate;
use think\Validate;
use think\Db;
class Distribution extends Validate
{
    // 验证规则
    protected $rule = [
        ['agent_user_id','require|checkId'],
        ['user_id', 'require|checkId'],
        ['start_time','require'],
        ['end_time','require|checkEndTime'],
        ['agent_level','require|number|checkAgentLevel'],
        ['title','require'],
        ['cover','require'],
        ['init_reward','require|number'],
        ['sale_reward_scale','require|maxScale'],
        ['reward_type','require|number'],
        ['reward_num','require|number'],
        ['reward_scale','require|validRewardScale'],
        ['province_id','require'],
        ['city_id','require'],
    ];
    //错误信息
    protected $message  = [
        'agent_user_id.require'         => '必须选择一个代理商',
        'agent_user_id.checkId'         => '必须选择的用户不存在',
        'user_id.require'         => '必须选择一个用户',
        'user_id.checkId'         => '必须选择的用户不存在',
        'start_time.require'    => '请选择开始时间',
        'end_time.require'      => '请选择结束时间',
        'end_time.checkEndTime' => '结束时间不能早于开始时间',
        'agent_level.require'   =>'请选择代理商类型',
        'agent_level.number'   =>'代理商类型不正确',
        'title.require'         =>'请填写活动标题',
        'cover.require'         =>'请选择一张封面图',
        'init_reward.require'         =>'请填写初始奖池',
        'sale_reward_scale.require'         =>'奖金占销售额的比例不能为空',
        'reward_type.require'         =>'请填写派奖方式',
        'reward_num.require'         =>'请填写奖励人数',
        'reward_scale.require'         =>'请填写瓜分比例',
        'city_id.require'         =>'请选择地区',
    ];
    //场景
    protected $scene = [
        'partner'   =>  ['agent_user_id','user_id','start_time','end_time'],//新增合伙人
        'agent'  =>  ['user_id','start_time','end_time','agent_level','city_id'],//新增代理商
        'rankActivity'=>['title','cover','start_time','end_time','init_reward','sale_reward_scale','reward_type','reward_num','reward_scale'],//合伙人打榜活动
    ];
    /**
     * 检查结束时间
     * @param $value|验证数据
     * @param $rule|验证规则
     * @param $data|全部数据
     * @return bool|string
     */
    protected function checkEndTime($value, $rule ,$data)
    {
        if ($this->currentScene == 'rankActivity') {
            if ($value < time()) {
                return '活动时间不能为过去'.date('Y-m-d H:i:s',$value);
            }
            $isHave = Db::table('cf_partner_rank_activity')->where(['end_time'=>['>',$data['start_time']],'start_time'=>['<',$data['end_time']]])
                ->where(function ($query) use ($data) {
                    if (isset($data['id']) && $data['id']>0) {
                        $query->where('id',['<>',$data['id']]);
                    }
                })
                ->find();
            if ($isHave) {
                return '活动时间与已有的活动时间有重合';
            }
        }
        return ($value < $data['start_time']) ? false : true;
    }

    /**
     * 检查用户是否存在
     * @param $value
     * @param $rule
     * @param $data
     * @return bool|string
     */
    protected function checkId($value, $rule ,$data)
    {
        $isHave = Db::name('users')->where(['user_id'=>$value])->find();
        if($isHave){
            return true;
        }else{
            return '该用户不存在';
        }
    }
    //打榜活动瓜分比例
    protected function validRewardScale($value, $rule ,$data) {
        $value = str_replace('，',',',$value);//中文逗号换成英文逗号
        $scale = explode(',',$value);
        if (count($scale) != $data['reward_num']) {
            return '奖励人数和瓜分比例数量不一致';
        }
        foreach ($scale as $v){
            if (!is_numeric($v) || $v <=0) {
                return '瓜分比例必须为正数';
            }
        }
        if (array_sum($scale) != 100) {
            return '瓜分比例之和不等于 100';
        }
        return true;
    }
    protected function maxScale($value, $rule ,$data){
        if ($value > 100) {
            return '奖金金额占销售额比例大于100';
        }
        return true;
    }

    protected function checkAgentLevel($value, $rule ,$data){
        if ($value == 1 && empty($data['city_id'])){
            return '市代理需选择一个市级地区';
        } elseif ($value == 2 && empty($data['area_id'])) {
            return '区代理需选择一个区级地区';
        } elseif ($value == 3 && empty($data['town_id'])) {
            return '街道办代理需选择一个街道办地区';
        } elseif ($value == 4 && empty($data['school_id'])) {
            return '校园代理需选择一个学校';
        }
        //检测当前地理位置是否已有代理商
        $array = [
            '1'=>$data['city_id']??0,
            '2'=>$data['area_id']??0,
            '3'=>$data['town_id']??0,
            '4'=>$data['school_id']??0,
        ];
        $region_id = $array[$value];
        $isHaveData = Db::table('cf_user_agent')->where('region_id',$region_id)->find();
        if ($isHaveData) {
            return '该地区已经有一个代理商';
        }
        //父级区域是否有代理商, 如添加武侯区代理商，要检查是否有成都市代理商
        $parent_area_agent = Db::table('cf_user_agent ua')
            ->field('ua.*')
            ->join(['tp_region'=>'r'],'ua.region_id=r.parent_id','left')
            ->where('r.id',$region_id)
            ->find();
        if (empty($parent_area_agent) && $value > 1) {
            return '没有父级区域代理商';
        }
        return true;
    }
}