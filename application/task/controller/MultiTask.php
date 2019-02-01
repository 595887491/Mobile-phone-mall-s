<?php
/**
 * @Author: 陈静
 * @Date: 2018/08/09 15:42:51
 * @Description: 多任务生产者和消费者示例
 */

namespace app\task\controller;

use think\Db;
use think\Exception;
use think\Queue;
use think\queue\Job;
// php think queue:listen --queue multiTaskJobQueue --tries  2
class MultiTask
{

    //多任务生产者
    public function actionWithMultiTask(){

        $jobHandlerClassName  = 'app\\task\\controller\\MultiTask@taskA';
        $jobDataArr = ['a'	=> '1'];
        $jobQueueName = "multiTaskJobQueue";

        $isPushed = Queue::push($jobHandlerClassName, $jobDataArr, $jobQueueName);
        if ($isPushed !== false) {
            echo("the $jobHandlerClassName of MultiTask Job has been Pushed to ".$jobQueueName ."<br>");
        }else{
            throw new Exception("push a new $jobHandlerClassName of MultiTask Job Failed!");
        }
    }

    /****多任务消费者***/
    public function taskA(Job $job, $data)
    {
        $_doTaskA = function ($data) {
            print("Info: doing TaskA of Job MultiTask " . "\n");
            return false;
//            return true;
        };
        $isJobDone = $_doTaskA($data);
        if ($isJobDone) {
            $job->delete();
            print("Info: TaskA of Job MultiTask has been done and deleted" . "\n");
        } else {
            if ($job->attempts() > 3) {
                $job->delete();
            }
        }
    }

    /**
     * 该方法用于接收任务执行失败的通知，你可以发送邮件给相应的负责人员
     * @param $jobData  string|array|...      //发布任务时传递的 jobData 数据
     */
    public function failed($jobData){
        //send_mail_to_somebody() ;

        print("Warning: Job failed after max retries. job data is :".var_export($jobData,true)."\n");
    }




}