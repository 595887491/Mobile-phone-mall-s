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

class CalculateKpi extends Command
{
    public function __construct()
    {
        parent::__construct();

        Logs::sentryLogs();
    }

    protected function configure()
    {
        $this->setName('calculate_kpi')->setDescription('计算用户kpi');
    }

    protected function execute(Input $input, Output $output)
    {
        $calculateModel = new \app\task\controller\CalculateKpi();
        $calculateModel->insertData();

    }
}