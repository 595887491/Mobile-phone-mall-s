<?php
/**
 * Created by PhpStorm.
 * User: Mikkle
 * QQ:776329498
 * Date: 2017/6/19
 * Time: 10:10
 */

namespace app\task\library\Base;

use app\common\library\Redis;
use think\Db;
use think\Log;
use think\Config;
use think\Exception;

abstract class WorkerBase
{
    protected $listName;
    protected $redis;
    protected $workList;
    protected $workerName;
    public static $instance;
    protected $connect = [];
    protected $saveLog = false;
    protected $tableName = "mk_log_service_queue";//设置记录日志的表
    /**
     * SET FOREIGN_KEY_CHECKS=0;
    -- ----------------------------
    -- Table structure for mk_log_service_queue
    -- ----------------------------
    DROP TABLE IF EXISTS `mk_log_service_queue`;
    CREATE TABLE `mk_log_service_queue` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `class` varchar(200) DEFAULT NULL,
    `args` text,
    `result` varchar(20) DEFAULT NULL,
    `error` varchar(100) DEFAULT NULL,
    `time` varchar(20) DEFAULT NULL,
    PRIMARY KEY (`id`)
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
     */
    protected $error;

    /**
     * Base constructor.
     * @param array $options
     */
    public function __construct($options = [])
    {
        $this->_initialize();
        $this->redis = $this->redis();
        $this->workList = "worker_list";
        $this->workerName = get_called_class();
        $this->listName = md5($this->workerName);

    }

    public function _initialize()
    {

    }

    abstract protected function runHandle($data);

    /**
     * @title redis
     * @description redis加载自定义Redis类
     * @return \use app\common\library\Redis;
     */
    protected static function redis()
    {
        $options = empty($options) ? $redis = Config::get("redis") : $options;
        return Redis::instance($options);
    }


    /**
     * @title runWorker
     * @description 标注命令行执行此任务
     * @param string $handleName
     */
    public function runWorker($handleName = "run")
    {
        $this->redis->hset($this->workList, $this->workerName, $handleName);
    }

    /**
     * 标注命令行清除此任务
     * Email：776329498@qq.com
     */
    public function clearWorker()
    {
        $this->redis->hdel($this->workList, $this->workerName);
    }


    /**
     * @param array $options
     * @return static
     */
    static public function instance($options = [])
    {
        if (isset(static::$instance)) {
            return static::$instance;
        } else {
            return new static($options);
        }
    }

    /**
     * 当命令行未运行 直接执行
     * description add
     * @param $data
     * @param array $options
     * @param string $handleName
     * @return bool
     */
    static public function add($data, $options = [], $handleName = "run")
    {
        try {
            $data = json_encode($data);
            $instance = static::instance($options);
            switch (true) {
                case (self::checkCommandRun()):
                    $instance->redis->lpush($instance->listName, $data);
                    Log::notice("Command service start work!!");
                    $instance->runWorker($handleName);
                    break;
                default:
                    Log::notice("Command service No away!!");
                    $instance->runHandle($data);
            }
            return true;
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return false;
        }
    }

    /**
     * 命令行执行的方法
     */
    static public function run()
    {
        $instance = static::instance();
        try {
            $i = 0;
            while (true) {
                $redisData = $instance->redis->rpop($instance->listName);
                $data = json_decode($redisData, true);
                if ($data) {
                    $result = $instance->runHandle($data);
                    if ($instance->saveLog) {
                        $instance->saveRunLog($result, $data);
                    }
                } else {
                    $instance->clearWorker();
                    break;
                }
                $i++;
                sleep(1);
            }
            echo "执行了{$i}次任务" . PHP_EOL;
        } catch (Exception $e) {
            Log::error($e);
            Log::error($e->getMessage());
            echo($e->getMessage());
        }
    }


    /**
     * 检测命令行是否执行中
     * @return bool
     */
    static public function checkCommandRun()
    {
        return self::redis()->get("command") ? true : false;
    }

    public function getError()
    {
        if (is_array($this->error)) {
            return json_encode($this->error);
        }
        return $this->error;
    }

    /*
 * 检查是注重某些值是非为空
 */
    protected function checkArrayValueEmpty($array, $value, $error = true)
    {
        switch (true) {
            case (empty($array) || !is_array($array)):
                if ($error == true) {
                    $this->addError("要检测的数据不存在或者非数组");
                }
                return false;
                break;
            case (is_array($value)):
                foreach ($value as $item) {
                    if (!isset($array[$item]) || (empty($array[$item]) && $array[$item] !== 0)) {
                        if ($error == true) {
                            $this->addError("要检测的数组数据有不存在键值{$item}");
                        }
                        return false;
                    }
                }
                break;
            case (is_string($value)):
                if (!isset($array[$value]) || empty($array[$value] && $array[$value] !== 0)) {
                    if ($error == true) {
                        $this->addError("要检测的数组数据有不存在键值{$value}");
                    }
                    return false;
                }
                break;
            default:
        }
        return true;
    }

    public function addError($error)
    {
        $this->error = is_string($error) ? $error : json_encode($error);
    }


    /**
     * @Date: 2018/05/10 11:09:36
     * @Description: 保存命令行运行日志
     * @param $result
     * @param $data
     */
    protected function saveRunLog($result, $data)
    {
        try {
            $operateData = [
                "class" => $this->workerName,
                "args" => json_encode($data),
                "result" => $result ? "true" : "false",
                "error" => $this->error ? $this->getError() : null,
                "time" => time(),
            ];
            Db::connect($this->connect)->table($this->tableName)->insert($operateData);
        } catch (Exception $e) {
            Log::error($e->getMessage());
        }
    }

    protected function sleep($time = 1)
    {
        sleep(sleep($time));
    }

}