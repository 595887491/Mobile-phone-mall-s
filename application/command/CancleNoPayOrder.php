<?php
/**
 * @Author: 陈静
 * @Date: 2018/05/03 08:35:36
 * @Description:
 */

namespace app\command;

use app\common\library\Logs;
use think\console\Command;
use think\console\Input;
use think\console\Output;

class CancleNoPayOrder extends Command
{
    public function __construct()
    {
        parent::__construct();
        Logs::sentryLogs();
    }

    protected function configure()
    {
        $this->setName('cancle_no_pay_team_order')->setDescription('定时取消拼团订单');
    }

    protected function execute(Input $input, Output $output)
    {
        (new \app\task\controller\CancleNoPayOrder())->cancle();
    }
}