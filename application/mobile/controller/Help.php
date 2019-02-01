<?php
/**
 * @Author: 陈静
 * @Date: 2018/04/19 11:52:21
 * @Description:
 */

namespace app\mobile\controller;


use app\admin\logic\ArticleCatLogic;
use app\mobile\model\ArticleModel;

class Help extends MobileBase
{
    //获取帮助列表页面
    public function lists()
    {
        //获取帮助页面的分类
        $ArticleCat = new ArticleCatLogic();
        $catId = 18;//获取尚美服务下的所有分类
        $helpCat = $ArticleCat->article_cat_list($catId, $catId, false);

        $catData = [];
        $i = 0;
        foreach ($helpCat as $k => $v) {
            if ($k != $catId) {
                $catData[$k]['name'] = $v['cat_name'];
                $catData[$k]['url'] = U('Mobile/Help/lists',['cat_id' => $v['cat_id']]);
                if ($i == 0) {
                    $currentCatId = $v['cat_id'];
                    $i++;
                }
            }
        }

        $catId = I('get.cat_id/d',$currentCatId);

        $catData[$catId]['status'] = 1;

        //获取分类下的列表
        $helpArticlList = (new ArticleModel())->getArticlePage($catId,15);

        $this->assign('cat_id',$catId);
        $this->assign('article_datas',$helpArticlList);
        $this->assign('cate_datas',$catData);

        if (IS_AJAX) {
            return $this->fetch('ajaxGetMore');
        }else{
            $func = function ($userInfo) {
                if (!$userInfo) {
                    return [];
                }
                $userSex = '保密';
                if ($userInfo['sex'] == 1) {
                    $userSex = '男';
                }elseif ($userInfo['sex'] == 2) {
                    $userSex = '女';
                }
                $address = get_user_address_list($userInfo['user_id']);


                if ($address) {
                    $addressInfo = getTotalAddress($address[0]['province'],$address[0]['city'],$address[0]['district'],$address[0]['twon'],$address[0]['address']);
                }else {
                    $addressInfo = '未填写';
                }
                $returnData = [
                    'name' => $userInfo['nickname'],
                    'address' => $addressInfo,
                    'age' => $userInfo['age'] ? $userInfo['age'] : '未填写',
                    'email' => $userInfo['email'],
                    'gender' => $userSex,
                    'tel' => $userInfo['mobile'],
                ];
                return $returnData;
            };

            $userInfo = session('user');
            $this->assign('service_user_info',$func($userInfo));
            return $this->fetch('cf_help');
        }
    }

    //获取帮助详情页
    public function detail()
    {
        $articleDetail = (new ArticleModel())->getArticleDetail();
        if ($articleDetail) {
            $articleDetail['content'] = htmlspecialchars_decode($articleDetail['content']);
        }
        $this->assign('article_detail',$articleDetail);
        return $this->fetch('cf_help_content');
    }
}