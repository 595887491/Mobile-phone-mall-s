<?php
/**
 * @Author: 陈静
 * @Date: 2018/08/20 15:52:26
 * @Description:
 */

namespace app\common\behavior;


class QueueFailedLogger {

    const should_run_hook_callback = true;

    /**
     * @param $jobObject   \think\queue\Job   //任务对象，保存了该任务的执行情况和业务数据
     * @return bool     true                  //是否需要删除任务并触发其failed() 方法
     */
    public function logAllFailedQueues(&$jobObject){

        $failedJobLog = [
            'jobHandlerClassName'   => $jobObject->getName(), // 'application\index\job\Hello'
            'queueName' => $jobObject->getQueue(),			   // 'helloJobQueue'
            'jobData'   => $jobObject->getRawBody(),  // '{'a': 1 }'
            'attempts'  => $jobObject->attempts(),            // 3
        ];
        var_export(json_encode($failedJobLog,true));

        // $jobObject->release();     //重发任务
        //$jobObject->delete();         //删除任务
        //$jobObject->failed();	  //通知消费者类任务执行失败

        return self::should_run_hook_callback;
    }
}