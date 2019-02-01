<?php
/**
 * @Author: 陈静
 * @Date: 2018/08/17 08:58:26
 * @Description:
 */

namespace app\command;
use app\common\model\UserUserModel;
use think\Config;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;

class generateInviteCode extends Command
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('generateInviteCode')->setDescription('下单7天送积分');
    }

    protected function execute(Input $input, Output $output)
    {
        $test = Db::table('cf_user_user')->getField('user_id',true);
        foreach ($test as $v){
            Db::table('cf_user_user')->where('user_id',$v)->save([
                'invite_friend_code' => generateInviteCode()
            ]);
        }
        $test1 = Db::table('cf_user_agent')->getField('user_id',true);
        foreach ($test1 as $v){
            Db::table('cf_user_agent')->where('user_id',$v)->update([
                'invite_partner_code' => generateInviteCode('agent')
            ]);
        }
    }
}