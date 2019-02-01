<?php
/**
 * @Author: 陈静
 * @Date: 2018/09/05 09:34:39
 * @Description:
 */

namespace app\mobile\controller;

use app\common\library\Logs;
use app\common\logic\OssLogic;
use app\common\logic\distribution\DistributionDevideLogLogic;
use app\common\logic\wechat\WechatUtil;
use app\common\logic\WechatLogic;
use app\common\model\DistributeDivideLog;
use app\mobile\model\VoteActivityModel;
use app\mobile\model\VoteFocusModel;
use app\mobile\model\VoteFoundModel;
use think\AjaxPage;
use app\common\model\GoodsTopic;
use think\Cache;
use think\Db;
use think\Page;
use think\Session;

class Vote extends MobileBase
{
    private $activityModel;
    public function _initialize()
    {
        parent::_initialize();
        $nologin = [
            'index','getFoundUsers','openPrize','voteMainInfo','clickVote','voteRule'
        ];
        $this->checkUserLogin($nologin);

        //检测是否活动过期
        $this->checkActivityStatus();
    }

    const VOTECOUNTLIMIT=3;//每日上限投票数
    const VOTEID=1;//活动id

    private function checkActivityStatus()
    {
        $this->activityModel = new VoteActivityModel();
        $activity = $this->activityModel->field('end_time,status')->where('id',self::VOTEID)->find();

        if (($activity->end_time <= time()) && ($activity->status != 3) ) {
            $this->activityModel->where('id',self::VOTEID)->update([ 'status' => 2 ]);
        }
    }

    //开奖通知
    public function openLotteryNotify()
    {
        exec('/usr/local/php7.1/bin/php think send_hanfu_remind > /dev/null 2>&1 &',$resultArr,$result);
        return outPut(1,'success');
    }


    //首页
    public function index()
    {
        $act = Db::table('cf_vote_activity')->where('id',self::VOTEID)->find();
        $this->assign('cover',$act['cover']);
        $status = $act['status'];
        $this->assign('status',$status);
        if($act['is_online'] == 0) $this->error('该投票活动已下线');
        if ($status == 3)$this->redirect('Vote/openPrize');
        $this->assign('user_id',$this->user_id);

        $foundModel = new VoteFoundModel();
        $foundInfo = $foundModel->getUserFoundInfo($this->user_id);
        if ( $foundInfo['user_id'] ) {
            $foundInfo['user_rank'] = $foundModel->getUserRank($this->user_id);
        }
        $this->assign('found_info',$foundInfo);

        $headPic = $this->user['head_pic'];

        $this->assign('head_pic',$headPic);

        //活动信息
        $activityInfo = $this->activityModel->getActivityInfo(self::VOTEID);

        $this->assign('activity_info',$activityInfo);

        $foundCount = $foundModel->where('status',1)->count();
        $this->assign('found_count',$foundCount);
        $ua = $_SERVER['HTTP_USER_AGENT'];
        //获取授权,是否关注公众号信息
        if(strpos($ua,"MicroMessenger")!==false){ //判断是否在微信浏览器打开
            if (!Session::has('subscribe')){
                $loginInfo = $this->GetOpenid();
                $subscribe = $loginInfo['subscribe'];//获取subscribe,1为已关注
                Session::set('subscribe',$subscribe);
            }
        }
        return $this->fetch('vote/hanfu/index');
    }

    //参赛者信息
    public function getFoundUsers()
    {
        $sort = I('get.sort','found_time');
        $foundModel = new VoteFoundModel();
        $foundCount = $foundModel->where('status',1)->count();
        $pageObj = new AjaxPage($foundCount);

        $foundList = $foundModel->field('found_id,my_photo,title,slogan,vote_number')
            ->where('status',1)
            ->limit($pageObj->firstRow,$pageObj->listRows)
            ->order("$sort DESC,found_id")
            ->select()->toArray();

        $this->assign('found_list',$foundList);
        return $this->fetch('vote/hanfu/getFoundUsers');
    }

    //活动规则页面
    public function voteRule()
    {
        //活动信息
        $activityInfo = $this->activityModel->getActivityInfo(self::VOTEID);

        $this->assign('rule',$activityInfo['rule']);

        return $this->fetch('vote/hanfu/voteRule');
    }

    //上传图片接口
    public function uploadPic()
    {
        $base64_image_content = I('post.dataURL');
        if (preg_match('/^(data:\s*image\/(\w+);base64,)/',$base64_image_content,$result)){
            $type = $result[2];//图片后缀
            $new_file = TEMP_PATH;
            $filename = time() . '_' . uniqid() . ".{$type}"; //文件名
            $new_file = $new_file . $filename;
            //写入操作
            if(file_put_contents($new_file, base64_decode(str_replace($result[1], '', $base64_image_content)))) {
                $result1 = (new OssLogic())->uploadFile($new_file,'images/hanfu/'.date('Ymd').'/'.$filename);
                if ($result1) {
                    @unlink($new_file);
                    return outPut(1,'success',['my_photo' => config('aliyun_oss')['Oss_cdn'].$result1 ]);
                }
                return outPut(-1,'aliyun oss error');
            }
            return outPut(-1,'write file error');
        }
    }

    public function createQrcode($userId,$foundId)
    {
        $wechatUtil = new WechatUtil();
        $res = $wechatUtil->createTempQrcode(30*24*3600,'hanfu|'.$userId.'|'.$foundId);
        if ( $res && isset($res['url']) && $res['url'] ) {
            return $res['url'];
        }
        return 'error';
    }

    //完善资料
    public function perferInfo()
    {
        //检测是否有此用户的信息
        $foundModel = new VoteFoundModel();
        $userFoundCount = $foundModel->where('user_id',$this->user_id)
            ->where('status',1)->count();

        if ($userFoundCount) {
            //查找用户的信息
            $foundInfo = $foundModel->getUserFoundInfo($this->user_id);
            $this->assign('have_join',1);
        }else{
            $this->assign('have_join',0);
        }

        if (IS_AJAX) {
            $postData = I('post.');

            if ($userFoundCount) {
                $foundInfo->my_photo = $postData['my_photo'];
                $foundInfo->title = $postData['title'];
                $foundInfo->slogan = $postData['slogan'];
                $res = $foundInfo->save();
                if ($res !== false) {
                    return outPut(1,'sussess',['found_id' => $foundInfo->found_id]);
                }
                return outPut(-1,'error');
            }else{
                $data['found_time'] = time();
                $data['title'] = $postData['title'];
                $data['slogan'] = $postData['slogan'];
                $data['user_id'] = $this->user_id;
                $data['vote_id'] = self::VOTEID;
                $data['status'] = 1;
                $data['my_photo'] = $postData['my_photo'];
                $result2 = $foundModel->insertGetId($data);
                if ($result2) {
                    return outPut(1,'success',['found_id' => $result2 ]);
                }
                return outPut(-1,'change db error');
            }
        }
        $slogan = (new VoteActivityModel())->where('id',self::VOTEID)->getField('slogan');

        $this->assign('slogan',$slogan);
        $this->assign('found_info',$foundInfo);
        return $this->fetch('vote/hanfu/perferInfo');
    }

    //赠送投票次数
    public function giveVoteTime($msg)
    {
        $arr = explode('|',$msg['EventKey']);
        $userId = $arr[1];
        $foundId = $arr[2];

        if (!$userId) {
            return;
        }
        $voteFocusModel = new VoteFocusModel();
        $count = $voteFocusModel->where('follow_user_id',$userId)->count();
        if ($count) {
            return;
        }
        $followFocusDayTimes = $this->activityModel->where('id',self::VOTEID)->getField('follow_focus_day_times');
        $data['follow_user_id'] = $userId;
        $data['focus_vote_count'] = $followFocusDayTimes;
        $data['focus_time'] = time();
        $result = $voteFocusModel->insertGetId($data);
        if (!$result) {
            Logs::sentryLogs('赠送投票次数失败',$msg);
        }
        $txt = '奖励的投票次数已发放(奖励不可重复领取哟)，点击此链接继续投票：<a href="'.SITE_URL.url('Mobile/vote/voteMainInfo',['found_id' => $foundId ]).'">投票页面</a>';
        //推送
        $resultStr = (new WechatUtil())->createReplyMsgOfText( $msg['ToUserName'], $msg['FromUserName'] , $txt);
        exit($resultStr);
    }










    /*
  * @Author:赵磊
  * 投票详情
  * */
    public function voteMainInfo()
    {
        $foundid = I('found_id');
        if (empty($foundid))$this->error('参赛信息有误');

        //发起投票人信息
        $condition['status'] = 1;
        $condition['found_id'] = $foundid;
        $info = Db::table('cf_vote_found')
            ->field('a.*,b.head_pic')
            ->alias('a')->join(['tp_users'=>'b'],'a.user_id=b.user_id')
            ->where($condition)
            ->find();
        if (empty($info))$this->error('该图片审核未通过');
        if ($info['user_id']==$this->user_id) $is_mine = 1;//是自己的详情
        //活动状态
        $activityInfo = Db::table('cf_vote_activity')
            ->where('id',1)
            ->find();
        $info['activity_status'] = $activityInfo['status'];
        if ($activityInfo['is_online'] == 0) $this->error('该投票活动已下线');
        //最新投票
        $info['list'] = $this->getNewVoteInfo($foundid);

        //投票详情商品
        $goods = (new GoodsTopic())->field('goods_id')->where('topic_id',$activityInfo['goods_topic_id'])->find();
        if ($goods->goods_id) $goodsArr = explode(',',$goods->goods_id);//商品id数组
        $count = count($goodsArr);
        $page = new Page($count,20);
        $where['goods_id'] = ['in',$goodsArr];
        $goodsInfo = Db::table('tp_goods')
            ->distinct(true)
            ->field('original_img,goods_remark,goods_name,goods_id,shop_price,market_price')
            ->where($where)->limit($page->firstRow,$page->listRows)->select();
        dealGoodsPrice($goodsInfo);
        $this->assign('goodsInfo',$goodsInfo);
        $this->assign('info',$info);
        $this->assign('is_mine',$is_mine);
        $this->assign('found_id',$foundid);
        if (IS_AJAX){
            return $this->fetch('vote/hanfu/voteMainInfo_ajax');
        }
        return $this->fetch('vote/hanfu/voteMainInfo');
    }





    /*
     * @Author:赵磊
     * 点击投票
     * */
    public function clickVote()
    {
        if (empty($this->user_id)){
            return json(['code'=>-300,'msg'=>'未登录']);
        }
        $found = I('found_id');
        //每日投票上限次数
        $act = Db::table('cf_vote_found')
            ->alias('a')->join(['cf_vote_activity'=>'b'],'a.vote_id=b.id')
            ->field('b.follow_focus_day_times,a.user_id,b.status,a.status a_status')
            ->where('a.found_id',$found)
            ->find();
        $actcount = $act['follow_focus_day_times'];//上限票数
        if ($act['status']==2 || $act['status']==3) return json(['code'=>-500,'msg'=>'投票结束,等待开奖']);
        if ($act['a_status']==0) return json(['code'=>-500,'msg'=>'投票失败,参赛审核未通过']);//参赛人员未通过审核
        $data['follow_user_id'] = $this->user_id;//投票人id
        $data['found_id'] = $found;//参赛id
        $data['vote_id'] = I('vote_id');//活动id
        $data['follow_time'] = time();//当前时间
        $today = date("Y-m-d",time());//当天开始时间
        $todayend = date("Y-m-d",strtotime($today) + 24*3600);//当天结束时间
        $vote_count = Db::table('cf_vote_follow')->where('follow_time','between time',[$today,$todayend])->where('follow_user_id',$this->user_id)->count();//当前用户当日已投票次数
        //已获得关注奖励
        $prize_count = Db::table('cf_vote_focus')->where('focus_time','between time',[$today,$todayend])->where('follow_user_id',$this->user_id)->getField('focus_vote_count');

        $head_pic = Db::table('tp_users')->where('user_id',$this->user_id)->getField('head_pic');
        if ($vote_count <= $actcount+$prize_count-1){//当日投票次数未达上限
            $surplus = $actcount+$prize_count-1-$vote_count;//剩余多少投票次数,每日上限减一再减去已投
            $res = Db::table('cf_vote_follow')->add($data);//投票新增
            //参赛者已投票数
            $counts = Db::table('cf_vote_found')->alias('a')->join(['cf_vote_follow'=>'b'],'a.found_id=b.found_id')->where('a.found_id',$found)->count();
            $res2 = (new VoteFoundModel())->where('found_id',$found)->update(['vote_number'=>$counts]);//改变票数
            if ($res && $res2){
                $this->dayFirstVote($found,$counts,$act['user_id'],$this->user_id);
                $newVoteList = $this->getNewVoteInfo($found);
                return json(['code'=>200,'msg'=>"投票成功,剩余投票次数 $surplus 次",'count'=>$surplus,'voteCounts'=>$counts,'newVoteList'=>$newVoteList]);
            }else{
                return json(['code'=>-200,'msg'=>'投票失败']);
            }
        }else{ //当日投票次数已达上限,查看是否有关注赠送的次数
            if ($prize_count){ //已获得关注奖励
                return json(['code'=>-200,'msg'=>'当日投票次数已用尽,请明日再来']);
            }else{
                //未获得关注奖励
                if(Session::get('subscribe')!=1){
                    $url = $this->createQrcode($this->user_id,$found);
                    $img = "/index.php?m=Home&c=Index&a=qr_code&data=$url&head_pic=$head_pic";
                    return json(['code'=>-400,'msg'=>'关注公众号获取次数奖励','img'=>$img]);
                }else{
                    return json(['code'=>-200,'msg'=>'当日投票次数已用尽,请明日再来']);
                }
            }
        }
    }


    /*
     * 最新投票信息
     * */
    public function getNewVoteInfo($found_id)
    {
        $info = Db::query("SELECT DISTINCT  `a`.`follow_user_id`,`b`.`head_pic` FROM `cf_vote_follow` `a` INNER JOIN `tp_users` `b` ON `a`.`follow_user_id`=`b`.`user_id` WHERE  `a`.`found_id` = $found_id ORDER BY follow_id DESC limit 0,10");
        return $info;
    }

    /*
     * @Author:赵磊
     * 开奖
     * */
    public function openPrize()
    {
        $act = Db::table('cf_vote_activity')->where('id',self::VOTEID)->find();
        $status = $act['status'];
        if ($act['is_online'] == 0) $this->error('该投票活动已下线');
        if ($status == 1 || $status == 2)$this->redirect('Vote/index');
        $foundModel = new VoteFoundModel();
        $activiId = 1;
        //是否结束
        $is_over = (new VoteActivityModel())->where('id',$activiId)->getField('status');
        $user_rank = 0;//当前排名
        //是否登录
        if ($this->user_id){
            $is_login = 1;
            $userInfo = $foundModel->where('user_id',$this->user_id)->find();//当前登录用户信息
            $user_rank = (new VoteFoundModel())->getUserRank($this->user_id);
        }else{
            $is_login = 0;//未登录
        }
        //是否参加活动
        if ($userInfo){
            $is_join = 1;
        }else{
            $is_join = 0;//未参加
        }
        //是否中奖且下单
        $condition1['user_id'] = $this->user_id;
        $condition1['admin_note'] = '汉服投票中奖(禁止修改)';
        $is_order = Db::table('tp_order')->where($condition1)->find();
        if ($is_order){
            $order['is_order'] = 1;
            $order['order_id'] = $is_order['order_id'];
        }else{
            $order['is_order'] = 0;//未下单
        }

        //获得奖项
        if ($userInfo['is_win'] == 1){
            $prizeName = $this->prizeConfig($userInfo['prize_level']);
            $this->assign('prizeName',$prizeName['name']);//获得的奖项名
            $this->assign('prizeGoods',$prizeName['goods_id']);//获得的奖品id
        }

        //全部排行
        $condition['status'] = 1;//参赛人员状态,0关闭,1正常
        $count = $foundModel->where($condition)->count();//参赛人总数量
        $page = new Page($count,20);
        $ranking = $foundModel->where('status',1)
            ->order('vote_number desc,found_time asc')
            ->limit($page->firstRow,$page->listRows)
            ->select()->toArray();
        foreach ($ranking as $k=>$v){
            $ranking[$k]['user_rank'] = $foundModel->getUserRank($v['user_id']);;
        }

        $this->assign('user_rank',$user_rank);
        $this->assign('ranking',$ranking);//排行
        $this->assign('is_over',$is_over);//是否结束
        $this->assign('is_login',$is_login);//是否登录
        $this->assign('is_join',$is_join);//是否参加活动
        $this->assign('order',$order);//是否下单
        $this->assign('is_win',$userInfo['is_win']);//是否获奖 0-未获奖 1-获奖
        if (IS_AJAX){
            return $this->fetch('vote/hanfu/openPrize_ajax');
        }

        //获奖用户
        //一等奖
        $is_win = 1;
        $prize_level = 1;
        $orderby = 'vote_number desc,found_time asc';
        $prize_user['one'] = $foundModel->where("is_win=$is_win and prize_level=$prize_level")->order($orderby)->select()->toArray();
        $prize_user['onePrizeInfo'] = $this->prizeConfig($prize_level);//奖励配置
        //二等奖
        $prize_level = 2;
        $prize_user['two'] = $foundModel->where("is_win=$is_win and prize_level=$prize_level")->order($orderby)->select()->toArray();
        $prize_user['twoPrizeInfo'] = $this->prizeConfig($prize_level);
//        halt($prize_user);
        //三等奖
        $prize_level = 3;
        $prize_user['three'] = $foundModel->where("is_win=$is_win and prize_level=$prize_level")->order($orderby)->select()->toArray();
        $prize_user['threePrizeInfo'] = $this->prizeConfig($prize_level);
        //入围奖
        $prize_level = 4;
        $prize_user['four'] = $foundModel->where("is_win=$is_win and prize_level=$prize_level")->order($orderby)->select()->toArray();
        $prize_user['fourPrizeInfo'] = $this->prizeConfig($prize_level);


        $this->assign('prize_user',$prize_user);//排行
        return $this->fetch('vote/hanfu/openPrize');
    }


    /*
     * @Author:zhaolei
     * 奖励配置
     * */
    public function prizeConfig($prize_level='',$activity_id=1)
    {
        $activity = (new VoteActivityModel())->where('id',$activity_id)->find();
        $prize_setting = $activity->prize_setting;//奖项设置
        $prize_setting = \GuzzleHttp\json_decode($prize_setting,true);
        for($i=1;$i<count($prize_setting)+1;$i++){
            if ($prize_setting[$i]['level'] == $prize_level){
                $info = $prize_setting[$i];
                break;
            }
        }
        return $info;
    }


    /*
     *@Author:赵磊
     * 用户当日首次给参赛者投票tuisong
     *  */
    public function dayFirstVote($foundId,$counts,$cs_userId,$tp_userId)
    {
        $today = date("Y-m-d",time());//当天开始时间
        $todayend = date("Y-m-d",strtotime($today) + 24*3600);//当天结束时间
        $condition['found_id'] = $foundId;
        $condition['follow_user_id'] = $this->user_id;
        $res = Db::table('cf_vote_follow')->where('follow_time','between time',[$today,$todayend])->where($condition)->count();//当天
        if ($res == 1 && $cs_userId != $this->user_id){  //当日给某个参赛者首次投票推送
            $info['found_id'] = $foundId;
            $info['num'] = $counts;
            $info['nickname'] = Db::table('tp_users')->where('user_id',$tp_userId)->getField('nickname');
            $info['openid'] = Db::table('tp_oauth_users')->where('user_id',$cs_userId)->getField('openid');
            (new WechatLogic())->sendTemplateMsgOnVoteSuccess($info);
            return true;
        }
        return false;
    }

}