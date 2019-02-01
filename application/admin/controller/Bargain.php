<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/8/31
 * Time: 14:36
 */

namespace app\admin\controller;


use app\mobile\model\BargainActivityModel;
use app\mobile\model\BargainFoundModel;
use think\AjaxPage;
use think\Db;

class Bargain extends Base
{

    /*
     * @Author;赵磊
     * 砍价商品列表
     * */
    public function bargainList()
    {
        $condition = [];
        //筛选条件
        $param = I('post.');
        $actType = I('typeTab');//商品类型;全部订单;1砍价免费拿;2砍价底价购
        if ($actType == 1) $condition['a.act_type'] = 0;//砍价免费拿
        if ($actType == 2) $condition['a.act_type'] = 1;//砍价底价购
        $keywords = I('keywords');
        if($keywords) $condition['b.goods_id|b.goods_name'] = ['like',"%$keywords%"];

        $activity = new BargainActivityModel();
        $list = $activity->alias('a')
            ->join(['tp_goods'=>'b'],'a.goods_id=b.goods_id')
            ->where($condition)
            ->field('a.*,b.goods_name,b.original_img,b.goods_id')
            ->order('a.sort desc')
            ->select()
            ->toArray();
        $found = new BargainFoundModel();
        for ($i=0;$i<count($list);$i++){
            $where['bargain_id'] = $list[$i]['id'];
            $where['status'] = 2;//砍价成功
            $list[$i]['success_count'] = $found->where($where)->count();//该活动砍价成功数
            $where['status'] = 1;//砍价进行中
            $list[$i]['cuting_count'] = $found->where($where)->count();//该活动砍价进行中的数量
            if ($list[$i]['time_limit']) $list[$i]['time_limit'] = ($list[$i]['time_limit'])/3600;//该活动砍价有效期
        }

        //点击排序
        if ($param['order_by'] != 'be_partner_start'){
            $orderby = array_column($list,$param['order_by']);
            if ($param['sort'] == 'asc')array_multisort($orderby, SORT_ASC, $list);
            if ($param['sort'] == 'desc')array_multisort($orderby, SORT_DESC, $list);
        }
        //分页
        $count = count($list);
        $page = new AjaxPage($count,20);
        if ($list) $list = array_slice($list,$page->firstRow,20);


        $show = $page->show();
        $this->assign('keywords',$keywords);//搜索
        $this->assign('count',$count);//搜索结果数量
        $this->assign('lists',$list);//搜索结果
        $this->assign('page',$show);//赋值分页
        if (IS_AJAX){
            return $this->fetch('bargainList_ajax');
        }
        return $this->fetch();
    }


    /*
     * @Author :赵磊
     * 删除
     * */
    public function delete()
    {
        $id = I('id');
        $res = (new BargainActivityModel())->where('id',$id)->delete();//
        if ($res){
            return json(['code'=>200,'msg'=>'删除成功']);
        }else{
            return json(['code'=>-200,'msg'=>'删除失败']);
        }
    }


    /*
     * @Author:赵磊
     * 新增和编辑砍价商品
     * */
    public function addEdit()
    {
        $data = input("post.");
        $id = I('id');
        //编辑
        if ($id){
            $info = (new BargainActivityModel())->where('id',$id)->find();//编辑时详情
            if($info['time_limit']) $info['time_limit'] = $info['time_limit'] /3600;
            $goodsList = Db::table('tp_goods')->where('goods_id', $info['goods_id'])->select();
            $this->assign('goodsList', $goodsList);
            $this->assign('info',$info);
        }
        //新增
        if (IS_POST){
            if($data['act_type'] == 0) $data['min_price'] = 0;//为免费那的时候,底价为零
            if (empty($data['time_limit'])) {
                $this->ajaxReturn(['status'=>0, 'msg'=>'有效期不能为空']);
            }
            if (empty($data['share_img'])) {
                $this->ajaxReturn(['status'=>0, 'msg'=>'分享图片不能为空']);
            }
            if (empty($data['goods'])) {
                $this->ajaxReturn(['status'=>0, 'msg'=>'请选择砍价活动商品']);
            }
            $goods_arr = array_keys($data['goods']);
            $data['goods_id'] = $goods_arr[0];//所选商品id
            if ($data['goods_id']) $data['act_name'] = Db::table('tp_goods')->where('goods_id',$data['goods_id'])->getField('goods_name');
            $data['time_limit'] = $data['time_limit'] * 3600;
            if ($id) {
                Db::table('cf_bargain_activity')->where('id',$id)->save($data);
                adminLog("管理员修改了砍价商品 " . $data['goods_id']);
            } else {
                Db::table('cf_bargain_activity')->add($data);
                adminLog("管理员添加了管理员修改了砍价商品 " . $data['goods_ids']);
            }
            $this->ajaxReturn(['status'=>1, 'msg'=>'操作成功']);
        }

        //模板下拉框选择
        $temp0 = Db::table('cf_bargain_template')->field('template_id,template_name')->where('status=1 and act_type=0')->select();//免费领
        $temp1 = Db::table('cf_bargain_template')->field('template_id,template_name')->where('status=1 and act_type=1')->select();//底价购
        $this->assign('temp0',$temp0);
        $this->assign('temp1',$temp1);
        return $this->fetch();
    }




    /*
     * @Author:赵磊
     * Ajax更改砍价商品状态 排序
     * */
    public function changeStatus()
    {
        $table = I('table'); // 表名
        $id_name = I('id_name'); // 表主键id名
        $id_value = I('id_value'); // 表主键id值
        $field  = I('field'); // 修改哪个字段
        $value  = I('value'); // 修改字段值
        $res = Db::table($table)->where("$id_name = $id_value")->save([$field=>$value]); // 根据条件保存修改的数据
        if ($res !== false){
            return json(['code'=>200,'msg'=>'操作成功']);
        }else{
            return json(['code'=>-200,'msg'=>'操作失败']);
        }
    }


    /*
     * @Author :赵磊
     * 砍价金额模板配置
     * */
    public function amountConfig()
    {
        $sort = I('sort','desc');
        $count = Db::table('cf_bargain_template')->count();
        $page = new AjaxPage($count,20);
        $show = $page->show();
        $res = Db::table('cf_bargain_template')->limit($page->firstRow,$page->listRows)->order("template_id $sort")->select();
        for ($i=0;$i<count($res);$i++){
            $res[$i]['goods_count'] = Db::table('cf_bargain_activity')->where('template_id',$res[$i]['id'])->count();//使用模板活动商品数
        }
        $this->assign('lists',$res);
        $this->assign('page',$show);
        if (IS_AJAX){
            return $this->fetch('amountConfig_ajax');
        }
        return $this->fetch();
    }

    /*
     * @Author:赵磊
     * 删除金额配置模板
     * */
    public function deleteConfig()
    {
        $id = I('id');
        $res = Db::table('cf_bargain_template')->where('template_id',$id)->delete();//
        if ($res){
            return json(['code'=>200,'msg'=>'删除成功']);
        }else{
            return json(['code'=>-200,'msg'=>'删除失败']);
        }
    }

    /*
     * @Author:赵磊
     * */
    public function addEditConfig()
    {
        $config = tpCache('bargain','','cf_config');
        $bargain_num = $config['bargain_count_follow_day'];//允许好友每天砍价的次数
        $this->assign('bargain_num',$bargain_num);
        $param = I('post.');
        $template_id = I('template_id');
        $param = $this->handle($param,1);//数据处理,转字串

        if ($template_id) {
            //编辑操作
            $info = Db::table('cf_bargain_template')->where('template_id', $template_id)->find();//编辑预览
            $info = $this->handle($info,2);//转数组
            $this->assign('info', $info);
        }
        if(IS_POST){
            if ($template_id) {
                //编辑操作
                $info = Db::table('cf_bargain_template')->where('template_id',$template_id)->find();//编辑预览
                $this->assign('info',$info);
                $res = Db::table('cf_bargain_template')->where('template_id',$template_id)->save($param);
                if ($res){
                    $this->ajaxReturn(['status'=>1, 'msg'=>'操作成功']);
                }else{
                    $this->ajaxReturn(['status'=>-1, 'msg'=>'操作失败']);
                }
                adminLog("管理员修改了砍价金额模板配置 " . $template_id);
            }else{
                unset($param['template_id']);
                $res = Db::table('cf_bargain_template')->add($param);
                adminLog("管理员添加了管理员修改了砍价金额模板配置 " . $param['template_name']);
                if ($res){
                    $this->ajaxReturn(['status'=>1, 'msg'=>'操作成功']);
                }else{
                    $this->ajaxReturn(['status'=>-1, 'msg'=>'操作失败']);
                }
            }

        }

        return $this->fetch();
    }
    //砍价金额配置模板数据处理
    public function handle($param,$type)
    {
        //数组转字串
        if ($type==1){
            if ($param['found_price_max_percent'])$param['found_price_max_percent'] = implode(',',$param['found_price_max_percent']);
            if ($param['found_price_min_percent'])$param['found_price_min_percent'] = implode(',',$param['found_price_min_percent']);
            if ($param['old_follow_price_max_percent'])$param['old_follow_price_max_percent'] = implode(',',$param['old_follow_price_max_percent']);
            if ($param['old_follow_price_min_percent'])$param['old_follow_price_min_percent'] = implode(',',$param['old_follow_price_min_percent']);
            if ($param['new_follow_price_max_percent'])$param['new_follow_price_max_percent'] = implode(',',$param['new_follow_price_max_percent']);
            if ($param['new_follow_price_min_percent'])$param['new_follow_price_min_percent'] = implode(',',$param['new_follow_price_min_percent']);
            if ($param['old_follow_price_max'])$param['old_follow_price_max'] = implode(',',$param['old_follow_price_max']);
            if ($param['old_follow_price_min'])$param['old_follow_price_min'] = implode(',',$param['old_follow_price_min']);
        }
        if ($type==2){
            if ($param['found_price_max_percent'])$param['found_price_max_percent'] = explode(',',$param['found_price_max_percent']);
            if ($param['found_price_min_percent'])$param['found_price_min_percent'] = explode(',',$param['found_price_min_percent']);
            if ($param['old_follow_price_max_percent'])$param['old_follow_price_max_percent'] = explode(',',$param['old_follow_price_max_percent']);
            if ($param['old_follow_price_min_percent'])$param['old_follow_price_min_percent'] = explode(',',$param['old_follow_price_min_percent']);
            if ($param['new_follow_price_max_percent'])$param['new_follow_price_max_percent'] = explode(',',$param['new_follow_price_max_percent']);
            if ($param['new_follow_price_min_percent'])$param['new_follow_price_min_percent'] = explode(',',$param['new_follow_price_min_percent']);
            if ($param['old_follow_price_max'])$param['old_follow_price_max'] = explode(',',$param['old_follow_price_max']);
            if ($param['old_follow_price_min'])$param['old_follow_price_min'] = explode(',',$param['old_follow_price_min']);
        }
        return $param;
    }



    /*
     * @Author:赵磊
     * 砍价规则配置
     * */
    public function ruleConfig()
    {
        return $this->fetch();
    }




}