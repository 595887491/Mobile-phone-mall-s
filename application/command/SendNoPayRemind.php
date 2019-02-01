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

class SendNoPayRemind extends Command
{
    public function __construct()
    {
        parent::__construct();
        Logs::sentryLogs();
    }

    protected function configure()
    {
        $this->setName('send_no_pay_remind')->setDescription('发送未支付提醒');
    }

    protected function execute(Input $input, Output $output)
    {
        (new \app\task\controller\SendNoPayRemind())->sendMsg();
    }
}