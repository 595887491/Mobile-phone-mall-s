<?php
/**
 * @Author: 陈静
 * @Date: 2018/08/29 14:17:39
 * @Description:
 */

namespace app\mobile\model;


use think\Model;

class BargainTemplateModel extends Model
{
    protected $table = 'cf_bargain_template';
    protected $resultSetType = 'collection';

    public function getBargainConfig($templateId,$reducePricePercent = '')
    {
        $defaultConfig = tpCache('bargain','' , 'cf_config' );

        $config['default'] = $defaultConfig;

        $templateConfig = $this->where('template_id',$templateId)->find();

        //自己分比例
        $selfBargainMaxPercentArr = explode(',',$templateConfig->found_price_max_percent);
        $selfBargainMinPercentArr = explode(',',$templateConfig->found_price_min_percent);

        if ($reducePricePercent) {
            $selfBargainArr = explode(',',$reducePricePercent);
        } else {
            $selfBargainArr[0] = mt_rand((int)$selfBargainMinPercentArr[0]*100,(int)$selfBargainMaxPercentArr[0]*100) / 100;
            $selfBargainArr[1] = mt_rand((int)$selfBargainMinPercentArr[1]*100,(int)$selfBargainMaxPercentArr[1]*100) / 100;
            $selfBargainArr[2] = mt_rand((int)$selfBargainMinPercentArr[2]*100,(int)$selfBargainMaxPercentArr[2]*100) / 100;
            $selfBargainArr[3] = mt_rand((int)$selfBargainMinPercentArr[3]*100,(int)$selfBargainMaxPercentArr[3]*100) / 100;
        }
        $config['self_bargain'] = $selfBargainArr;(int)

        //老用户分比例
        $oldBargainMaxPercentArr = explode(',',$templateConfig->old_follow_price_max_percent);
        $oldBargainMinPercentArr = explode(',',$templateConfig->old_follow_price_min_percent);

        $oldBargainArr[0] = mt_rand((int)$oldBargainMinPercentArr[0]*100,(int)$oldBargainMaxPercentArr[0]*100) / 100;
        $oldBargainArr[1] = mt_rand((int)$oldBargainMinPercentArr[1]*100,(int)$oldBargainMaxPercentArr[1]*100) / 100;
        $oldBargainArr[2] = mt_rand((int)$oldBargainMinPercentArr[2]*100,(int)$oldBargainMaxPercentArr[2]*100) / 100;
        $config['old_bargain'] = $oldBargainArr;
        $oldBargainMonenyLimitMaxArr = explode(',',$templateConfig->old_follow_price_max);
        $oldBargainMonenyLimitMinArr = explode(',',$templateConfig->old_follow_price_min);
        //老用户的砍价极限
        $oldBargainMonenyLimitArr[0] = [
            'min' => $oldBargainMonenyLimitMinArr[0],
            'max' => $oldBargainMonenyLimitMaxArr[0],
        ];
        $oldBargainMonenyLimitArr[1] = [
            'min' => $oldBargainMonenyLimitMinArr[1],
            'max' => $oldBargainMonenyLimitMaxArr[1],
        ];
        $oldBargainMonenyLimitArr[2] = [
            'min' => $oldBargainMonenyLimitMinArr[2],
            'max' => $oldBargainMonenyLimitMaxArr[2],
        ];
        $config['old_bargain_money_limit'] = $oldBargainMonenyLimitArr;

        //新用户分比例
        $newBargainMaxPercentArr = explode(',',$templateConfig->new_follow_price_max_percent);
        $newBargainMinPercentArr = explode(',',$templateConfig->new_follow_price_min_percent);

        $newBargainArr[0] = mt_rand((int)$newBargainMinPercentArr[0]*100,(int)$newBargainMaxPercentArr[0]*100) / 100;
        $newBargainArr[1] = mt_rand((int)$newBargainMinPercentArr[1]*100,(int)$newBargainMaxPercentArr[1]*100) / 100;
        $newBargainArr[2] = mt_rand((int)$newBargainMinPercentArr[2]*100,(int)$newBargainMaxPercentArr[2]*100) / 100;
        $config['new_bargain'] = $newBargainArr;

        return $config;
    }


}