<?php
/**
 * tpshop
 * ============================================================================
 * 版权所有 2015-2027 深圳搜豹网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.tp-shop.cn
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 如果商业用途务必到官方购买正版授权, 以免引起不必要的法律纠纷.
 * ============================================================================
 */

namespace app\common\logic;

use app\common\library\Logs;
use app\common\model\Order;
use app\common\model\UserAgentModel;
use app\common\model\UserModel;
use app\common\model\Users;
use app\common\model\UserUserModel;
use app\mobile\controller\Activity;
use app\mobile\controller\Vote;
use gmars\nestedsets\NestedSets;
use Monolog\Handler\SocketHandlerTest;
use think\Db;
use think\Cache;
use think\Exception;
use think\Image;
use think\Log;
use think\Validate;
use app\common\model\WxNews;
use app\common\model\WxReply;
use app\common\model\WxTplMsg;
use app\common\model\WxKeyword;
use app\common\model\WxMaterial;
use app\common\logic\wechat\WechatUtil;

/**
 * 微信公众号的业务逻辑类
 */
class WechatLogic
{
    static private $wx_user = null;
    static private $wechat_obj;

    public function __construct($config = null)
    {
        if (!self::$wx_user) {
            if ($config === null) {
                $config = Db::name('wx_user')->find();
            } 
            self::$wx_user = $config;
            self::$wechat_obj = new WechatUtil(self::$wx_user);
        }
    }

    /**
     * 处理接收推送消息
     */
    public function handleMessage()
    {
        self::$wechat_obj->registerMsgEvent(WechatUtil::EVENT_TEXT, function ($msg) {
            $this->handleTextMsg($msg);
        });

        self::$wechat_obj->registerMsgEvent(WechatUtil::EVENT_CLICK, function ($msg) {
            $this->handleClickEvent($msg);
        });

        self::$wechat_obj->registerMsgEvent(WechatUtil::EVENT_SUBSCRIBE, function ($msg) {
            $this->handleSubscribeEvent($msg);
        });

        self::$wechat_obj->registerMsgEvent(WechatUtil::EVENT_SCAN, function ($msg) {
            $this->handleScanEvent($msg);
        });

        self::$wechat_obj->handleMsgEvent();
    }

    /**
     * 已关注公众号，扫码推送事件 SCAN
     * 处理逻辑同关注
     * @param $msg
     */
    private function handleScanEvent($msg){
        $this->handleSubscribeEvent($msg);
    }
    /**
     * 处理关注事件
     * @param array $msg
     * @return array
     */
    private function handleSubscribeEvent($msg)
    {
        $openid = $msg['FromUserName'];
        if (!$openid) {
            exit("openid无效");
        }
        Log::info($msg);
        if ($msg['EventKey'] == 'a51096771' || $msg['EventKey'] =='a51025120') {//扫码兼容之前苏总的二维码
            $msg['EventKey'] = 'a1023';
        }
        if ($msg['MsgType'] != 'event' || ($msg['Event'] != 'subscribe' && $msg['Event'] != 'SCAN')) {
            exit("不是关注事件");
        }
        if (false === ($wxdata = self::$wechat_obj->getFanInfo($openid))) {
            exit(self::$wechat_obj->getError());
        }
        $parent_id = 0;
        if (!empty($msg['EventKey'])) {
            if ($msg['Event'] == 'SCAN') {
                if (substr($msg['EventKey'],0,1) == 'a') {
                    // 扫描代理商二维码进入
                    $parent_id = substr($msg['EventKey'], 1);
                    $is_agent = 1;
                } else {
                    $parent_id = $msg['EventKey'];
                }
            } else{
                if (substr($msg['EventKey'],8,1) == 'a') {
                    // 扫描代理商二维码进入
                    $parent_id = substr($msg['EventKey'], 9);
                    $is_agent = 1;
                } else {
                    $parent_id = substr($msg['EventKey'], 8);
                }

                //扫码关注赠送次数
                if (preg_match("/qrscene_hanfu|/", $msg['EventKey'] )) {
                    (new Vote())->giveVoteTime($msg);
                }
            }
        }
        $scan_type = $is_agent == 1 ? 1 : 0;
        $scan_log = Db::table('cf_user_scan')
            ->where(['open_id'=> $openid, 'scan_type'=>$scan_type,'parent_id'=>$parent_id])
            ->getField('id');
        // 记录用户扫描事件
        if ($parent_id) {
            if ($scan_log) {
                Db::table('cf_user_scan')
                    ->where(['id'=> $scan_log])
                    ->update(['scan_time'=>time(), 'expire_time'=>$is_agent == 1 ? time() + 86400 : 3999999999]);
            } else {
                Db::table('cf_user_scan')->insert([
                    'open_id' => $openid,
                    'union_id' => isset($wxdata['unionid']) ? $wxdata['unionid'] : '',
                    'parent_id' => $parent_id,
                    'scan_type'=> $scan_type,
                    'scan_time' => time(),
                    'expire_time' => $is_agent == 1 ? time() + 86400 : 3999999999 //扫描普通二维码有效期无限长
                ]);
            }
        }
        $this->replySubscribe($msg['ToUserName'], $openid);
    }

    /**
     * 关注时回复消息
     */
    private function replySubscribe($from, $to)
    {
        $result_str = $this->createReplyMsg($from, $to, WxReply::TYPE_FOLLOW);
        if ( ! $result_str) {
            //没有设置关注回复，则默认回复如下：
            $store_name = tpCache("shop_info.store_name");
            $result_str = self::$wechat_obj->createReplyMsgOfText($from, $to, "欢迎来到 $store_name !\n商城入口：".SITE_URL.'/mobile');
        }

        exit($result_str);
    }

    /**
     * 创建回复消息
     * @param $from string 发送方
     * @param $to string 被发送方
     * @param $type string WxReply的类型
     * @param array $data 附加数据
     * @return string
     */
    private function createReplyMsg($from, $to, $type, $data = [])
    {
        if ($type != WxReply::TYPE_KEYWORD) {
            $reply = WxReply::get(['type' => $type]);
        } else {
            $wx_keyword = WxKeyword::get(['keyword' => $data['keyword'], 'type' => WxKeyword::TYPE_AUTO_REPLY], 'wxReply');
            $wx_keyword && $reply = $wx_keyword->wx_reply;
        }

        if (empty($reply)) {
            return '';
        }

        $resultStr = '';
        if ($reply->msg_type == WxReply::MSG_TEXT && $reply['data']) {
            $resultStr = self::$wechat_obj->createReplyMsgOfText($from, $to, $reply['data']);
        } elseif ($reply->msg_type == WxReply::MSG_NEWS) {
            $resultStr = $this->createNewsReplyMsg($from, $to, $reply->material_id);
        } else {
            //扩展其他类型，如image，voice等
        }

        return $resultStr;
    }

    /**
     * 处理点击事件
     * @param array $msg
     */
    private function handleClickEvent($msg)
    {
        $from = $msg['ToUserName'];
        $to = $msg['FromUserName'];
        $eventKey = $msg['EventKey'];
        $distribut = tpCache('distribut');

        // 分销二维码图片
        if ($eventKey === $distribut['qrcode_menu_word']) {
            $this->replyMyQrcode($msg);
        }

        // 关键字自动回复
        $this->replyKeyword($from, $to, $eventKey);
    }

    /**
     * 回复我的二维码
     */
    private function replyMyQrcode($msg)
    {
        $fromUsername = $msg['FromUserName'];
        $toUsername   = $msg['ToUserName'];
        $wechatObj = self::$wechat_obj;

        $user = Db::name('oauth_users')->alias('o')->join('__USERS__ u', 'u.user_id=o.user_id')
            ->field('u.*')->where('o.openid', $fromUsername)->find();
        if (!$user) {
            $content = '请进入商城: '.SITE_URL.' , 再获取二维码哦';
            $reply = $wechatObj->createReplyMsgOfText($toUsername, $fromUsername, $content);
            exit($reply);
        }

        //获取缓存的图片id
        $distribut = tpCache('distribut');
        $mediaId = $this->getCacheQrcodeMedia($user['user_id'], $user['head_pic'], $distribut['qr_big_back']);
        if (!$mediaId) {
            $mediaId = $this->createQrcodeMedia($msg, $user['user_id'], $user['head_pic'], $distribut['qr_big_back']);
        }

        //回复图片消息
        $reply = $wechatObj->createReplyMsgOfImage($toUsername, $fromUsername, $mediaId);
        exit($reply);
    }

    private function createQrcodeMedia($msg, $userId, $headPic, $qrBackImg)
    {
        $wechatObj = self::$wechat_obj;

        //创建二维码关注url
        $qrCode = $wechatObj->createTempQrcode(2592000, $userId);
        if (!(is_array($qrCode) && $qrCode['url'])) {
            $this->replyError($msg, '创建二维码失败');
        }

        //创建分销二维码图片
        empty($headPic) && $headPic = '/public/images/icon_goods_thumb_empty_300.png'; //没有头像用默认图片
        $shareImg = $this->createShareQrCode('.'.$qrBackImg, $qrCode['url'], $headPic);
        if (!$shareImg) {
            $this->replyError($msg, '生成图片失败');
        }

        //上传二维码图片
        if (!($mediaInfo = $wechatObj->uploadTempMaterial($shareImg, 'image'))) {
            @unlink($shareImg);
            $this->replyError($msg, '上传图片失败');
        }
        @unlink($shareImg);

        $this->setCacheQrcodeMedia($userId, $headPic, $qrBackImg, $mediaInfo);

        return $mediaInfo['media_id'];
    }

    private function getCacheQrcodeMedia($userId, $headPic, $qrBackImg)
    {
        $symbol = md5("{$headPic}:{$qrBackImg}");
        $mediaIdCache = "distributQrCode:{$userId}:{$symbol}";
        $config = cache($mediaIdCache);
        if (!$config) {
            return false;
        }

        //$config = json_decode($config);
        //有效期3天（259200s）,提前5小时(18000s)过期
        if (!(is_array($config) && $config['media_id'] && ($config['created_at'] + 259200 - 18000) > time())) {
            return false;
        }

        return $config['media_id'];
    }

    private function setCacheQrcodeMedia($userId, $headPic, $qrBackImg, $mediaInfo)
    {
        $symbol = md5("{$headPic}:{$qrBackImg}");
        $mediaIdCache = "distributQrCode:{$userId}:{$symbol}";
        cache($mediaIdCache, $mediaInfo);
    }

    /**
     * 处理点击推送事件
     * @param array $msg
     */
    private function handleTextMsg($msg)
    {
        $from = $msg['ToUserName'];
        $to = $msg['FromUserName'];
        $keyword = trim($msg['Content']);

        //分销二维码图片
        $distribut = tpCache('distribut');
        if ($distribut['qrcode_input_word'] === $keyword) {
            $this->replyMyQrcode($msg);
        }

        // 关键字自动回复
        $this->replyKeyword($from, $to, $keyword);
    }

    /**
     * 关键字自动回复
     * @param $from
     * @param $to
     * @param $keyword
     */
    private function replyKeyword($from, $to, $keyword)
    {
        if (!$keyword) {
            $this->replyDefault($from, $to);
        }

        $resultStr = $this->createReplyMsg($from, $to, WxReply::TYPE_KEYWORD, ['keyword' => $keyword]);
        if ($resultStr) {
            exit($resultStr);
        } else {
            $this->replyDefault($from, $to);
        }
    }

    /**
     * 创建图文回复消息
     */
    private function createNewsReplyMsg($fromUser, $toUser, $material_id)
    {
        $material = WxMaterial::get(['id' => $material_id, 'type' => WxMaterial::TYPE_NEWS], 'wxNews');
        if (!$material || !$material->wx_news) {
            return '';
        }

        $articles = [];
        foreach ($material->wx_news as $news) {
            $articles[] = [
                'title'       => $news->title,
                'description' => $news->digest ?: $news->content_digest,
                'picurl'      => SITE_URL . $news->thumb_url,
                'url'         => SITE_URL . url('/mobile/article/news', ['id' => $news->id])
            ];
        }

        return self::$wechat_obj->createReplyMsgOfNews($fromUser, $toUser, $articles);
    }

    /**
     * 默认回复
     * @param array $msg
     */
    private function replyDefault($from, $to)
    {
        $resultStr = $this->createReplyMsg($from, $to, WxReply::TYPE_DEFAULT);
        if ( ! $resultStr) {
            //没有设置默认回复，则默认回复如下：
            $store_name = tpCache("shop_info.store_name");
            $resultStr = self::$wechat_obj->createReplyMsgOfText($from, $to, "欢迎来到 $store_name !");
        }

        exit($resultStr);
    }

    /**
     * 错误回复
     */
    private function replyError($msg, $extraMsg = '')
    {
        $fromUsername = $msg['FromUserName'];
        $toUsername   = $msg['ToUserName'];
        $wechatObj = self::$wechat_obj;

        if ($wechatObj->isDedug()) {
            $content = '错误信息：';
            $content .= $wechatObj->getError() ?: '';
            $content .= $extraMsg ?: '';
        } elseif ($extraMsg) {
            $content = '系统信息：'.$extraMsg;
        } else {
            $content = '系统正在处理...';
        }

        $resultStr = $wechatObj->createReplyMsgOfText($toUsername, $fromUsername, $content);
        exit($resultStr);
    }

    /**
     * 创建分享二维码图片
     * @param string $backImg 背景大图片
     * @param string $qrText  二维码文本:关注入口
     * @param string $headPic 头像路径
     * @return string 图片路径
     */
    private function createShareQrCode($backImg, $qrText, $headPic)
    {
        if (!is_file($backImg) || !$headPic || !$qrText) {
            return false;
        }

        vendor('phpqrcode.phpqrcode');
        vendor('topthink.think-image.src.Image');

        $qr_code_path = UPLOAD_PATH.'qr_code/';
        !file_exists($qr_code_path) && mkdir($qr_code_path, 0777, true);

        /* 生成二维码 */
        $qr_code_file = $qr_code_path.time().rand(1, 10000).'.png';
        \QRcode::png($qrText, $qr_code_file, QR_ECLEVEL_M);

        $QR = Image::open($qr_code_file);
        $QR_width = $QR->width();
        //$QR_height = $QR->height();

        /* 添加背景图 */
        if ($backImg && is_file($backImg)) {
            $back =Image::open($backImg);
            $backWidth = $back->width();
            $backHeight = $back->height();

            //生成的图片大小以540*960为准
            if ($backWidth <= $backHeight) {
                $refWidth = 540;
                $refHeight = 960;
                if (($backWidth / $backHeight) > ($refWidth / $refHeight)) {
                    $backRatio = $refWidth / $backWidth;
                    $backWidth = $refWidth;
                    $backHeight = $backHeight * $backRatio;
                } else {
                    $backRatio = $refHeight / $backHeight;
                    $backHeight = $refHeight;
                    $backWidth = $backWidth * $backRatio;
                }
            } else {
                $refWidth = 960;
                $refHeight = 540;
                if (($backWidth / $backHeight) > ($refWidth / $refHeight)) {
                    $backRatio = $refHeight / $backHeight;
                    $backHeight = $refHeight;
                    $backWidth = $backWidth * $backRatio;
                } else {
                    $backRatio = $refWidth / $backWidth;
                    $backWidth = $refWidth;
                    $backHeight = $backHeight * $backRatio;
                }
            }

            $shortSize = $backWidth > $backHeight ? $backHeight : $backWidth;
            $QR_width = $shortSize / 2;
            $QR_height = $QR_width;
            $QR->thumb($QR_width, $QR_height, \think\Image::THUMB_CENTER)->save($qr_code_file, null, 100);
            $back->thumb($backWidth, $backHeight, \think\Image::THUMB_CENTER)
                ->water($qr_code_file, \think\Image::WATER_CENTER, 90)->save($qr_code_file, null, 100);
            $QR = $back;
        }

        /* 添加头像 */
        if ($headPic) {
            //如果是网络头像
            if (strpos($headPic, 'http') === 0) {
                //下载头像
                $ch = curl_init();
                curl_setopt($ch,CURLOPT_URL, $headPic);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                $file_content = curl_exec($ch);
                curl_close($ch);
                //保存头像
                if ($file_content) {
                    $head_pic_path = $qr_code_path.time().rand(1, 10000).'.png';
                    file_put_contents($head_pic_path, $file_content);
                    $headPic = $head_pic_path;
                }
            }
            //如果是本地头像
            if (file_exists($headPic)) {
                $logo = Image::open($headPic);
                $logo_width = $logo->height();
                $logo_height = $logo->width();
                $logo_qr_width = $QR_width / 5;
                $scale = $logo_width / $logo_qr_width;
                $logo_qr_height = $logo_height / $scale;
                $logo_file = $qr_code_path.time().rand(1, 10000);
                $logo->thumb($logo_qr_width, $logo_qr_height)->save($logo_file, null, 100);
                $QR = $QR->water($logo_file, \think\Image::WATER_CENTER);
                unlink($logo_file);
            }
            if (!empty($head_pic_path)) {
                unlink($head_pic_path);
            }
        }

        //加上有效时间
        $valid_date = date('Y.m.d', strtotime('+30 days'));
        $QR->text('有效时间 '.$valid_date, "./vendor/topthink/think-captcha/assets/zhttfs/1.ttf", 16, '#FFFFFF', Image::WATER_SOUTH)->save($qr_code_file);

        return $qr_code_file;
    }

    /**
     * 获取粉丝列表
     */
    public function getFanList($p, $num = 10)
    {
        $wechatObj = self::$wechat_obj;
        if (!$access_token = $wechatObj->getAccessToken()) {
            return ['status' => -1, 'msg' => $wechatObj->getError()];
        }

        $p = intval($p) > 0 ? intval($p) : 1;
        $offset = ($p - 1) * $num;
        $max = 10000; //粉丝列表每次只能拉取的数量

        /* 获取所有粉丝列表openid并缓存 */
        $fans_key = 'wechat.fan_list';
        if (!$fans = Cache::get($fans_key)) {
            $next_openid = '';
            $fans = [];
            do {
                $ids = $wechatObj->getFanIdList($next_openid);
                if ($ids === false) {
                    return ['status' => -1, 'msg' => $wechatObj->getError()];
                }
                $fans = array_merge($fans, $ids['data']['openid']);
                $next_openid = $ids['next_openid'];
            } while ($ids['total'] > $max && $ids['count'] == $max);
            Cache::set($fans_key, $fans, 3600); //缓存列表一个小时
        }

        /* 获取指定粉丝，并获取详细信息 */
        $part_fans = array_slice($fans, $offset, $num);
        $user_list = [];
        $fan_key = 'wechat.fan_info';
        foreach ($part_fans as $openid) {
            if (!$fan = Cache::get($fan_key.'.'.$openid)) {
                $fan = $wechatObj->getFanInfo($openid, $access_token);
                if ($fan === false) {
                    continue;//不要因为一个粉丝的离开而影响整个列表
                }
                $fan['tags'] = $wechatObj->getFanTagNames($fan['tagid_list']);
                if ($fan['tags'] === false) {
                    continue;//不要因为一个粉丝的离开而影响整个列表
                }
                Cache::set($fan_key.'.'.$openid, $fan, 3600); //缓存粉丝一个小时
            }
            $user_list[$openid] = $fan;
        }

        return ['status' => 1, 'msg' => '获取成功', 'result' => [
            'total' => count($fans),
            'list' => $user_list
        ]];
    }

    /**
     * 商城用户里的粉丝列表
     */
    public function getUserFanList($p, $num = 10, $keyword= '')
    {
        $wechatObj = self::$wechat_obj;
        if (!$access_token = $wechatObj->getAccessToken()) {
            return ['status' => -1, 'msg' => $wechatObj->getError()];
        }

        $p = intval($p) > 0 ? intval($p) : 1;
        $condition = ['o.openid' => ['<>', ''], 'o.oauth' => 'weixin', 'o.oauth_child' => 'mp'];
        $keyword = trim($keyword);
        $keyword && $condition['o.openid|u.nickname'] = ['like', "%$keyword%"];

        $query = Db::name('oauth_users')->field('o.*')->alias('o')->join('__USERS__ u', 'u.user_id = o.user_id')->where($condition);
        $copyQuery = clone $query;
        $users = $query->page($p, $num)->select();
        $user_num = $copyQuery->count();

        $fan_key = 'wechat.user_fan_info';
        foreach ($users as &$user) {
            if (!$fan = Cache::get($fan_key.'.'.$user['openid'])) {
                $fan = $wechatObj->getFanInfo($user['openid'], $access_token);
                if ($fan === false) {
                    continue;//不要因为一个粉丝的离开而影响整个列表
                }
                Cache::set($fan_key.'.'.$user['openid'], $fan, 3600); //缓存粉丝一个小时
            }
            $user['weixin'] = $fan;
        }

        return ['status' => 1, 'msg' => '获取成功', 'result' => [
            'total' => $user_num,
            'list' => $users
        ]];
    }

    /**
     * 新建和更新文本素材
     * （图文素材只需保存在本地，微信不存储文本素材）
     */
    public function createOrUpdateText($material_id, $data)
    {
        $validate = new Validate([
            ['title','require|max:64','标题必填|标题最多64字'],
            ['content','require|max:600','内容必填|内容最多600字'],
        ]);
        if (!$validate->check($data)) {
            return ['status' => -1, 'msg' => $validate->getError()];
        }

        $text = [
            'type' => 'text',
            'update_time' => time(),
            'data' => [
                'title' => $data['title'],
                'content' => $data['content'],
            ]
        ];

        if ($material_id) {
            if (!$material = WxMaterial::get(['id' => $material_id, 'type' => WxMaterial::TYPE_TEXT])) {
                return ['status' => -1, 'msg' => '文本素材不存在'];
            }
            $material->save($text);
        } else {
            $material = WxMaterial::create($text);
        }

        return ['status' => 1, 'msg' => '素材提交成功！', 'result' => $material->id];
    }

    /**
     * 删除文本素材
     */
    public function deleteText($material_id)
    {
        if (!$material_id || !$material = WxMaterial::get(['id' => $material_id, 'type' => WxMaterial::TYPE_TEXT])) {
            return ['status' => -1, 'msg' => '文本素材不存在'];
        }

        $material->delete();

        return ['status' => 1, 'msg' => '删除文本素材成功'];
    }


    /**
     * 新建和更新图文素材
     */
    public function createOrUpdateNews($material_id, $news_id, $data)
    {
        $article = [
            "title"             => $data['title'],
            //"thumb_media_id"    => $data['thumb_media_id'],
            "thumb_url"         => $data['thumb_url'],
            "author"            => $data['author'],
            "digest"            => $data['digest'],
            "show_cover_pic"    => $data['show_cover_pic'] ? 1 : 0,
            "content"           => $data['content'],
            "content_source_url" => $data['content_source_url'],
            "material_id"       => $material_id,
            "update_time"       => time(),
        ];

        if ($material_id) {
            if (!$material = WxMaterial::get(['id' => $material_id, 'type' => WxMaterial::TYPE_NEWS])) {
                return ['status' => -1, 'msg' => '图文素材不存在'];
            }

            if ($news_id) {
                //更新单图文
                if (!$news = WxNews::get(['id' => $news_id, 'material_id' => $material_id])) {
                    return ['status' => -1, 'msg' => '单图文素材不存在'];
                }
                $news->save($article);
                if ($material->media_id) {
                    $material->save(['media_id' => 0]); // 需要重新上传
                }

            } else {
                //新增单图文
                $all_news = WxNews::all(['material_id' => $material_id]);
                $max_news_per_material = 8;
                if (count($all_news) >= $max_news_per_material) {
                    return ['status' => -1, 'msg' => "一个图文素材中的文章最多 $max_news_per_material 篇"];
                }
                WxNews::create($article);
            }
            $material->save([
                'update_time' => time(),
                'media_id' => 0 // 需要重新上传
            ]);

        } else {
            //新增多图文
            $material = WxMaterial::create([
                'type' => WxMaterial::TYPE_NEWS,
                'update_time' => time(),
            ]);
            $article['material_id'] = $material->id;
            WxNews::create($article);
        }

        //先不用上传到微信服务器，等实际使用的时候再上传

        return ['status' => 1, 'msg' => '素材提交成功！'];
    }

    /**
     * 删除图文素材
     * @param $material_id int 素材id
     * @return array
     */
    public function deleteNews($material_id)
    {
        if (!$material_id || !$material = WxMaterial::get(['id' => $material_id, 'type' => WxMaterial::TYPE_NEWS], 'wxNews')) {
            return ['status' => -1, 'msg' => '素材不存在'];
        }

        if (WxReply::get(['material_id' => $material_id, 'msg_type' => WxReply::MSG_NEWS])) {
            return ['status' => -1, 'msg' => '该素材正被自动回复使用，无法删除'];
        }

        if ($material->media_id) {
            self::$wechat_obj->delMaterial($material->media_id);
        }

        if (is_array($material->wx_news)) {
            foreach ($material->wx_news as $news) {
                $news->delete();
            }
        }
        $material->delete();

        return ['status' => 1, 'msg' => '删除图文成功'];
    }

    /**
     * 删除单图文
     * @param $news_id int 单图文的id
     * @return array
     */
    public function deleteSingleNews($news_id)
    {
        if (!$news_id || !$news = WxNews::get($news_id, 'wxMaterial')) {
            return ['status' => -1, 'msg' => '单图文素材不存在'];
        }

        if (!$news->wx_material) {
            return ['status' => -1, 'msg' => '该单图文所属素材不存在'];
        }

        if (count($news->wx_material->wx_news) == 1) {
            return $this->deleteNews($news->material_id);
        } else {
            if ($news->wx_material->media_id) {
                $news->wx_material->save(['media_id' => 0]); // 需要重新上传
            }
            $news->delete();
        }

        return ['status' => 1, 'msg' => '删除单图文成功'];
    }

    /**
     * 上传图文
     * @param $material WxMaterial
     * @return array
     */
    private function uploadNews($material)
    {
        $articles = [];
        foreach ($material->wx_news as $news) {
            // 1.获取或上传封面
            if ($thumb = WxMaterial::get(['type' => WxMaterial::TYPE_IMAGE, 'key' => md5($news['thumb_url'])])) {
                $thumb_media_id = $thumb->media_id;
            } else {
                $thumb = self::$wechat_obj->uploadMaterial('.'.$news['thumb_url'], 'image');
                if ($thumb ===  false) {
                    return ['status' => -1, 'msg' => self::$wechat_obj->getError()];
                }
                $thumb_media_id = $thumb['media_id'];
                WxMaterial::create([
                    'type' => WxMaterial::TYPE_IMAGE,
                    'key'  => md5($news['thumb_url']),
                    'media_id' => $thumb_media_id,
                    'update_time' => time(),
                    'data' => [
                        'url' => $news['thumb_url'],
                        'mp_url' => $thumb['url'],
                    ]
                ]);
            }

            // 2.将文章中的图片上传
            $news['content'] = htmlspecialchars_decode($news['content']);
            if (preg_match_all('#<img .*?src="(.*?)".*?/>#i', $news['content'], $matches)) {
                $imgs = array_unique($matches[1]);
                foreach ($imgs as $img) {
                    if (stripos($img, 'http') === 0) {
                        continue;
                    }

                    // 3.获取或上传文章中图片
                    if ($news_image = WxMaterial::get(['type' => WxMaterial::TYPE_NEWS_IMAGE, 'key' => md5($img)])) {
                        $news_image_url = $news_image->data['mp_url'];
                    } else {
                        $news_image_url = self::$wechat_obj->uploadNewsImage('.'.$img);
                        if ($news_image_url ===  false) {
                            return ['status' => -1, 'msg' => self::$wechat_obj->getError()];
                        }
                        WxMaterial::create([
                            'type' => WxMaterial::TYPE_NEWS_IMAGE,
                            'key'  => md5($img),
                            'update_time' => time(),
                            'data' => [
                                'url' => $news['thumb_url'],
                                'mp_url' => $news_image_url,
                            ]
                        ]);
                    }

                    $news['content'] = str_replace($img, $news_image_url, $news['content']);
                }
            }

            $articles[] = [
                "title"             => $news['title'],
                "thumb_media_id"    => $thumb_media_id,
                "author"            => $news['author'] ?: '',
                "digest"            => $news['digest'] ?: '',
                "show_cover_pic"    => $news['show_cover_pic'] ? 1 : 0,
                "content"           => $news['content'],
                "content_source_url" => $news['content_source_url'],
            ];
        }

        $news_media_id = self::$wechat_obj->uploadNews($articles);
        if ($news_media_id ===  false) {
            return ['status' => -1, 'msg' => self::$wechat_obj->getError()];
        }
        $material->save(['media_id' => $news_media_id]);

        return ['status' => 1, 'msg' => '上传成功', 'result' => $news_media_id];
    }

    /**
     * 发送图文消息
     * @param $material_id int 素材id
     * @param $openids array|string 可多个用户openid
     * @param int $to_all 0由openids决定，1所有粉丝
     * @return array
     */
    public function sendNewsMsg($material_id, $openids, $to_all = 0)
    {
        $material = WxMaterial::get(['id' => $material_id, 'type' => WxMaterial::TYPE_NEWS], 'wxNews');
        if (!$material || !$material->wx_news) {
            return ['status' => -1, 'msg' => '该素材不存在'];
        }

        if ($material->media_id) {
            $news_media_id = $material->media_id;
            if (false === self::$wechat_obj->getMaterial($material->media_id)) {
                $news_media_id = 0; //获取失败，可能被手动删了，需要重新上传
            }
        }
        if (empty($news_media_id)) {
            $return = $this->uploadNews($material);
            if ($return['status'] != 1) {
                return $return;
            }
            $news_media_id = $return['result'];
        }

        // 5.发送消息
        if ($to_all) {
            $result = self::$wechat_obj->sendMsgToAll(0, 'mpnews', $news_media_id);
        } else {
            $result = self::$wechat_obj->sendMsg($openids, 'mpnews', $news_media_id);
        }
        if ($result === false) {
            return ['status' => -1, 'msg' => self::$wechat_obj->getError()];
        }

        return ['status' => 1, 'msg' => '发送成功'];
    }

    /**
     * 删除图片
     * @param $url string 存储在本地的url
     */
    public function deleteImage($url)
    {
        if (strpos($url, 'weixin_mp_image/') === false) {
            return;
        }
        if (!$image = WxMaterial::get(['type' => WxMaterial::TYPE_IMAGE, 'key' => md5($url)])) {
            return;
        }
        if ($image->media_id) {
            self::$wechat_obj->delMaterial($image->media_id);
        }
    }

    /**
     * 系统默认模板消息
     * @return array
     */
    public function getDefaultTemplateMsg($template_sn = null)
    {
        $templates = [
            [
                "template_sn" => "OPENTM204987032",
                "title" => "订单支付成功通知",
                "content" =>
                    "{{first.DATA}}\n"
                    ."订单：{{keyword1.DATA}}\n"
                    ."支付状态：{{keyword2.DATA}}\n"
                    ."支付日期：{{keyword3.DATA}}\n"
                    ."商户：{{keyword4.DATA}}\n"
                    ."金额：{{keyword5.DATA}}\n"
                    ."{{remark.DATA}}",
            ], [
                "template_sn" => "OPENTM202243318",
                "title" => "订单发货通知",
                "content" =>
                    "{{first.DATA}}\n"
                    ."订单内容：{{keyword1.DATA}}\n"
                    ."物流服务：{{keyword2.DATA}}\n"
                    ."快递单号：{{keyword3.DATA}}\n"
                    ."收货信息：{{keyword4.DATA}}\n"
                    ."{{remark.DATA}}",
            ],[
                "template_sn" => "OPENTM410958953",
                "title" => "订单提交成功通知",
                "content" =>
                    "{{first.DATA}}\n"
                    ."订单号：{{keyword1.DATA}}\n"
                    ."订单金额：{{keyword2.DATA}}\n"
                    ."创建时间：{{keyword3.DATA}}\n"
                    ."{{remark.DATA}}",
            ], [
                "template_sn" => "OPENTM400339500",
                "title" => "秒杀提醒通知",
                "content" =>
                    "{{first.DATA}}\n"
                    ."预约时间：{{keyword1.DATA}}\n"
                    ."预约内容：{{keyword2.DATA}}\n"
                    ."{{remark.DATA}}",
            ], [
                "template_sn" => "TM00850",
                "title" => "取消订单",
                "content" =>
                    "{{first.DATA}}\n"
                    ."订单金额：{{orderProductPrice.DATA}}\n"
                    ."商品详情：{{orderProductName.DATA}}\n"
                    ."收货信息：{{orderAddress.DATA}}\n"
                    ."订单编号：{{orderName.DATA}}\n"
                    ."{{remark.DATA}}"
            ], [
                "template_sn" => "OPENTM405552552",
                "title" => "退换货相关",
                "content" =>
                    "{{first.DATA}}\n"
                    ."订单编号：{{keyword1.DATA}}\n"
                    ."商品信息：{{keyword2.DATA}}\n"
                    ."商品数量：{{keyword3.DATA}}\n"
                    ."商品金额：{{keyword4.DATA}}\n"
                    ."{{remark.DATA}}"
            ], [
                "template_sn" => "TM00184",
                "title" => "未支付订单，下单1小时未支付",
                "content" =>
                    "{{first.DATA}}\n"
                    ."下单时间：{{ordertape.DATA}}\n"
                    ."订单号：{{ordeID.DATA}}\n"
                    ."{{remark.DATA}}"
            ], [
                "template_sn" => "OPENTM205496702",
                "title" => "提现受理成功 || 提现受理不成功",
                "content" =>
                    "{{first.DATA}}\n"
                    ."提现商户：{{keyword1.DATA}}\n"
                    ."提现金额：{{keyword2.DATA}}\n"
                    ."提现账户：{{keyword3.DATA}}\n"
                    ."处理时间：{{keyword4.DATA}}\n"
                    ."{{remark.DATA}}"
            ],[
                "template_sn" => "OPENTM405878534",
                "title" => "新增订单收益（合伙人） || 新增订单收益（代理商）",
                "content" =>
                    "{{first.DATA}}\n"
                    ."订单编号：{{keyword1.DATA}}\n"
                    ."订单金额：{{keyword2.DATA}}\n"
                    ."预计收益：{{keyword3.DATA}}\n"
                    ."{{remark.DATA}}"
            ],[
                "template_sn" => "OPENTM405637175",
                "title" => "邀请合伙人收益（代理商）",
                "content" =>
                    "{{first.DATA}}\n"
                    ."收益金额：{{keyword1.DATA}}\n"
                    ."收益来源：{{keyword2.DATA}}\n"
                    ."到账时间：{{keyword3.DATA}}\n"
                    ."{{remark.DATA}}"
            ],[
                "template_sn" => "OPENTM413839164",
                "title" => "打榜活动奖金发放通知",
                "content" =>
                    "{{first.DATA}}\n"
                    ."合伙人：{{keyword1.DATA}}\n"
                    ."结算等级：{{keyword2.DATA}}\n"
                    ."发放金额：{{keyword3.DATA}}\n"
                    ."发放时间：{{keyword4.DATA}}\n"
                    ."{{remark.DATA}}"
            ],[
                "template_sn" => "OPENTM408917543",
                "title" => "拼团成功",
                "content" =>
                    "{{first.DATA}}\n"
                    ."商品名称：{{keyword1.DATA}}\n"
                    ."拼团价格：{{keyword2.DATA}}\n"
                    ."成团人数：{{keyword3.DATA}}\n"
                    ."{{remark.DATA}}"
            ],[
                "template_sn" => "OPENTM401113750",
                "title" => "拼团失败",
                "content" =>
                    "{{first.DATA}}\n"
                    ."拼团商品：{{keyword1.DATA}}\n"
                    ."商品金额：{{keyword2.DATA}}\n"
                    ."退款金额：{{keyword3.DATA}}\n"
                    ."{{remark.DATA}}"
            ],
            [
                "template_sn" => "OPENTM406281958",
                "title" => "拼团提醒",
                "content" =>
                    "{{first.DATA}}\n"
                    ."团购商品：{{keyword1.DATA}}\n"
                    ."剩余拼团时间：{{keyword2.DATA}}\n"
                    ."剩余拼团人数：{{keyword3.DATA}}\n"
                    ."{{remark.DATA}}"
            ],
            [
                "template_sn" => "OPENTM207031514",
                "title" => "优惠券过期提醒",
                "content" =>
                    "{{first.DATA}}\n"
                    ."商城名称：{{keyword1.DATA}}\n"
                    ."有效期至：{{keyword2.DATA}}\n"
                    ."{{remark.DATA}}"
            ],
            [
                "template_sn" => "OPENTM206854010",
                "title" => "投票开奖通知",
                "content" =>
                    "{{first.DATA}}\n"
                    ."活动奖品：{{keyword1.DATA}}\n"
                    ."开奖时间：{{keyword2.DATA}}\n"
                    ."{{remark.DATA}}"
            ],
            [
                "template_sn" => "OPENTM405636738",
                "title" => "投票成功提醒",
                "content" =>
                    "{{first.DATA}}\n"
                    ."投票选手：{{keyword1.DATA}}\n"
                    ."投票次数：{{keyword2.DATA}}\n"
                    ."投票时间：{{keyword3.DATA}}\n"
                    ."{{remark.DATA}}"
            ],
            [
                "template_sn" => "OPENTM405733213",
                "title" => "审核未通过通知",
                "content" =>
                    "{{first.DATA}}\n"
                    ."拒绝原因：{{keyword1.DATA}}\n"
                    ."审核时间：{{keyword2.DATA}}\n"
                    ."操作建议：{{keyword3.DATA}}\n"
                    ."{{remark.DATA}}"
            ],
            [
                "template_sn" => "OPENTM411651133",
                "title" => "发起砍价成功",
                "content" =>
                    "{{first.DATA}}\n"
                    ."活动名：{{keyword1.DATA}}\n"
                    ."活动时间：{{keyword2.DATA}}\n"
                    ."{{remark.DATA}}"
            ],
            [
                "template_sn" => "OPENTM410292733",
                "title" => "砍价成功",
                "content" =>
                    "{{first.DATA}}\n"
                    ."商品名称：{{keyword1.DATA}}\n"
                    ."底价：{{keyword2.DATA}}\n"
                    ."{{remark.DATA}}"
            ],
            [
                "template_sn" => "OPENTM415579988",
                "title" => "砍价失败",
                "content" =>
                    "{{first.DATA}}\n"
                    ."商品：{{keyword1.DATA}}\n"
                    ."已砍金额：{{keyword2.DATA}}\n"
                    ."类型：{{keyword3.DATA}}\n"
                    ."{{remark.DATA}}"
            ],
        ];

        $templates = convert_arr_key($templates, 'template_sn');

        //目前网站需要使用的模板
        $valid_sns = [
            'OPENTM204987032', //订单支付成功通知
            'OPENTM202243318', //订单发货通知
            'OPENTM410958953', //订单提交成功通知
            'OPENTM400339500', //秒杀提醒通知
            'TM00850', //取消订单
            'OPENTM405552552', //退换货提交成功 || 退换货审核成功，不回寄商品 || 退换货审核成功，需回寄商品 || 退换货审核失败
            'TM00184', // 未支付订单，下单1小时未支付
            'OPENTM205496702', // 提现受理成功 || 提现受理不成功
            'OPENTM405878534', // 新增订单收益（合伙人） || 新增订单收益（代理商）
            'OPENTM405637175', // 邀请合伙人收益（代理商）
            'OPENTM413839164', // 合伙人打榜活动派发奖金
            'OPENTM408917543', // 拼团成功
            'OPENTM401113750', // 拼团失败
            'OPENTM406281958', // 拼团提醒
            'OPENTM207031514', // 优惠券过期提醒
            'OPENTM206854010', // 投票开奖通知
            'OPENTM405636738', // 投票成功提醒
            'OPENTM405733213', // 汉服活动审核失败
            'OPENTM411651133', // 发起砍价成功
            'OPENTM410292733', // 砍价成功
            'OPENTM415579988', // 砍价失败
        ];
        $valid_templates = [];
        foreach ($valid_sns as $sn) {
            if (isset($templates[$sn])) {
                $valid_templates[$sn] = $templates[$sn];
            }
        }

        if ($template_sn) {
            return $valid_templates[$template_sn];
        }
        return $valid_templates;
    }

    /**
     * 配置模板
     * @param $data array 配置
     */
    public function setTemplateMsg($template_sn, $data)
    {
        if (!isset($data['is_use']) && !isset($data['remark'])) {
            return ['status' => -1, 'msg' => '参数为空'];
        }

        $tpls = $this->getDefaultTemplateMsg();
        if (!key_exists($template_sn, $tpls)) {
            return ['status' => -1, 'msg' => "模板消息的编号[$template_sn]不存在"];
        }

        if ($tpl_msg = WxTplMsg::get(['template_sn' => $template_sn])) {
            $tpl_msg->save($data);
        } else {
            if (!$template_id = self::$wechat_obj->addTemplateMsg($template_sn)) {
                return ['status' => -1, 'msg' => self::$wechat_obj->getError()];
            }
            WxTplMsg::create([
                'template_id' => $template_id,
                'template_sn' => $template_sn,
                'title' => $tpls[$template_sn]['title'],
                'is_use' => isset($data['is_use']) ? $data['is_use'] : 0,
                'remark' => isset($data['remark']) ? $data['remark'] : '',
                'add_time' => time(),
            ]);
        }

        return ['status' => 1, 'msg' => '操作成功'];
    }

    /**
     * 重置模板消息
     */
    public function resetTemplateMsg($template_sn)
    {
        if (!$template_sn) {
            return ['status' => -1, 'msg' => '参数不为空'];
        }

        if ($tpl_msg = WxTplMsg::get(['template_sn' => $template_sn])) {
            if ($tpl_msg->template_id) {
                self::$wechat_obj->delTemplateMsg($tpl_msg->template_id);
            }
            $tpl_msg->delete();
        }

        return ['status' => 1, 'msg' => '操作成功'];
    }

    /**
     * 发送模板消息（投票成功提醒）
     * @param $order array 订单数据
     */
    public function sendTemplateMsgOnVoteSuccess($info)
    {
        $template_sn = 'OPENTM405636738';
        if ( ! $this->getDefaultTemplateMsg($template_sn)) {
            return ['status' => -1, 'msg' => '消息模板不存在'];
        }

        $tpl_msg = WxTplMsg::get(['template_sn' => $template_sn, 'is_use' => 1]);
        if ( ! $tpl_msg || ! $tpl_msg->template_id) {
            return ['status' => -1, 'msg' => '消息模板未开启'];
        }

        $data = [
            'first' => ['value' => '好友为你投票成功，你当前的总票数：'.$info['num'].'票'],
            'keyword1' => ['value' => $info['nickname']],
            'keyword2' => ['value' => '1次'],
            'keyword3' => ['value' => date('Y年m月d日 H:i',time())],
            'remark' => ['value' => '点击查看详情>>'],
        ];
        if (PHP_SAPI == 'cli') {
            switch ($GLOBALS['ENV']) {
                case 'DEV':
                    $host = 'https://dev.cfo2o.com';
                    break;
                case 'TEST':
                    $host = 'https://test.cfo2o.com';
                    break;
                case 'FORMAL':
                    $host = 'https://formal.cfo2o.com';
                    break;
                case 'PRODUCT':
                    $host = 'https://www.cfo2o.com';
                    break;
                default:
                    $host = 'https://www.cfo2o.com';
            }
            $url = $host.url('Mobile/vote/voteMainInfo',['found_id' => $info['found_id']]);
        }else{
            $url = SITE_URL.url('Mobile/vote/voteMainInfo',['found_id' => $info['found_id']]);
        }
        $return = self::$wechat_obj->sendTemplateMsg($info['openid'], $tpl_msg->template_id, $url, $data);
        if ($return === false) {
            Logs::sentryLogs('发送投票成功提醒失败：'.self::$wechat_obj->getError(),$info);
        }
    }

    /**
     * 发送模板消息（图片审核失败提醒）
     * @param $order array 订单数据
     */
    public function sendTemplateMsgExamineFail($info)
    {
        $template_sn = 'OPENTM405733213';
        if ( ! $this->getDefaultTemplateMsg($template_sn)) {
            return ['status' => -1, 'msg' => '消息模板不存在'];
        }

        $tpl_msg = WxTplMsg::get(['template_sn' => $template_sn, 'is_use' => 1]);
        if ( ! $tpl_msg || ! $tpl_msg->template_id) {
            return ['status' => -1, 'msg' => '消息模板未开启'];
        }

        $data = [
            'first' => ['value' => '抱歉，您在“寻找最美汉服”活动中上传的图片审核未通过'],
            'keyword1' => ['value' => '图片不符合活动规则'],
            'keyword2' => ['value' => date('Y年m月d日 H:i',time())],
            'keyword3' => ['value' => '建议重新上传符合规则的图片(本人的汉服照片)'],
            'remark' => ['value' => '点击重新上传>>'],
        ];
        if (PHP_SAPI == 'cli') {
            switch ($GLOBALS['ENV']) {
                case 'DEV':
                    $host = 'https://dev.cfo2o.com';
                    break;
                case 'TEST':
                    $host = 'https://test.cfo2o.com';
                    break;
                case 'FORMAL':
                    $host = 'https://formal.cfo2o.com';
                    break;
                case 'PRODUCT':
                    $host = 'https://www.cfo2o.com';
                    break;
                default:
                    $host = 'https://www.cfo2o.com';
            }
            $url = $host.url('Mobile/vote/index');
        }else{
            $url = SITE_URL.url('Mobile/vote/index');
        }
        $return = self::$wechat_obj->sendTemplateMsg($info['openid'], $tpl_msg->template_id, $url, $data);
        if ($return === false) {
            Logs::sentryLogs('发送图片审核失败提醒失败：'.self::$wechat_obj->getError(),$info);
        }
    }

    /**
     * 发送模板消息（投票开奖通知）
     * @param $order array 订单数据
     */
    public function sendTemplateMsgOnOpenVote($info)
    {
        $template_sn = 'OPENTM206854010';
        if ( ! $this->getDefaultTemplateMsg($template_sn)) {
            return ['status' => -1, 'msg' => '消息模板不存在'];
        }

        $tpl_msg = WxTplMsg::get(['template_sn' => $template_sn, 'is_use' => 1]);
        if ( ! $tpl_msg || ! $tpl_msg->template_id) {
            return ['status' => -1, 'msg' => '消息模板未开启'];
        }

        $data = [
            'first' => ['value' => '你参加的“寻找最美汉服”投票活动已开奖'],
            'keyword1' => ['value' => '点击查看'],
            'keyword2' => ['value' => date('Y年m月d日 H:i',time())],
            'remark' => ['value' => '点击查看详情>>'],
        ];
        if (PHP_SAPI == 'cli') {
            switch ($GLOBALS['ENV']) {
                case 'DEV':
                    $host = 'https://dev.cfo2o.com';
                    break;
                case 'TEST':
                    $host = 'https://test.cfo2o.com';
                    break;
                case 'FORMAL':
                    $host = 'https://formal.cfo2o.com';
                    break;
                case 'PRODUCT':
                    $host = 'https://www.cfo2o.com';
                    break;
                default:
                    $host = 'https://www.cfo2o.com';
            }
            $url = $host.url('Mobile/Vote/openPrize');
        }else{
            $url = SITE_URL.url('Mobile/Vote/openPrize');
        }
        $return = self::$wechat_obj->sendTemplateMsg($info['openid'], $tpl_msg->template_id, $url, $data);
        if ($return === false) {
            Logs::sentryLogs('发送投票开奖通知失败：'.self::$wechat_obj->getError(),$info);
        }
    }


    /**
     * 发送模板消息（发起砍价成功）
     * @param $order array 砍价活动信息
     */
    public function sendTemplateMsgStartBargain($info)
    {
        $template_sn = 'OPENTM411651133';
        if ( ! $this->getDefaultTemplateMsg($template_sn)) {
            return ['status' => -1, 'msg' => '消息模板不存在'];
        }

        $tpl_msg = WxTplMsg::get(['template_sn' => $template_sn, 'is_use' => 1]);
        if ( ! $tpl_msg || ! $tpl_msg->template_id) {
            return ['status' => -1, 'msg' => '消息模板未开启'];
        }

        $data = [
            'first' => ['value' => '恭喜您创建砍价成功，已经砍'.$info['money'].'元，赶快邀请好友帮忙砍吧~'],
            'keyword1' => ['value' => '砍价免费拿|砍价底价购','color'=>'#8E5500'],
            'keyword2' => ['value' => date('Y年m月d日 H:i',time()),'color'=>'#8E5500'],
            'remark' => ['value' => '点击邀请好友>>','color'=>'#f00'],
        ];
        if (PHP_SAPI == 'cli') {
            switch ($GLOBALS['ENV']) {
                case 'DEV':
                    $host = 'https://dev.cfo2o.com';
                    break;
                case 'TEST':
                    $host = 'https://test.cfo2o.com';
                    break;
                case 'FORMAL':
                    $host = 'https://formal.cfo2o.com';
                    break;
                case 'PRODUCT':
                    $host = 'https://www.cfo2o.com';
                    break;
                default:
                    $host = 'https://www.cfo2o.com';
            }
            $url = $host.url('Mobile/bargain/info?item_id=0&activity_id='.$info['activity_id'].'&found_id='.$info['found_id']);
        }else{
            $url = SITE_URL.url('Mobile/bargain/info?item_id=0&activity_id='.$info['activity_id'].'&found_id='.$info['found_id']);
        }
        $return = self::$wechat_obj->sendTemplateMsg($info['openid'], $tpl_msg->template_id, $url, $data);
        if ($return === false) {
            Logs::sentryLogs('发送发起砍价失败：'.self::$wechat_obj->getError(),$info);
        }
    }


    /**
     * 发送模板消息（朋友砍价成功，砍价免费拿成功，砍价底价购成功）
     * @param $order array 砍价活动信息
     */
    public function sendTemplateMsgBargainSuccess($info)
    {
        $template_sn = 'OPENTM410292733';
        if ( ! $this->getDefaultTemplateMsg($template_sn)) {
            return ['status' => -1, 'msg' => '消息模板不存在'];
        }

        $tpl_msg = WxTplMsg::get(['template_sn' => $template_sn, 'is_use' => 1]);
        if ( ! $tpl_msg || ! $tpl_msg->template_id) {
            return ['status' => -1, 'msg' => '消息模板未开启'];
        }

        $data = [
            'first' => ['value' => $info['first_data']],
            'keyword1' => ['value' => $info['goods_name'],'color'=>'#8E5500'],
            'keyword2' => ['value' => $info['goods_price'],'color'=>'#8E5500'],
            'remark' => ['value' => $info['remark'],'color'=>'#f00'],
        ];
        if (PHP_SAPI == 'cli') {
            switch ($GLOBALS['ENV']) {
                case 'DEV':
                    $host = 'https://dev.cfo2o.com';
                    break;
                case 'TEST':
                    $host = 'https://test.cfo2o.com';
                    break;
                case 'FORMAL':
                    $host = 'https://formal.cfo2o.com';
                    break;
                case 'PRODUCT':
                    $host = 'https://www.cfo2o.com';
                    break;
                default:
                    $host = 'https://www.cfo2o.com';
            }
            $url = $host.url('Mobile/bargain/info?item_id=0&activity_id='.$info['activity_id'].'&found_id='.$info['found_id']);
        }else{
            $url = SITE_URL.url('Mobile/bargain/info?item_id=0&activity_id='.$info['activity_id'].'&found_id='.$info['found_id']);
        }
        $return = self::$wechat_obj->sendTemplateMsg($info['openid'], $tpl_msg->template_id, $url, $data);
        if ($return === false) {
            Logs::sentryLogs('发送砍价成功失败：'.self::$wechat_obj->getError(),$info);
        }
    }

    /**
     * 发送模板消息（砍价失败）
     * @param $order array 砍价活动信息
     */
    public function sendTemplateMsgBargainFail($info)
    {
        $template_sn = 'OPENTM415579988';
        if ( ! $this->getDefaultTemplateMsg($template_sn)) {
            return ['status' => -1, 'msg' => '消息模板不存在'];
        }

        $tpl_msg = WxTplMsg::get(['template_sn' => $template_sn, 'is_use' => 1]);
        if ( ! $tpl_msg || ! $tpl_msg->template_id) {
            return ['status' => -1, 'msg' => '消息模板未开启'];
        }

        $data = [
            'first' => ['value' => '很遗憾，您的砍价因时间过期，砍价失败'],
            'keyword1' => ['value' => $info['goods_name'],'color'=>'#8E5500'],
            'keyword2' => ['value' => $info['bargain_money'],'color'=>'#8E5500'],
            'keyword3' => ['value' => '砍价免费拿 | 砍价底价购','color'=>'#8E5500'],
            'remark' => ['value' => '感谢您的参与，点击查看详情>>','color'=>'#f00'],
        ];
        if (PHP_SAPI == 'cli') {
            switch ($GLOBALS['ENV']) {
                case 'DEV':
                    $host = 'https://dev.cfo2o.com';
                    break;
                case 'TEST':
                    $host = 'https://test.cfo2o.com';
                    break;
                case 'FORMAL':
                    $host = 'https://formal.cfo2o.com';
                    break;
                case 'PRODUCT':
                    $host = 'https://www.cfo2o.com';
                    break;
                default:
                    $host = 'https://www.cfo2o.com';
            }
            $url = $host.url('Mobile/bargain/info?item_id=0&activity_id='.$info['activity_id'].'&found_id='.$info['found_id']);
        }else{
            $url = SITE_URL.url('Mobile/bargain/info?item_id=0&activity_id='.$info['activity_id'].'&found_id='.$info['found_id']);
        }
        $return = self::$wechat_obj->sendTemplateMsg($info['openid'], $tpl_msg->template_id, $url, $data);
        if ($return === false) {
            Logs::sentryLogs('发送砍价失败信息失败：'.self::$wechat_obj->getError(),$info);
        }
    }


    /**
     * 发送模板消息（优惠券过期提醒）
     * @param $order array 订单数据
     */
    public function sendTemplateMsgOnCouponRemind($info)
    {

        $template_sn = 'OPENTM207031514';
        if ( ! $this->getDefaultTemplateMsg($template_sn)) {
            return ['status' => -1, 'msg' => '消息模板不存在'];
        }

        $tpl_msg = WxTplMsg::get(['template_sn' => $template_sn, 'is_use' => 1]);
        if ( ! $tpl_msg || ! $tpl_msg->template_id) {
            return ['status' => -1, 'msg' => '消息模板未开启'];
        }

        $data = [
            'first' => ['value' => '您有'.$info['coupon_num'].'张券即将过期，得来不易，别浪费哟！'],
            'keyword1' => ['value' => '尚美缤纷'],
            'keyword2' => ['value' => date('Y年m月d日',strtotime('+1 day'))],
            'remark' => ['value' => '点击查看详情>>'],
        ];
        if (PHP_SAPI == 'cli') {
            switch ($GLOBALS['ENV']) {
                case 'DEV':
                    $host = 'https://dev.cfo2o.com';
                    break;
                case 'TEST':
                    $host = 'https://test.cfo2o.com';
                    break;
                case 'FORMAL':
                    $host = 'https://formal.cfo2o.com';
                    break;
                case 'PRODUCT':
                    $host = 'https://www.cfo2o.com';
                    break;
                default:
                    $host = 'https://www.cfo2o.com';
            }
            $url = $host.url('Mobile/User/coupon');
        }else{
            $url = SITE_URL.url('Mobile/User/coupon');
        }
        $return = self::$wechat_obj->sendTemplateMsg($info['openid'], $tpl_msg->template_id, $url, $data);
        if ($return === false) {
            Logs::sentryLogs('发送优惠券提醒失败：'.self::$wechat_obj->getError(),$info);
        }
    }


    /**
     * 发送模板消息（拼团为完成提醒）
     * @param $order array 订单数据
     */
    public function sendTemplateMsgOnTeamRemind($info)
    {

        $template_sn = 'OPENTM406281958';
        if ( ! $this->getDefaultTemplateMsg($template_sn)) {
            return ['status' => -1, 'msg' => '消息模板不存在'];
        }

        $tpl_msg = WxTplMsg::get(['template_sn' => $template_sn, 'is_use' => 1]);
        if ( ! $tpl_msg || ! $tpl_msg->template_id) {
            return ['status' => -1, 'msg' => '消息模板未开启'];
        }

        $data = [
            'first' => ['value' => $info['first_data'] , 'color' => '#8E5500'],
            'keyword1' => ['value' => $info['goods_name']],
            'keyword2' => ['value' => $info['time_left'].'小时'],
            'keyword3' => ['value' => $info['needer_left'].'人'],
            'remark' => ['value' => $info['remark'] , 'color' => '#FF0000'],
        ];

        foreach ($info['follow_user'] as $v) {
            $user = Db::name('oauth_users')->where(['user_id' => $v['follow_user_id'], 'oauth' => 'weixin', 'oauth_child' => 'mp'])->find();
            $url = SITE_URL.url('/mobile/order/order_detail?id='.$v['order_id']);
            if ( $user && $user['openid']) {
                $return = self::$wechat_obj->sendTemplateMsg($user['openid'], $tpl_msg->template_id, $url, $data);
                if ($return === false) {
                    Logs::sentryLogs('发送平团提醒失败：'.self::$wechat_obj->getError(),$v);
                }
            }
            sleep(1);
        }
    }

    /**
     * 发送模板消息（订单提交成功通知）
     * @param $order array 订单数据
     */
    public function sendTemplateMsgOnOrderSuccess($order)
    {
        if ( ! $order) {
            return ['status' => -1, 'msg' => '订单不存在'];
        }

        $template_sn = 'OPENTM410958953';
        if ( ! $this->getDefaultTemplateMsg($template_sn)) {
            return ['status' => -1, 'msg' => '消息模板不存在'];
        }

        $tpl_msg = WxTplMsg::get(['template_sn' => $template_sn, 'is_use' => 1]);
        if ( ! $tpl_msg || ! $tpl_msg->template_id) {
            return ['status' => -1, 'msg' => '消息模板未开启'];
        }

        $user = Db::name('oauth_users')->where(['user_id' => $order['user_id'], 'oauth' => 'weixin', 'oauth_child' => 'mp'])->find();
        if ( ! $user || ! $user['openid']) {
            return ['status' => -1, 'msg' => '用户不存在或不是微信用户'];
        }
        $totalAmount = $order->order_amount + $order->user_money;
        $data = [
            'first' => ['value' => '您好，您的订单已经成功提交'],
            'keyword1' => ['value' => $order->order_sn],
            'keyword2' => ['value' => '￥ '.$totalAmount],
            'keyword3' => ['value' => date('Y-m-d H:i:s')],
            'remark' => ['value' => $tpl_msg->remark ?: ''],
        ];

        $url = SITE_URL.url('/mobile/order/order_detail?id='.$order['order_id']);
        $return = self::$wechat_obj->sendTemplateMsg($user['openid'], $tpl_msg->template_id, $url, $data);
        if ($return === false) {
            return ['status' => -1, 'msg' => self::$wechat_obj->getError()];
        }

        return ['status' => 1, 'msg' => '发送模板消息成功'];
    }

    /**
     * 发送模板消息（订单支付成功通知）
     * @param $order array 订单数据
     */
    public function sendTemplateMsgOnPaySuccess($order)
    {
        if (!$order) {
            return ['status' => -1, 'msg' => '订单不存在'];
        }

        $template_sn = 'OPENTM204987032';
        if ( ! $this->getDefaultTemplateMsg($template_sn)) {
            return ['status' => -1, 'msg' => '消息模板不存在'];
        }

        $tpl_msg = WxTplMsg::get(['template_sn' => $template_sn, 'is_use' => 1]);
        if ( ! $tpl_msg || ! $tpl_msg->template_id) {
            return ['status' => -1, 'msg' => '消息模板未开启'];
        }

        $user = Db::name('oauth_users')->where(['user_id' => $order['user_id'], 'oauth' => 'weixin', 'oauth_child' => 'mp'])->find();
        if ( ! $user || ! $user['openid']) {
            return ['status' => -1, 'msg' => '用户不存在或不是微信用户'];
        }
        $totalAmount = $order['order_amount'] + $order['user_money'];
        $store_name = tpCache('shop_info.store_name');
        $remarks = '您的订单已支付成功，请稍后，我们正在快马加鞭为您配货，敬请期待！';
        if ($order['prom_type'] == 6) {
            $remarks = '您的订单已付款成功，邀请好友一起参团，成团后为您安排发货。';
        }

        //产品要求不与之前统一
        $headRemarks = '订单支付成功！';
        if ($order['prom_type'] == 5) {
            $headRemarks = '卡券订单支付成功，有效期至'.date('Y-m-d H:i:s',$order['virtual_indate']).'，请在有效期内完成消费！';
            $remarks = '点击查看卡券订单详情>';
        }

        $data = [
            'first' => ['value' => $headRemarks],
            'keyword1' => ['value' => $order['order_sn']],
            'keyword2' => ['value' => '已支付'],
            'keyword3' => ['value' => date('Y-m-d H:i', $order['pay_time'])],
            'keyword4' => ['value' => $store_name],
            'keyword5' => ['value' => '￥ '.$totalAmount],
            'remark' => ['value' => $remarks],
        ];

        if ($order['prom_type'] == 5) {
            $url = SITE_URL.U('Mobile/User/getVirtualOrderInfo',[ 'order_id' => $order['order_id']]);
        }else{
            $url = SITE_URL.url('/mobile/order/order_detail?id='.$order['order_id']);
        }
        $return = self::$wechat_obj->sendTemplateMsg($user['openid'], $tpl_msg->template_id, $url, $data);
        if ($return === false) {
            return ['status' => -1, 'msg' => self::$wechat_obj->getError()];
        }

        return ['status' => 1, 'msg' => '发送模板消息成功'];
    }


    /**
     * 发送模板消息（订单发货通知）
     * @param $deliver array 物流信息
     */
    public function sendTemplateMsgOnDeliver($deliver)
    {
        if ( ! $deliver) {
            return ['status' => -1, 'msg' => '订单物流不存在'];
        }

        $template_sn = 'OPENTM202243318';
        if ( ! $this->getDefaultTemplateMsg($template_sn)) {
            return ['status' => -1, 'msg' => '消息模板不存在'];
        }

        $tpl_msg = WxTplMsg::get(['template_sn' => $template_sn, 'is_use' => 1]);
        if ( ! $tpl_msg || ! $tpl_msg->template_id) {
            return ['status' => -1, 'msg' => '消息模板未开启'];
        }

        $user = Db::name('oauth_users')->where(['user_id' => $deliver['user_id'], 'oauth' => 'weixin', 'oauth_child' => 'mp'])->find();
        if ( ! $user || ! $user['openid']) {
            return ['status' => -1, 'msg' => '用户不存在或不是微信用户'];
        }

        // 收货地址
        $province = getRegionName($deliver['province']);
        $city = getRegionName($deliver['city']);
        $district = getRegionName($deliver['district']);
        $full_address = $province.' '.$city.' '.$district.' '. $deliver['address'];

        $order_goods = Db::name('order_goods')->where('order_id', $deliver['order_id'])->find();
        $data = [
            'first' => ['value' => "订单{$deliver['order_sn']}发货成功！"],
            'keyword1' => ['value' => $order_goods['goods_name']],
            'keyword2' => ['value' => trim($deliver['shipping_name'])],
            'keyword3' => ['value' => $deliver['invoice_no']],
            'keyword4' => ['value' => $full_address],
            'remark' => ['value' => $tpl_msg->remark ?: ''],
        ];

        $url = SITE_URL.url('/mobile/order/order_detail?id='.$deliver['order_id']);
        $return = self::$wechat_obj->sendTemplateMsg($user['openid'], $tpl_msg->template_id, $url, $data);
        if ($return === false) {
            return ['status' => -1, 'msg' => self::$wechat_obj->getError()];
        }

        return ['status' => 1, 'msg' => '发送模板消息成功'];
    }

    /**
     * 发送模板消息（发送秒杀提醒）
     * @param $deliver array 物流信息
     */
    public function sendTemplateMsgOnFlashSale($flashInfo)
    {
        $template_sn = 'OPENTM400339500';
        if ( ! $this->getDefaultTemplateMsg($template_sn)) {
            return ['status' => -1, 'msg' => '消息模板不存在'];
        }

        $tpl_msg = WxTplMsg::get(['template_sn' => $template_sn, 'is_use' => 1]);

        if ( ! $tpl_msg || ! $tpl_msg->template_id) {
            return ['status' => -1, 'msg' => '消息模板未开启'];
        }

        $goodsInfo = $flashInfo['goods_num'] > 1 ? $flashInfo['goods_name'].' 等'.$flashInfo['goods_num'].'件商品' : $flashInfo['goods_name'] ;

        $data = [
            'first' => ['value' => "您关注的商品即将开始秒杀"],
            'keyword1' => ['value' => date('Y-m-d H:i',$flashInfo['flash_start_time'])],
            'keyword2' => ['value' => $goodsInfo],
            'remark' => ['value' => $tpl_msg->remark ?: ''],
        ];

        if (PHP_SAPI == 'cli') {
            switch ($GLOBALS['ENV']) {
                case 'DEV':
                    $host = 'https://dev.cfo2o.com';
                    break;
                case 'TEST':
                    $host = 'https://test.cfo2o.com';
                    break;
                case 'FORMAL':
                    $host = 'https://formal.cfo2o.com';
                    break;
                case 'PRODUCT':
                    $host = 'https://www.cfo2o.com';
                    break;
                default:
                    $host = 'https://www.cfo2o.com';
            }
            $url = $host.url('/Mobile/Activity/flash_sale_list');
        }else{
            $url = SITE_URL.url('/Mobile/Activity/flash_sale_list');
        }

        $return = self::$wechat_obj->sendTemplateMsg($flashInfo['openid'], $tpl_msg->template_id, $url, $data);
        if ($return === false) {
            return ['status' => -1, 'msg' => self::$wechat_obj->getError()];
        }

        return ['status' => 1, 'msg' => '发送模板消息成功'];
    }

    /**
     * 发送未完成支付提醒
     */
    public function sendTemplateMsgOnNoPayOrder($orderId)
    {
        $template_sn = 'TM00184';
        if ( ! $this->getDefaultTemplateMsg($template_sn)) {
            return ['status' => -1, 'msg' => '消息模板不存在'];
        }

        $tpl_msg = WxTplMsg::get(['template_sn' => $template_sn, 'is_use' => 1]);

        if ( ! $tpl_msg || ! $tpl_msg->template_id) {
            return ['status' => -1, 'msg' => '消息模板未开启'];
        }

        $orderInfo = (new Order())->where('order_id',$orderId)->find()->toArray();

        if ($orderInfo['order_status'] != 0 && $orderInfo['pay_status'] != 0) {
            return ['status' => -1, 'msg' => '发送模板消息失败'];
        }

        $user = Db::name('oauth_users')->where([
            'user_id' => $orderInfo['user_id'], 'oauth' => 'weixin', 'oauth_child' => 'mp','wx_bind' => 1
        ])->find();

        //发送短信
        if ( ! $user || ! $user['openid']) {
            return ['status' => -1, 'msg' => '发送模板消息成功'];
        }

        $data = [
            'first' => ['value' => "您好！您的订单还未支付，即将关闭"],
            'ordertape' => ['value' => date('Y-m-d H:i:s',$orderInfo['add_time'])],
            'ordeID' => ['value' => $orderInfo['order_sn']],
            'remark' => ['value' => '未付款订单将在24时内关闭，请及时付款。'],
        ];

        if (PHP_SAPI == 'cli') {
            switch ($GLOBALS['ENV']) {
                case 'DEV':
                    $host = 'https://dev.cfo2o.com';
                    break;
                case 'TEST':
                    $host = 'https://test.cfo2o.com';
                    break;
                case 'FORMAL':
                    $host = 'https://formal.cfo2o.com';
                    break;
                case 'PRODUCT':
                    $host = 'https://www.cfo2o.com';
                    break;
                default:
                    $host = 'https://www.cfo2o.com';
            }
            $url = $host.url('/mobile/order/order_detail?id='.$orderInfo['order_id']);
        }else{
            $url = SITE_URL.url('/mobile/order/order_detail?id='.$orderInfo['order_id']);
        }

        $return = self::$wechat_obj->sendTemplateMsg($user['openid'], $tpl_msg->template_id, $url, $data);
        if ($return === false) {
            return ['status' => -1, 'msg' => self::$wechat_obj->getError()];
        }

        return ['status' => 1, 'msg' => '发送模板消息成功'];
    }

    /**
     * 发送模板消息（取消订单）
     * @param $deliver array 物流信息
     */
    public function sendTemplateMsgOnCancleOrder($order)
    {
        $template_sn = 'TM00850';
        if ( ! $this->getDefaultTemplateMsg($template_sn)) {
            return ['status' => -1, 'msg' => '消息模板不存在'];
        }

        $tpl_msg = WxTplMsg::get(['template_sn' => $template_sn, 'is_use' => 1]);
        if ( ! $tpl_msg || ! $tpl_msg->template_id) {
            return ['status' => -1, 'msg' => '消息模板未开启'];
        }
        $orderInfo = current($order);

        $user = Db::name('oauth_users')->where([
            'user_id' => $orderInfo['user_id'], 'oauth' => 'weixin', 'oauth_child' => 'mp','wx_bind' => 1
        ])->find();

        //发送短信
        if ( ! $user || ! $user['openid']) {
            if (config('APP_DEBUG')) {
                return;
            }
            $sence = $orderInfo['pay_status'] ? 7 : 6 ;
            $mobile = (new Users())->where('user_id',$orderInfo['user_id'])->getField('mobile');

            if($orderInfo['pay_status']){
                $res = (new SmsLogic())->sendSms($sence,$mobile,['order_sn' => $orderInfo['order_sn']]);
            }else{
                $res = (new SmsLogic())->sendSms($sence,$mobile,['order_sn' => $orderInfo['order_sn']]);
            }
            if ($res['status'] != 1) {
                Logs::sentryLogs('发送取消短信失败');
            }
            return ['status' => 1, 'msg' => '发送模板消息成功'];
        }

        // 收货地址
        $address = M('region')
            ->where([
                'id'=>['in',[$orderInfo['province'],$orderInfo['city'],$orderInfo['district']]]
            ])->field('name')->select();

        $full_address = join(' ',array_column($address,'name')).' '. $orderInfo['address'];

        //判断是否支付了
        if ($orderInfo['pay_status']) {
            $firstMsg = '您的订单已取消，已支付金额将在1个工作日内原路退还至您的支付账户';
        }else{
            $firstMsg = '您的订单已取消';
        }

        $productName = $orderInfo['goods_name'];

        if ( ($goodsNum = count($order)) > 1) {
            $productName .= '等'.$goodsNum.'件商品';
        }
        $totalAmount = $orderInfo['user_money'] + $orderInfo['order_amount'];
        $data = [
            'first' => ['value' => $firstMsg],
            'orderProductPrice' => ['value' => '￥ '.$totalAmount],
            'orderProductName' => ['value' => $productName],
            'orderAddress' => ['value' => $full_address],
            'orderName' => ['value' => $orderInfo['order_sn']],
            'remark' => ['value' => $tpl_msg->remark ?: ''],
        ];

        $url = SITE_URL.url('/mobile/order/order_detail?id='.$orderInfo['order_id']);
        $return = self::$wechat_obj->sendTemplateMsg($user['openid'], $tpl_msg->template_id, $url, $data);
        if ($return === false) {
            return ['status' => -1, 'msg' => self::$wechat_obj->getError()];
        }

        return ['status' => 1, 'msg' => '发送模板消息成功'];
    }

    /**
     * 退换货申请提交成功
     */
    public function sendTemplateMsgOnReturnOrderSumbit($order,$post_data)
    {
        $template_sn = 'OPENTM405552552';
        if ( ! $this->getDefaultTemplateMsg($template_sn)) {
            return ['status' => -1, 'msg' => '消息模板不存在'];
        }

        $tpl_msg = WxTplMsg::get(['template_sn' => $template_sn, 'is_use' => 1]);
        if ( ! $tpl_msg || ! $tpl_msg->template_id) {
            return ['status' => -1, 'msg' => '消息模板未开启'];
        }
        $orderInfo = current($order);

        $user = Db::name('oauth_users')->where([
            'user_id' => $orderInfo['user_id'], 'oauth' => 'weixin', 'oauth_child' => 'mp','wx_bind' => 1
        ])->find();

        //发送短信
        if ( ! $user || ! $user['openid']) {
            if (config('APP_DEBUG')) {
                return;
            }
            if ($post_data['type'] == 0) {
                $param['service_type'] = '退款';
            }elseif ($post_data['type'] == 1){
                $param['service_type'] = '退款退货';
            }elseif ($post_data['type'] == 2){
                $param['service_type'] = '换货';
            }


            $mobile = (new Users())->where('user_id',$orderInfo['user_id'])->getField('mobile');

            $res = (new SmsLogic())->sendSms(8,$mobile,$param);

            if ($res['status'] != 1) {
                Logs::sentryLogs('发送取消短信失败');
            }
            return ['status' => 1, 'msg' => '发送模板消息成功'];
        }

        $productName = $orderInfo['goods_name'];
        $totalAmount = $orderInfo['user_money'] + $orderInfo['order_amount'];
        $data = [
            'first' => ['value' => '您的退货申请已提交成功'],
            'keyword1' => ['value' => $orderInfo['order_sn']],
            'keyword2' => ['value' => $productName],
            'keyword3' => ['value' => count($order)],
            'keyword4' => ['value' => '￥ '.$totalAmount],
            'remark' => ['value' => '我们会在1个工作日内为您处理，如有疑问可直接回复此公众号联系尚美缤纷客服'],
        ];

        $returnId = Db::name('return_goods')->where('rec_id',$orderInfo['rec_id'])->getField('id');

        $url = SITE_URL.url('/Mobile/Order/return_goods_info/id/'.$returnId);

        $return = self::$wechat_obj->sendTemplateMsg($user['openid'], $tpl_msg->template_id, $url, $data);
        if ($return === false) {
            return ['status' => -1, 'msg' => self::$wechat_obj->getError()];
        }

        return ['status' => 1, 'msg' => '发送模板消息成功'];
    }


    /**
     * 退换货提交成功 || 退换货审核成功，不回寄商品 || 退换货审核成功，需回寄商品 || 退换货审核失败
     * @param $deliver array 物流信息
     */
    public function sendTemplateMsgOnReturnOrderSuccessNotReturnGoods($order,$return_goods,$post_data)
    {
        $template_sn = 'OPENTM405552552';
        if ( ! $this->getDefaultTemplateMsg($template_sn)) {
            return ['status' => -1, 'msg' => '消息模板不存在'];
        }

        $tpl_msg = WxTplMsg::get(['template_sn' => $template_sn, 'is_use' => 1]);
        if ( ! $tpl_msg || ! $tpl_msg->template_id) {
            return ['status' => -1, 'msg' => '消息模板未开启'];
        }
        $orderInfo = current($order);

        $user = Db::name('oauth_users')->where([
            'user_id' => $orderInfo['user_id'], 'oauth' => 'weixin', 'oauth_child' => 'mp','wx_bind' => 1
        ])->find();

        //发送短信
        if ( ! $user || ! $user['openid']) {
            if (config('APP_DEBUG')) {
                return;
            }

            $mobile = (new Users())->where('user_id',$orderInfo['user_id'])->getField('mobile');
            $param = [];
            //退货审核成功（不回寄商品）
            if( $post_data['status'] == 1 && $return_goods['type'] == 0 ){
                $scene = 9;
            }
            //退货审核成功（需回寄商品）
            if( $post_data['status'] == 1 && $return_goods['type'] == 1 ){
                $scene = 10;
            }
            //换货审核成功
            if( $post_data['status'] == 1 && $return_goods['type'] == 2 ){
                $scene = 11;
            }
            //退换货审核失败
            if( $post_data['status'] != 1){
                $scene = 12;
                if ($return_goods['type'] == 1) {//退货
                    $param['service_type'] = '退货';
                }elseif($return_goods['type'] == 0){//换货
                    $param['service_type'] = '退款';
                }elseif($return_goods['type'] == 2){//换货
                    $param['service_type'] = '换货';
                }
                $param['check_reason'] = $post_data['remark'];
            }

            $res = (new SmsLogic())->sendSms($scene,$mobile,$param);

            if ($res['status'] != 1) {
                Logs::sentryLogs('发送取消短信失败');
            }
            return ['status' => 1, 'msg' => '发送模板消息成功'];
        }

        $productName = $orderInfo['goods_name'];

        //是否需要寄回物品
        if ($return_goods['type'] == 0) {
            $remarkMsg = '已支付金额将在1个工作日内原路退还至您的支付账户，如有疑问可直接回复此公众号联系尚美缤纷客服。';
        }else{
            $remarkMsg = '请将商品回寄回公司，我们将在收到商品1个工作日内为您退款，如有疑问可直接回复此公众号联系尚美缤纷客服。';
        }

        //是否审核通过
        if ($post_data['status'] == 1) {
            $firstMsg = '您的退货申请已通过';
        }else{
            $firstMsg = '您的退货申请未受理，原因：'.$post_data['remark'];
            $remarkMsg = '如有疑问可直接回复此公众号联系尚美缤纷客服。';
        }
        $totalAmount = $orderInfo['user_money'] + $orderInfo['order_amount'];
        $data = [
            'first' => ['value' => $firstMsg],
            'keyword1' => ['value' => $orderInfo['order_sn']],
            'keyword2' => ['value' => $productName],
            'keyword3' => ['value' => count($order)],
            'keyword4' => ['value' => '￥ '.$totalAmount],
            'remark' => ['value' => $remarkMsg],
        ];

        $returnId = Db::name('return_goods')->where('rec_id',$orderInfo['rec_id'])->getField('id');

        $url = SITE_URL.url('/Mobile/Order/return_goods_info/id/'.$returnId);

        $return = self::$wechat_obj->sendTemplateMsg($user['openid'], $tpl_msg->template_id, $url, $data);
        if ($return === false) {
            return ['status' => -1, 'msg' => self::$wechat_obj->getError()];
        }

        return ['status' => 1, 'msg' => '发送模板消息成功'];
    }


    /**
     * 退换货申请提交成功
     */
    public function sendTemplateMsgOnWithdrawal($withdrawals,$post_data)
    {
        $template_sn = 'OPENTM205496702';
        if ( ! $this->getDefaultTemplateMsg($template_sn)) {
            return ['status' => -1, 'msg' => '消息模板不存在'];
        }

        $tpl_msg = WxTplMsg::get(['template_sn' => $template_sn, 'is_use' => 1]);
        if ( ! $tpl_msg || ! $tpl_msg->template_id) {
            return ['status' => -1, 'msg' => '消息模板未开启'];
        }


        $user = Db::name('oauth_users')->where([
            'user_id' => $withdrawals[0]['user_id'], 'oauth' => 'weixin', 'oauth_child' => 'mp','wx_bind' => 1
        ])->find();

        //发送短信
        if ( ! $user || ! $user['openid']) {
            if (config('APP_DEBUG')) {
                return;
            }

            $mobile = (new Users())->where('user_id',$withdrawals[0]['user_id'])->getField('mobile');

            $param = [];
            if ($post_data['status'] == 1) {
                $scene = 13;
            }else{
                $param['withdraw_refuse_reason'] = $post_data['remark'];
                $scene = 14;
            }
            $res = (new SmsLogic())->sendSms($scene,$mobile,$param);

            if ($res['status'] != 1) {
                Logs::sentryLogs('发送取消短信失败');
            }
            return ['status' => 1, 'msg' => '发送模板消息成功'];
        }

        if ($post_data['status'] == 1) {
            $firstMsg = '您的提现申请已受理，提现金额将会在1个工作日内到账';
        }else{
            $firstMsg = '您的提现申请被拒绝，原因：'.$post_data['remark'];
        }

        $data = [
            'first' => ['value' => $firstMsg],
            'keyword1' => ['value' => $withdrawals[0]['realname']],
            'keyword2' => ['value' => $withdrawals[0]['money']],
            'keyword3' => ['value' => $withdrawals[0]['bank_name'].'(尾号:'.substr($withdrawals[0]['bank_card'],-4).')'],
            'keyword4' => ['value' => date('Y年m月d日 H:i:s')],
            'remark' => ['value' => '如有疑问可直接回复此公众号联系尚美缤纷客服。'],
        ];

        $url = SITE_URL.url('/Mobile/distribution/withdrawalDetail');

        $return = self::$wechat_obj->sendTemplateMsg($user['openid'], $tpl_msg->template_id, $url, $data);

        if ($return === false) {
            return ['status' => -1, 'msg' => self::$wechat_obj->getError()];
        }

        return ['status' => 1, 'msg' => '发送模板消息成功'];
    }

    /**
     * 退换货申请提交成功
     */
    public function sendTemplateMsgOnDistribute($distribute_datas)
    {
        $template_sn = 'OPENTM405878534';
        if ( ! $this->getDefaultTemplateMsg($template_sn)) {
            return ['status' => -1, 'msg' => '消息模板不存在'];
        }

        $tpl_msg = WxTplMsg::get(['template_sn' => $template_sn, 'is_use' => 1]);
        if ( ! $tpl_msg || ! $tpl_msg->template_id) {
            return ['status' => -1, 'msg' => '消息模板未开启'];
        }

        $user = Db::name('oauth_users')->where([
            'user_id' => $distribute_datas['to_user_id'], 'oauth' => 'weixin', 'oauth_child' => 'mp','wx_bind' => 1
        ])->find();

        if ( ! $user || ! $user['openid']) {
            return ['status' => -1, 'msg' => '用户未绑定微信'];
        }

        $nickname = (new Users())->where('user_id',$distribute_datas['from_user_id'])->getField('nickname');
        //合伙人
        if ($distribute_datas['distribute_type'] == 2) {
            if ($distribute_datas['divider_type'] == 1) {
                $level = '一';
            }else{
                $level = '二';
            }
            $firstMsg = '您有新的收益，您的'.$level.'级会员“'.trim($nickname).'”成功下单';
        }elseif($distribute_datas['distribute_type'] == 4){//代理商
            if ($distribute_datas['divider_type'] <= 2) {
                if ($distribute_datas['divider_type'] == 1) {
                    $level = '一';
                }else{
                    $level = '二';
                }
                $firstMsg = '您有新的收益，您的'.$level.'级会员"'.trim($nickname).'"成功下单';
            }else {
                switch ($distribute_datas['divider_type']) {
                    case 4:
                    case 7:
                    case 10:
                        $identityName = '您代管的区县会员';
                        break;
                    case 5:
                    case 8:
                    case 11:
                        $identityName = '您代管的镇/街道办会员';
                        break;
                    case 6:
                        $identityName = '您的直营合伙人会员';
                        break;
                    case 9:
                        $identityName = '您直营合伙人的会员';
                        break;

                }
                $firstMsg = '您有新的收益，'.$identityName.'"'.trim($nickname).'"成功下单';
            }
        }
        $orderInfo = (new Order())->getOrderInfoByTradeNo($distribute_datas['order_sn']);
        $orderMoney =  $orderInfo['user_money'] + $orderInfo['order_amount'];
        $data = [
            'first' => ['value' => $firstMsg],
            'keyword1' => ['value' => $distribute_datas['order_sn']],
            'keyword2' => ['value' => '￥ '.sprintf("%.2f",$orderMoney)],
            'keyword3' => ['value' => '￥ '.sprintf("%.2f",$distribute_datas['divide_money'])],
            'remark' => ['value' => '预计收益可在用户收货7天后进行提现，订单取消或退货将不能获得该笔收益。'],
        ];

        $url = SITE_URL.url('/Mobile/distribution/earningsDetails');

        $return = self::$wechat_obj->sendTemplateMsg($user['openid'], $tpl_msg->template_id, $url, $data);

        if ($return === false) {
            return ['status' => -1, 'msg' => self::$wechat_obj->getError()];
        }

        return ['status' => 1, 'msg' => '发送模板消息成功'];
    }

    /**
     * 退换货申请提交成功
     */
    public function sendTemplateMsgOnAgentDistribute($distribute_datas)
    {
        $template_sn = 'OPENTM405637175';
        if ( ! $this->getDefaultTemplateMsg($template_sn)) {
            return ['status' => -1, 'msg' => '消息模板不存在'];
        }

        $tpl_msg = WxTplMsg::get(['template_sn' => $template_sn, 'is_use' => 1]);
        if ( ! $tpl_msg || ! $tpl_msg->template_id) {
            return ['status' => -1, 'msg' => '消息模板未开启'];
        }

        $user = Db::name('oauth_users')->where([
            'user_id' => $distribute_datas['to_user_id'], 'oauth' => 'weixin', 'oauth_child' => 'mp','wx_bind' => 1
        ])->find();

        if ( !$user || !$user['openid']) {
            return ['status' => -1, 'msg' => '用户未绑定微信'];
        }

        $agentLevel = (new UserAgentModel())->where('user_id',$distribute_datas['to_user_id'])->getField('agent_level');

        if ($distribute_datas['profit_type'] == 1) {
            $msg = '发展直营合伙人:';
        }elseif ($distribute_datas['profit_type'] == 2) {
            if ($agentLevel == 1) {
                $msg = '发展区县合伙人:';
            }elseif ($agentLevel == 2) {
                $msg = '发展直营合伙人:';
            }
        }elseif ($distribute_datas['profit_type'] == 3){
            if ($agentLevel == 3) {
                $msg = '发展直营合伙人:';
            }else{
                $msg = '发展镇/街道办合伙人:';
            }
        }

        $nickname = (new Users())->where('user_id',$distribute_datas['from_user_id'])->getField('nickname');
        $data = [
            'first' => ['value' => '您有新的收益'],
            'keyword1' => ['value' => "￥".$distribute_datas['profit_money']],
            'keyword2' => ['value' => $msg.'“'.$nickname.'”'],
            'keyword3' => ['value' => date('Y年m月d日 H:i:s')],
            'remark' => ['value' => ''],
        ];

        $url = SITE_URL.url('/Mobile/distribution/earningsDetails');

        $return = self::$wechat_obj->sendTemplateMsg($user['openid'], $tpl_msg->template_id, $url, $data);

        if ($return === false) {
            return ['status' => -1, 'msg' => self::$wechat_obj->getError()];
        }

        return ['status' => 1, 'msg' => '发送模板消息成功'];
    }

    /**
     * 打榜活动奖金发放通知
     */
    public function sendTemplateMsgOnAward($msgInfo){
        $template_sn = 'OPENTM413839164';
        if ( ! $this->getDefaultTemplateMsg($template_sn)) {
            return ['status' => -1, 'msg' => '消息模板不存在'];
        }

        $tpl_msg = WxTplMsg::get(['template_sn' => $template_sn, 'is_use' => 1]);
        if ( ! $tpl_msg || ! $tpl_msg->template_id) {
            return ['status' => -1, 'msg' => '消息模板未开启'];
        }

        $data = [
            'first' => ['value' => $msgInfo['first']],
            'keyword1' => ['value' => $msgInfo['nickname']],
            'keyword2' => ['value' => '第'.$msgInfo['rank'].'名'],
            'keyword3' => ['value' => $msgInfo['scale_amount'].'元'],
            'keyword4' => ['value' => date('Y年m月d日 H:i',$msgInfo['time'])],
            'remark' => ['value' => '感谢您的付出，期待下次活动获得更好的排名，点击查看详情>'],
        ];
        $return = self::$wechat_obj->sendTemplateMsg($msgInfo['openid'], $tpl_msg->template_id, $msgInfo['msg_url'], $data);

        if ($return === false) {
            return ['status' => -1, 'msg' => self::$wechat_obj->getError()];
        }
        return ['status' => 1, 'msg' => '发送模板消息成功'];
    }

    /**
     * 拼团成功
     */
    public function sendTemplateMsgOnTeamSucess($foundId){
        $template_sn = 'OPENTM408917543';
        if ( ! $this->getDefaultTemplateMsg($template_sn)) {
            return ['status' => -1, 'msg' => '消息模板不存在'];
        }

        $tpl_msg = WxTplMsg::get(['template_sn' => $template_sn, 'is_use' => 1]);
        if ( ! $tpl_msg || ! $tpl_msg->template_id) {
            return ['status' => -1, 'msg' => '消息模板未开启'];
        }

        //查询拼团活动中的人数
        $foundInfo = Db::name('team_found')->alias('a')->field('a.user_id as found_user_id,b.follow_user_id')
            ->join('team_follow b','a.found_id = b.found_id')
            ->where('a.found_id',$foundId)
            ->where('a.status',2)
            ->where('b.status',2)
            ->select();

        $userIdArr = array_unique(array_merge(array_column($foundInfo,'found_user_id'),array_column($foundInfo,'follow_user_id')));


        //用户信息
        $user = Db::name('oauth_users')->where([
            'user_id' => ['in',$userIdArr],
            'oauth' => 'weixin',
            'oauth_child' => 'mp',
            'wx_bind' => 1
        ])->select();

        //拼团信息
        $teamActivityInfo = Db::name('team_activity')->alias('a')
            ->join('team_found b','a.team_id = b.team_id','left')->where('b.found_id',$foundId)->find();

        $data = [
            'first' => ['value' => '恭喜您，拼团成功'],
            'keyword1' => ['value' => $teamActivityInfo['goods_name']],
            'keyword2' => ['value' => '￥ '.$teamActivityInfo['price']],
            'keyword3' => ['value' => $teamActivityInfo['needer']],
            'remark' => ['value' => '预计一个工作日左右为你发货'],
        ];
        $url = SITE_URL.url('Mobile/team/myTeam');
        foreach ($user as $v) {
            self::$wechat_obj->sendTemplateMsg($v['openid'], $tpl_msg->template_id, $url, $data);
            usleep(500);
        }

        return ['status' => 1, 'msg' => '发送模板消息成功'];
    }

    /**
     * 拼团失败
     */
    public function sendTemplateMsgOnTeamFail($foundId){
        $template_sn = 'OPENTM401113750';
        if ( ! $this->getDefaultTemplateMsg($template_sn)) {
            return ['status' => -1, 'msg' => '消息模板不存在'];
        }

        $tpl_msg = WxTplMsg::get(['template_sn' => $template_sn, 'is_use' => 1]);
        if ( ! $tpl_msg || ! $tpl_msg->template_id) {
            return ['status' => -1, 'msg' => '消息模板未开启'];
        }

        //查询拼团活动中的人数
        $foundInfo = Db::name('team_found')->alias('a')->field('a.user_id as found_user_id,b.follow_user_id')
            ->join('team_follow b','a.found_id = b.found_id','left')
            ->where('a.found_id',$foundId)
            ->select();

        $userIdArr = array_unique(array_merge(array_column($foundInfo,'found_user_id'),array_column($foundInfo,'follow_user_id')));

        //用户信息
        $user = Db::name('oauth_users')->where([
            'user_id' => ['in',$userIdArr],
            'oauth' => 'weixin',
            'oauth_child' => 'mp',
            'wx_bind' => 1
        ])->select();

        //拼团信息
        $teamActivityInfo = Db::name('team_activity')->alias('a')
            ->join('team_found b','a.team_id = b.team_id','left')->where('b.found_id',$foundId)->find();

        $data = [
            'first' => ['value' => '您好，您参加的拼团已过期，拼团失败'],
            'keyword1' => ['value' => $teamActivityInfo['goods_name']],
            'keyword2' => ['value' => '￥ '.$teamActivityInfo['price']],
            'keyword3' => ['value' => '￥ '.$teamActivityInfo['price']],
            'remark' => ['value' => '您的退款将在1个工作日内原路退还至您的支付账户'],
        ];
        $url = SITE_URL.url('Mobile/team/myTeam');
        foreach ($user as $v) {
            self::$wechat_obj->sendTemplateMsg($v['openid'], $tpl_msg->template_id, $url, $data);
            usleep(500);
        }

        return ['status' => 1, 'msg' => '发送模板消息成功'];
    }

    /**
     * 图片插件中展示的列表
     * @param $size int 拉取多少
     * @param $start int 开始位置
     * @return string
     */
    public function getPluginImages($size, $start = 0)
    {
        $data = self::$wechat_obj->getMaterialList('image', $size * $start, $size);
        if ($data === false) {
            return json_encode([
                "state" => self::$wechat_obj->getError(),
                "list" => [],
                "start" => $start,
                "total" => 0
            ]);
        }

        $list = [];
        foreach ($data['item'] as $item) {
            $list[] = [
                'url' => $item['url'],
                'mtime' => $item['update_time'],
                'name' => $item['name'],
            ];
        }

        return json_encode([
            "state" => "no match file",
            "list" => $list,
            "start" => $start,
            "total" => $data['total_count']
        ]);
    }

    /**
     * 修正关键字
     * @param $keywords
     * @return array
     */
    private function trimKeywords($keywords)
    {
        $keywords = explode(',', $keywords);
        $keywords = array_map('trim', $keywords);
        $keywords = array_unique($keywords);
        foreach ($keywords as $k => $keyword) {
            if (!$keyword) {
                unset($keywords[$k]);
            }
        }

        return array_values($keywords);
    }

    /**
     * 更新关键字
     * @param $reply_id int 回复id
     * @param $wx_keywords WxKeyword[]
     * @param $keywords array 关键字数组
     */
    private function updateKeywords($reply_id, $wx_keywords, $keywords)
    {
        $wx_keywords = convert_arr_key($wx_keywords, 'keyword');

        //先删除不存在的keyword
        foreach ($wx_keywords as $key => $word) {
            if (!in_array($key, $keywords)) {
                $word->delete();
                unset($wx_keywords[$key]);
            }
        }
        //创建要设置的keyword
        foreach ($keywords as $keyword) {
            if (!isset($wx_keywords[$keyword])) {
                WxKeyword::create([
                    'keyword' => $keyword,
                    'pid' => $reply_id,
                    'type' => WxKeyword::TYPE_AUTO_REPLY
                ]);
            }
        }
    }

    /**
     * 检查文本自动回复表单
     */
    private function checkTextAutoReplyForm(&$data)
    {
        if ($data['type'] == WxReply::TYPE_KEYWORD) {
            $rules = [
                ['type', 'require', '回复类型必需'],
                ['keywords','require','关键词必填'],
                ['rule','require|max:32','规则名必填|规则名最多32字'],
                ['content','require|max:600','文本内容必填|文本内容最多600字'],
            ];
        } else {
            $rules = [
                ['type', 'require', '回复类型必需'],
                ['content','max:600','文本内容最多600字'],
            ];
        }
        $validate = new Validate($rules);
        if (!$validate->check($data)) {
            return ['status' => -1, 'msg' => $validate->getError()];
        }

        if ( ! key_exists($data['type'], WxReply::getAllType())) {
            return ['status' => -1, 'msg' => '回复类型不存在'];
        }

        if ($data['type'] == WxReply::TYPE_KEYWORD) {
            if (!$data['keywords'] = $this->trimKeywords($data['keywords'])) {
                return ['status' => -1, 'msg' => '关键字不存在'];
            }
        }

        return ['status' => 1, 'msg' => '检查成功'];
    }

    /**
     * 添加文本自动回复
     */
    public function addTextAutoReply($data)
    {
        $return = $this->checkTextAutoReplyForm($data);
        if ($return['status'] != 1) {
            return $return;
        }

        if ($data['type'] == WxReply::TYPE_KEYWORD) {
            if (WxKeyword::get(['keyword' => ['in', $data['keywords']], 'type' => WxKeyword::TYPE_AUTO_REPLY])) {
                return ['status' => -1, 'msg' => '有关键字被其他规则使用'];
            }
        }

        $reply = WxReply::create([
            'rule' => $data['rule'],
            'update_time' => time(),
            'type' => $data['type'],
            'msg_type' => WxReply::MSG_TEXT,
            'data' => $data['content'],
        ]);

        if ($data['type'] == WxReply::TYPE_KEYWORD) {
            foreach ($data['keywords'] as $keyword) {
                WxKeyword::create([
                    'keyword' => $keyword,
                    'pid' => $reply->id,
                    'type' => WxKeyword::TYPE_AUTO_REPLY
                ]);
            }
        }

        return ['status' => 1, 'msg' => '添加成功'];
    }

    /**
     * 更新文本自动回复
     * @param $reply_id int 回复id
     * @param $data array
     * @return array
     */
    public function updateTextAutoReply($reply_id, $data)
    {
        $return = $this->checkTextAutoReplyForm($data);
        if ($return['status'] != 1) {
            return $return;
        }

        $with = ($data['type'] == WxReply::TYPE_KEYWORD) ? 'wxKeywords' : [];
        if (!$reply = WxReply::get(['id' => $reply_id], $with)) {
            return ['status' => -1, 'msg' => '该自动回复不存在'];
        }

        if ($data['type'] == WxReply::TYPE_KEYWORD) {
            $keyword_ids = get_arr_column($reply->wx_keywords, 'id');
            if (WxKeyword::all(['keyword' => ['in', $data['keywords']], 'type' => WxKeyword::TYPE_AUTO_REPLY, 'id' => ['not in', $keyword_ids]])) {
                return ['status' => -1, 'msg' => '有关键字被其他规则使用'];
            }

            $this->updateKeywords($reply_id, $reply->wx_keywords, $data['keywords']);
        }

        $reply->save([
            'rule' => $data['rule'],
            'update_time' => time(),
            'data' => $data['content'],
            'material_id' => 0,
            'msg_type' => WxReply::MSG_TEXT
        ]);

        return ['status' => 1, 'msg' => '更新成功'];
    }

    /**
     * 检查文本自动回复表单
     */
    private function checkNewsAutoReplyForm(&$data)
    {
        if ($data['type'] == WxReply::TYPE_KEYWORD) {
            $rules = [
                ['keywords','require','关键词必填'],
                ['rule','require|max:32','规则名必填|规则名最多32字'],
                ['type', 'require', '回复类型必需'],
                ['material_id','require','关联素材id必需'],
            ];
        } else {
            $rules = [
                ['type', 'require', '回复类型必需'],
                ['material_id','require','关联素材id必需'],
            ];
        }
        $validate = new Validate($rules);
        if (!$validate->check($data)) {
            return ['status' => -1, 'msg' => $validate->getError()];
        }

        if ($data['type'] == WxReply::TYPE_KEYWORD) {
            if (!$data['keywords'] = $this->trimKeywords($data['keywords'])) {
                return ['status' => -1, 'msg' => '关键字不存在'];
            }
        }

        if (!WxMaterial::get(['id' => $data['material_id'], 'type' => WxMaterial::TYPE_NEWS])) {
            return ['status' => -1, 'msg' => '关联图文素材不存在'];
        }

        return ['status' => 1, 'msg' => '检查成功'];
    }

    /**
     * 新增图文自动回复
     */
    public function addNewsAutoReply($data)
    {
        $return = $this->checkNewsAutoReplyForm($data);
        if ($return['status'] != 1) {
            return $return;
        }

        if ($data['type'] == WxReply::TYPE_KEYWORD) {
            if (WxKeyword::get(['keyword' => ['in', $data['keywords']], 'type' => WxKeyword::TYPE_AUTO_REPLY])) {
                return ['status' => -1, 'msg' => '有关键字被其他规则使用'];
            }
        }

        $reply = WxReply::create([
            'rule' => $data['rule'],
            'update_time' => time(),
            'type' => $data['type'],
            'msg_type' => WxReply::MSG_NEWS,
            'material_id' => $data['material_id'],
        ]);

        if ($data['type'] == WxReply::TYPE_KEYWORD) {
            foreach ($data['keywords'] as $keyword) {
                WxKeyword::create([
                    'keyword' => $keyword,
                    'pid' => $reply->id,
                    'type' => WxKeyword::TYPE_AUTO_REPLY
                ]);
            }
        }

        return ['status' => 1, 'msg' => '添加成功'];
    }

    /**
     * 更新图文自动回复
     * @param $reply_id int 回复id
     * @param $data array
     * @return array
     */
    public function updateNewsAutoReply($reply_id, $data)
    {
        $return = $this->checkNewsAutoReplyForm($data);
        if ($return['status'] != 1) {
            return $return;
        }

        $with = ($data['type'] == WxReply::TYPE_KEYWORD) ? 'wxKeywords' : [];
        if (!$reply = WxReply::get(['id' => $reply_id], $with)) {
            return ['status' => -1, 'msg' => '该自动回复不存在'];
        }

        if ($data['type'] == WxReply::TYPE_KEYWORD) {
            $keyword_ids = get_arr_column($reply->wx_keywords, 'id');
            if (WxKeyword::all(['keyword' => ['in', $data['keywords']], 'type' => WxKeyword::TYPE_AUTO_REPLY, 'id' => ['not in', $keyword_ids]])) {
                return ['status' => -1, 'msg' => '有关键字被其他规则使用'];
            }

            $this->updateKeywords($reply_id, $reply->wx_keywords, $data['keywords']);
        }

        $reply->save([
            'rule' => $data['rule'],
            'update_time' => time(),
            'material_id' => $data['material_id'],
            'msg_type' => WxReply::MSG_NEWS,
            'data' => '',
        ]);

        return ['status' => 1, 'msg' => '更新成功'];
    }

    /**
     * 添加自动回复
     */
    public function addAutoReply($type, $data)
    {
        if ($type == 'text') {
            return $this->addTextAutoReply($data);
        } elseif ($type == 'news') {
            return $this->addNewsAutoReply($data);
        } else {
            return ['status' => -1, 'msg' => '自动回复类型不存在'];
        }
    }

    /**
     * 更新自动回复
     */
    public function updateAutoReply($type, $reply_id, $data)
    {
        if ($type == 'text') {
            return $this->updateTextAutoReply($reply_id, $data);
        } elseif ($type == 'news') {
            return $this->updateNewsAutoReply($reply_id, $data);
        } else {
            return ['status' => -1, 'msg' => '自动回复类型不存在'];
        }
    }

    /**
     * 删除自动回复
     */
    public function deleteAutoReply($reply_id)
    {
        if (!$reply = WxReply::get(['id' => $reply_id])) {
            return ['status' => -1, 'msg' => '该自动回复不存在'];
        }

        if ($reply->type == WxReply::TYPE_KEYWORD) {
            WxKeyword::where(['pid' => $reply_id])->delete();
        }

        $reply->delete();

        return ['status' => 1, 'msg' => '删除成功'];
    }
}