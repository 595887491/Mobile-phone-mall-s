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
 * Author: IT宇宙人      
 * 
 * Date: 2016-03-09
 */

namespace app\admin\controller;
use app\common\model\AgentLevelModel;
use app\common\model\DistributorLevelModel;
use gmars\nestedsets\NestedSets;
use think\Page;
use app\admin\logic\GoodsLogic;
use think\Db;

class Divide extends Base {
    
    /*
     * 初始化操作
     */
    public function _initialize() {
       parent::_initialize();
    }    
    
    /**
     * 移动层级关系
     */
    public function moveHierarchy(){
//        $moveId = I('get.move_id');//移动的人
//        $parentId = I('get.parent_id');//移动到哪
        $moveId = 2966;
        $parentId = 13;
        $identity = 'common';

        //移动普通会员之间的层级
        if ($identity == 'common') {
            $nest = new NestedSets((new DistributorLevelModel()));
            halt($nest->moveUnder($moveId,$parentId,'bottom'));
        }

//        $moveId = 2966;
//        $parentId = 13;
//        $identity = 'partner';
        //移动合伙人对应代理商的层级
        if ($identity == 'partner') {
            halt(Db::table('cf_user_relation_role')
                ->where('user_id' , '=', $moveId)->update(['p_partner_id' => $parentId]));
        }

//        $moveId = 2966;
//        $parentId = 13;
//        $identity = 'agent';
        //移动应代理商的层级
        if ($identity == 'agent') {
            $nest = new NestedSets((new AgentLevelModel()));
            halt($nest->moveUnder($moveId,$parentId,'bottom'));
        }

    }
    
    
    //添加层级关系
    public function addHierarchy()
    {
        $moveId = 2966;
        $parentId = 13;
        $identity = 'common';

        //移动普通会员之间的层级
        if ($identity == 'common') {
            $nest = new NestedSets((new DistributorLevelModel()));
            halt($nest->moveUnder($moveId,$parentId,'bottom'));
        }

//        $moveId = 2966;
//        $parentId = 13;
//        $identity = 'partner';
        //移动合伙人对应代理商的层级
        if ($identity == 'partner') {
            halt(Db::table('cf_user_relation_role')
                ->where('user_id' , '=', $moveId)->update(['p_partner_id' => $parentId]));
        }

//        $moveId = 2966;
//        $parentId = 13;
//        $identity = 'agent';
        //移动应代理商的层级
        if ($identity == 'agent') {
            $nest = new NestedSets((new AgentLevelModel()));
            halt($nest->moveUnder($moveId,$parentId,'bottom'));
        }
    }
    

}