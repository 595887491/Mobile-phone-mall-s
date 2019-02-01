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
use think\Log;

class GiveIntegral extends Command
{
    public function __construct()
    {
        parent::__construct();

        Logs::sentryLogs();
    }

    protected function configure()
    {
        $this->setName('give_integral')->setDescription('下单7天送积分');
    }

    protected function execute(Input $input, Output $output)
    {
        $giveIntegral = new \app\task\controller\GiveIntegral();
        $giveIntegral->giveOrder();

    }
}