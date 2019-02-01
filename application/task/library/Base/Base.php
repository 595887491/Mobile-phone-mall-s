<?php
/**
 * @Author: 陈静
 * @Date: 2018/04/18 23:08:29
 * @Description:
 */

namespace app\task\library\Base;

use app\common\library\Redis;
use think\Config;

abstract class Base
{
    protected $redis;
    protected $workerListName;
    protected $workerName;
    public static $instance;

    /**
     * Base constructor.
     * @param array $options
     */
    public function __construct($options=[])
    {
        $this->redis = $this->redis();
        $this->workerListName = "worker_list";
        $this->workerName = get_called_class();
    }


    /**
     * redis加载自定义Redis类
     * Power: Mikkle
     * Email：776329498@qq.com
     * @param array $options
     * @return Redis
     */
    protected static function redis($options=[]){
        $options = empty($options) ? $redis = Config::get("redis") : $options;
        return Redis::instance($options);
    }


    /**
     * 标注命令行执行此任务
     * Power: Mikkle
     * Email：776329498@qq.com
     */
    public function runWorker(){
        $this->redis->hset($this->workerListName,$this->workerName,$this->workerName);
    }

    /**
     * 标注命令行清除此任务
     * Power: Mikkle
     * Email：776329498@qq.com
     */
    public function clearWorker(){
        $this->redis->hdel($this->workerListName,$this->workerName);
    }


    /**
     * Power: Mikkle
     * Email：776329498@qq.com
     * @param array $options
     * @return static
     */
    static public function instance($options=[]){
        if (isset(self::$instance)){
            return self::$instance;
        }else{
            return new static($options);
        }
    }


}