<?php

namespace app\admin\controller;

use app\common\model\EsModel;
use think\Controller;
use think\Db;


//分词搜索
class ElasticSearch extends Controller
{
    public  $client ;


    const FINDING_TYPE = [
        1 => 1,//搜索查询
        2 => 2,//用作判断,检测库是否存在
        3 => 3 //用作索引文档删除判断,检测库是否存在
    ];

    public function _initialize()
    {
        Vendor('elasticsearch.autoload');
        //host数组可配置多个节点
        $params = array(
            '127.0.0.1:9200'
        );
        $this->client = \Elasticsearch\ClientBuilder::create()->setHosts($params)->build();
    }



    /**
     * 创建索引
     * 提交方式: post
     * 入参:    索引名 indexes
                索引类型名 types
                索引主键名 ides
     */
    public function creating(){
        $client = \Elasticsearch\ClientBuilder::create()->setHosts(['localhost:9200'])->build();

        $res = $this->allGoods();//查询商品源数据库数据

        $index = I('indexes');//获取的索引名
        $type = I('types');//获取的索引类型名
        $id = I('ides');//获取的索引主键名

        for($i=0;$i<count($res);$i++){
            $params = [
                'index' => $index, //索引名称
                'type' => $type, //索引表
                'id' => $id.$res[$i]['goods_id'], //主键
                'body' => [
                    'goods_id' => $res[$i]['goods_id'],
                    'goods_name' => $res[$i]['goods_name'],
                    'keywords' => $res[$i]['keywords']
                ]
            ];
            $response = $client->index($params);
        }
        return outputSuccess($response);


    }




    /*
     * 查询
     * post 提交
     * 查询判断时传入参数 $search
     * */
    public function finding($indexes = '',$findTypes = '',$myId = '',$type = '',$search = '')
    {
        $client = \Elasticsearch\ClientBuilder::create()->setHosts(['localhost:9200'])->build();

        if(!$search) $search = I('search'); //非查询判断时获取查询数据

        $findType = $findTypes?$findTypes: 1;
        $myIds = $myId?$myId: '';

        if ($findType == self::FINDING_TYPE[1]){ //用作条件搜索
            if (!$search) return outputError('请输入搜索条件');//没有索引类型返回错误信息
            $params = [
                'index' => 'tp_goods',
                'type' => 'goods_type',
                'body' => [
                    'query' => [
                        'multi_match' => [
                            'query' => $search,
                            'fields' =>['goods_name','keywords']
                        ]
                    ]
                ]
            ];
        }

        // 用作条件判断,检测库是否存在
        if ($findType == self::FINDING_TYPE[2]){
            $params = [
                'index' => $indexes,
                'type' => 'goods_type',
                'id' => "my_id$myIds"
            ];
            $res = $client->exists($params);   //检测库是否存在
            return $res;
        }

        // 用作索引文档条件判断,检测库是否存在
        if ($findType == self::FINDING_TYPE[3]){
            $params = [
                'index' => $indexes,
                'type' => 'goods_type',
                'id' => "my_id"
            ];
            $res = $client->exists($params);   //检测库是否存在
            return $res;
        }


        $response = $client->search($params);
        $resArr = $response['hits']['hits'];
        if (empty($resArr)) return $nullArr = [];
        for ($i = 0;$i<count($resArr);$i++){  //获取搜索索引结果(二位数组)
            $arr[] = $resArr[$i]['_source'];
        }
        $goodsIdArr = array_column($arr, 'goods_id'); //取出结果二位数组中的商品id集

//        return outputSuccess($response);
        return outputSuccess($goodsIdArr);
    }



    /*
     * 联动更新索引库内容$myId,$goodsName,$keyWords
     * */
    public function updateIndex()
    {
        $client = \Elasticsearch\ClientBuilder::create()->setHosts(['localhost:9200'])->build();

        $myId = 2020;
        $goodsName = 123;
        $keyWords = 456;

        $params = [
            'index' => 'tp_goods',
            'type' => 'goods_type',
            'id' => "my_id$myId",
            'body' => [
                'doc' => [
                    'goods_name' => $goodsName,
                    'keywords' => $keyWords
                ]
            ]
        ];

        $res = $client->update($params);
        return $res;
    }




    /*
     * 查询商品源数据库
     * */
    public function allGoods()
    {
        $field = [
            'goods_id',
            'goods_name',
            'keywords'
        ];
        //查询条件,排除goods为100万的特殊商品
        $res = Db::table('tp_goods')->field($field)->where('goods_id','<>',1000000)->select();//查询源数据库商品id,商品名,商品关键字
        return $res;
    }




    /**
     * 删除索引
     * 提交方式:  get
     * 入参:  索引库名 indexes
     */
    public function deleteIndex(){
        $indexes = I('indexes');
        $myId = 2003;
        $res = $this->finding($indexes,self::FINDING_TYPE[2],$myId);
        if (!$res) return outputError('该商品索引不存在');
        $client = \Elasticsearch\ClientBuilder::create()->setHosts(['localhost:9200'])->build();

        $params = [
            'index' => $indexes,
            'type' => 'goods_type',
            'id' => "my_id$myId"
        ];

        $res = $client->delete($params);

        if ($res){
            return  outputSuccess('','删除成功');
        }
        return outputError('删除失败');
    }


    /**************     慎重使用文档删除      *************************/
    /*
     * 删除索引文档
     * **/
    public function deleteDoc()
    {
        $indexes = I('indexes');
        $client = \Elasticsearch\ClientBuilder::create()->setHosts(['localhost:9200'])->build();

//        $res = $this->finding($indexes,self::FINDING_TYPE[3]);
//        if (!$res) return outputError('该索引文档不存在');
        $params = ['index' => $indexes];
//        dd($params);
        $response = $client->indices()->delete($params);
        if ($response){
            return  outputSuccess('','!!索引文档删除成功');
        }
        return outputError('索引文档删除失败');
    }







}


