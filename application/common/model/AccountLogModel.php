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

class AccountLogModel extends Model
{
    protected $table = 'tp_account_log';
    protected $pk = 'id';
    protected $resultSetType = 'collection';

    //添加余额明细
    public function addDistributionDatas($divide_data,$wallet_balance)
    {
        $data = [];
        //普通用户才会有余额明细
        $data['user_id'] = $divide_data['to_user_id'];
        $data['user_money'] = $divide_data['divide_money'];
        $data['wallet_balance'] = $wallet_balance;
        $data['change_time'] = time();
        $data['desc'] = '普通会员分成，自动转入余额';
        $data['order_sn'] = $divide_data['order_sn'];

        $result = $this->insert($data);
        if (!$result) {
            throw new Exception('插入余额明细失败');
        }
        return $result;
    }

}
