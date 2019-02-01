<?php
/**
 * @Author: 陈静
 * @Date: 2018/04/13 14:48:09
 * @Description:
 */

namespace app\mobile\model;


use think\Exception;
use think\Model;

class ArticleModel extends Model
{
    protected $table = 'tp_article';
    protected $resultSetType = 'collection';

    //获取分类下的所有文章
    public function getArticlePage($cat_id ,$page_list = 10)
    {
        $page = I('get.p',1);

        try{
            $articleDatas = $this->where('cat_id' ,'=', $cat_id)
                ->field('content,author,author_email,file_url',true)
                ->where('is_open',1)
                ->page($page,$page_list)
                ->order('publish_time DESC')
                ->select()->toArray();
        }catch (\Throwable $e){
            $articleDatas = [];
        }
        return $articleDatas;
    }

    //获取头条文章详情
    public function getArticleDetail()
    {
        $articleID = I('get.article_id',0);

        try{
            //增加文章点击数
            $this->where('article_id',$articleID)->setInc('click');
            $articleDetail = $this->alias('a')
                ->field('a.*,b.cat_name')
                ->where('article_id' ,'=', $articleID)
                ->join('article_cat b','a.cat_id = b.cat_id')
                ->find()->toArray();
        }catch (\Throwable $e){
            $articleDetail = [];
        }

        return $articleDetail;
    }

}