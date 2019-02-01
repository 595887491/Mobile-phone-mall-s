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

class SendTimingMsg extends Command
{
    public function __construct()
    {
        parent::__construct();
        Logs::sentryLogs();
    }

    protected function configure()
    {
        $this->setName('send_timing_sms')->setDescription('定时发送短信逻辑');
    }

    protected function execute(Input $input, Output $output)
    {
        (new \app\task\controller\SendTimingMsg())->sendMsg();
    }
}