<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/9/6
 * Time: 14:19
 */

namespace app\admin\controller;


use app\admin\model\VoteActivity;
use app\admin\model\VoteFound;
use app\common\logic\OssLogic;
use app\common\logic\WechatLogic;
use app\mobile\model\VoteActivityModel;
use app\mobile\model\VoteFoundModel;
use think\AjaxPage;
use think\Db;
use think\Page;

class Vote extends Base
{
    public function index(){
        $status = I('get.status/d',-1);
        $this->assign('status',$status);
        return $this->fetch();
    }
    public function getNextData()
    {
        $model = new VoteActivity();
        $act_name = I('post.act_name','','trim');
        $status = I('get.status/d',-1);
        $sort = I('sort');
        $orderBy = I('order_by');

        $where = [];
        if ($act_name) {
            $where['a.act_name'] = ['like', '%' . $act_name . '%'];
        }
        if ($status >= 0) {
            $where['a.status'] = $status;
        }
        $count = $model->alias('a')->where($where)->count();
        $Page = $pager = new AjaxPage($count,10);
        $show = $Page->show();

        $act_list = $model->alias('a')
            ->field('a.*,count(f.found_id) as number')
            ->join(['cf_vote_found' => 'f'],'a.id = f.vote_id','left')
            ->where($where)
            ->limit($Page->firstRow.','.$Page->listRows)
            ->group('a.id');

        if ($sort) {
            $act_list = $act_list->order("$orderBy $sort")->select();
        }else{
            $act_list = $act_list->select();
        }

        $this->assign('act_list',$act_list);
        $this->assign('page',$show);// 赋值分页输出
        $this->assign('pager',$pager);// 赋值分页输出
        return $this->fetch();
    }


    public function add()
    {
        $id = I('id',0);
        if ($id) {
            $voteActivity = new VoteActivity();
            $info = $voteActivity->where('id' , $id)->find();
            $info->slogan = join(PHP_EOL,json_decode($info->slogan,true));

            $info->prize_setting = json_decode($info->prize_setting,true);

            $this->assign('info',$info);
        }
        return $this->fetch();
    }


    public function save()
    {
        $data = I('post.');
        if ( count(array_filter($data)) < 10 ) {
            return $this->ajaxReturn([
                'status' => -1,
                'msg' => '必填数据未填写'
            ]);
        }
        $data['status'] = 1;
        $data['start_time'] = strtotime($data['start_time']);
        $data['end_time'] = strtotime($data['end_time']);
        $data['slogan'] = preg_replace('#\r\n#','|',trim($data['slogan']));
        $data['slogan'] = json_encode(explode('|',$data['slogan']));
        if (isset($data['is_vote_found']) && $data['is_vote_found'] == 'on') {
            $data['is_vote_found'] = 1;
        }else{
            $data['is_vote_found'] = 0;
        }

        //处理二维码图片
        $file = file_get_contents('https://dev.cfo2o.com/Home/Index/qr_code?data='.$data['qrcode_url']);

        $new_file = TEMP_PATH;
        $filename = time() . '_' . uniqid() . ".jpg"; //文件名
        $new_file = $new_file . $filename;
        //写入操作
        if(file_put_contents($new_file, $file)) {
            $result1 = (new OssLogic())->uploadFile($new_file,'images/hanfu/'.date('Ymd').'/'.$filename);
            if ($result1) {
                @unlink($new_file);
                $data['qrcode_img'] = config('aliyun_oss')['Oss_cdn'].$result1;
            }
        }

        $data['prize_setting'] = json_encode($data['prize_setting']);

        $voteActivity = new VoteActivity();
        if (isset($data['id']) && $data['id'] ) {
            $res = $voteActivity->isUpdate(true)->save($data);
        }else{
            $res = $voteActivity->insert($data);
        }

        if ($res) {
            return $this->ajaxReturn([
                'status' => 1,
                'msg' => '保存成功'
            ]);
        }
        return $this->ajaxReturn([
            'status' => -1,
            'msg' => '保存失败'
        ]);

    }





    public function delete()
    {
        $id = I('post.ids');
        $res = Db::table('cf_vote_activity')->where('id',$id)->delete();
        if ( $res ) {
            return $this->ajaxReturn([
                'status' => 1,
                'msg' => '操作成功',
                'url' => U('Vote/index')
            ]);
        }
        return $this->ajaxReturn([
            'status' => -1,
            'msg' => '操作失败',
            'url' => U('Vote/index')
        ]);

    }






    /*
     * @Author:赵磊
     * 开奖列表
     * */
    public function voteFoundList()
    {
        $foundModel = new VoteFoundModel();
        $typeTab = I('typeTab');
        $voteId = I('vote_id',1);
        $activity = Db::table('cf_vote_activity')->where('id',$voteId)->getField('status');//活动状态
        $condition['vote_id'] = $voteId;//活动id
        if ($typeTab == 1)$condition['status'] = 1;//审核通过
        if ($typeTab == 2)$condition['status'] = 0;//审核失败
        //搜索
        $keywords = I('keywords');
        if ($keywords) $condition['a.title|b.user_id|b.mobile'] = ['like',"%$keywords%"];

        //排序
        $orderby = I('order_by','found_id');
        $sort = I('sort','desc');

        $list = Db::table('cf_vote_found')
            ->alias('a')
            ->field('a.*,b.user_id,b.nickname,b.mobile,b.head_pic')
            ->join(['tp_users'=>'b'],'a.user_id=b.user_id')
            ->where($condition)
            ->order('a.vote_number desc,a.found_id asc')
            ->select();
        $count = count($list);//数据数量
        for ($i=0;$i<$count;$i++){
            $list[$i]['ranking'] = $foundModel->getUserRank($list[$i]['user_id']);
            if(is_mobile($list[$i]['nickname']))$list[$i]['nickname'] = phoneToStar($list[$i]['nickname']);
            $list[$i]['mobile'] = phoneToStar($list[$i]['mobile']);
            $foundid = $list[$i]['found_id'];
            $list[$i]['vote_users'] = Db::query("SELECT COUNT(DISTINCT follow_user_id) AS tp_count FROM `cf_vote_follow` WHERE  `found_id` = $foundid")[0]['tp_count'];
        }
        $orderby = array_column($list,$orderby);
        if ($sort == 'desc') array_multisort($orderby,SORT_DESC,$list);//数组排序
        if ($sort == 'asc') array_multisort($orderby,SORT_ASC,$list);
        $page = new AjaxPage($count,20);
        $show = $page->show();
        $list = array_slice($list,$page->firstRow,20);
        $this->assign('count',$count);//数liang
        $this->assign('page',$show);//fenye
        $this->assign('lists',$list);//列表数据
        $this->assign('activity',$activity);//活动状态
        $this->assign('vote_id',$voteId);//活动id
        if(IS_AJAX){
            return $this->fetch('voteFoundList_ajax');
        }
        return $this->fetch();
    }


    /**
     * ajax 修改指定表数据字段  一般修改状态 比如 是否推荐 是否开启 等 图标切换的
     * table,id_name,id_value,field,value
     */
    public function changeCfTableVal(){
        $foundid = I('id_value'); // 表主键id值
        $value  = I('value'); // 修改字段值
        $res = Db::table('cf_vote_found')->where('found_id',$foundid)->update(['status'=>$value]); // 根据条件保存修改的数据
        if($res){
            if ($value == 0)$this->sendExamineTem($foundid);
            return json(['code'=>200,'status'=>$value]);
        }else{
            return json(['code'=>-200,'status'=>$value]);
        }
    }

    /*
     * @Author:赵磊
     * 审核未通过通知
     * */
    public function sendExamineTem($found_id)
    {
        $info = (new VoteFoundModel())
            ->field('a.found_id,a.user_id,b.openid')
            ->alias('a')
            ->join(['tp_oauth_users'=>'b'],'a.user_id=b.user_id')
            ->where('found_id',$found_id)
            ->find();
        $res = (new WechatLogic())->sendTemplateMsgExamineFail($info);
        return $res;
    }


    /*
     * @Author:赵磊
     * 删除
     * */
    public function delVoteUser()
    {
        $foundid = I('found_id'); // 表主键id值
        $res = Db::table('cf_vote_found')->where('found_id',$foundid)->delete();
        if ($res){
            return json(['code'=>200,'msg'=>'删除成功']);
        }else{
            return json(['code'=>-200,'msg'=>'删除失败']);
        }
    }


    /*
     * @Author;zhaolei
     * 开奖
     * */
    public function openPrize()
    {
        $vote_id = I('vote_id',1);
        $open = I('open');//确认开奖
        $condition['a.status'] = 1;
        $condition['a.vote_id'] = $vote_id;
        //奖项设置
        $prize = $this->prizeConfig($vote_id);
        $actModel = new VoteActivityModel();

        $ranking = Db::table('cf_vote_found')
            ->alias('a')
            ->field('a.*,b.nickname,b.head_pic,b.mobile')
            ->join(['tp_users'=>'b'],'a.user_id=b.user_id')
            ->where($condition)
            ->order('a.vote_number desc')
            ->limit(0,$prize['num'])
            ->select();

        for($i=0;$i<count($ranking);$i++){
            $ranking[$i]['rank'] = $i+1;
            $ranking[$i]['mobile'] = phoneToStar($ranking[$i]['mobile']);
            if(is_mobile($ranking[$i]['nickname']))$ranking[$i]['nickname'] = phoneToStar($ranking[$i]['nickname']);
        }

        $prize_setting = $prize['prize_setting'];//奖项设置
        $start = 0;
        $info = [];
        for ($k=1;$k<count($prize_setting)+1;$k++){
            $len = $prize_setting[$k]['num'];//该奖颁发数
            $start += $prize_setting[$k-1]['num'];//上个奖项的数量
            if($start < 0 ) $start = 0;
            $list[$k] = array_slice($ranking,$start,$len);
            for ($i=0;$i<count($list[$k]);$i++){
                $list[$k][$i]['prizeName'] = $prize_setting[$k]['name'];//分配奖项
                $list[$k][$i]['level'] = $prize_setting[$k]['level'];
            }
            $info = array_merge($info,$list[$k]);
        }

        //开奖
        if (IS_AJAX && $open==1){
            for ($i=0;$i<count($info);$i++){
                $update[$i]['found_id'] = $info[$i]['found_id'];
                $update[$i]['is_win'] = 1;
                $update[$i]['prize_level'] = $info[$i]['level'];
            }
            $act_status = $actModel->where('id',$vote_id)->field('status,is_online')->find();//活动状态
            if ($act_status['status'] == 1) return json(['code'=>-300,'msg'=>'活动正在进行中,不能开奖']);
            if ($act_status['status'] == 3) return json(['code'=>-300,'msg'=>'活动已开奖']);
            if ($act_status['is_online'] == 0) return json(['code'=>-300,'msg'=>'活动未上线,不能开奖']);
            Db::startTrans();
            $act_res = $actModel->where('id',$vote_id)->update(['status'=>3]);//改变为活动结束开奖
            if($act_res)$res = (new VoteFoundModel())->saveAll($update);//更新
            if ($res){
                Db::commit();
                (new \app\mobile\controller\Vote())->openLotteryNotify();
                return json(['code'=>200,'msg'=>'开奖成功']);
            }
            Db::rollback();
            return json(['code'=>-200,'msg'=>'开奖失败']);
        }
        $this->assign('ranking',$info);
        $this->assign('vote_id',$vote_id);//活动id
        return $this->fetch();
    }

    /*
 * @Author:zhaolei
 * 奖励配置
 * */
    public function prizeConfig($activity_id =1)
    {
        $activity = (new VoteActivityModel())->where('id',$activity_id)->find();
        $prize_setting = $activity->prize_setting;//奖项设置
        $prize_setting = json_decode($prize_setting,true);
        for($i=1;$i<count($prize_setting)+1;$i++){
            $prize[$i] = $prize_setting[$i]['num'];
        }
        $prizeInfo['num'] = array_sum($prize);//奖项设置总人数
        $prizeInfo['prize_setting'] = $prize_setting;
        return $prizeInfo;
    }

    /*
     * @Author:赵磊
     * 投票记录
     * **/
    public function votingRecord()
    {
        $found_id = I('found_id');
        $keywords = I('keywords');
        $start = I('startTime');
        $end = I('endTime');
        $condition['a.found_id'] = $found_id;
        $condition['b.user_id|b.mobile'] = ['like',"%$keywords%"];
        if ($start && $end)$condition['a.follow_time'] = ['between time',[$start,$end]];

        $count = Db::table('cf_vote_follow')->alias('a')->join(['tp_users'=>'b'],'a.follow_user_id=b.user_id')->where($condition)->count();
        $page = new AjaxPage($count,8);
        $show = $page->show();
        $list = Db::table('cf_vote_follow')
            ->alias('a')
            ->field('a.*,b.user_id,b.head_pic,b.mobile,b.nickname')
            ->join(['tp_users'=>'b'],'a.follow_user_id=b.user_id')
            ->where($condition)
            ->limit($page->firstRow,$page->listRows)
            ->select();
        for($i=0;$i<count($list);$i++){
            $list[$i]['mobile'] = phoneToStar( $list[$i]['mobile']);
            if (is_mobile( $list[$i]['nickname'])) $list[$i]['nickname'] = phoneToStar($list[$i]['nickname']);
        }

        $this->assign('lists',$list);
        $this->assign('found_id',$found_id);
        $this->assign('keywords',$keywords);
        $this->assign('page',$show);
        if (IS_AJAX){
            return $this->fetch('votingRecord_ajax');
        }
        return $this->fetch();

    }
}