<?php
/**
 * @Author: 陈静
 * @Date: 2018/04/19 14:34:25
 * @Description:
 */

namespace app\common\model;

use app\common\logic\WechatLogic;
use think\Db;
use think\Exception;
use think\Model;

class DistributeDivideLog extends Model
{
    protected $table = 'cf_distribute_divide_log';
    protected $resultSetType = 'collection';

    //取消分成
    public function cancleDivideData($order_sn)
    {
        $res = $this->where('order_sn',$order_sn)->where('is_divided',0)->delete();
    }

    /**
     * @Author: 陈静
     * @Date: 2018/04/03 21:54:13
     * @Description: 生成待分成数据
     */
    public function createWaitDistribut($result)
    {
        //获取订单信息
        $orderInfo = (new Order())->getOrderInfoByTradeNo($result['out_trade_no']);

        if (empty($orderInfo)|| ( empty($orderInfo['order_amount'])) && empty($orderInfo['user_money']) ) {
            return;
        }

        //获取此订单的父级,获取分成用户数据
        $data = $this->getDivideDatas($orderInfo);

        if ($data) {
            //插入数据
            try{
                $this->saveAll($data);

                //发送模板消息
                foreach ($data as $v){
                    if ( round($v['divide_money'],2) > 0 && ($v['distribute_type'] == 2 || $v['distribute_type'] == 4) ) {
                        $res = (new WechatLogic())->sendTemplateMsgOnDistribute($v);
                        if ($res['status'] != 1) {
                            \app\common\library\Logs::sentryLogs('发送收益微信模板消息失败:'.$res['msg'],$data);
                        }
                    }
                }
            }catch (Exception $e){
                //记录日志
                \app\common\library\Logs::sentryLogs($e,['msg' => '插入分成数据失败']);
            }
        }
    }

    /**
     * @Author: 陈静
     * @Date: 2018/04/20 11:35:14
     * @Description: 获取待分成数据
     * @param $result
     */
    public function getDivideDatas($result)
    {
        //查找顶级父节点
        $parentInfoDatas = (new UserUserModel())->getTreeByUserId($result['user_id']);

        //根据购买者自身用户信息，确定分成给哪些代理商
        $returnDatas = $this->judgeUserDivide($parentInfoDatas);

        //删除购买者自身信息，开始分成
        $selfUserInfo = array_pop($parentInfoDatas);

        $data = [];

        foreach (array_reverse($parentInfoDatas) as $v) {
            $relativeLevel = $selfUserInfo['level'] - $v['level'];
            if ($relativeLevel <= 2 ) {
                //判断用户当前身份
                $identity = $this->judgeUserIdentity($v,$relativeLevel);
                $parentData = $this->generateDivideData($result,$identity);
                if ($parentData) {
                    $data[] = $parentData;
                }
            }
        }

        /*分成按照分成比例高的分成
         * $func = function($returnDatas,$divideData,$result){
            foreach ($returnDatas as $v){
                $divideToUserIdArr = array_column($divideData,'to_user_id');
                foreach ($v as $vv) {
                    dump($vv);
                    $vv['identity_name'] = config('distribute')['distribute_type']['agent'];
//                    if (in_array($vv['agent_id'],$divideToUserIdArr)) {
//                        $index = array_search($vv['agent_id'],$divideToUserIdArr);
//                        //比较分成比例，按照比例最高的分成
//                        if ($vv['divide_ratio'] > $divideData[$index]['divide_ratio'] ) {
//                            $divideData[$index]['divide_ratio'] = $vv['divide_ratio'];
//                            $divideData[$index]['divide_money'] = $vv['divide_ratio'] * ($result['order_amount'] + $result['user_money'] - $result['shipping_price'])/ 100;
//                            $divideData[$index]['divider_type'] = $vv['divider_type'];
//                            $divideData[$index]['remarks'] = $vv['remarks'];
//                        }
//                    }else{
                    $divideData[] = $this->generateDivideData($result,$vv);
//                    }
                }
            }
            return $divideData;
        };*/

        //代理商获得不同的三个身份的分成（代管代理商，代管代理商合伙人，代管代理商合伙人的会员）叠加分成
        $func = function($returnDatas,$divideData,$result){
            foreach ($returnDatas as $v){
                foreach ($v as $vv) {
                    $vv['identity_name'] = config('distribute')['distribute_type']['agent'];
                    $divideData[] = $this->generateDivideData($result,$vv);
                }
            }
            return $divideData;
        };

        $data = $func($returnDatas,$data,$result);

        return $data;
    }

    //判断购买者将要分成给哪些人
    public function judgeUserDivide($treeInfo)
    {
        $selfUserInfo = array_pop($treeInfo);
        $selfUserAgentInfo = $selfUserInfo['agent_relation'];
        $selfUserPartnerInfo = $selfUserInfo['partner_relation'];
        $selfUserUserInfo = $selfUserInfo['user_relation'];

        $divideRatioConfig = tpCache('distribute','','cf_config');

        $returnData = [];

        //（2）作为直营合伙人的会员（不是合伙人，也不是代理商）
        if ($selfUserUserInfo['first_partner_id'] && empty($selfUserPartnerInfo) && empty($selfUserAgentInfo) ) {
            //先查询出是哪个合伙人的下级会员再查询代理商表
            $partnerAgentId = (new UserModel())->where('user_id',$selfUserUserInfo['user_id'])->getField('first_agent_id');
            $parentAgentInfo = (new UserAgentModel())->getTreeByUserId($partnerAgentId);
            switch (count($parentAgentInfo)) {
                case 1:
                    $returnData['user'] = [
                        //"市代"获得"直营合伙人会员"订单分成的比例
                        [
                            'agent_id' => $parentAgentInfo[0]['user_id'],
                            'agent_level' => $parentAgentInfo[0]['agent_level'],
                            'divide_ratio' => $divideRatioConfig['agent_city_ratio8'],
                            'divider_type' => config('distribute')['divider_type']['direct_partner_member'],
                            'remarks' => '直营合伙人的会员返利'.$divideRatioConfig['agent_city_ratio8'].'%',
                            'comment' => '"市代"获得"直营合伙人会员"订单分成的比例'
                        ],
                    ];
                    break;
                case 2://代管区县代理商直营合伙人会员
                    $returnData['user'] = [
                        //"市代"获得代管"区/县代直营合伙人会员"订单分成的比例
                        [
                            'agent_id' => $parentAgentInfo[0]['user_id'],
                            'agent_level' => $parentAgentInfo[0]['agent_level'],
                            'divide_ratio' => $divideRatioConfig['agent_city_ratio9'],
                            'divider_type' => config('distribute')['divider_type']['county_partner_member'],
                            'remarks' => '代管区县会员返利'.$divideRatioConfig['agent_city_ratio9'].'%',
                            'comment' => '"市代"获得代管"区/县代直营合伙人会员"订单分成的比例'
                        ],
                        //"区/县代"获得"直营合伙人会员"订单分成的比例
                        [
                            'agent_id' => $parentAgentInfo[1]['user_id'],
                            'agent_level' => $parentAgentInfo[1]['agent_level'],
                            'divide_ratio' => $divideRatioConfig['agent_county_ratio8'],
                            'divider_type' => config('distribute')['divider_type']['direct_partner_member'],
                            'remarks' => '直营合伙人的会员返利'.$divideRatioConfig['agent_county_ratio8'].'%',
                            'comment' => '"区/县代"获得"直营合伙人会员"订单分成的比例'
                        ],
                    ];
                    break;
                case 3://代管镇/街道代理商直营合伙人会员
                    $returnData['user'] = [
                        //"市代"获得代管"镇/办事处代直营合伙人会员"订单分成的比例
                        [
                            'agent_id' => $parentAgentInfo[0]['user_id'],
                            'agent_level' => $parentAgentInfo[0]['agent_level'],
                            'divide_ratio' => $divideRatioConfig['agent_city_ratio10'], //市代理商获得代管镇/街道代理商直营合伙人会员会员订单分成比例
                            'divider_type' => config('distribute')['divider_type']['town_partner_member'],
                            'remarks' => '代管镇/街道办会员返利'.$divideRatioConfig['agent_city_ratio10'].'%',
                            'comment' => '"市代"获得代管"镇/办事处代直营合伙人会员"订单分成的比例'
                        ],
                        //"区/县代"获得代管"镇/办事处代直营合伙人会员"订单分成的比例
                        [
                            'agent_id' => $parentAgentInfo[1]['user_id'],
                            'agent_level' => $parentAgentInfo[1]['agent_level'],
                            'divide_ratio' => $divideRatioConfig['agent_county_ratio7'], //区县代理商获得代管镇/街道代理商直营合伙人会员订单分成的比例
                            'divider_type' => config('distribute')['divider_type']['town_partner_member'],
                            'remarks' => '代管镇/街道办会员返利'.$divideRatioConfig['agent_county_ratio7'].'%',
                            'comment' => '"区/县代"获得代管"镇/办事处代直营合伙人会员"订单分成的比例'
                        ],
                        //"镇/办事处代"获得"直营合伙人会员"订单分成的比例
                        [
                            'agent_id' => $parentAgentInfo[2]['user_id'],
                            'agent_level' => $parentAgentInfo[2]['agent_level'],
                            'divide_ratio' => $divideRatioConfig['agent_town_ratio6'], //区县代理商获得代管镇/街道代理商直营合伙人会员订单分成的比例
                            'divider_type' => config('distribute')['divider_type']['direct_partner_member'],
                            'remarks' => '直营合伙人的会员返利'.$divideRatioConfig['agent_town_ratio6'].'%',
                            'comment' => '"镇/办事处代"获得"直营合伙人会员"订单分成的比例'
                        ]
                    ];
                    break;
            }
        }else{
            //1.判断购买用户是否具有代理商身份
            if ($selfUserAgentInfo) {
                //1.1判断代理商是具有哪几种身份（市代理，代管区县代理，代管镇/街道办代理）
                $parentAgentInfo = (new UserAgentModel())->getTreeByUserId($selfUserAgentInfo['user_id']);
                array_pop($parentAgentInfo);

                switch (count($parentAgentInfo)) {
                    case 1:
                        $returnData['agent'] = [
                            //"市代"获得代管"区县"代订单分成的比例
                            [
                                'agent_id' => $parentAgentInfo[0]['user_id'],
                                'agent_level' => $parentAgentInfo[0]['agent_level'],
                                'divide_ratio' => $divideRatioConfig['agent_city_ratio2'],
                                'divider_type' => config('distribute')['divider_type']['county_agent'],
                                'remarks' => '代管区县会员返利'.$divideRatioConfig['agent_city_ratio2'].'%',
                                'comment' => '"市代"获得代管"区县"代订单分成的比例'
                            ],
                        ];
                        break;
                    case 2:
                        $returnData['agent'] = [
                            //"市代"获得代管"镇/街道"代订单分成的比例
                            [
                                'agent_id' => $parentAgentInfo[0]['user_id'],
                                'agent_level' => $parentAgentInfo[0]['agent_level'],
                                'divide_ratio' => $divideRatioConfig['agent_city_ratio3'],
                                'divider_type' => config('distribute')['divider_type']['town_agent'],
                                'remarks' => '代管镇/街道办会员返利'.$divideRatioConfig['agent_city_ratio3'].'%',
                                'comment' => '"市代"获得代管"镇/街道"代订单分成的比例'
                            ],
                            //"区/县代"获得代管"镇/街道"代订单分成的比例
                            [
                                'agent_id' => $parentAgentInfo[1]['user_id'],
                                'agent_level' => $parentAgentInfo[1]['agent_level'],
                                'divide_ratio' => $divideRatioConfig['agent_county_ratio2'],
                                'divider_type' => config('distribute')['divider_type']['town_agent'],
                                'remarks' => '代管镇/街道办会员返利'.$divideRatioConfig['agent_county_ratio2'].'%',
                                'comment' => '"区/县代"获得代管"镇/街道"代订单分成的比例'
                            ],
                        ];
                        break;
                }
            }

            //2..判断购买用户是否具有合伙人身份
            if ($selfUserPartnerInfo) {
                //2.1判断合伙人是具有哪几种合伙人身份（直营合伙人，代管区县代理直营合伙人，代管镇/街道代理直营合伙人）
                //判断上级代理商的身份
                $parentAgentInfo = (new UserAgentModel())->getTreeByUserId($selfUserPartnerInfo['first_agent_id']);
                switch (count($parentAgentInfo)) {
                    case 1:
                        $returnData['partner'] = [
                            //"市代"获得"直营合伙人"订单分成的比例
                            [
                                'agent_id' => $parentAgentInfo[0]['user_id'],
                                'agent_level' => $parentAgentInfo[0]['agent_level'],
                                'divide_ratio' => $divideRatioConfig['agent_city_ratio1'],
                                'divider_type' => config('distribute')['divider_type']['direct_partner'],
                                'remarks' => '直营合伙人会员返利'.$divideRatioConfig['agent_city_ratio1'].'%',
                                'comment' => '"市代"获得"直营合伙人"订单分成的比例'
                            ]
                        ];
                        break;
                    case 2:
                        $returnData['partner'] = [
                            //"市代"获得代管"区/县代直营合伙人"订单分成的比例
                            [
                                'agent_id' => $parentAgentInfo[0]['user_id'],
                                'agent_level' => $parentAgentInfo[0]['agent_level'],
                                'divide_ratio' => $divideRatioConfig['agent_city_ratio6'],
                                'divider_type' => config('distribute')['divider_type']['county_partner'],
                                'remarks' => '代管区县会员返利'.$divideRatioConfig['agent_city_ratio6'].'%',
                                'comment' => '"市代"获得代管"区/县代直营合伙人"订单分成的比例'
                            ],
                            //"区/县代"获得"直营合伙人"订单分成的比例
                            [
                                'agent_id' => $parentAgentInfo[1]['user_id'],
                                'agent_level' => $parentAgentInfo[1]['agent_level'],
                                'divide_ratio' => $divideRatioConfig['agent_county_ratio1'],
                                'divider_type' => config('distribute')['divider_type']['direct_partner'],
                                'remarks' => '直营合伙人会员返利'.$divideRatioConfig['agent_county_ratio1'].'%',
                                'comment' => '"区/县代"获得"直营合伙人"订单分成的比例'
                            ]
                        ];
                        break;
                    case 3:
                        $returnData['partner'] = [
                            //"市代"获得代管"镇/办事处代直营合伙人"订单分成的比例
                            [
                                'agent_id' => $parentAgentInfo[0]['user_id'],
                                'agent_level' => $parentAgentInfo[0]['agent_level'],
                                'divide_ratio' => $divideRatioConfig['agent_city_ratio7'],
                                'divider_type' => config('distribute')['divider_type']['town_partner'],
                                'remarks' => '代管镇/街道办会员返利'.$divideRatioConfig['agent_city_ratio7'].'%',
                                'comment' => '"市代"获得代管"镇/办事处代直营合伙人"订单分成的比例'
                            ],
                            //"区/县代获得代管"镇/办事处代直营合伙人"订单分成的比例
                            [
                                'agent_id' => $parentAgentInfo[1]['user_id'],
                                'agent_level' => $parentAgentInfo[1]['agent_level'],
                                'divide_ratio' => $divideRatioConfig['agent_county_ratio6'], //区县代理商获得代管镇/街道代理直营合伙人订单分成的比例
                                'divider_type' => config('distribute')['divider_type']['town_partner'],
                                'remarks' => '代管镇/街道办会员返利'.$divideRatioConfig['agent_county_ratio6'].'%',
                                'comment' => '"区/县代获得代管"镇/办事处代直营合伙人"订单分成的比例'
                            ],
                            //"镇/办事处代"获得"直营合伙人"订单分成的比例
                            [
                                'agent_id' => $parentAgentInfo[2]['user_id'],
                                'agent_level' => $parentAgentInfo[2]['agent_level'],
                                'divide_ratio' => $divideRatioConfig['agent_town_ratio1'], //区县代理商获得代管镇/街道代理直营合伙人订单分成的比例
                                'divider_type' => config('distribute')['divider_type']['direct_partner'],
                                'remarks' => '直营合伙人会员返利'.$divideRatioConfig['agent_town_ratio1'].'%',
                                'comment' => '"镇/办事处代"获得"直营合伙人"订单分成的比例'
                            ],
                        ];
                        break;
                }
            }
        }

        return $returnData;
    }

    /**
     * @Author: 陈静
     * @Date: 2018/04/08 13:27:02
     * @Description: 判断当前用户身份
     * @return array 传入数组用户的身份
     */
    public function judgeUserIdentity($user_info,$relativeLevel)
    {
        $identity['user_id'] = $user_info['user_id'];

        if ($relativeLevel == 1) {
            $identity['remarks'] = '一级会员返利';
            $identity['level'] = 1;
            $identity['divider_type'] = config('distribute')['divider_type']['first_member'];
        }

        if ($relativeLevel == 2) {
            $identity['remarks'] = '二级会员返利';
            $identity['level'] = 2;
            $identity['divider_type'] = config('distribute')['divider_type']['second_member'];
        }

        //关系对应
        $divideRatioConfig = tpCache('distribute','','cf_config');
        //根据身份来设置分成比例
        $agent[1][1] = $divideRatioConfig['agent_city_ratio4'];//市代
        $agent[1][2] = $divideRatioConfig['agent_city_ratio5'];//市代
        $agent[2][1] = $divideRatioConfig['agent_county_ratio4'];//区县
        $agent[2][2] = $divideRatioConfig['agent_county_ratio5'];//区县
        $agent[3][1] = $divideRatioConfig['agent_town_ratio4'];//镇
        $agent[3][2] = $divideRatioConfig['agent_town_ratio5'];//镇
        $partner[1] = $divideRatioConfig['partner_ratio1'];//合伙人
        $partner[2] = $divideRatioConfig['partner_ratio2'];//合伙人
        //1.具有多个身份的时候，选择按照身份等级高的分成
        if ($user_info['partner_relation'] && $user_info['agent_relation']) {
            $agentRatio = $agent[$user_info['agent_relation']['agent_level']][$relativeLevel];
            $identity['divide_ratio'] = $agentRatio;
            $identity['identity_name'] = config('distribute')['distribute_type']['agent'];
        }elseif ($user_info['partner_relation']) {//2.判断是否有合伙人身份
            $identity['divide_ratio'] = $partner[$relativeLevel];
            $identity['identity_name'] = config('distribute')['distribute_type']['partner'];
        }elseif ($user_info['agent_relation']) {//3.判断是否有代理商身份
            $identity['divide_ratio'] = $agent[$user_info['agent_relation']['agent_level']][$relativeLevel];
            $identity['identity_name'] = config('distribute')['distribute_type']['agent'];
        }else{//4.普通身份
            $user[1] = $divideRatioConfig['user_ratio1'];
            $user[2] = $divideRatioConfig['user_ratio2'];
            $identity['divide_ratio'] = $user[$relativeLevel];
            $identity['identity_name'] = config('distribute')['distribute_type']['common_user'];
        }
        $identity['remarks'] .= $identity['divide_ratio'].'%';
        return $identity;
    }

    /**
     * @Author: 陈静
     * @Date: 2018/04/08 14:16:37
     * @Description: 生成分销数据
     */
    public function generateDivideData($result,$identity)
    {
        $parentData = [];
        $divdeMoey = ($result['order_amount'] + $result['user_money'] - $result['shipping_price']) * $identity['divide_ratio'] / 100;
        if ($divdeMoey) {
            $parentData = [
                'from_user_id' => $result['user_id'],
                'order_sn' => $result['order_sn'],
                'to_user_id' => isset($identity['user_id']) ? $identity['user_id'] : $identity['agent_id'],
                'distribute_type' => $identity['identity_name'],
                'divider_type' => $identity['divider_type'],
                'remarks' => $identity['remarks'],
                'divide_ratio' => $identity['divide_ratio'],
                'divide_money' => round($divdeMoey,2),
                'score' => 0,
                'is_divided' => 0,
                'add_time' => time()
            ];
        }

        return $parentData;
    }


    /*
     * @Author : 赵磊
     * 卡券兑换码取消分成
     * */
    public function VrCodeCancelSplit($order_sn)
    {
        $goodsPrice = Db::table('tp_order')->field('goods_price')->where('order_sn',$order_sn)->find();
        $condition['is_divided'] = 0;//待分成
        $condition['order_sn'] = $order_sn;//该笔订单
        $info = $this->field('id,divide_ratio,divide_money')->where('order_sn',$order_sn)->select();
        $info = $info->toArray();
//        halt($info);
        for ($i=0;$i<count($info);$i++){
           $cancel = 0.01 * $info[$i]['divide_ratio'] * $goodsPrice['goods_price'];
           $divide_money = $info[$i]['divide_money'] - $cancel;
           if ($divide_money < 0) return false;
           $res = $this->where('id',$info[$i]['id'])->update(['divide_money'=>$divide_money]);
        }
        return $res;
    }


}