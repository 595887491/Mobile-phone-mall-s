<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/6
 * Time: 14:20
 */

namespace app\common\model;


use think\Db;
use think\Exception;
use think\Model;

class EsModel extends Model
{
    public  $client ;


    const FINDING_TYPE = [
        1 => 1,//搜索查询
        2 => 2,//用作判断,检测库是否存在
        3 => 3 //用作索引文档删除判断,检测库是否存在
    ];

    const INDEX = 'tp_goods'; //索引名称
    const TYPE = 'goods_type'; //索引类型名

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
     * 初始化/更新索引文档
     * 提交方式: post
     * 入参:    索引名 indexes
                索引类型名 types
                索引主键名 ides
     */
    public function creating($goodsName = '',$keyWords = ''){
        $client = \Elasticsearch\ClientBuilder::create()->setHosts(['localhost:9200'])->build();

        $res = $this->allGoods();//查询商品源数据库数据
//halt($res);
        foreach($res as $k=>$value){
            $params['body'][] = [
                'index' => [
                    '_index' => self::INDEX,
                    '_type' => self::TYPE,
                    '_id'  =>$value['goods_id']
                ]
            ];
            $params['body'][] = [
                'goods_id' => $value['goods_id'],
                'goods_name' => $value["goods_name"],
                'keywords' => $value["keywords"],
                'is_on_sale' => $value["is_on_sale"]
            ];
        }

//        halt($params);
//        $params = [
//            'index' => self::INDEX,  //索引名（相当于mysql的数据库）
//            'body' => [
//                'mappings' => [
//                    self::INDEX => [ //类型名（相当于mysql的表）
//                        '_all'=>[   //  是否开启所有字段的检索
//                            'enabled' => 'false'
//                        ],
//                        'properties' => [ //文档类型设置（相当于mysql的数据类型）
//                            'goods_id' => [
//                                'type' => 'integer' // 字段类型为整型
//                            ],
//                            'goods_name' => [
//                                'type' => 'text' // 字段类型为关键字,如果需要全文检索,则修改为text,注意keyword字段为整体查询,不能作为模糊搜索
//                            ],
//                            'keywords' => [
//                                'type' => 'keyword'
//                            ]
//
//                        ]
//                    ]
//                ]
//            ]
//        ];
//        $response = $client->create($params);
        $response = $client->bulk($params);
        return outputSuccess($response);



    }



    /*
     * 增量更新索引
     * */
    public function addIndex($myId,$goodsName,$keyWords,$is_on_sale)
    {
        $client = \Elasticsearch\ClientBuilder::create()->setHosts(['localhost:9200'])->build();

        $params = [
            'index' => self::INDEX,
            'type' => self::TYPE,
            'id' => "my_id$myId",
            'body' => [
                'doc' => [
                    'goods_id' => $myId,
                    'goods_name' => $goodsName,
                    'keywords' => $keyWords,
                    'is_on_sale' => $is_on_sale
                ]
            ]
        ];

        $res = $client->index($params);
        return $res;
    }




    /*
     * 查询
     * post 提交
     * 查询判断时传入参数 $search
     * */
    public function finding($search = '',$indexes = '',$findTypes = '',$myId = '',$type = '')
    {
        $client = \Elasticsearch\ClientBuilder::create()->setHosts(['localhost:9200'])->build();

        if(!$search) $search = I('search'); //非查询判断时获取查询数据

        $findType = $findTypes?$findTypes: 1;
        $myIds = $myId?$myId: '';

        if ($findType == self::FINDING_TYPE[1]){ //用作条件搜索
            if (!$search) return outputError('请输入搜索条件');//没有索引类型返回错误信息
            $params = [
                'index' => self::INDEX,
                'type' => self::TYPE,
                'size' => 9999, // 查询数量
                'body' => [
                    'query' => [
                        'multi_match' => [
                             'query' => $search,
                             'fields' => ['goods_name','keywords']
                         ]
                    ],
                ]
            ];
        }

        // 用作条件判断,检测库是否存在
        if ($findType == self::FINDING_TYPE[2]){
            $params = [
                'index' => $indexes,
                'type' => self::TYPE,
                'id' => "my_id$myIds"
            ];
            $res = $client->exists($params);   //检测库是否存在

            return $res;
        }

        // 用作索引文档条件判断,检测库是否存在
        if ($findType == self::FINDING_TYPE[3]){
            $params = [
                'index' => $indexes,
                'type' => self::TYPE,
                'id' => "my_id"
            ];
            $res = $client->exists($params);   //检测库是否存在
            return $res;
        }

        $response = $client->search($params);  //搜索


        $resArr = $response['hits']['hits'];//取值

        $arr = array_column($resArr, '_source');

        foreach ($arr as $k=>$v) {
            if ($v['is_on_sale'] == 1) $goodsIdArr[] = $v['goods_id'];  //取出结果二位数组中的商品id集
        }


        return $goodsIdArr;
    }



    /*
     * 联动更新索引库内容
     * */
    public function updateIndex($myId,$goodsName,$keyWords,$is_on_sale)
    {
        $client = \Elasticsearch\ClientBuilder::create()->setHosts(['localhost:9200'])->build();

        $params = [
            'index' => self::INDEX,
            'type' => self::TYPE,
            'id' => "my_id$myId",
            'body' => [
                'doc' => [
                    'goods_name' => $goodsName,
                    'keywords' => $keyWords,
                    'is_on_sale' => $is_on_sale
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
            'keywords',
            'is_on_sale'
        ];
        //查询条件,排除goods_id为100万的特殊商品
        $res = Db::table('tp_goods')->field($field)->where('goods_id','<>',1000000)->select();//查询源数据库商品id,商品名,商品关键字
        return $res;
    }




    /**
     * 删除索引
     * 提交方式:  get
     * 入参:  索引库名 indexes  商品id  $myId
     */
    public function deleteIndex($myId){
        $indexes = I('indexes');
        $res = $this->finding('',$indexes,self::FINDING_TYPE[2],$myId);
        if (!$res) return outputError('该商品索引不存在');
        $client = \Elasticsearch\ClientBuilder::create()->setHosts(['localhost:9200'])->build();

        $params = [
            'index' => self::INDEX,
            'type' => self::TYPE,
            'id' => "my_id$myId"
        ];
        $res = $client->delete($params);

        if ($res){
            return  outputSuccess('','删除成功');
        }
        return outputError('删除失败');
    }



    /*
     * 获取索引信息
     * */
    public function getIndexInfo()
    {
        $client = \Elasticsearch\ClientBuilder::create()->setHosts(['localhost:9200'])->build();

        $params = [
            'index' => self::INDEX,
            'client' => [
                'ignore' => 404
            ]
        ];
        $res = $client->indices()->getSettings($params);//获取库索引设置
        return $res;
    }


    /**************     慎重使用文档删除      *************************/
    /*
     * 删除索引文档
     * **/
    public function deleteDoc()
    {
        $indexes = I('indexes');
        $indexes = 'tp_goods';
        $client = \Elasticsearch\ClientBuilder::create()->setHosts(['localhost:9200'])->build();

//        $res = $this->finding($indexes,self::FINDING_TYPE[3]);
//        if (!$res) return outputError('该索引文档不存在');
        $params = ['index' => $indexes];

        $response = $client->indices()->delete($params);
        if ($response){
            return  outputSuccess('','!!索引文档删除成功');
        }
        return outputError('索引文档删除失败');
    }

    //删除后新增索引
    public function initialization()
    {
        $res = $this->deleteDoc();
        if ($res) $result = $this->creating();
        return $result;
    }




}