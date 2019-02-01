<?php
/**
 * @Author: 陈静
 * @Date: 2018/04/26 22:17:20
 * @Description:
 */

namespace app\task\library\Base;

use app\common\library\Redis;
use think\Config;
use think\Db;
use think\Exception;
use think\Log;

abstract class CycleWorkerBase
{
    protected $listName;
    protected $redis;
    protected $workList;
    protected $workerName;
    public static $instance;
    protected  $connect =[] ;
    protected $saveLog=false;
    protected $tableName = "mk_log_service_queue";
    protected $error;
    protected $stopTime;
    protected $startTime;
    protected $nextTime=60;

    /**
     * Base constructor.
     * @param array $options
     */
    public function __construct($options=[])
    {
        $this->_initialize();
        $this->redis = $this->redis();
        $this->workList = "worker_list";
        $this->workerName = get_called_class();
        $this->listName = md5($this->workerName);

    }
    public function _initialize(){

    }

    abstract  protected function runCycleHandle($data);
    protected function runHandle($data)
    {
        try{
            $instance=self::instance();
            $i =0;
            while ( true ){
                echo self::checkWorkingStop() ;
                if ( self::checkWorkingStop() ){
                    echo  "任务被终止!";
                    break;
                }
                $i++;
                self::signWorking();
                $time = $instance->getNextRunTime();
                if (!empty($instance->startTime ) && is_int( $instance->startTime ) ){
                    if ( time()>strtotime( $instance->startTime )){
                        $instance->pcntlWorker($data,"runCycleHandle");
                        Log::notice("执行了程序pcntlWorker".$this->workerName );
                    }else{
                        $this->sleep($time);
                        Log::notice("未到执行时间_".$instance->startTime);
                        continue;
                    }
                }else{
                    $instance->pcntlWorker($data,"runCycleHandle");
                    $this->sleep($time);
                    Log::notice("执行了程序pcntlWorker".$this->workerName );
                }
                Log::notice("睡眠$time 秒,循环执行程序执行程序".$this->workerName );
                echo  "睡眠$time 秒,循环执行程序执行程序.$this->workerName ";

            }
            $this-> clearWorkingWork();
            Log::notice("循环执行程序执结束");
        }catch (Exception $e){
            Log::notice( $e ->getMessage());
        }
    }

    protected function getNextRunTime(){
        if (!empty( $this->nextTime ) && is_int( $this->nextTime)){
            return $this->nextTime;
        }
        return 10;
    }

    /**
     * @title redis
     * @description redis加载自定义Redis类
     * User: Mikkle
     * QQ:776329498
     */
    protected static function redis()
    {
        $options = empty($options) ? $redis = Config::get("redis") : $options;
        return Redis::instance($options);
    }


    /**
     * @title runWorker
     * @description 标注命令行执行此任务
     * User: Mikkle
     * QQ:776329498
     * @param string $handleName
     */
    public function runWorker($handleName="run"){
        $this->redis->hset($this->workList,$this->workerName,$handleName);
    }

    /**
     * 标注命令行清除此任务
     * Power: Mikkle
     * Email：776329498@qq.com
     */
    public function clearWorker(){
        $this->redis->hDel("cycle_list",$this->listName);
        $this->redis->hdel($this->workList,$this->workerName);
    }


    /**
     * Power: Mikkle
     * Email：776329498@qq.com
     * @return static
     */
    static public function instance(){

        if (self::$instance){
            return self::$instance;
        }
        return  new static();
    }



    /**
     *
     * 当命令行未运行 直接执行
     * description add
     * User: Mikkle
     * QQ:776329498
     * @param $data
     * @return bool
     */
    static public function start($data=[]){
        try{
            $data=json_encode($data);
            $instance = static::instance();
            self::clearWorkingStop();
            switch (true){
                case (self::checkWorking()):
                    Log::notice("Work service is Running!!");
                    return "CycleWork service is Running!!";
                    break;
                case (self::checkCommandRun()):
                    $instance->redis->hSet("cycle_list",$instance->listName,$data);
                    Log::notice("Command service start work!!");
                    $instance->runWorker();
                    break;
                default:
                    Log::notice("Command service No away!!");
                    return "Command service No away!!";
                // $instance->runHandle($data);
            }
            return "true";
        }catch (Exception $e){
            Log::error($e->getMessage());
            return false;
        }
    }

    /**
     *
     * 当命令行未运行 直接执行
     * description add
     * User: Mikkle
     * QQ:776329498
     */
    static public function stop(){
        return self::signWorkingStop();
    }

    static public function status(){
        return self::checkWorking();
    }


    static  public function signWorking($time=20){
        self::redis()->set(self::instance()->workerName."_run","true",$time);
    }

    static public function checkWorking(){
        return self::redis()->get(self::instance()->workerName."_run") ? true :false;
    }
    static  public function clearWorkingWork(){
        self::redis()->hDel("cycle_list",self::instance()->listName);
        return  self::redis()->delete( self::instance()->workerName."_stop");
    }

    static  public function signWorkingStop($time= 86400){
        self::redis()->hDel("cycle_list",self::instance()->listName);
        return self::redis()->set(self::instance()->workerName."_stop","true",$time);
    }

    static  public function clearWorkingStop(){
        return self::redis()->delete( self::instance()->workerName."_stop");
    }

    static public function checkWorkingStop(){
        if (!empty(self::instance()->stopTime )){
            if ( time()>strtotime( self::instance()->stopTime )){
                return true;
            }else{
                return self::redis()->get(self::instance()->workerName."_stop") ? true :false;
            }
        }
        return self::redis()->get(self::instance()->workerName."_stop") ? true :false;
    }


    /**
     * 命令行执行的方法
     * Power: Mikkle
     * Email：776329498@qq.com
     */
    static public function run(){
        $instance = static::instance();
        try {
            $redisData = $instance->redis->hGet("cycle_list", $instance->listName);
            if ($redisData) {
                $data = json_decode($redisData, true);
                if ($data) {
                    echo "开始执行循环任务" . PHP_EOL;
                    $instance->pcntlWorker($data);
                    $instance->redis->hDel("cycle_list", $instance->listName);
                } else {
                    $instance->clearWorker();
                }
            } else {
                $instance->clearWorker();
                echo "未检测到循环任务" . PHP_EOL;
            }
        } catch (Exception $e) {
            Log::error($e->getMessage());
            echo($e->getMessage());
        }
    }


    /**
     * 检测命令行是否执行中
     * Power: Mikkle
     * Email：776329498@qq.com
     * @return bool
     */
    static public function checkCommandRun(){
        return self::redis()->get("command") ? true :false;
    }

    public function getError(){
        if (is_array($this->error )){
            return json_encode( $this->error );
        }
        return $this->error;
    }

    /*
 * 检查是注重某些值是非为空
 */
    protected function checkArrayValueEmpty($array,$value,$error=true){
        switch (true){
            case (empty($array)||!is_array($array)):
                if ($error==true){
                    $this->addError("要检测的数据不存在或者非数组");
                }
                return false;
                break;
            case (is_array($value)):
                foreach ($value as $item){
                    if (!isset($array[$item]) || (empty($array[$item]) && $array[$item]!==0)){
                        if ($error==true) {
                            $this->addError("要检测的数组数据有不存在键值{$item}");
                        }
                        return false;
                    }
                }
                break;
            case (is_string($value)):
                if (!isset($array[$value]) || empty($array[$value] && $array[$value]!==0)){
                    if ($error==true) {
                        $this->addError("要检测的数组数据有不存在键值{$value}");
                    }
                    return false;
                }
                break;
            default:
        }
        return true;
    }
    public function addError($error){
        $this->error = is_string($error) ? $error : json_encode($error);
    }


    protected function saveRunLog($result,$data){
        try{
            $operateData = [
                "class" => $this->workerName,
                "args" =>  json_encode($data),
                "result"=> $result ? "true":"false",
                "error" => $this->error ? $this->getError() : null,
                "time" => time(),
            ];
            Db::connect($this->connect)->table($this->tableName)->insert($operateData);
        }catch (Exception $e){
            Log::error($e->getMessage());
        }
    }

    protected function  sleep($time=1){
        if ($time<=10){
            sleep(sleep($time));
        }else{
            $ci = $time/10;
            for($i=0; $i<$ci;$i++){
                self::signWorking();
                echo "已睡眠 10 秒";
                sleep(10);
            }
            sleep(sleep($time%10));
        }
    }

    /**
     * title 分进程
     * description pcntlWorker
     * User: Mikkle
     * QQ:776329498
     * @param $data
     * @param string $action
     */
    protected function pcntlWorker($data,$action = "runHandle")
    {
        try{
            // 通过pcntl得到一个子进程的PID
            $pid = pcntl_fork();
            if ($pid == -1) {
                // 错误处理：创建子进程失败时返回-1.
                die ('could not fork');
            } else if ($pid) {
                // 父进程逻辑

                // 等待子进程中断，防止子进程成为僵尸进程。
                // WNOHANG为非阻塞进程，具体请查阅pcntl_wait PHP官方文档
                pcntl_wait($status, WNOHANG);
            } else {
                // 子进程逻辑
                $pid_2 = pcntl_fork();
                if ($pid_2 == -1) {
                    // 错误处理：创建子进程失败时返回-1.
                    die ('could not fork');
                } else if ($pid_2) {
                    // 父进程逻辑
                    echo "父进程逻辑开始" . PHP_EOL;
                    // 等待子进程中断，防止子进程成为僵尸进程。
                    // WNOHANG为非阻塞进程，具体请查阅pcntl_wait PHP官方文档
                    pcntl_wait($status, WNOHANG);
                    echo "父进程逻辑结束" . PHP_EOL;
                } else {
                    // 子进程逻辑
                    echo "子进程逻辑开始" . PHP_EOL;
                    $this->$action( $data );
                    echo "子进程逻辑结束" . PHP_EOL;
                    $this->pcntlKill();
                }
                $this->pcntlKill();
            }
        }catch (Exception $e){
            Log::error($e->getMessage());
        }
    }
    /**
     * Kill子进程
     * Power: Mikkle
     * Email：776329498@qq.com
     */
    protected function pcntlKill(){
        // 为避免僵尸进程，当子进程结束后，手动杀死进程
        if (function_exists("posix_kill")) {
            posix_kill(getmypid(), SIGTERM);
        }
        system('kill -9 ' . getmypid());

        exit ();
    }


}