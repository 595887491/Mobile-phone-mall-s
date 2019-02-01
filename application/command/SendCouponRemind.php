<?php
/**
 * @Author: 陈静
 * @Date: 2018/05/03 08:35:36
 * @Description:
 */

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;

class SendCouponRemind extends Command
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('send_coupon_remind')->setDescription('发送优惠券过期提醒');
    }

    protected function execute(Input $input, Output $output)
    {
        (new \app\task\controller\SendCouponRemind())->sendMsg();
    }
}