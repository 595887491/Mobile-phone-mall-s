<?php
/**
 * @Author: 陈静
 * @Date: 2018/05/30 09:34:17
 * @Description:
 */

namespace app\common\model;


use think\Model;

class FlashRemindModel extends Model
{
    protected $table = 'cf_flash_remind';
    protected $pk = 'id';
    protected $resultSetType = 'collection';
}