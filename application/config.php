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
 * 采用最新Thinkphp5助手函数特性实现单字母函数M D U等简写方式
 * ============================================================================
 * $Author: IT宇宙人 2015-08-10 $
 */
//基础配置(tp5原始)
$base_config = [
    // +----------------------------------------------------------------------
    // | 应用设置
    // +----------------------------------------------------------------------

    // 应用命名空间
    'app_namespace'          => 'app',
    // 是否支持多模块
    'app_multi_module'       => true,
    // 入口自动绑定模块
    'auto_bind_module'       => false,
    // 注册的根命名空间
    'root_namespace'         => [],
    // 扩展函数文件
    'extra_file_list'        => [THINK_PATH . 'helper' . EXT,APP_PATH.'function.php'],
    // 默认输出类型
    'default_return_type'    => 'html',
    // 默认AJAX 数据返回格式,可选json xml ...
    'default_ajax_return'    => 'html',
    // 默认JSONP格式返回的处理方法
    'default_jsonp_handler'  => 'jsonpReturn',
    // 默认JSONP处理方法
    'var_jsonp_handler'      => 'callback',
    // 默认时区
    'default_timezone'       => 'PRC',
    // 是否开启多语言
    'lang_switch_on'         => false,
    // 默认全局过滤方法 用逗号分隔多个
    'default_filter'         => 'htmlspecialchars',
    // 默认语言
    'default_lang'           => 'zh-cn',
    // 应用类库后缀
    'class_suffix'           => false,
    // 控制器类后缀
    'controller_suffix'      => false,

    // +----------------------------------------------------------------------
    // | 模块设置
    // +----------------------------------------------------------------------

    // 默认模块名
    'default_module'         => 'home',
    // 禁止访问模块
    'deny_module_list'       => ['common'],
    // 默认控制器名
    'default_controller'     => 'Index',
    // 默认操作名
    'default_action'         => 'index',
    // 默认验证器
    'default_validate'       => '',
    // 默认的空控制器名
    'empty_controller'       => 'Error',
    // 操作方法后缀
    'action_suffix'          => '',
    // 自动搜索控制器
    'controller_auto_search' => false,

    // +----------------------------------------------------------------------
    // | URL设置
    // +----------------------------------------------------------------------

    // PATHINFO变量名 用于兼容模式
    'var_pathinfo'           => 's',
    // 兼容PATH_INFO获取
    'pathinfo_fetch'         => ['ORIG_PATH_INFO', 'REDIRECT_PATH_INFO', 'REDIRECT_URL'],
    // pathinfo分隔符
    'pathinfo_depr'          => '/',
    // URL伪静态后缀
    'url_html_suffix'        => 'html',
    // URL普通方式参数 用于自动生成
    'url_common_param'       => false,
    // URL参数方式 0 按名称成对解析 1 按顺序解析
    'url_param_type'         => 0,
    // 是否开启路由
    'url_route_on'           => true,
    // 路由使用完整匹配
    'route_complete_match'   => false,
    // 路由配置文件（支持配置多个）
    'route_config_file'      => ['route'],
    // 是否强制使用路由
    'url_route_must'         => false,
    // 域名部署
    'url_domain_deploy'      => false,
    // 域名根，如thinkphp.cn
    'url_domain_root'        => '',
    // 是否自动转换URL中的控制器和操作名
    'url_convert'            => false,
    // 默认的访问控制器层
    'url_controller_layer'   => 'controller',
    // 表单请求类型伪装变量
    'var_method'             => '_method',
    // 表单ajax伪装变量
    'var_ajax'               => '_ajax',
    // 表单pjax伪装变量
    'var_pjax'               => '_pjax',
    // 是否开启请求缓存 true自动缓存 支持设置请求缓存规则
    'request_cache'          => false,
    // 请求缓存有效期
    'request_cache_expire'   => null,
    // 全局请求缓存排除规则
    'request_cache_except'   => [],

    // +----------------------------------------------------------------------
    // | 模板设置
    // +----------------------------------------------------------------------

    'template'               => [
        // 模板引擎类型 支持 php think 支持扩展
        'type'         => 'Think',
        // 模板路径
        'view_path'    => '',
        // 模板后缀
        'view_suffix'  => 'html',
        // 模板文件名分隔符
        'view_depr'    => DS,
        // 模板引擎普通标签开始标记
        'tpl_begin'    => '{',
        // 模板引擎普通标签结束标记
        'tpl_end'      => '}',
        // 标签库标签开始标记
        'taglib_begin' => '{',
        // 标签库标签结束标记
        'taglib_end'   => '}',
    ],

    // 视图输出字符串内容替换
    'view_replace_str'       => [],
    // 默认跳转页面对应的模板文件
    'dispatch_success_tmpl'  => THINK_PATH . 'tpl' . DS . 'dispatch_jump.tpl',
    'dispatch_error_tmpl'    => THINK_PATH . 'tpl' . DS . 'dispatch_jump.tpl',

    // +----------------------------------------------------------------------
    // | 异常及错误设置
    // +----------------------------------------------------------------------

    // 异常页面的模板文件
    'exception_tmpl'         => THINK_PATH . 'tpl' . DS . 'think_exception.tpl',
    // errorpage 错误页面
    'error_tmpl'         => THINK_PATH . 'tpl' . DS . 'think_error.tpl',


    // 错误显示信息,非调试模式有效
    'error_message'          => '页面错误！请稍后再试～',
    // 显示错误信息
    'show_error_msg'         => false,
    // 异常处理handle类 留空使用 \think\exception\Handle
    'exception_handle'       => '',

    // +----------------------------------------------------------------------
    // | 会话设置
    // +----------------------------------------------------------------------

    'session'                => [
        'id'             => '',
        // SESSION_ID的提交变量,解决flash上传跨域
        'var_session_id' => '',

        'expire' => 2592000,//180天过期

        // SESSION 前缀
        'prefix'         => 'think',
        // 驱动方式 支持redis memcache memcached
        'type'           => '',
        // 是否自动开启 SESSION
        'auto_start'     => true,
    ],

    // +----------------------------------------------------------------------
    // | Cookie设置
    // +----------------------------------------------------------------------
    'cookie'                 => [
        // cookie 名称前缀
        'prefix'    => '',
        // cookie 保存时间
        'expire'    => 2592000,
        // cookie 保存路径
        'path'      => '/',
        // cookie 有效域名
        'domain'    => '',
        //  cookie 启用安全传输
        'secure'    => false,
        // httponly设置
        'httponly'  => '',
        // 是否使用 setcookie
        'setcookie' => true,
    ],

    // +----------------------------------------------------------------------
    // | 缓存设置
    // +----------------------------------------------------------------------
    /**/
//    'cache'                  => [
//        // 驱动方式
//        'type'   => 'File',
//        // 缓存保存目录
//        'path'   => CACHE_PATH,
//        // 缓存前缀
//        'prefix' => '29fbabf56996a078876228c0b7ca7f6d',
//        // 缓存有效期 0表示永久缓存
//        'expire' => 180 * 24 * 3600,
//    ],

    'cache'                  => [
        // 驱动方式
        'type'   => 'redis',
        // 服务器地址
        'host'       => '127.0.0.1',
        'port' => 6379,
        // 缓存有效期 0表示永久缓存
        'expire' => 300,

        'select' => $GLOBALS['ENV'] == 'PRODUCT' ? 2 : 15,//正式环境用2，其他环境都用1
    ],

    //分页配置
    'paginate'               => [
        'type'      => 'bootstrap',
        'var_page'  => 'page',
        'list_rows' => 15,
    ],
    // 密码加密串
    'AUTH_CODE' => "TPSHOP", //安装完毕之后不要改变，否则所有密码都会出错

    'ORDER_STATUS' =>[
        0 => '待确认',
        1 => '已确认',
        2 => '已收货',
        3 => '已取消',
        4 => '已完成',//评价完
        5 => '已作废',
    ],
    'SHIPPING_STATUS' => array(
        0 => '未发货',
        1 => '已发货',
        2 => '部分发货'
    ),
    'PAY_STATUS' => array(
        0 => '未支付',
        1 => '已支付',
        2 => '部分支付',
        3 => '已退款',
        4 => '拒绝退款'
    ),
    'SEX' => [
        0 => '保密',
        1 => '男',
        2 => '女'
    ],
    'COUPON_TYPE' => [
        0 => '下单赠送',
        1 => '指定发放',
        2 => '免费领取',
        3 => '线下发放',
        4 => '注册免费领取',
        5 => '完善资料送券',
        6 => '邀请用户送券',
        7 => '新人大礼包'
    ],
    'PROM_TYPE' => [
        0 => '默认',
        1 => '抢购',
        2 => '团购',
        3 => '优惠'
    ],
    'TEAM_FOUND_STATUS' => array(
        '0'=>'待开团',
        '1'=>'已开团',
        '2'=>'拼团成功',
        '3'=>'拼团失败',
    ),
    'TEAM_FOLLOW_STATUS' => array(
        '0'=>'待拼单',
        '1'=>'拼单成功',
        '2'=>'成团成功',
        '3'=>'成团失败',
    ),

    'TEAM_TYPE' => [0 => '分享团', 1 => '佣金团', 2 => '抽奖团'],
    'FREIGHT_TYPE' => [0 => '件数', 1 => '重量', 2 => '体积'],
    // 订单用户端显示状态
    'WAITPAY'=>' AND pay_status = 0 AND order_status = 0 AND pay_code !="cod" ', //订单查询状态 待支付
    'WAITSEND'=>' AND (pay_status=1 OR pay_code="cod") AND shipping_status !=1 AND order_status in(0,1) ', //订单查询状态 待发货
    'WAITRECEIVE'=>' AND shipping_status=1 AND order_status = 1 ', //订单查询状态 待收货
    'WAITCCOMMENT'=> ' AND order_status=2 ', // 待评价 确认收货     //'FINISHED'=>'  AND order_status=1 ', //订单查询状态 已完成
    'FINISH'=> ' AND order_status = 4 ', // 已完成
    'CANCEL'=> ' AND order_status = 3 ', // 已取消
    'CANCELLED'=> 'AND order_status = 5 ',//已作废
    'PAYED'=>' AND (order_status=2 OR (order_status=1 AND pay_status=1) ) ', //虚拟订单状态:已付款

    'ORDER_STATUS_DESC' => [
        'WAITPAY' => '待支付',
        'WAITSEND'=>'待发货',
        'PORTIONSEND'=>'部分发货',
        'WAITRECEIVE'=>'待收货',
        'WAITCCOMMENT'=> '待评价',
        'CANCEL'=> '已取消',
        'FINISH'=> '已完成', //
        'CANCELLED'=> '已作废'
    ],

    'REFUND_STATUS'=>array(
        -2 => '服务单取消',//会员取消
        -1 => '审核失败',//不同意
        0  => '待审核',//卖家审核
        1  => '审核通过',//同意
        2  => '买家发货',//买家发货
        3  => '已收货',//服务单完成
        4  => '换货完成',
        5  => '退款完成',
    ),
    /**
     * 售后类型
     */
    'RETURN_TYPE'=>array(
        0=>'仅退款',
        1=>'退货退款',
        2=>'换货',
    ),
    //短信使用场景（tpcache中可设置开关）
    'SEND_SCENE' => array(
        '1'=>array('用户注册','验证码：${code}，您正在使用手机号码+短信验证码登录商城，请及时完成验证操作！','regis_sms_enable'),
        '2'=>array('退款提醒','尊敬的：${name}，您的退款已完成，请注意查收！','regis_sms_enable'),
        '3'=>array('秒杀提醒','你关注的 ”${goods_name}” 马上就要开始抢购啦，数量有限，赶快进入”尚美缤纷“公众号进行抢购吧！','regis_sms_enable'),
        '4'=>array('验证码','您的验证码${code}，该验证码2分钟内有效，请勿泄漏于他人！','regis_sms_enable'),
        '5'=>array('付款成功通知','尊敬的：${name}，您的订单：${order_sn}，已经付款成功，正快马加鞭为您安排发货，请您耐心等待！','regis_sms_enable'),
        '6'=>array('取消订单（未付款）','您的订单已取消，订单号：${order_sn}，详情请登录尚美缤纷公众号查询','regis_sms_enable'),
        '7'=>array('取消订单（已付款）','您的订单已取消，订单号：${order_sn}，已付金额将在${refund_period}个工作日原路退还至您的账户，详情请登录尚美缤纷公众号','regis_sms_enable'),
        '8'=>array('退换货申请提交成功','您的${service_type}申请已提交成功，我们会在${service_period}个工作日内为您处理，详情请登录尚美缤纷公众号查询','regis_sms_enable'),
        '9'=>array('退货审核成功（不回寄商品）','您的退货申请已通过，已付金额将在${return_goods_period}个工作日内原路退还至您的账户，详情请登录尚美缤纷公众号查询','regis_sms_enable'),
        '10'=>array('退货审核成功（需回寄商品）','您的退货申请已通过，请回寄商品，我们收到商品后${return_goods_period}个工作日内为您退款，详情请登录尚美缤纷公众号查询','regis_sms_enable'),
        '11'=>array('换货审核成功','您的换货申请已通过，请尽快回寄商品，详情请登录尚美缤纷公众号查询','regis_sms_enable'),
        '12'=>array('退换货审核失败','您的${service_type}申请审核未通过，原因：${check_reason}，详情请登录尚美缤纷公众号查询','regis_sms_enable'),
        '13'=>array('提现受理成功','你的提现已受理，提现资金将会在${withdraw_period}个工作日内到账，详情请登录尚美缤纷公众号查询','regis_sms_enable'),
        '14'=>array('提现受理不成功','你的提现申请未通过审核，原因：${withdraw_refuse_reason}，详情请登录尚美缤纷公众号查询','regis_sms_enable'),
        '15'=>array('打榜活动奖金发放','排行榜活动奖金已发放至您的${reward_type}，金额${current_rank_reward}元，详情请登录尚美缤纷公众号查询','regis_sms_enable'),
        '16' => ['拼团下单成功','尊敬的：${user_name}，您的拼团订单：${order_sn}，已经付款成功，邀请好友一起参团，成团后为您安排发货。'],
        '17' => ['卡券下单成功','尊敬的：${user_name}，您的卡券订单：${order_sn}，已经付款成功，有效期至${virtual_indate}，请在有效期内完成消费。'],
    ),
    'APP_TOKEN_TIME' => 60 * 60 * 24 , //App保持token时间 , 此处为1天

    /**
     *  订单用户端显示按钮
    去支付     AND pay_status=0 AND order_status=0 AND pay_code ! ="cod"
    取消按钮  AND pay_status=0 AND shipping_status=0 AND order_status=0
    确认收货  AND shipping_status=1 AND order_status=0
    评价      AND order_status=1
    查看物流  if(!empty(物流单号))
    退货按钮（联系客服）  所有退换货操作， 都需要人工介入   不支持在线退换货
     */

    /*分页每页显示数*/
    'PAGESIZE' => 10,

    'WX_PAY2' => 1,

    /**假设这个访问地址是 www.tpshop.cn/home/goods/goodsInfo/id/1.html
     *就保存名字为 home_goods_goodsinfo_1.html
     *配置成这样, 指定 模块 控制器 方法名 参数名
     */
    'HTML_CACHE_ARR'=> [
        ['mca'=>'home_Goods_goodsInfo','p'=>['id']],
        ['mca'=>'home_Index_index'],  // 缓存首页静态页面
        ['mca'=>'home_Goods_ajaxComment','p'=>['goods_id','commentType','p']],  // 缓存评论静态页面 http://www.tpshop2.0.com/index.php?m=Home&c=Goods&a=ajaxComment&goods_id=142&commentType=1&p=1
        ['mca'=>'home_Goods_ajax_consult','p'=>['goods_id','consult_type','p']],  // 缓存咨询静态页面 http://www.tpshop2.0.com/index.php?m=Home&c=Goods&a=ajax_consult&goods_id=142&consult_type=0&p=2
    ],

    /*订单操作*/
    'CONVERT_ACTION'=>[
        'pay'=> '付款',
        'pay_cancel'=>'取消付款',
        'confirm'=>'确认订单',
        'cancel'=>'取消确认',
        'invalid'=>'作废订单',
        'remove'=>'删除订单',
        'delivery'=>'确认发货',
        'delivery_confirm'=>'确认收货',
    ],
    'WITHDRAW_STATUS'=>[
        '-2'=>'删除作废',
        '-1'=>'审核失败',
        '0'=>'申请中',
        '1'=>'审核通过',
        '2'=>'付款成功',
        '3'=>'付款失败',
    ],
    'RECHARGE_STATUS'=>[
        '0'=>'待支付',
        '1'=>'支付成功',
        '2'=>'交易关闭',
    ],
    'erasable_type' =>['.gif','.jpg','.jpeg','.bmp','.png','.mp4','.3gp','.flv','.avi','.wmv'],
    'COUPON_USER_TYPE'=>['全店通用','指定商品可用','指定分类商品可用'],
    'image_upload_limit_size'=>1024 * 1024 * 5,//上传图片大小限制

];

//基于环境的配置
switch ($GLOBALS['ENV'])
{
    //测试环境
    default:
    case 'CHENJING':       $base_on_env_config = [
    // 应用调试模式
    'app_debug'              => true,
    // 应用Trace
    'app_trace'              => false,
    // 应用模式状态
    'app_status'             => '',

    // +----------------------------------------------------------------------
    // | 日志设置
    // +----------------------------------------------------------------------
//            'log'                    => [
//                //日志记录方式，内置 file socket 支持扩展
//                'type'  => 'socket',
//                'host'  => '47.100.19.77',
//                //日志强制记录到配置的client_id
//                'force_client_ids'    => ['test'],
//                //限制允许读取日志的client_id
//                'allow_client_ids'    => ['test'],
//                // 日志开关  1 开启 0 关闭
//                'switch' => 1,
//            ],

    'trace'                  => [
        // 内置Html Console 支持扩展
        'type' => 'Html',
    ],
];
    break;
    case 'LOCAL':
    case 'DEV':
    case 'TEST':
        $base_on_env_config = [
            // 应用调试模式
            'app_debug'              => true,
            // 应用Trace
            'app_trace'              => false,
            // 应用模式状态
            'app_status'             => '',

            // +----------------------------------------------------------------------
            // | 日志设置
            // +----------------------------------------------------------------------
//            'log'                    => [
//                //日志记录方式，内置 file socket 支持扩展
//                'type'  => 'socket',
//                'host'  => '47.100.19.77',
//                //日志强制记录到配置的client_id
//                'force_client_ids'    => ['test'],
//                //限制允许读取日志的client_id
//                'allow_client_ids'    => ['test'],
//                // 日志开关  1 开启 0 关闭
//                'switch' => 1,
//            ],
            'log'                    => [
                // 日志记录方式，内置 file socket 支持扩展
                'type'  => 'File',
                // 日志保存目录
                'path'  => LOG_PATH,
                // 日志记录级别
                'level' => ['error','warning'],
                // 日志开关  1 开启 0 关闭
                'switch' => 1,
            ],

            'trace'                  => [
                // 内置Html Console 支持扩展
                'type' => 'Html',
            ],
        ];
        break;
    //正式环境
    case 'FORMAL':
        $base_on_env_config = [
            // 应用调试模式
            'app_debug'              => true,
            // 应用Trace
            'app_trace'              => false,
            // 应用模式状态
            'app_status'             => '',

            // +----------------------------------------------------------------------
            // | 日志设置
            // +----------------------------------------------------------------------
            'log'                    => [
                // 日志记录方式，内置 file socket 支持扩展
                'type'  => 'File',
                // 日志保存目录
                'path'  => LOG_PATH,
                // 日志记录级别
                'level' => ['error','warning'],
                // 日志开关  1 开启 0 关闭
                'switch' => 1,
            ],
            // +----------------------------------------------------------------------
            // | Trace设置 开启 app_trace 后 有效
            // +----------------------------------------------------------------------
            'trace'                  => [
                // 内置Html Console 支持扩展
                'type' => 'Html',
            ]
        ];
        break;
    //正式环境
    case 'PRODUCT':
        $base_on_env_config = [
            // 应用调试模式
            'app_debug'              => false,
            // 应用Trace
            'app_trace'              => false,
            // 应用模式状态
            'app_status'             => '',

            // +----------------------------------------------------------------------
            // | 日志设置
            // +----------------------------------------------------------------------
            'log'                    => [
                // 日志记录方式，内置 file socket 支持扩展
                'type'  => 'File',
                // 日志保存目录
                'path'  => LOG_PATH,
                // 日志记录级别
                'level' => ['error'],
                // 日志开关  1 开启 0 关闭
                'switch' => 1,
            ],





            // +----------------------------------------------------------------------
            // | Trace设置 开启 app_trace 后 有效
            // +----------------------------------------------------------------------
            'trace'                  => [
                // 内置Html Console 支持扩展
                'type' => 'Html',
            ]
        ];
        break;

}

return array_merge($base_on_env_config,$base_config);


