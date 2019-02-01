<?php
/**
 * tpshop
 * ============================================================================
 * 版权所有 2015-2027 深圳搜豹网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.tp-shop.cn
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用最新Thinkphp5助手函数特性实现单字母函数M D U等简写方式
 * ============================================================================
 * Author: lhb
 * Date: 2017-05-15
 */

namespace app\common\logic;

use app\admin\model\PromGoods;
use app\common\model\Coupon;
use think\Exception;
use think\Model;
use think\Db;

/**
 * 活动逻辑类
 */
class ActivityLogic extends Model
{
    
    /**
     * 团购总数
     * @param type $sort_type
     * @param type $page_index
     * @param type $page_size
     */
    public function getGroupBuyCount()
    {
        $group_by_where = array(
            'start_time'=>array('lt',time()),
            'end_time'=>array('gt',time()),
        );
        $count = M('group_buy')->alias('b')
                ->field('b.goods_id,b.rebate,b.virtual_num,b.buy_num,b.title,b.goods_price,b.end_time,b.price,b.order_num,g.comment_count')
                ->join('__GOODS__ g', 'b.goods_id=g.goods_id AND g.prom_type=2 AND g.is_on_sale=1')
                ->where($group_by_where)
                ->count();
        return $count;
    }
    
    /**
     * 团购列表
     * @param type $sort_type
     * @param type $page_index
     * @param type $page_size
     */
    public function getGroupBuyList($sort_type = '', $page_index = 1, $page_size = 20)
    {
        if ($sort_type == 'new') {
            $type = 'start_time';
        } elseif ($sort_type == 'comment') {
            $type = 'g.comment_count';
        } else {
            $type = '';
        }
        
        $group_by_where = array(
            'start_time'=>array('lt',time()),
            'end_time'=>array('gt',time()),
            'is_end' => 0
        );
        $list = M('group_buy')->alias('b')
                ->field('b.goods_id,b.item_id,b.rebate,b.virtual_num,b.buy_num,b.title,b.goods_price,b.end_time,b.price,b.order_num,g.comment_count')
                ->join('__GOODS__ g', 'b.goods_id=g.goods_id AND g.prom_type=2 AND g.is_on_sale=1')
                ->where($group_by_where)->page($page_index, $page_size)
                ->order($type, 'desc')
                ->select(); // 找出这个商品
        
        $groups = array();
        $server_time = time();
        foreach ($list as $v) {
            $v["server_time"] = $server_time;
            $groups[] = $v;
        }

        return $groups;
    }

    /**
     * 优惠券列表
     * @param type $atype 排序类型 1:默认id排序，2:即将过期，3:面值最大
     * @param $user_id  用户ID
     * @param int $p 第几页
     * @return array
     */
    public function getCouponList($atype, $user_id, $p = 1)
    {
        $time = time();
        $where = array('type' => 2,'status'=>1,'send_start_time'=>['elt',time()],'send_end_time'=>['egt',time()], 'use_end_time'=>['egt',time()]);
        $order = array('id' => 'desc');
        if ($atype == 2) {
            //即将过期
            $order = ['spacing_time' => 'asc'];
            $where["send_end_time-'$time'"] = ['egt', 0];
        } elseif ($atype == 3) {
            //面值最大
            $order = ['money' => 'desc'];
        }

        $coupon_list = M('coupon')->field("*,send_end_time-'$time' as spacing_time")
            ->where($where)->page($p, 15)->order($order)->select();

        if (is_array($coupon_list) && count($coupon_list) > 0) {
            if ($user_id) {
                $user_coupon = M('coupon_list')->where(['uid' => $user_id, 'type' => 2])->getField('cid',true);
            }

            foreach ($coupon_list as $k => $val) {
                $coupon_list[$k]['isget'] = 0;
                if (in_array($val['id'],$user_coupon)) {
                    $coupon_list[$k]['isget'] = 1;
                    unset($coupon_list[$k]);
                    continue;
                }
                $coupon_list[$k]['use_scope'] = C('COUPON_USER_TYPE')[$coupon_list[$k]['use_type']];
            }
        }
        return $coupon_list;
    }

    /**
     * 获取用户已领取的优惠券
     * @param type $atype 排序类型 1:默认id排序，2:即将过期，3:面值最大
     * @param $user_id  用户ID
     * @param int $p 第几页
     * @return array
     */
    public function getCouponListCanUse($atype, $user_id, $p = 1){
        $time = time();
        $where = array('type' => 2,'status'=>1,'send_start_time'=>['elt',time()],'send_end_time'=>['egt',time()]);
        $order = array('id' => 'desc');
        if ($atype == 2) {
            //即将过期
            $order = ['spacing_time' => 'asc'];
            $where["send_end_time-'$time'"] = ['egt', 0];
        } elseif ($atype == 3) {
            //面值最大
            $order = ['money' => 'desc'];
        }
        $coupon_list = M('coupon')->field("*,send_end_time-'$time' as spacing_time")
            ->where($where)->page($p, 15)->order($order)->select();
        if (is_array($coupon_list) && count($coupon_list) > 0) {
            if ($user_id) {
                $user_coupon_used = M('coupon_list')->where(['uid' => $user_id, 'type' => 2])->field('cid, status')->select();
            }
            // 如果用户的券已经使用过，那么 unset 掉

            if (!empty($user_coupon_used)) {
                foreach ($coupon_list as $k => $val) {
                    $coupon_list[$k]['isget'] = 0;
                    $use_condition = '';
                    if ($val['use_type'] == 0) {
                        $use_condition .= '全场';
                    } elseif ($val['use_type'] == 1) {
                        $use_condition .= '指定商品';
                    } elseif ($val['use_type'] == 2) {
                        $use_condition .= '指定分类';
                    }
                    $use_condition .= '满'.$val['condition'].'可使用';

                    $coupon_list[$k]['use_condition'] = $use_condition; //使用条件
                    $coupon_list[$k]['user_url'] = '/'; // 点击去使用是跳转连接
                    foreach ($user_coupon_used as $v){
                        if ($val['id'] == $v['cid']) {
                            if ($v['status'] == 1 || $v['status'] == 2) {
                                unset($coupon_list[$k]);
                            } else {
                                $coupon_list[$k]['isget'] = 1;
                            }
                        }
                    }
                    $coupon_list[$k]['use_scope'] = C('COUPON_USER_TYPE')[$coupon_list[$k]['use_type']];
                }
            }
        }
        return $coupon_list;
    }

    /**
     * 获取优惠券查询对象
     * @param int $queryType 0:count 1:select
     * @param type $user_id
     * @param int $type 查询类型 0:未使用，1:已使用，2:已过期
     * @param type $orderBy 排序类型，use_end_time、send_time,默认send_time
     * @param int $order_money
     * @return Query
     */
    public function getCouponQuery($queryType, $user_id, $type = 0, $orderBy = null , $order_money = 0)
    {
        $whereCoupon['l.uid'] = $user_id;
        $whereCoupon['c.status'] = ['in','1,3'];

        if ($orderBy == 'use_end_time') {
            //即将过期，$type = 0 AND $orderBy = 'use_end_time'
            $order['l.use_end_time'] = 'asc';
        } elseif ($orderBy == 'send_time') {
            //最近到账，$type = 0 AND $orderBy = 'send_time'
            $where['l.send_time'] = array('lt',time());
            $order['l.send_time'] = 'desc';
        } elseif (empty($orderBy)) {
            $order = array('l.send_time' => 'DESC', 'l.use_time');
        }
        $condition = floatval($order_money) ? ' AND c.condition <= '.$order_money : '';

        $query = M('coupon_list')->alias('l')
            ->join('__COUPON__ c','l.cid = c.id'.$condition)->where($whereCoupon);

        //查询条件
        if (empty($type)) {
            // 未使用
            $where['l.order_id'] = 0;
            $where['l.use_end_time'] = array('gt', time());
            $where['l.status'] = 0;
            $query->where(function ($query) use ($where){
                $query->where($where);
            });
        } elseif ($type == 1) {
            //已使用
            $where['l.order_id'] = array('gt', 0);
            $where['l.use_time'] = array('gt', 0);
            $where['l.status'] = 1;
            $query->where(function ($query) use ($where){
                $query->where($where);
            });
        } elseif ($type == 2) {
            //已过期
            $where['l.use_end_time'] = array('lt', time());
            $where['l.status'] = array('in', '0,2');
            $query->where(function ($query) use ($where){
                $query->where(function ($query) use ($where){
                    $query->where($where);
                })->whereOr(function ($query){
                    $query->where('l.status',3);
                });
            });
        }

        if ($queryType != 0) {
            $query = $query->field('l.*,c.name,c.money,l.use_start_time,l.use_end_time,c.condition,c.use_type')
                    ->order($order);
        }

        return $query;
    }

    /**
     * 获取优惠券数目
     * @param $user_id
     * @param int $type
     * @param null $orderBy
     * @param int $order_money
     * @return mixed
     */
    public function getUserCouponNum($user_id, $type = 0, $orderBy = null,$order_money = 0)
    {
        $query = $this->getCouponQuery(0, $user_id, $type, $orderBy,$order_money);
        return $query->count();
    }

    /**
     * 获取用户优惠券列表
     * @param $firstRow
     * @param $listRows
     * @param $user_id
     * @param int $type
     * @param null $orderBy
     * @param int $order_money
     * @return mixed
     */
    public function getUserCouponList($firstRow, $listRows, $user_id, $type = 0, $orderBy = null,$order_money = 0)
    {
        $query = $this->getCouponQuery(1, $user_id, $type, $orderBy,$order_money);
        return $query->limit($firstRow, $listRows)->select();
    }
    
    /**
     * 领券中心
     * @param type $cat_id 领券类型id
     * @param type $user_id 用户id
     * @param type $p 第几页
     * @return type
     */
    public function getCouponCenterList($cat_id, $user_id, $p = 1)
    {
        /* 获取优惠券列表 */
        $cur_time = time();
        $coupon_where = ['type'=>2, 'status'=>1, 'send_start_time'=>['elt',time()], 'send_end_time'=>['egt',time()]];
        $query = M('coupon')->alias('c')
            ->field('c.use_type,c.name,c.id,c.money,c.condition,c.createnum,c.send_num,c.send_end_time-'.$cur_time.' as spacing_time')
            ->where('((createnum-send_num>0 AND createnum>0) OR (createnum=0))')    //领完的也不要显示了
            ->where($coupon_where)->page($p, 15)
            ->order('spacing_time', 'asc');
        if ($cat_id > 0) {
            $query = $query->join('__GOODS_COUPON__ gc', 'gc.coupon_id=c.id AND gc.goods_category_id='.$cat_id);
        }
        $coupon_list = $query->select();
        
        if (!(is_array($coupon_list) && count($coupon_list) > 0)) {
            return [];
        }
        
        $user_coupon = [];
        if ($user_id) {
            $user_coupon = M('coupon_list')->where(['uid' => $user_id, 'type' => 2])->column('cid');
        }

        $types = [];
        if ($cat_id) {
            /* 优惠券类型格式转换 */
            $couponType = $this->getCouponTypes();
            foreach ($couponType as $v) {
                $types[$v['id']] = $v['mobile_name'];
            }
        }

        $store_logo = tpCache('shop_info.store_logo') ?: '';
        $Coupon = new Coupon();
        foreach ($coupon_list as $k => $coupon) {
            /* 是否已获取 */
            $coupon_list[$k]['use_type_title'] = $Coupon->getUseTypeTitleAttr(null, $coupon_list[$k]);
            $coupon_list[$k]['isget'] = 0;
            if (in_array($coupon['id'], $user_coupon)) {
                $coupon_list[$k]['isget'] = 1;
            }

            /* 构造封面和标题 */
            $coupon_list[$k]['image'] = $store_logo;
        }
        
        return  $coupon_list;
    }
    
    /**
     * 优惠券类型列表
     * @param type $p 第几页
     * @param type $num 每页多少，null表示全部
     * @return type
     */
    public function getCouponTypes($p = 1, $num = null)
    {
        $list = M('coupon')->alias('c')
                ->join('__GOODS_COUPON__ gc', 'gc.coupon_id=c.id AND gc.goods_category_id!=0')
                ->where(['type' => 2, 'status' => 1])
                ->column('gc.goods_category_id');
        
        $result = M('goods_category')->field('id, mobile_name')->where("id", "IN", $list)->page($p, $num)->select();
        $result = $result ?: [];
        array_unshift($result, ['id'=>0, 'mobile_name'=>'精选']);

        return $result;
    }
    
    /**
     * 领券
     * @param $id 优惠券id
     * @param $user_id
     */
    public function get_coupon($id, $user_id)
    {
        if (empty($id)){
            $return = ['status' => 0, 'msg' => '参数错误'];
        }
        if ($user_id) {
            $_SERVER['HTTP_REFERER'] = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : U('Home/Activity/coupon_list');
            $coupon_info = M('coupon')->where(array('id' => $id, 'status' => 1))->find();
            if (empty($coupon_info)) {
                $return = ['status' => 0, 'msg' => '活动已结束或不存在，看下其他活动吧~','return_url'=>$_SERVER['HTTP_REFERER']];
            } elseif ($coupon_info['send_end_time'] < time()) {
                //来晚了，过了领取时间
                $return = ['status' => 0, 'msg' => '抱歉，已经过了领取时间','return_url'=>$_SERVER['HTTP_REFERER']];
            } elseif ($coupon_info['send_num'] >= $coupon_info['createnum'] && $coupon_info['createnum'] != 0) {
                //来晚了，优惠券被抢完了
                $return = ['status' => 0, 'msg' => '来晚了，优惠券被抢完了','return_url'=>$_SERVER['HTTP_REFERER']];
            } else {
                if (M('coupon_list')->where(array('cid' => $id, 'uid' => $user_id))->find()) {
                    //已经领取过
                    $return = ['status' => 2, 'msg' => '您已领取过该优惠券','return_url'=>$_SERVER['HTTP_REFERER']];
                } else {
                    $data = array('uid' => $user_id, 'cid' => $id, 'type' => 2, 'send_time' => time(),'status'=>0,
                        'use_start_time'=>max(time(),$coupon_info['use_start_time']),
                        'use_end_time'=> $coupon_info['valid_time'] > 0 ? min(time() + $coupon_info['valid_time'], $coupon_info['use_end_time']) : $coupon_info['use_end_time']);
                    M('coupon_list')->add($data);
                    $user_coupon_id = Db::getLastInsID();
                    M('coupon')->where(array('id' => $id, 'status' => 1))->setInc('send_num');
                    $return = ['status' => 1, 'msg' => '恭喜您，抢到' . $coupon_info['money'] . '元优惠券!','return_url'=>$_SERVER['HTTP_REFERER'],'user_coupon_id'=>$user_coupon_id];
                }
            }
        } else {
            $return = ['status' => 0, 'msg' => '请先登录','return_url'=>U('User/login')];
        }
        
        return $return;
    }

    /**
     * @Author: 陈静
     * @Date: 2018/04/17 16:35:00
     * @Description: 用户获取优惠券
     */
    public function grantUserCoupon($user_id,$type = 4)
    {
        $where = [
            'type' => $type,
            'status' => 1,
            'send_end_time' => ['>' , time()],
            'send_start_time' => ['<',time()]
        ];

        //查询优惠券
        $couponIdsArr = M('coupon')
            ->field('id,createnum,send_num,use_start_time,use_end_time,valid_time')
            ->where($where)
            ->select();

        //查询已领取
        $haveGetedCoupon = [];
        if ($type != 6 && $type != 8) {//当类型为邀请用户或新用户下首单的时候不检查是否领取过此优惠券
            $haveGetedCoupon = M('coupon_list')->field('cid')->where(array( 'uid' => $user_id))->select();
        }

        $insertArrs = [];
        $idsStr = '';
        foreach ($couponIdsArr as $k => $v){
            if (!in_array($v['id'],array_column($haveGetedCoupon,'cid'))) {
                if ( $v['createnum'] == 0 || ($v['createnum'] > $v['send_num']) ) {
                    $idsStr .= $v['id'].',';
                    $insertArrs[$k]['cid'] = $v['id'];
                    $insertArrs[$k]['type'] = $type;
                    $insertArrs[$k]['uid'] = $user_id;
                    $insertArrs[$k]['send_time'] = time();
                    $insertArrs[$k]['use_start_time'] = time();
                    $insertArrs[$k]['use_end_time'] = time() + $v['valid_time'];
                }
            }
        }

        try{
            //插入
            $res1 = M('coupon_list')->insertAll($insertArrs);
            $res2 = M('coupon')
                ->where([
                    'id' => ['in',rtrim($idsStr,',')],
                    'status' => 1
                ])
                ->setInc('send_num');
            if ($res1 && $res2) {
                return true;
            }
            return false;
        }catch (Exception $e){
            return false;
            //记录日志
            \app\common\library\Logs::sentryLogs($e,['msg' => '送券失败,送券类型：'.$type]);
        }
    }


    
    /**
     * 获取活动简要信息
     */
    public function getActivitySimpleInfo(&$goods, $user)
    {
        //1.商品促销
        $activity = $this->getGoodsPromSimpleInfo($user, $goods);
        
        //2.订单促销
        $activity_order = $this->getOrderPromSimpleInfo($user, $goods);
        
        if ($activity['data'] || $activity_order) {
            empty($activity['data']) && $activity['data'] = [];
            $activity['data'] = array_merge($activity['data'], $activity_order);
        }

        $activity['server_current_time'] = time();//服务器时间
        
        return $activity;
    }
    
    /**
     * 获取商品促销简单信息
     */
    public function getGoodsPromSimpleInfo($user, &$goods)
    {
        $goods['prom_is_able'] = 0;
        $activity['prom_type'] = 0;
    
        //1.商品促销
        $goodsPromFactory = new \app\common\logic\GoodsPromFactory;
        if (!$goodsPromFactory->checkPromType($goods['prom_type'])) {
            return $activity;
        } 
        $goodsPromLogic = $goodsPromFactory->makeModule($goods, $goods['prom_id']);
        //上面会自动更新商品活动状态，所以商品需要重新查询
        $goods  = M('Goods')->where('goods_id', $goods['goods_id'])->find();
        unset($goods['goods_content']);
        $goods['prom_is_able'] = 0;
        
        //prom_type:0默认 1抢购 2团购 3优惠促销 4预售(不考虑)
        if (!$goodsPromLogic->checkActivityIsAble()) {
            return $activity;
        }
        $prom = $goodsPromLogic->getPromModel()->getData();
        if (in_array($goods['prom_type'], [1, 2])) {
            $prom['virtual_num'] = $prom['virtual_num'] + $prom['buy_num'];//参与人数
            $goods['prom_is_able'] = 1;
            $activity = [
                'prom_type' => $goods['prom_type'],
                'prom_price' => $prom['price'],
                'virtual_num' => $prom['virtual_num']
            ];
            if($prom['start_time']){
                $activity['prom_start_time'] = $prom['start_time'];
            }
            if($prom['end_time']) {
                $activity['prom_end_time'] = $prom['end_time'];
            }
            return $activity;
        }
        
        // 3优惠促销
        $levels = explode(',', $prom['group']);
        if ($prom['group'] && (isset($user['level']) && in_array($user['level'], $levels))) {
            //type:0直接打折,1减价优惠,2固定金额出售,3买就赠优惠券
            if ($prom['type'] == 0) {
                $activityData[] = ['title' => '折扣', 'content' => "指定商品立打{$prom['expression']}折"];
            } elseif ($prom['type'] == 1) {
                $activityData[] = ['title' => '直减', 'content' => "指定商品立减{$prom['expression']}元"];
            } elseif ($prom['type'] == 2) {
                $activityData[] = ['title' => '促销', 'content' => "促销价{$prom['expression']}元"];
            } elseif ($prom['type'] == 3) {
                $couponLogic = new \app\common\logic\CouponLogic;
                $money = $couponLogic->getSendValidCouponMoney($prom['expression'], $goods['goods_id'], $goods['cat_id3']);
                if ($money !== false) {
                    $activityData[] = ['title' => '送券', 'content' => "买就送代金券{$money}元"];
                }
            }
            if ($activityData) {
                $goods['prom_is_able'] = 1;
                $activity = [
                    'prom_type' => $goods['prom_type'],
                    'data' => $activityData
                ];
                if($prom['start_time']){
                    $activity['prom_start_time'] = $prom['start_time'];
                }
                if($prom['end_time']) {
                    $activity['prom_end_time'] = $prom['end_time'];
                }
            }
        }
        
        return $activity;
    }
    
    /**
     * 获取
     * @param type $user_level
     * @param type $cur_time
     * @param type $goods
     * @return string|array
     */
    public function getOrderPromSimpleInfo($user, $goods)
    {
        $cur_time = time();
        $sql = "select * from __PREFIX__prom_order where start_time <= $cur_time AND end_time > $cur_time";
        $data = [];
        $po = Db::query($sql);
        if (!empty($po)) {
            foreach ($po as $p) {
                //type:0满额打折,1满额优惠金额,2满额送积分,3满额送优惠券
                if ($p['type'] == 0) {
                    $data[] = ['title' => '折扣', 'content' => "满{$p['money']}元打".round($p['expression']/10, 1)."折"];
                } elseif ($p['type'] == 1) {
                    $data[] = ['title' => '优惠', 'content' => "满{$p['money']}元优惠{$p['expression']}元"];
                } elseif ($p['type'] == 2) {
                    //积分暂不支持?
                } elseif ($p['type'] == 3) {
                    $couponLogic = new \app\common\logic\CouponLogic;
                    $money = $couponLogic->getSendValidCouponMoney($p['expression'], $goods['goods_id'], $goods['cat_id']);
                    if ($money !== false) {
                        $data[] = ['title' => '送券', 'content' => "满{$p['money']}元送{$money}元优惠券"];
                    }
                }
            }
        }
        
        return $data;
    }
    
    /**
     * 订单支付时显示的优惠显示
     * @param type $user
     * @param type $store_id
     * @return type
     */
    public function getOrderPayProm($order_amount=0)
    {

        $cur_time = time();
        $sql = "select * from __PREFIX__prom_order where type<2 and start_time <= $cur_time "
                . "AND end_time > $cur_time AND  money<=$order_amount order by money desc limit 1"; //显示满额打折,减价优惠信息
        $data = '';
        $po = Db::query($sql);
        if (!empty($po)) {
            foreach ($po as $p) {
                //type:0满额打折,1满额优惠金额,2满额送积分,3满额送优惠券
                if ($p['type'] == 0) {
                    $data = "满{$p['money']}元打".round($p['expression']/10, 1)."折";
                } elseif ($p['type'] == 1) {
                    $data = "满{$p['money']}元优惠{$p['expression']}元";
                }
            }
        }
        
        return $data;
    }

    /**
     * 计算订单优惠价格
     * @param type $user
     * @param type $store_id
     * @return type
     */
    public function getOrderPromMoney($order_amount=0)
    {

        $cur_time = time();
        $sql = "select * from __PREFIX__prom_order where type<2 and start_time <= $cur_time "
            . "AND end_time > $cur_time AND  money<=$order_amount order by money desc limit 1"; //显示满额打折,减价优惠信息
        $data = '';
        $po = Db::query($sql);
        if (!empty($po)) {
            foreach ($po as $p) {
                //type:0满额打折,1满额优惠金额,2满额送积分,3满额送优惠券
                if ($p['type'] == 0) {
                    $total_fee = round($order_amount * $p['expression'] / 100, 2);//满额打折
                } elseif ($p['type'] == 1) {
                    $total_fee = round($order_amount - $p['expression'], 2);
                }
            }
        }
        return $total_fee;
    }
    // 检测秒杀
    public function flashStatus($goods_id,$item_id){
        $now = time();
        $flash = Db::name('flash_sale')
            ->where('is_end',0)
            ->where(['start_time'=>['<',$now]])
            ->where(['end_time'=>['>',$now]])
            ->where('goods_id',$goods_id)
            ->where('item_id',$item_id)
            ->find();
        $is_end = false;
        if (empty($flash)) $is_end = true;
        if($flash['buy_num'] >= $flash['goods_num'] || time() > $flash['end_time']){
            Db::name('flash_sale')->where('id',$flash['id'])->update(['is_end'=>1]);
            $is_end = true;
        }

        return $is_end ? []:[
            'prom_type'=>1,
            'prom_id'       =>$flash['id'],
            'activity_title'=>$flash['title'],
            'start_time'    =>$flash['start_time'],
            'end_time'      =>$flash['end_time'],
            'prom_detail'   =>'正在进行秒杀活动！'
            ];
    }
    //商品活动
    public function promStatus($goods_id,$item_id = 0){
        $now = time();
        $prom = Db::name('prom_goods_list li')
            ->join('prom_goods pg','li.promote_id=pg.id','left')
            ->where('goods_id',$goods_id)
            ->where('item_id',$item_id)
            ->where(['pg.start_time'=>['<',$now]])
            ->where(['pg.end_time'=>['>',$now]])
            ->where('is_end',0)
            ->find();
        $is_end = false;
        if (empty($prom)) $is_end = true;
        if($prom['buy_num'] >= $prom['goods_num'] || time() > $prom['end_time']){
            $is_end = true;
        }
        $PromGoods = new PromGoods();
        return $is_end ? []:[
            'prom_type'=>3,
            'prom_id'   =>$prom['id'],
            'activity_title'=>$prom['title'],
            'start_time'    =>$prom['start_time'],
            'end_time'      =>$prom['end_time'],
            'prom_detail'   =>$PromGoods->getPromDetailAttr(null, $prom)
        ];
    }

    public function goodsRelatedActivity($goods_id,$item_id = 0){
        $activity = [];
        $flash = $this->flashStatus($goods_id,$item_id);
        if ($flash) {
            $activity = $flash;
        } else {
            $prom = $this->promStatus($goods_id,$item_id);
            $activity = $prom;
        }
        return $activity;
    }


    /*
     * @Author:赵磊
     * 用户首单确认收货时,给上级分发首单优惠券
     * */
    public function firstOrderCoupon($userId)
    {
        $users = Db::table('cf_user_user')->field('parent_id')->where('user_id',$userId)->find();
        $inviteUser = $users['parent_id'];//该用户邀请人id
        $count = (new \app\common\model\Order())->finishOrderNum($userId);
        if ($count == 1) $this->grantUserCoupon($inviteUser,8);//确认收货订单量有且只为1时,为首单,给上级发五元优惠券
    }

}