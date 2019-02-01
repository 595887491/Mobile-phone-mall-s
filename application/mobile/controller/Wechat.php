<?php
/**
 * @Author: 陈静
 * @Date: 2018/04/01 17:10:18
 * @Description:
 */

namespace app\mobile\controller;

use EasyWeChat\Factory;
class Wechat extends MobileBase
{
    /**
     * @Author: 陈静
     * @Date: 2018/04/01 22:25:08
     * @Description: 生成临时二维码
     */
    public function index()
    {
//        $wechatConfig = config('easywechat');
//
//        $app = Factory::officialAccount($wechatConfig);
//
//        $result = $app->qrcode->temporary('foo', 6 * 24 * 3600);
        $result = [
            "ticket" => "gQGF8DwAAAAAAAAAAS5odHRwOi8vd2VpeGluLnFxLmNvbS9xLzAyZEpkcWdMZGZiRFQxVjNOOE5xYzMAAgRDyMBaAwQA6QcA",
    "expire_seconds" => 518400,
    "url" => "http://weixin.qq.com/q/02dJdqgLdfbDT1V3N8Nqc3"
        ];
        $this->createQrcode($result['url']);
//        halt($result);
    }


    /**
     * @Author: 陈静
     * @Date: 2018/04/01 20:23:07
     * @Description: 生成二维码
     */
    public function createQrcode($url)
    {
        vendor('phpqrcode.phpqrcode');
        //容错级别
        $errorCorrectionLevel = 'L';
        //生成图片大小
        $matrixPointSize = 6;
        //生成二维码图片
//        ob_start();
        $test =  (\QRcode::png($url,false, $errorCorrectionLevel, $matrixPointSize, 2));
//        $ob_contents = ob_get_contents(); //读取缓存区数据
//        ob_end_clean();
        file_put_contents('test.png',$test);

    }




}