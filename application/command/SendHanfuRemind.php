<?php
/**
 * @Author: 陈静
 * @Date: 2018/05/03 08:35:36
 * @Description:
 */

namespace app\command;

use app\common\logic\WechatLogic;
use app\mobile\model\VoteFoundModel;
use think\console\Command;
use think\console\Input;
use think\console\Output;

class SendHanfuRemind extends Command
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('send_hanfu_remind')->setDescription('发送汉服活动领奖提醒');
    }

    protected function execute(Input $input, Output $output)
    {
        $userDatas = (new VoteFoundModel())->alias('a')
            ->join('oauth_users b','a.user_id = b.user_id','INNER')
            ->field('b.user_id,b.openid')->select()->toArray();
        foreach ($userDatas as $v) {
            (new WechatLogic())->sendTemplateMsgOnOpenVote($v);
            usleep(500000);
        }
    }
}