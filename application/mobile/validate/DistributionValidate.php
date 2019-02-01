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
 * Author: dyr
 * Date: 2016-08-23
 */

namespace app\mobile\validate;

/**
 * 用户分销验证器
 * Class Distribut
 * @package app\mobile\validate
 */
class DistributionValidate extends BaseValidate
{
    //验证规则
    protected $rule = [
        'bank_name'    =>'require',
        'bank_card'     =>'require|checkBankCard',
        'money'            =>'number',
        'realname'        =>'require',
    ];

    //错误信息
    protected $message  = [
        'bank_name.require'    => '银行卡名必须填写',
        'bank_card.require'     => '银行卡号必须填写',
        'bank_card.checkBankCard'     => '正确填写银行卡号',
        'money.number'             => '金额必须是数字',
        'realname.require'        => '真实姓名必须填写',
    ];

    protected $scene = [
        'add'  =>  ['bank_name','bank_card','money','realname'],
    ];

    /**
     * 检查手机格式
     * @param $value|验证数据
     * @param $rule|验证规则
     * @param $data|全部数据
     * @return bool|string
     */
    protected function checkMobile($value, $rule ,$data)
    {
        return check_mobile($value);
    }

    /**
     * 检查手机格式
     * @param $value|验证数据
     * @param $rule|验证规则
     * @param $data|全部数据
     * @return bool|string
     */
    protected function checkBankCard($value, $rule ,$data)
    {

        preg_match('/^([1-9]{1})(\d{15}|\d{18})$/', $value,$match);

        return isset($match[0]) && $match[0] ? true : false;
    }
}