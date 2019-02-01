<?php
/**
 * @Author: 陈静
 * @Date: 2018/08/30 11:19:38
 * @Description:
 */

namespace app\mobile\model;


use think\Model;

class BargainShareModel extends Model
{
    protected $table = 'cf_bargain_share';
    protected $resultSetType = 'collection';

    public function addShareInfo($foundInfo)
    {
        $templateId = (new BargainActivityModel())->where('id',$foundInfo->bargain_id)->getField('template_id');
        $bargainConfig = (new BargainTemplateModel())->getBargainConfig($templateId,$foundInfo->reduce_price_percent);

        $data['found_id'] = $foundInfo->found_id;
        $data['found_user_id'] = $foundInfo->user_id;

        //1.查询当前用户的分享信息
        $preShareInfo = $this->where('found_id',$data['found_id'])
            ->where('found_user_id',$data['found_user_id'])->find();

        if (empty($preShareInfo)) {
            //第二刀获取规则
            $data['step'] = 1;
            if ( $bargainConfig['default']['bargain_count_found_share1'] <= 1) {
                $data['step_status'] = 1;
            }else{
                $data['step_status'] = 0;
            }

            $data['share_count'] = 1;
            $data['content'] = time();
            $this->save($data);
        }else{
            //第三刀获取规则
            if ($preShareInfo->step_status == 1) {
                $data['step'] = 3;
                if ( $bargainConfig['default']['bargain_count_found_share1'] + $bargainConfig['default']['bargain_count_found_share2'] - 1 <= $preShareInfo-> share_count) {
                    $data['step_status'] = 1;
                }else{
                    $data['step_status'] = 0;
                }
                $data['share_count'] = $preShareInfo->share_count + 1;
                $data['content'] = $preShareInfo->content.','.time();
            }else{
                if ($preShareInfo->step == 3) {
                    $data['step'] = 3;
                    //继续第二刀规则
                    if ( $preShareInfo->share_count + 1 >= $bargainConfig['default']['bargain_count_found_share1'] + $bargainConfig['default']['bargain_count_found_share2']) {
                        $data['step_status'] = 1;
                    }else{
                        $data['step_status'] = 0;
                    }
                } elseif($preShareInfo->step == 1) {
                    $data['step'] = 1;
                    if ( $preShareInfo->share_count + 1 >= $bargainConfig['default']['bargain_count_found_share1'] ) {
                        $data['step_status'] = 1;
                    }else{
                        $data['step_status'] = 0;
                    }

                }
                $data['share_count'] = $preShareInfo->share_count + 1;
                $data['content'] = $preShareInfo->content.','.time();
            }

            $data['id'] = $preShareInfo->id;
            $this->isUpdate(true)->save($data);
        }

        return $data;

    }

}