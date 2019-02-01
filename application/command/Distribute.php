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

class Distribute extends Command
{
    public function __construct()
    {
        parent::__construct();

        Logs::sentryLogs();
    }

    protected function configure()
    {
        $this->setName('distribute')->setDescription('自动分成命令');
    }

    protected function execute(Input $input, Output $output)
    {
        $distributeModel = new \app\task\controller\Distribute();
        $distributeModel->autoDistribute();
        $distributeModel->autoTakeDeliveryOfGoods();
    }
}