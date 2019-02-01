<?php
/**
 * tpshop
 * ============================================================================
 * 版权所有 2015-2027 深圳搜豹网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.tp-shop.cn
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * Author: IT宇宙人
 * Date: 2015-09-09
 */
namespace app\common\model;

use think\Exception;
use think\Model;

class SkinTypeModel extends Model
{
    protected $table = 'cf_skin_type';
    protected $pk = 'id';

    public function getSkinTypeDatas()
    {
        $datas = $this->alias('a')
            ->field('a.id as first_id,b.id as second_id,a.name as first_name,b.name as second_name')
            ->join(['cf_skin_type' => 'b'],'a.id = b.parent_id','LEFT')
            ->where('a.status',1)
            ->where('a.level',1)
            ->select();
        $skinDatas = [];
        foreach ($datas as $k => $v) {
            $skinDatas[$v['first_id']]['name'] = $v['first_name'];
            $skinDatas[$v['first_id']][$v['second_id']]['second_name'] = $v['second_name'];
        }

        return $skinDatas;
    }
}
