<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018\5\8 0008
 * Time: 20:16
 */
namespace app\transfer\common;

class DbClass
{
    public $config = [
        'host' => 'dev.cfo2o.com',  //47.100.19.77
        'user_name' => 'root',
        'password' => "shangmei&bf&0314" ,   // "shangmei&bf&0314" ,
        'db_name' =>'tpshop_dev'     // 'shangmeibf_test'
    ];

    public function __construct()
    {
        $this->mysqli = new \mysqli(
            $this->config['host'],
            $this->config['user_name'],
            $this->config['password'],
            $this->config['db_name']
        );
    }

    /**
     * 选择数据库
     * @param $db_name 数据库名
     */
    public function selectDatabase($db_name)
    {
        mysqli_select_db($this->mysqli,$db_name);
    }

    /**
     * 执行sql语句
     * @param $sql 要执行的SQL语句
     * @param int $type 代表SQL语句的类型，0代表增删改 1代表查询
     * @return bool|mixed|mysqli_result
     */
    public function query($sql, $type = 1)
    {

        $result =  $this->mysqli->query($sql);
        if ($type) {
            //如果是查询，返回数据
            return $result->fetch_all(MYSQLI_ASSOC);
        } else {//如果是增删改，返回true或false
            return $result;
        }
    }

    /**
     * @param $table 表名
     * @param null $field 字段名
     * @param null $where where条件
     * @return int
     */
    public function select($table, $field = null, $where = null)
    {
        $sql = "SELECT * FROM {$table}";
        if (!empty($field)) {
            $field = '`' . implode('`,`', $field) . '`';
            $sql = str_replace('*', $field, $sql);
        }
        if (!empty($where)) {
            $sql = $sql . ' WHERE ' . $where;
        }
        $this->result = $this->mysqli->query($sql);
        return $this->result->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * 插入数据
     * @param $table 数据表
     * @param $data 数据数组
     * @return mixed 插入ID
     */
    public function insert($table, $data)
    {
        foreach ($data as $key => $value) {
            $data[$key] = $this->mysqli->real_escape_string($value);
        }
        $keys = '`' . implode('`,`', array_keys($data)) . '`';
        $values = '\'' . implode("','", array_values($data)) . '\'';
        $sql = "INSERT INTO {$table}( {$keys} )VALUES( {$values} )";
        $this->mysqli->query($sql);
        return $this->mysqli->insert_id;
    }
    /**
     * 更新数据
     * @param $table 数据表
     * @param $data 数据数组
     * @param $where 过滤条件
     * @return mixed 受影响记录
     */
    public function update($table, $data, $where)
    {
        foreach ($data as $key => $value) {
            $data[$key] = $this->mysqli->real_escape_string($value);
        }
        $sets = array();
        foreach ($data as $key => $value) {
            $kstr = '`' . $key . '`';
            $vstr = '\'' . $value . '\'';
            array_push($sets, $kstr . '=' . $vstr);
        }
        $kav = implode(',', $sets);
        $sql = "UPDATE {$table} SET {$kav} WHERE {$where}";
        $this->mysqli->query($sql);
        return $this->mysqli->affected_rows;
    }
    /**
     * 删除数据
     * @param $table 数据表
     * @param $where 过滤条件
     * @return mixed 受影响记录
     */
    public function delete($table, $where)
    {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        $this->mysqli->query($sql);
        return $this->mysqli->affected_rows;
    }
}