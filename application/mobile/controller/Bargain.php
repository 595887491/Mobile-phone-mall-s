<?php
/**
 *
 */
namespace app\mobile\controller;


use app\common\logic\CartLogic;
use app\common\logic\GoodsLogic;
use app\mobile\model\BargainActivityModel;
use app\mobile\model\BargainFollowModel;
use app\mobile\model\BargainFoundModel;
use app\mobile\model\BargainShareModel;
use app\mobile\model\BargainTemplateModel;
use app\mobile\model\BargainUserModel;
use think\AjaxPage;
use think\Db;
use think\Log;

class Bargain extends MobileBase
{
    public function  __construct()
    {
        parent::__construct();

        $this->checkUserLogin([
            'info','bargainFreeList','bargainShare','getPreferSelectGoods','getRegion'
        ]);

        //砍价商品过期更改状态
        if ($this->user_id){
            $condition['found_end_time'] = ['<',time()+2];//砍价结束
            $condition['user_id'] = $this->user_id;
            (new BargainFoundModel())->where($condition)->update(['status'=>4]);//砍价过期
        }
    }

    /**
     * 砍价商品首页显示
     */
    public function info()
    {
        //用户是否登陆
        $this->assign('user',$this->user);

        $activityId = I('activity_id',0);
        $itemId = I('item_id',0);
        $foundId = I('found_id',0);
        $is_share = I('is_share',0);
        $this->assign('is_share',$is_share);
        //活动信息
        $activityInfo = (new BargainActivityModel())->getBargainDetail($activityId,$itemId);
        if (empty($activityInfo)) {
            return $this->error('不存在砍价信息');
        }

        $this->assign('activity_info',$activityInfo);

        //砍价成功的用户
        if (empty($this->user_id)) {
            $successUserInfo = (new BargainUserModel())->getSuccessUser($activityInfo->activity_id);
            $this->assign('success_user',$successUserInfo);
        }

        //登陆状态检测砍价状态
        if ($this->user) {
            $bargainFollowModel = new BargainFollowModel();
            //帮砍和自己砍价
            $bargainInfo = $bargainFollowModel->autoBargain($activityInfo,$this->user_id,$foundId,$is_share);
            if ($bargainInfo == false) {
                return $this->error('砍价页面出错，稍后再试');
            }
            $this->assign('bargain_info',$bargainInfo);
        }

        halt($bargainInfo);

        if ( $this->user || $foundId) {
            //砍价成功的用户
            $userDatas = $bargainFollowModel->getBargainUserByFoundId($bargainInfo['found_id']);
            $this->assign('user_data',$userDatas);
        }

        return $this->fetch();
    }

    //分享接口
    public function bargainShare()
    {
        $foundId = I('post.found_id/d',0);
        $userId = I('post.user_id/d',0);
        $foundInfo = (new BargainFoundModel())->where('found_id',$foundId)->find();

        if (empty($foundInfo) || $foundInfo->user_id != $userId ) {
            return outPut(-1,'砍价信息有误');
        }

        $shareInfo = (new BargainShareModel())->addShareInfo($foundInfo);

        $currentTimes = (new BargainFollowModel())->where('found_id',$foundId)
            ->where('follow_user_id',$userId)
            ->order('follow_time DESC')
            ->getField('cut_no');

        $shareInfo['next_share_info'] = (new BargainFollowModel())->getNextShareInfo($shareInfo,$currentTimes);

        return outPut(1,'success',$shareInfo);

    }

    //精选好物
    public function getPreferSelectGoods()
    {
        //精选好物（cf_goods_topic  id=52）
        $goodsId = Db::table('cf_goods_topic')->where('topic_id',52)->getField('goods_id');

        $pageObj = new AjaxPage(count($goodsId), 20);

        $goodsData = (new \app\common\model\Goods())->where('goods_id','in',$goodsId)
            ->field('goods_id,goods_name,goods_remark,original_img,shop_price,market_price')
            ->field('goods_id,goods_name,goods_remark,original_img,shop_price,market_price')
            ->where('goods_id' , 'in',$goodsId)
            ->limit($pageObj->firstRow,$pageObj->listRows)
            ->order("INSTR(',".$goodsId.",',CONCAT(',',goods_id,','))")
            ->select();

        $this->assign('goods_data',$goodsData);
        return $this->fetch();
    }

    //用户地址接口
    public function getUserAddressList()
    {
        $address_lists = get_user_address_list($this->user_id);

        $zitiAddress = [
            "address_id" => -1,
            "user_id" => $this->user_id,
            "consignee" => "到店自提",
            "email" => "",
            "country" => 0,
            "province" => 33007,
            "city" => 33008,
            "district" => 33058,
            "twon" => 0,
            "address" => "领事馆路17号鸿川大楼8F (尚美缤纷)",
            "zipcode" => "",
            "mobile" => "时间: 工作日8:30-17:30",
            "is_default" => 0,
            "is_pickup" => 0
        ];
        array_push($address_lists,$zitiAddress);
        $provinceId = array_column($address_lists,'province');
        $cityId = array_column($address_lists,'city');
        $districtId = array_column($address_lists,'district');

        $region_list = M('region')
            ->where('id','in',join(',',array_unique(array_merge($provinceId,$cityId,$districtId))))
            ->getField('id,name');;
        return outPut(1,'success',['region_list' => $region_list,'lists'=>$address_lists]);
    }

    //添加地址
    public function addUserAddress()
    {
        $addressId = I('address_id/d',0);
        $foundId = I('found_id/d');

        if (empty($foundId)) {
            return outPut(-1,'砍价信息错误');
        }

        $res = (new BargainFoundModel())->where('found_id',$foundId)->update([
            'address_id' => $addressId
        ]);

        $address = Db::name('UserAddress')->where("address_id", $addressId)->find();
        $activityFoundInfo = (new BargainFoundModel())->where('found_id',$foundId)->find()->toArray();
        $activityInfo = (new BargainActivityModel())->where('id',$activityFoundInfo['bargain_id'])->find();
        //计算运费价格-start
        $cartLogic = new CartLogic();
        $cartList = [];
        $cartLogic->setUserId($activityFoundInfo['user_id']);
        $cartLogic->setGoodsModel($activityInfo->goods_id);
        $cartLogic->setSpecGoodsPriceModel($activityInfo->item_id);
        $cartLogic->setGoodsBuyNum(1);
        $buyGoods = $cartLogic->buyNow();
        $buyGoods['goods_price'] = $activityFoundInfo['price'];
        $buyGoods['member_goods_price'] = $activityFoundInfo['price'];
        $buyGoods['goods']->shop_price = $activityFoundInfo['price'];
        $buyGoods['goods']->is_free_shipping = $activityInfo->is_shipping;

        array_push($cartList,$buyGoods);

        $GoodsLogic = new GoodsLogic();
        $checkGoodsShipping = $GoodsLogic->checkGoodsListShipping($cartList, $address['district']);
        foreach($checkGoodsShipping as $shippingKey => $shippingVal){
            if($shippingVal['shipping_able'] != true){
                return outPut(-1,'砍价商品不支持对当前地址的配送');
            }
        }
        //计算运费价格-end

        if ($res) {
            return outPut(1,'添加成功');
        }else{
            return outPut(-1,'添加失败');
        }
    }
    
    //地区选择
    public function getRegion()
    {
        $region1 = M('region')->field('id,name,parent_id')->where('level',1)->cache(true)->select();
        $region2 = M('region')->field('id,name,parent_id')->where('level',2)->cache(true)->select();
        $region3 = M('region')->field('id,name,parent_id')->where('level',3)->cache(true)->select();

        $data = [];
        foreach ($region1 as $k => $v) {
            $data[$k]['id'] = $v['id'];
            $data[$k]['value'] = $v['name'];
            foreach ($region2 as $kk => $vv) {
                if ($v['id'] == $vv['parent_id']) {
                    $data[$k]['childs'][$kk]['id'] = $vv['id'];
                    $data[$k]['childs'][$kk]['value'] = $vv['name'];
                    foreach ($region3 as $kkk => $vvv) {
                        if ($vv['id'] == $vvv['parent_id']) {
                            $data[$k]['childs'][$kk]['childs'][$kkk]['id'] = $vvv['id'];
                            $data[$k]['childs'][$kk]['childs'][$kkk]['value'] = $vvv['name'];
                        }
                    }
                    if ($data[$k]['childs'][$kk]['childs']) {
                        $data[$k]['childs'][$kk]['childs'] = array_values($data[$k]['childs'][$kk]['childs']);
                    }
                }
            }
            if ($data[$k]['childs']) {
                $data[$k]['childs'] = array_values($data[$k]['childs']);
            }
        }

        return json_encode(['data' => $data]);
    }


    /**
     * @Author :赵磊
     * 砍价免费拿列表
     */
    public function bargainFreeList()
    {
        //砍价商品列表
        $list = (new BargainActivityModel())->getBargainList();
        $this->assign('list',$list);
        if (IS_AJAX){
            return $this->fetch('ajax_bargainFreeList');
        }

        //滚动消息
        $roll = Db::query("SELECT DISTINCT `b`.`act_type`,`c`.`nickname`,`c`.`head_pic`,`d`.`goods_name` FROM `cf_bargain_found` `a` INNER JOIN `cf_bargain_activity` `b` ON `a`.`bargain_id`=`b`.`id` INNER JOIN `tp_users` `c` ON `a`.`user_id`=`c`.`user_id` INNER JOIN `tp_goods` `d` ON `b`.`goods_id`=`d`.`goods_id` WHERE  `a`.`status` = 2 ORDER BY bargain_ok_time desc LIMIT 0,20");
        for ($i=0;$i<count($roll);$i++){
            if(is_mobile($roll[$i]['nickname'])) $roll[$i]['nickname'] = phoneToStar($roll[$i]['nickname']);
        }
        $this->assign('roll',$roll);//滚动消息
        return $this->fetch();
    }

    /**
     * @Author :赵磊
     * 我的砍价
     */
    public function myBargain()
    {
        $list = (new BargainFoundModel())->getMyBargain($this->user_id);
        $this->assign('list',$list);
        if(IS_AJAX){
            return $this->fetch('ajax_myBargain');
        }
        return $this->fetch();
    }


    /*
     * 超时修改状态
     * */
//    public function outTime()
//    {
//        $foundId = I('found_id');
//        $condition['found_id'] = $foundId;
//        $condition['found_end_time'] = ['<',time()+3];//砍价结束
//        $res = (new BargainFoundModel())->where($condition)->update(['status'=>4]);
//        if ($res){
//            return json(['code'=>200,'msg'=>'已过期']);
//        }
//        return json(['code'=>-200,'msg'=>'未过期']);
//    }

    public function test()
    {
//        $list = (new BargainFoundModel())->getMyBargain($this->user_id);
//        halt($list);
return $this->fetch();
    }

    public function bargainRule()
    {
        return $this->fetch();
    }


}