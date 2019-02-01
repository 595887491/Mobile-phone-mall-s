<?php
/**
 * @Author: 陈静
 * @Date: 2018/03/26 08:50:35
 * @Description: 一些通用的Api方法
 */

namespace app\api\controller;

use app\common\model\UserModel;
use app\common\model\Users;
use think\Exception;

class UserController extends BaseController
{
    /**
     * @Author: 陈静
     * @Date: 2018/03/28 14:59:45
     * @Description: 实名制认证
     */
    public function RealNameCertification()
    {
        //身份证号
        $idCardName = $this->getPostParams('user_name');
        $idCardNum = $this->getPostParams('user_num');

        $userId = $this->validateToken();

        if (empty($idCardName) || empty($idCardNum)) {
            return outPut(-1,'信息有误');
        }
        if ( is_id_card($idCardNum) === false)
            return outPut(-1, '请输入合法的姓名和身份证号码！');

        //检查是否认证
        $userModel = new UserModel();
        $userInfo = $userModel->getUserInfo($userId);

        if(empty($userInfo)){
            return outPut(-1,'该用户不存在');
        }

        if ($userInfo->id_card_num && $userInfo->id_card_name) {
            return outPut(-1,'该用户已认证');
        }

        //检查身份证
        $realNameAuth = $userModel->where('id_card_num',$idCardNum)->find();

        if ($realNameAuth) {
            return outPut(-1,'该身份证已被使用，请联系客服');
        }

        //接口调用
        $url  = 'http://op.juhe.cn/idcard/query?key=a4e75da8be12bbfeb1fd0fb8886c9a41';
        $url .='&idcard='.$idCardNum;
        $url .='&realname='.urlencode($idCardName);
        if (!config('APP_DEBUG')) {
            $authRes = json_decode(httpRequest($url));
            if ($authRes->error_code != 0) {
                return outPut(-1,$authRes->reason);
            }
        }

        try{
            $res = $userModel->where('user_id' , $userId)
                ->save([
                    'id_card_num' => $idCardNum,'id_card_name' => $idCardName
                ]);
        }catch (Exception $e){
            //记录日志
            \app\common\library\Logs::sentryLogs($e);
            return outPut(-1,'认证失败，请联系客服');
        }
        if ($res) {
            return outPut(1,'认证成功');
        }
        return outPut(-1,'认证失败，请联系客服');
    }

    //取消完善资料提醒
    public function cancleCompleteUserInfo()
    {
        $userId = $this->validateToken();

        (new Users())->where('user_id',$userId)->update([
            'show_complete_info' => 0
        ]);

        return outPut(1,'success');

    }
}