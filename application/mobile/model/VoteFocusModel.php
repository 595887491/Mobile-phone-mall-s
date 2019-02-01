<?php
/**
 * @Author: 陈静
 * @Date: 2018/09/05 10:34:57
 * @Description:
 */

namespace app\mobile\model;


use app\common\model\Goods;
use think\Model;

class VoteFocusModel extends Model
{
    protected $table = 'cf_vote_focus';
    protected $resultSetType = 'collection';
}