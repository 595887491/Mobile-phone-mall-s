<?php
/**
 * tpshop
 * ============================================================================
 * * 版权所有 2015-2027 深圳搜豹网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.tp-shop.cn
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 如果商业用途务必到官方购买正版授权, 以免引起不必要的法律纠纷.
 * ============================================================================
 * $Author: IT宇宙人 2015-08-10 $
 */
namespace app\mobile\controller;

use app\mobile\model\ArticleModel;

class Article extends MobileBase
{
    //头条文章分类
    public function cfFrontPageList()
    {
        $articleModel = new ArticleModel();

        $articleDatas = $articleModel->getArticlePage(17);

        foreach ($articleDatas as &$v) {
            $v['goods_nums'] = preg_match_all('#,#',$v['cf_relate_goods']);
        }
        $this->assign('article_datas',$articleDatas);
        if (IS_AJAX) {
            return $this->fetch('ajaxGetMore');
        }else{
            return $this->fetch('cf_line_more');
        }
    }

    //头条文章详情
    public function articleDetail()
    {
        $articleModel = new ArticleModel();
        $articleDetail = $articleModel->getArticleDetail();
        $articleDetail['content'] = htmlspecialchars_decode($articleDetail['content']);
        $goodsIdsArr = explode(',',trim($articleDetail['cf_relate_goods'],','));

        if (empty($goodsIdsArr)) {
            $this->assign('goods_list','');
        }

        $goodsListInfo = (new \app\admin\model\Goods())->where('goods_id','in',$goodsIdsArr)
            ->where('is_on_sale',1)
            ->field('goods_id,goods_name,shop_price,market_price,original_img')
            ->select();

        if (!empty($goodsListInfo)) {
            $this->assign('goods_list_info',$goodsListInfo);
        }

        $this->assign('article_detail',$articleDetail);
        $this->assign('article_cate','头条详情');
//        $this->assign('article_cate',$articleDetail['cat_name']);
        $this->assign('title','头条详情');

        if ($articleDetail['article_id'] == 58) {
            $this->assign('article_cate','用户注册协议');
            $this->assign('title','用户注册协议');
        }

        $this->assign('article_id',$articleDetail['article_id']);

        return $this->fetch('cf_line_comment');
    }
}