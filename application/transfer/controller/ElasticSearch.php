<?php
/**
 * Created by PhpStorm.
 * User: bluce
 * Date: 2018/6/17 0021
 * Time: 上午 9:24
 */
namespace app\transfer\controller;


use app\mobile\controller\Goods;
use app\transfer\common\DbClass;
use Elasticsearch\ClientBuilder ;

define( 'SRC_DB', 'tpshop_dev' )   ;   //源数据库 只能读
define( 'DIST_DB', 'tpshop_dev' ) ;     //目标数据库，可读写


//分词搜索
class ElasticSearch{
    public  $client  ;
    public function _initialize()
    {

//        Vendor('elasticsearch.autoload');
//        $params['hosts'] = array(
//            '127.0.0.1:9200'
//        );
//      $this->client = new \Elasticsearch\Client($params);

        //host数组可配置多个节点
        $params = array(
            '127.0.0.1:9200'
        );
        $this->client = \Elasticsearch\ClientBuilder::create()->setHosts($params)->build();

    }

    /**
     * 创建索引
     */
    public function creating(){
        $client = \Elasticsearch\ClientBuilder::create()->setHosts(['localhost:9200'])->build();
        $params = [
            'index' => 'my_test', //索引名称
            'type' => 'my_type', //索引表
            'id' => 'my_id', //主键
            'body' => [
                '_id'=>'goods_id',
                '_name'=>'goods_name',
                '_key'=>'keywords'
            ]
        ];

        $res = $client->index($params);

    }



    //查询
    public function finding()
    {
        $indexes = I('indexes');
        $client = \Elasticsearch\ClientBuilder::create()->setHosts(['localhost:9200'])->build();
        $params = [
            'index' => "$indexes",
            'type' => 'my_type',
            'id' => 'my_id',
//            'body' => [
//                'query' => [
//                    'match' => "Anna", //全局分词
//                    'fields' => ['name','age'] //查询字段
//                ]
//            ]
        ];
//        $params = json_encode($params);
//        dump($params);die;

        $res = $client->search($params);
        dump($res);die;
    }



  /*
   * 查看mapping
   * */
    public function getMappings(){
        $indexes = I('indexes');

        $client = \Elasticsearch\ClientBuilder::create()->setHosts(['localhost:9200'])->build();

        $params = [
            'index' => "$indexes"
        ];

        $res = $client->indices()->getMapping($params);
        dump($res);
//        if(!empty($res)) return json_encode($res);
    }

    /*
     * 创建多条
     * */
    public function postBulkDoc(){
        $indexes = I('indexes'); //索引名
        $filed = [
            'goods_id',
            'goods_name',
            'keywords'
        ];
        $res = \think\Db::table('tp_goods')->field($filed)->select(); //查询源数据库商品id,商品名,商品关键字


        $client = \Elasticsearch\ClientBuilder::create()->setHosts(['localhost:9200'])->build();  //调用es库文件,创建对象

//        for($i = 0; $i < count($res); $i++) {  //添加索引多条数据即源数据库id,名,关键字
//            $params['body'][] = [
//                'index' => [
//                '_index' => "$indexes",
//                '_type' => 'my_type',
//                '_id'=> $res[$i]['goods_id'],
//                '_name'=> $res[$i]['goods_name'],
//                '_key'=> $res[$i]['keywords']
//                ],
//            ];
//        }

        $params = [
            'index' => "$indexes",
            'type' => 'my_type',
            'body' => [
                '_id' => 17,
                '_name' => 'saki',
                '_key' => '女性',
            ]
        ];

        dump($params);die;
        $res = $client->index($params);
        dump($res);

    }



















/**
 * 删除索引
 */
public function deleteIndex(){
    $indexes = I('indexes');
    $client = \Elasticsearch\ClientBuilder::create()->setHosts(['localhost:9200'])->build();

    $params = [
        'index' => "$indexes"
    ];

    $res = $client->indices()->delete($params);
    if ($res){
        return  '索引删除成功';
    }
    return false;
}



//转移用户
public function test()
{

//        $sql    = "SELECT * FROM emp";
//        $conn   = get_conn();
//        $stmt   = $conn->query($sql);
//        $rtn    = $stmt->fetchAll();

    echo "ok!"  ;
    //  var_dump( $client  ) ;
    //  $this->createIndex() ;
    $client = \Elasticsearch\ClientBuilder::create()->setHosts(['localhost:9200'])->build();

    $params = array(
        'index' => 'website',
        'type' => 'blog',
        'id' => 7,
        'body' => array(
            'title' => 'ElasticSearch-PHP之使用二',
            'content' => '有关于ElasticSearch在PHP下的扩展使用方法之谈',
            'create_time' => '2016-11-18 08:00:00',
        ),
    );
    dump($params);
    //$resp = $this->client ;
    // $res =$client->index($params) ;

    $params = [
        'index' => 'website',
        'client' => [
            'ignore' => 404
        ]
    ];

    //  $res = $client->indices()->getSettings($params);//获取库索引设置信息
    //  $res = $client->indices()->getMapping($params);   //获取mapping信息


    $params = [
        'index' => 'website',
        'type' => 'blog',
        'id' => 7
    ];

    $res = $this->client->get()   -get()>get($params);   //获取指定的文档


    var_dump( $res) ;die() ;


}



    /**
     * 创建索引
     */

    public function  createIndex() {

        $client = \Elasticsearch\ClientBuilder::create()->setHosts(['localhost:9200'])->build();

        $params = [
            'index' => 'my_test',  //索引名（相当于mysql的数据库）
            'body' => [
                'settings' => [
                    'number_of_shards' => 5,  #分片数
                ],
                'mappings' => [
                    'tp_goods' => [ //类型名（相当于mysql的表）
                        '_all' => [
                            'enabled' => 'false'
                        ],
                        '_routing' => [
                            'required' => 'true'
                        ],
                        'properties' => [ //文档类型设置（相当于mysql的数据类型）
                            'goods_name' => [
                                'type' => 'string',
                                'store' => 'true'
                            ],
                            'goods_id' => [
                                'type' => 'integer'
                            ],
                            'keywords' => [
                                'type' => 'string',
                                'store' => 'true'
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $res = $client->indices()->create($params);   //创建库索引

    }
}


