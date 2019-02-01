<?php
/**
 * @Author: 陈静
 * @Date: 2018/04/02 09:55:35
 * @Description: 阿里云oss配置
 */

switch ($GLOBALS['ENV']){
    //测试环境
    default:
    case 'LOCAL':
    case 'TEST':
        $config = [
            'Access_Key_ID' => 'LTAIzMfUM7D4yQJ3',//oss 云存储 Access_Key_ID
            'Access_Key_Secret' => 'aaf8LnVPd1lStdIBLM06zjqT6c68Kl',//oss云存储 Access_Key_Secret
            'bucket' => 'shangmei-dev',//oss云存储 bucket
            'EndPoint' => 'oss-cn-shanghai.aliyuncs.com',//oss云存储 EndPoint
            'Oss_cdn' => 'http://cdn-dev.cfo2o.com',//cdn  图片加速域名
            'img_object' => 'images/',
            'save_path' => ['goods/', 'water/','ad/','article/','head_pic/','brand/','category/','activity/','goods_comment/'], //application\common\logic\EditorLogic.php 70,line 上传文件需要保存到阿里云的文件夹
        ];
        break;
    case 'FORMAL':
    case 'PRODUCT':
        $config = [
            'Access_Key_ID' => 'LTAI8CIwh3wj9pxJ',//oss 云存储 Access_Key_ID
            'Access_Key_Secret' => '3Ghp0TnEXqYYBHoOD9whvFS3scNsLs',//oss云存储 Access_Key_Secret
            'bucket' => 'shangmei-formal',//oss云存储 bucket
            'EndPoint' => 'oss-cn-shanghai.aliyuncs.com',//oss云存储 EndPoint
            'Oss_cdn' => 'http://cdn.cfo2o.com',//cdn  图片加速域名
            'img_object' => 'images/',
            'save_path' => ['goods/', 'water/','ad/','article/','head_pic/','brand/','category/','activity/','goods_comment/'],  //application\common\logic\EditorLogic.php 70,line 上传文件需要保存到阿里云的文件夹
        ];
        break;
}

//$totalconfig['Access_Key_ID']     = 'LTAIzMfUM7D4yQJ3';//oss 云存储 Access_Key_ID
//$totalconfig['Access_Key_Secret'] = 'aaf8LnVPd1lStdIBLM06zjqT6c68Kl';//oss云存储 Access_Key_Secret
//$totalconfig['bucket']          = 'shangmei-dev';//oss云存储 bucket
//$totalconfig['EndPoint']        = 'oss-cn-shanghai.aliyuncs.com';//oss云存储 EndPoint
//$totalconfig['Oss_cdn']         = 'cdn-dev.cfo2o.com';//cdn  图片加速域名


return $config;