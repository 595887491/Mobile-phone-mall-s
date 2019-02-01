<?php
/**
 * Created by 七月
 * User: 七月
 * Date: 2017/2/14
 * Time: 12:16
 */

namespace app\mobile\validate;

use think\Request;
use think\Validate;

/**
 * Class BaseValidate
 * 验证类的基类
 */
class BaseValidate extends Validate
{
    /**
     * 检测所有客户端发来的参数是否符合验证类规则
     * 基类定义了很多自定义验证方法
     * 这些自定义验证方法其实，也可以直接调用
     * @return true
     */
    public function goCheck($scene = 'add')
    {
        $request = Request::instance();
        $params = $request->param();

        if (!$this->scene($scene)->check($params)) {
            $message = is_array($this->error) ? implode(';', $this->error) : $this->error;
            return outPut(-1,$message);
        }

        return true;
    }

    //正整数验证
    protected function isPositiveInteger($value, $rule='', $data='', $field='')
    {
        if (is_numeric($value) && is_int($value + 0) && ($value + 0) > 0) {
            return true;
        }
        return false;
    }

    //非空验证
    protected function isNotEmpty($value, $rule='', $data='', $field='')
    {
        if (empty($value) || $value == '') {
            return false;
        } else {
            return true;
        }
    }

    //手机号的验证规则
    protected function isMobile($value)
    {
        $rule = '^1(3|4|5|7|8)[0-9]\d{8}$^';
        $result = preg_match($rule, $value);
        if ($result) {
            return true;
        } else {
            return false;
        }
    }
}