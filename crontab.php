<?php
//https://github.com/jobbyphp/jobby github,地址
//http://www.xiabin.me/2016/06/16/php-note9/ 说明
/**
 * echo  的输出都会出现在output的配置文件中
 */

require_once 'vendor/autoload.php';
$env = is_file('ENV') ? trim(file_get_contents('ENV')) : 'LOCAL';

switch ($env){
    case 'DEV':
        $environment = $dir = 'dev_tpshop';
        break;
    case 'TEST':
        $environment = $dir = 'tpshop_test';
        break;
    case 'FORMAL':
        $environment = $dir = 'formal_tpshop';
        break;
    case 'PRODUCT':
        $environment = $dir = 'tpshop';
        break;
    default:
        $environment = $dir = 'dev_tpshop';
}

$jobby = new \Jobby\Jobby();

//额外的配置
$data = [
//    'runAs' => 'www',
//    'recipients' => '465497241@qq.com',
//    'mailer' => 'stmp',
//    'smtpHost' => 'smtp.mxhichina.com',
//    'smtpPort' => '587',
//    'smtpUsername' => 'system@cfo2o.com',
//    'smtpPassword' => 'SSy123456',
//    'smtpSender' => 'system@cfo2o.com',
//    'smtpSenderName' => 'Crontab',
];

/**
 * @Author: 陈静
 * @Date: 2018/05/14 22:40:19
 * @Description: 自动分成，每小时执行一次
 */
$jobby->add('Distribute', [
    'runAs' => 'www',
    'command'  => 'cd /home/www/'.$dir.' && /usr/local/php7.1/bin/php think distribute',
//    'command'  => 'cd /home/www/'.$dir.' && echo 1 >> test.txt',
    'schedule' => '1 * * * *',
    'environment'=>$environment,
    'enabled'  => true,
    'output'   => '/home/www/walle/command_log/'.$environment.'/'.date('Ym').'/'.date('d').'_distribute.log'
]);

/**
 * @Author: 陈静
 * @Date: 2018/05/14 22:49:26
 * @Description: 计算用户kpi,每周日0点10执行
 */
$jobby->add('Calculate', [
    'runAs' => 'www',
    'command'  => 'cd /home/www/'.$dir.' && /usr/local/php7.1/bin/php think calculate_kpi',
    'schedule' => '10 0 * * 0',
    'environment' => $environment,
    'enabled'  => true,
    'output'   => '/home/www/walle/command_log/'.$environment.'/'.date('Ym').'/'.date('d').'_calculate.log'
]);

/**
 * @Author: 陈静
 * @Date: 2018/05/14 22:49:26
 * @Description: 发送秒杀提醒,每分钟执行
 */
$jobby->add('SendTimingMsg', [
    'runAs' => 'www',
    'command'  => 'cd /home/www/'.$dir.' && /usr/local/php7.1/bin/php think send_timing_sms',
    'schedule' => '50-59 * * * *',
    'environment' => $environment,
    'enabled'  => true,
    'output'   => '/home/www/walle/command_log/'.$environment.'/'.date('Ym').'/'.date('d').'_send_timing_sms.log'
]);

/**
 * @Author: 陈静
 * @Date: 2018/06/14 22:49:26
 * @Description: 发送未支付提醒,每20分钟执行
 */
$jobby->add('SendNoPayRemind', [
    'runAs' => 'www',
    'command'  => 'cd /home/www/'.$dir.' && /usr/local/php7.1/bin/php think send_no_pay_remind',
    'schedule' => '*/20 * * * *',
    'environment' => $environment,
    'enabled'  => true,
    'output'   => '/home/www/walle/command_log/'.$environment.'/'.date('Ym').'/'.date('d').'_send_no_pay_remind.log'
]);

/**
 * @Author: 龙信红
 * @Date: 2018/06/23 22:49:26
 * @Description: 下单送积分,每天执行
 */
$jobby->add('GiveIntegral', [
    'runAs' => 'www',
    'command'  => 'cd /home/www/'.$dir.' && /usr/local/php7.1/bin/php think give_integral',
    'schedule' => '1 24 * * *',
    'environment' => $environment,
    'enabled'  => true,
    'output'   => '/home/www/walle/command_log/'.$environment.'/'.date('Ym').'/'.date('d').'_give_integral.log'
]);

/**
 * @Author: 陈静
 * @Date: 2018/06/23 22:49:26
 * @Description: 修改runtime权限  每月1号，每30分钟执行一次
 */
$jobby->add('ChangePermission', [
    'runAs' => 'root',
    'command'  => 'chmod -R 777 /home/www/'.$dir.'/runtime',
    'schedule' => '*/30 * 1 * *',
    'environment' => $environment,
    'enabled'  => true,
    'output'   => '/home/www/walle/command_log/'.$environment.'/'.date('Ym').'/'.date('d').'ChangePermission.log'
]);

/**
 * @Author: 陈静
 * @Date: 2018/06/14 22:49:26
 * @Description: 发送未支付提醒,每5分钟执行
 */
$jobby->add('CancleNoPayOrder', [
    'runAs' => 'www',
    'command'  => 'cd /home/www/'.$dir.' && /usr/local/php7.1/bin/php think cancle_no_pay_team_order',
    'schedule' => '*/5 * * * *',
    'environment' => $environment,
    'enabled'  => true,
    'output'   => '/home/www/walle/command_log/'.$environment.'/'.date('Ym').'/'.date('d').'_cancle_no_pay_team_order.log'
]);

/**
 * @Author: 陈静
 * @Date: 2018/06/14 22:49:26
 * @Description: 发送未支付提醒,每小时执行
 */
$jobby->add('SendTeamRemind', [
    'runAs' => 'www',
    'command'  => 'cd /home/www/'.$dir.' && /usr/local/php7.1/bin/php think send_team_remind',
    'schedule' => '1 * * * *',
    'environment' => $environment,
    'enabled'  => true,
    'output'   => '/home/www/walle/command_log/'.$environment.'/'.date('Ym').'/'.date('d').'_send_team_remind.log'
]);

/**
 * @Author: 陈静
 * @Date: 2018/06/14 22:49:26
 * @Description: 发送优惠券过期提醒,每天10点
 */
$jobby->add('SendCouponRemind', [
    'runAs' => 'www',
    'command'  => 'cd /home/www/'.$dir.' && /usr/local/php7.1/bin/php think send_coupon_remind',
    'schedule' => '1 10 * * *',
    'environment' => $environment,
    'enabled'  => true,
    'output'   => '/home/www/walle/command_log/'.$environment.'/'.date('Ym').'/'.date('d').'_send_coupon_remind.log'
]);


$jobby->run();
