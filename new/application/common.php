<?php
/**
 * tpshop
 * ============================================================================
 * * 版权所有 2015-2027 深圳搜豹网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.tp-shop.cn
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: IT宇宙人 2015-08-10 $
 */
use think\Db;
use think\Session;
use app\common\logic\RedisRegister;
use Qcloud\Sms\SmsMultiSender;
use Qcloud\Sms\SmsSingleSender;

/**
 * 群发短信 [腾讯云短信]
 * @param $mobile 电话 为数组多个或单个
 * @param $params ['code'] 验证码  $params['tempid'] 模板id
 * @param $scene 用户场景1:用户注册,2:找回密码,3:客户下单,4:客户支付,5:商家发货,6:身份验证,7:商家活动。
 */
function sendNotify($phoneNumbers, $templateId,$params=[],$smsSign="")
{
    $sms_config = C('sms_key');
    vendor('.qcloud.qcloudsms_php.autoload');
    $msender = new SmsMultiSender($sms_config['appid'], $sms_config['appkey']);  //数组具体的元素个数和模板中变量个数必须一致，例如事例中 templateId:5678对应一个变量，参数数组中元素个数也必须是一个
    $result = $msender->sendWithParam("86", $phoneNumbers,$templateId, $params, $smsSign, "", "");  // 签名参数未提供或者为空时，会使用默认签名发送短信
    $rsp = json_decode($result);
    if($rsp->result != 0){
        return array('status' => -1, 'msg' => '发送失败');
    }

    return array('status' => 0, 'msg' => '发送成功！');
}

/**
 * 单条发送短信 [腾讯云短信]
 * params 短信所需参数
 * @param $scene 用户场景 1:用户注册,2:找回密码,3:客户下单,4:客户支付,5:商家发货,6:身份验证,7:商家活动。
 */
function send_SMS($mobile,$scene,$params){
    $sms_config = C('sms_key');
    $data = Db::name('sms_log')->where(array('mobile' => $mobile, 'scene' => $scene))->order('id DESC')->find();   //判断是否存在验证码
    $sms_time_out =  120;      // 获取时间配置
    if ($data && (time() - $data['add_time']) < $sms_time_out)  return ['status' => -1, 'msg' => $sms_time_out . '秒内不允许重复发送'];
    $row = Db::name('sms_log')->insertGetId(['mobile' => $mobile, 'code' => $params['code'], 'scene' => $scene, 'add_time' => time(),'send_ip'=>getIP()]);
    if (!$row) return['status' => -1, 'msg' => '发送失败'];
    vendor('.qcloud.qcloudsms_php.autoload');
    $sender = new SmsSingleSender($sms_config['appid'], $sms_config['appkey']);
    $par[] = $params['code'];
    //获取发送场景
    switch ($scene){
        default:
            $tempId = 78278;   //默认为短信验证码
    }
    $result = $sender->sendWithParam("86", $mobile, $tempId, $par, "", "", "");
    $rsp = json_decode($result);
    if($rsp->result != 0)  return ['status' => -1, 'msg' => '发送失败'];
    return ['status' => 1, 'msg' => '发送成功！'];
}

/**
 * 发送通知短信，带多个参数 [腾讯云短信]
 * @param $mobile 电话[发多个用英文逗号隔开]
 * @param $scene 用户场景1:用户注册,2:找回密码,3:客户下单,4:客户支付,5:商家发货,6:身份验证。
 */
function sendNotifySMS($scene, $mobile, $params,$tempId)
{
    $sms_config = C('sms_key');
    $data = Db::name('sms_log')->where(array('mobile' => $mobile, 'scene' => $scene))->order('id DESC')->find();   //判断是否存在验证码
    $sms_time_out =  120;      // 获取时间配置
    if ($data && (time() - $data['add_time']) < $sms_time_out)  return ['status' => -1, 'msg' => $sms_time_out . '秒内不允许重复发送'];
    $row = Db::name('sms_log')->insertGetId(['mobile' => $mobile, 'code' => $params['code'], 'scene' => $scene, 'add_time' => time(),'send_ip'=>getIP()]);
    if (!$row) return['status' => -1, 'msg' => '发送失败'];
    vendor('.qcloud.qcloudsms_php.autoload');
    $sender = new SmsSingleSender($sms_config['appid'], $sms_config['appkey']);
    $result = $sender->sendWithParam("86", $mobile, $tempId, $params, "", "", "");
    $rsp = json_decode($result);
    if($rsp->result != 0)  return ['status' => -1, 'msg' => '发送失败'];
    return ['status' => 1, 'msg' => '发送成功！'];
}
/**
 * 发送通知短信，没有参数 [腾讯云短信]
 * @param $mobile 电话[发多个用英文逗号隔开]
 * @param $scene 用户场景1:用户注册,2:找回密码,3:客户下单,4:客户支付,5:商家发货,6:身份验证。
 */
function noParamsSMS($mobile,$tempId)
{
    $sms_config = C('sms_key');
    vendor('.qcloud.qcloudsms_php.autoload');
    $sender = new SmsSingleSender($sms_config['appid'], $sms_config['appkey']);
    $result = $sender->sendWithParam("86", $mobile, $tempId, [], "", "", "");
    $rsp = json_decode($result);
    if($rsp->result != 0)  return ['status' => -1, 'msg' => '发送失败'];
    return ['status' => 1, 'msg' => '发送成功！'];
}


// 通知短信
function notifySms($mobile, $tempId)
{
    vendor('sendsms.Sendsms');
    $mysend = new \Sendsms();
    $mysend->sendTemplateSMS($mobile, array(), $tempId);
}

/**
 * 发送短信逻辑
 * @param $mobile 电话
 * @param $params ['code'] 验证码  $params['tempid'] 模板id
 * @param $scene 用户场景1:用户注册,2:找回密码,3:客户下单,4:客户支付,5:商家发货,6:身份验证。
 */
function sendSms($scene, $mobile, $params)
{
    $code = $params['code'];
    $data = M('sms_log')->where(array('mobile' => $mobile, 'scene' => $scene))->order('id DESC')->find();   //判断是否存在验证码
    //获取时间配置
    $sms_time_out = tpCache('sms.sms_time_out');
    $sms_time_out = $sms_time_out ? $sms_time_out : 120;
    //120秒以内不可重复发送
    if ($data && (time() - $data['add_time']) < $sms_time_out)
        return array('status' => -1, 'msg' => $sms_time_out . '秒内不允许重复发送');
    $row = M('sms_log')->add(array('mobile' => $mobile, 'code' => $code, 'scene' => $scene, 'add_time' => time()));
    if (!$row) return array('status' => -1, 'msg' => '发送失败');
    vendor('sendsms.Sendsms');
    $mysend = new \Sendsms();
    $tempId = $params['tempid'];//注册
    $mysend->sendTemplateSMS($mobile, array($code), $tempId);
    return array('status' => 1, 'msg' => '发送成功');
}

/**
 * 发送通知短信，带多个参数
 * @param $mobile 电话[发多个用英文逗号隔开]
 * @param $scene 用户场景1:用户注册,2:找回密码,3:客户下单,4:客户支付,5:商家发货,6:身份验证。
 */
function send_notify_sms($scene, $mobile, $params,$tempId)
{
    $row = M('sms_log')->add(array('mobile' => $mobile, 'scene' => $scene, 'add_time' => time()));
    if (!$row) return array('status' => -1, 'msg' => '发送失败');
    vendor('sendsms.Sendsms');
    $mysend = new \Sendsms();
    $mysend->sendTemplateSMS($mobile, $params, $tempId);
    return array('status' => 1, 'msg' => '发送成功');
}

/**
 * 验证用户是否登录
 * @param string $token
 * @return bool
 */
function is_login($token = '')
{
    if (!empty($token)) {
        $redis = RedisRegister::getIndex(0);
        $respose = $redis->get($token);
        return $respose;
    }
    return false;
}

/**
 * 获取用户信息
 * @param $user_id_or_name  用户id 邮箱 手机 第三方id
 * @param int $type 类型 0 user_id查找 1 邮箱查找 2 手机查找 3 第三方唯一标识查找
 * @param string $oauth 第三方来源
 * @return mixed
 */
function get_user_info($user_id_or_name, $type = 0, $oauth = '')
{
    $map = array();
    if ($type == 0)
        $map['user_id'] = $user_id_or_name;
    if ($type == 1)
        $map['email'] = $user_id_or_name;
    if ($type == 2)
        $map['mobile'] = $user_id_or_name;
    if ($type == 3) {
        $map['openid'] = $user_id_or_name;
        $map['oauth'] = $oauth;
    }
    if ($type == 4) {
        $map['unionid'] = $user_id_or_name;
        $map['oauth'] = $oauth;
    }
    $user = M('users')->where($map)->find();
    return $user;
}

/**
 * 更新会员等级,折扣，消费总额
 * @param $user_id  用户ID
 * @return boolean
 */
function update_user_level($user_id)
{
    $level_info = M('user_level')->order('level_id')->select();
    $total_amount = M('order')->master()->where("user_id=:user_id AND pay_status=1 and order_status not in (3,5)")->bind(['user_id' => $user_id])->sum('order_amount+user_money');
    if ($level_info) {
        foreach ($level_info as $k => $v) {
            if ($total_amount >= $v['amount']) {
                $level = $level_info[$k]['level_id'];
                //$discount = $level_info[$k]['discount']/100;
            }
        }
        $user = session('user');
        $updata['total_amount'] = $total_amount;//更新累计修复额度
        //累计额度达到新等级，更新会员折扣
        if (isset($level) && $level > $user['level']) {
            $updata['level'] = $level;
            //$updata['discount'] = $discount;
        }
        M('users')->where("user_id", $user_id)->save($updata);
    }
}

/**
 *  商品缩略图 给于标签调用 拿出商品表的 original_img 调用阿里云oss
 * @param type $goods_id 商品id
 * @param type $width 生成缩略图的宽度
 * @param type $height 生成缩略图的高度
 */
function goods_thum_images($goods_id, $width, $height)
{
    $original_img = M('Goods')->where("goods_id", $goods_id)->getField('original_img');
    return $original_img . '?x-oss-process=image/resize,m_pad,h_' . $height . ',w_' . $width;
}


/**
 * 刷新商品库存, 如果商品有设置规格库存, 则商品总库存 等于 所有规格库存相加
 * @param type $goods_id 商品id
 */
function refresh_stock($goods_id)
{
    $count = M("SpecGoodsPrice")->where("goods_id", $goods_id)->count();
    if ($count == 0) return false; // 没有使用规格方式 没必要更改总库存
    
    $store_count = M("SpecGoodsPrice")->where("goods_id", $goods_id)->sum('store_count');
    M("Goods")->where("goods_id", $goods_id)->save(array('store_count' => $store_count)); // 更新商品的总库存
}

/**
 * 根据 order_goods 表扣除商品库存
 * @param type $order_id 订单id
 */
function minus_stock($order)
{
    $orderGoodsArr = M('OrderGoods')->master()->where(array('order_id' => $order['order_id']))->select(); // 有可能是刚下完订单的 需要到主库里面去查
    foreach ($orderGoodsArr as $key => $val) {
        // 有选择规格的商品
        if (!empty($val['spec_key'])) {   // 先到规格表里面扣除数量 再重新刷新一个 这件商品的总数量
            M('SpecGoodsPrice')->where(['goods_id' => $val['goods_id'], 'key' => $val['spec_key']])->setDec('store_count', $val['goods_num']);
            refresh_stock($val['goods_id']);
        } else {
            M('Goods')->where("goods_id", $val['goods_id'])->setDec('store_count', $val['goods_num']); // 直接扣除商品总数量
        }
        update_stock_log($order['user_id'], -$val['goods_num'], $val, $order['order_sn']);//库存出库日志
        M('Goods')->where("goods_id", $val['goods_id'])->setInc('sales_sum', $val['goods_num']); // 增加商品销售量
        //更新活动商品购买量
        if ($val['prom_type'] == 1 || $val['prom_type'] == 2) {
            $prom = get_goods_promotion($val['goods_id']);
            if ($prom['status'] == 1) {
                $tb = $val['prom_type'] == 1 ? 'flash_sale' : 'group_buy';
                M($tb)->where("id", $val['prom_id'])->setInc('buy_num', $val['goods_num']);
                M($tb)->where("id", $val['prom_id'])->setInc('order_num');
            }
        }
    }
}

/**
 * 商品库存操作日志
 * @param int $muid 操作 用户ID
 * @param int $stock 更改库存数
 * @param array $goods 库存商品
 * @param string $order_sn 订单编号
 */
function update_stock_log($muid, $stock = 1, $goods, $order_sn = '')
{
    $data['ctime'] = time();
    $data['stock'] = $stock;
    $data['muid'] = $muid;
    $data['goods_id'] = $goods['goods_id'];
    $data['goods_name'] = $goods['goods_name'];
    $data['goods_spec'] = empty($goods['spec_key_name']) ? '' : $goods['spec_key_name'];
    $data['store_id'] = $goods['store_id'];
    $data['order_sn'] = $order_sn;
    M('stock_log')->add($data);
}

/**
 * 邮件发送
 * @param $to    接收人
 * @param string $subject 邮件标题
 * @param string $content 邮件内容(html模板渲染后的内容)
 * @throws Exception
 * @throws phpmailerException
 */
function send_email($to, $subject = '', $content = '')
{
    vendor('phpmailer.PHPMailerAutoload');
    //判断openssl是否开启
    $openssl_funcs = get_extension_funcs('openssl');
    if (!$openssl_funcs) {
        return array('status' => -1, 'msg' => '请先开启openssl扩展');
    }
    $mail = new PHPMailer;
    $config = tpCache('smtp');
    $mail->CharSet = 'UTF-8'; //设定邮件编码，默认ISO-8859-1，如果发中文此项必须设置，否则乱码
    $mail->isSMTP();
    //Enable SMTP debugging
    // 0 = off (for production use)
    // 1 = client messages
    // 2 = client and server messages
    $mail->SMTPDebug = 0;
    //调试输出格式
    //$mail->Debugoutput = 'html';
    //smtp服务器
    $mail->Host = $config['smtp_server'];
    //端口 - likely to be 25, 465 or 587
    $mail->Port = $config['smtp_port'];
    if ($mail->Port === 465) $mail->SMTPSecure = 'ssl';// 使用安全协议
    //Whether to use SMTP authentication
    $mail->SMTPAuth = true;
    //用户名
    $mail->Username = $config['smtp_user'];
    //密码
    $mail->Password = $config['smtp_pwd'];
    //Set who the message is to be sent from
    $mail->setFrom($config['smtp_user']);
    //回复地址
    //$mail->addReplyTo('replyto@example.com', 'First Last');
    //接收邮件方
    if (is_array($to)) {
        foreach ($to as $v) {
            $mail->addAddress($v);
        }
    } else {
        $mail->addAddress($to);
    }
    
    $mail->isHTML(true);// send as HTML
    //标题
    $mail->Subject = $subject;
    //HTML内容转换
    $mail->msgHTML($content);
    //Replace the plain text body with one created manually
    //$mail->AltBody = 'This is a plain-text message body';
    //添加附件
    //$mail->addAttachment('images/phpmailer_mini.png');
    //send the message, check for errors
    if (!$mail->send()) {
        return array('status' => -1, 'msg' => '发送失败: ' . $mail->ErrorInfo);
    } else {
        return array('status' => 1, 'msg' => '发送成功');
    }
}


/**
 * 检测是否能够发送短信
 * @param unknown $scene
 * @return multitype:number string
 */
function checkEnableSendSms($scene)
{
    
    $scenes = C('SEND_SCENE');
    $sceneItem = $scenes[$scene];
    if (!$sceneItem) {
        return array("status" => -1, "msg" => "场景参数'scene'错误!");
    }
    $key = $sceneItem[2];
    $sceneName = $sceneItem[0];
    $config = tpCache('sms');
    $smsEnable = $config[$key];
    
    if (!$smsEnable) {
        return array("status" => 0, "msg" => "['$sceneName']发送短信被关闭'");
    }
    //判断是否添加"注册模板"
    $size = M('sms_template')->where("send_scene", $scene)->count('tpl_id');
    if (!$size) {
        return array("status" => -1, "msg" => "请先添加['$sceneName']短信模板");
    }
    return array("status" => 1, "msg" => "可以发送短信");
    
}


//    /**
//     * 发送短信
//     * @param $mobile  手机号码
//     * @param $code    验证码
//     * @return bool    短信发送成功返回true失败返回false
//     */
function realSendSMS($mobile, $smsSign, $smsParam, $templateCode)
{
    //时区设置：亚洲/上海
    date_default_timezone_set('Asia/Shanghai');
    //这个是你下面实例化的类
    vendor('Alidayu.TopClient');
    //这个是topClient 里面需要实例化一个类所以我们也要加载 不然会报错
    vendor('Alidayu.ResultSet');
    //这个是成功后返回的信息文件
    vendor('Alidayu.RequestCheckUtil');
    //这个是错误信息返回的一个php文件
    vendor('Alidayu.TopLogger');
    //这个也是你下面示例的类
    vendor('Alidayu.AlibabaAliqinFcSmsNumSendRequest');
    
    $c = new \TopClient;
    $config = tpCache('sms');
    //App Key的值 这个在开发者控制台的应用管理点击你添加过的应用就有了
    $c->appkey = $config['sms_appkey'];
    //App Secret的值也是在哪里一起的 你点击查看就有了
    $c->secretKey = $config['sms_secretKey'];
    //这个是用户名记录那个用户操作
    $req = new \AlibabaAliqinFcSmsNumSendRequest;
    //代理人编号 可选
    $req->setExtend("123456");
    //短信类型 此处默认 不用修改
    $req->setSmsType("normal");
    //短信签名 必须
    $req->setSmsFreeSignName($smsSign);
    //短信模板 必须
    $req->setSmsParam($smsParam);
    //短信接收号码 支持单个或多个手机号码，传入号码为11位手机号码，不能加0或+86。群发短信需传入多个号码，以英文逗号分隔，
    $req->setRecNum("$mobile");
    //短信模板ID，传入的模板必须是在阿里大鱼“管理中心-短信模板管理”中的可用模板。
    $req->setSmsTemplateCode($templateCode); // templateCode
    
    $c->format = 'json';
    //发送短信
    $resp = $c->execute($req);
    //短信发送成功返回True，失败返回false
    if ($resp && $resp->result) {
        return array('status' => 1, 'msg' => $resp->sub_msg);
    } else {
        return array('status' => -1, 'msg' => $resp->msg . ' ,sub_msg :' . $resp->sub_msg . ' subcode:' . $resp->sub_code);
    }
}

/**
 * 查询快递
 * @param $postcom  快递公司编码
 * @param $getNu  快递单号
 * @return array  物流跟踪信息数组
 */
function queryExpressInfo($postcom, $getNu)
{
    //getLast
    /*    $url = "http://wap.kuaidi100.com/wap_result.jsp?rand=".time()."&id={$postcom}&fromWeb=null&postid={$getNu}";
        //$resp = httpRequest($url,'GET');
        $resp = file_get_contents($url);
        if (empty($resp)) {
            return array('status'=>0, 'message'=>'物流公司网络异常，请稍后查询');
        }
        preg_match_all('/\\<p\\>&middot;(.*)\\<\\/p\\>/U', $resp, $arr);
        if (!isset($arr[1])) {
            return array( 'status'=>0, 'message'=>'查询失败，参数有误' );
        }else{
            foreach ($arr[1] as $key => $value) {
                $a = array();
                $a = explode('<br /> ', $value);
                $data[$key]['time'] = $a[0];
                $data[$key]['context'] = $a[1];
            }
            return array( 'status'=>1, 'message'=>'1','data'=> array_reverse($data));
        }*/
    $url = "https://m.kuaidi100.com/query?type=" . $postcom . "&postid=" . $getNu . "&id=1&valicode=&temp=0.49738534969422676";
    $resp = httpRequest($url, "GET");
    return json_decode($resp, true);
}

/**
 * 获取某个商品分类的 儿子 孙子  重子重孙 的 id
 * @param type $cat_id
 */
function getCatGrandson($cat_id)
{
    $GLOBALS['catGrandson'] = array();
    $GLOBALS['category_id_arr'] = array();
    // 先把自己的id 保存起来
    $GLOBALS['catGrandson'][] = $cat_id;
    // 把整张表找出来
    $GLOBALS['category_id_arr'] = M('GoodsCategory')->cache(true, TPSHOP_CACHE_TIME)->getField('id,parent_id');
    // 先把所有儿子找出来
    $son_id_arr = M('GoodsCategory')->where("parent_id", $cat_id)->cache(true, TPSHOP_CACHE_TIME)->getField('id', true);
    foreach ($son_id_arr as $k => $v) {
        getCatGrandson2($v);
    }
    return $GLOBALS['catGrandson'];
}

/**
 * 获取某个文章分类的 儿子 孙子  重子重孙 的 id
 * @param type $cat_id
 */
function getArticleCatGrandson($cat_id)
{
    $GLOBALS['ArticleCatGrandson'] = array();
    $GLOBALS['cat_id_arr'] = array();
    // 先把自己的id 保存起来
    $GLOBALS['ArticleCatGrandson'][] = $cat_id;
    // 把整张表找出来
    $GLOBALS['cat_id_arr'] = M('ArticleCat')->getField('cat_id,parent_id');
    // 先把所有儿子找出来
    $son_id_arr = M('ArticleCat')->where("parent_id", $cat_id)->getField('cat_id', true);
    foreach ($son_id_arr as $k => $v) {
        getArticleCatGrandson2($v);
    }
    return $GLOBALS['ArticleCatGrandson'];
}

/**
 * 递归调用找到 重子重孙
 * @param type $cat_id
 */
function getCatGrandson2($cat_id)
{
    $GLOBALS['catGrandson'][] = $cat_id;
    foreach ($GLOBALS['category_id_arr'] as $k => $v) {
        // 找到孙子
        if ($v == $cat_id) {
            getCatGrandson2($k); // 继续找孙子
        }
    }
}


/**
 * 递归调用找到 重子重孙
 * @param type $cat_id
 */
function getArticleCatGrandson2($cat_id)
{
    $GLOBALS['ArticleCatGrandson'][] = $cat_id;
    foreach ($GLOBALS['cat_id_arr'] as $k => $v) {
        // 找到孙子
        if ($v == $cat_id) {
            getArticleCatGrandson2($k); // 继续找孙子
        }
    }
}

/**
 * 查看某个用户购物车中商品的数量
 * @param type $user_id
 * @param type $session_id
 * @return type 购买数量
 */
function cart_goods_num($user_id = 0, $session_id = '')
{
//    $where = " session_id = '$session_id' ";
//    $user_id && $where .= " or user_id = $user_id ";
    // 查找购物车数量
//    $cart_count =  M('Cart')->where($where)->sum('goods_num');
    $cart_count = M('cart')->where(function ($query) use ($user_id, $session_id) {
        $query->where('session_id', $session_id);
        if ($user_id) {
            $query->whereOr('user_id', $user_id);
        }
    })->sum('goods_num');
    $cart_count = $cart_count ? $cart_count : 0;
    return $cart_count;
}

/**
 * 获取商品库存
 * @param type $goods_id 商品id
 * @param type $key 库存 key
 */
function getGoodNum($goods_id, $key)
{
    if (!empty($key))
        return M("SpecGoodsPrice")->where(['goods_id' => $goods_id, 'key' => $key])->getField('store_count');
    else
        return M("Goods")->where("goods_id", $goods_id)->getField('store_count');
}


/**
 * 用户登录密码规则
 *
 * @author spjiang
 * @param $password
 * @return mixed|string
 */
function encryptUserPasswd($password)
{
    if (!$password) return '';
    $password = md5($password);
    $passwordstr = C('passwordstr');
    $password = substr_replace($password, $passwordstr, 2, 0);
    $password = md5($password);
    return $password;
}

/**
 * 获取缓存或者更新缓存
 * @param string $config_key 缓存文件名称
 * @param array $data 缓存数据  array('k1'=>'v1','k2'=>'v3')
 * @return array or string or bool
 */
function tpCache($config_key, $data = array())
{
    $param = explode('.', $config_key);
    if (empty($data)) {
        //如$config_key=shop_info则获取网站信息数组
        //如$config_key=shop_info.logo则获取网站logo字符串
        $config = F($param[0], '', TEMP_PATH);//直接获取缓存文件
        if (empty($config)) {
            //缓存文件不存在就读取数据库
            $res = D('config')->where("inc_type", $param[0])->select();
            if ($res) {
                foreach ($res as $k => $val) {
                    $config[$val['name']] = $val['value'];
                }
                F($param[0], $config, TEMP_PATH);
            }
        }
        if (count($param) > 1) {
            return $config[$param[1]];
        } else {
            return $config;
        }
    } else {
        //更新缓存
        $result = D('config')->where("inc_type", $param[0])->select();
        if ($result) {
            foreach ($result as $val) {
                $temp[$val['name']] = $val['value'];
            }
            foreach ($data as $k => $v) {
                $newArr = array('name' => $k, 'value' => trim($v), 'inc_type' => $param[0]);
                if (!isset($temp[$k])) {
                    M('config')->add($newArr);//新key数据插入数据库
                } else {
                    if ($v != $temp[$k])
                        M('config')->where("name", $k)->save($newArr);//缓存key存在且值有变更新此项
                }
            }
            //更新后的数据库记录
            $newRes = D('config')->where("inc_type", $param[0])->select();
            foreach ($newRes as $rs) {
                $newData[$rs['name']] = $rs['value'];
            }
        } else {
            foreach ($data as $k => $v) {
                $newArr[] = array('name' => $k, 'value' => trim($v), 'inc_type' => $param[0]);
            }
            M('config')->insertAll($newArr);
            $newData = $data;
        }
        return F($param[0], $newData, TEMP_PATH);
    }
}

function orderOtherPayName($order_info)
{
    if (!isset($order_info['integral']) || !isset($order_info['user_money'])) {
        return '';
    }
    if ($order_info['integral'] != 0 && $order_info['user_money'] == 0) {
        $pay_name = '积分支付';
    } elseif ($order_info['integral'] == 0 && $order_info['user_money'] != 0) {
        $pay_name = '余额支付';
    } elseif ($order_info['integral'] != 0 && $order_info['user_money'] != 0) {
        $pay_name = '积分、余额支付';
    } else {
        $pay_name = '优惠券支付';
    }
    return $pay_name;
}

// 登录地址跳转
function MobileLoginUrl($access_type = 0, $app_type = 1)
{
    if ($access_type == 1) {
        if (in_array($app_type, array(1, 3))) {
            $page = U('Mobile/Login/ios_login');
        } else {
            $page = U('Mobile/Login/android_login');
        }
    } else {
        $page = U('Mobile/User/login');
    }
    return $page;
}

/**
 * 记录帐户变动
 * @param int $user_id 用户id
 * @param int $user_money 可用余额变动
 * @param int $pay_points 消费积分变动
 * @param string $desc 变动说明
 * @param int $distribut_money 分佣金额
 * @param int $order_id 订单id
 * @param string $order_sn 订单sn
 * @return bool
 */
function accountLog($user_id, $user_money = 0, $pay_points = 0, $desc = '', $distribut_money = 0, $order_id = 0, $order_sn = '')
{
    /* 插入帐户变动记录 */
    $account_log = array(
        'user_id' => $user_id,
        'user_money' => $user_money,
        'pay_points' => $pay_points,
        'change_time' => time(),
        'desc' => $desc,
        'order_id' => $order_id,
        'order_sn' => $order_sn
    );
    /* 更新用户信息 */
    //    $sql = "UPDATE __PREFIX__users SET user_money = user_money + $user_money," .
    //        " pay_points = pay_points + $pay_points, distribut_money = distribut_money + $distribut_money WHERE user_id = $user_id";
    $update_data = array(
        'user_money' => ['exp', 'user_money+' . $user_money],
        'pay_points' => ['exp', 'pay_points+' . $pay_points],
        'distribut_money' => ['exp', 'distribut_money+' . $distribut_money],
    );
    if (($user_money + $pay_points + $distribut_money) == 0)
        return false;
    $update = Db::name('users')->where('user_id', $user_id)->update($update_data);
    if ($update) {
        M('account_log')->add($account_log);
        return true;
    } else {
        return false;
    }
}

/**
 * 记录商家的帐户变动
 * @param $store_id 店铺ID
 * @param int $store_money 可用资金
 * @param $pending_money 可用余额变动
 * @param string $desc 变动说明
 * @param int $order_id 订单id
 * @param string $order_sn 订单sn
 * @return bool
 */
function storeAccountLog($store_id, $store_money = 0, $pending_money, $desc = '', $order_id = 0, $order_sn = '', $order_statis_id = 0)
{
    /* 插入帐户变动记录 */
    $account_log = array(
        'store_id' => $store_id,
        'store_money' => $store_money, // 可用资金
        'pending_money' => $pending_money, // 未结算资金
        'change_time' => time(),
        'desc' => $desc,
        'order_id' => $order_id,
        'order_sn' => $order_sn,
        'order_statis_id' => $order_statis_id,
    );
    /* 更新用户信息 */
//    $sql = "UPDATE __PREFIX__store SET store_money = store_money + $store_money," .
//        " pending_money = pending_money + $pending_money WHERE store_id = $store_id";
    $update_data = array(
        'store_money' => ['exp', 'store_money+' . $store_money],
        'pending_money' => ['exp', 'pending_money+' . $pending_money],
    );
    $update = Db::name('store')->where('store_id', $store_id)->update($update_data);
    if ($update) {
        //$account_log['desc'] = $account_log['desc'].':'.;
        M('account_log_store')->add($account_log);
        return true;
    } else {
        return false;
    }
}

/**
 * 订单操作日志
 * 参数示例
 * @param type $order_id 订单id
 * @param type $action_note 操作备注
 * @param type $status_desc 操作状态  提交订单, 付款成功, 取消, 等待收货, 完成
 * @param type $user_id 用户id 默认为管理员
 * @return boolean
 */
function logOrder($order_id, $action_note, $status_desc, $user_id = 0, $user_type = 0)
{
    $status_desc_arr = array('提交订单', '付款成功', '取消', '等待收货', '完成', '退货');
    // if(!in_array($status_desc, $status_desc_arr))
    // return false;
    
    $order = M('order')->master()->where("order_id", $order_id)->find();
    $action_info = array(
        'order_id' => $order_id,
        'action_user' => $user_id,
        'user_type' => $user_type,
        'order_status' => $order['order_status'],
        'shipping_status' => $order['shipping_status'],
        'pay_status' => $order['pay_status'],
        'action_note' => $action_note,
        'status_desc' => $status_desc, //''
        'log_time' => time(),
    );
    return M('order_action')->add($action_info);
}

/*
 * 获取地区列表
 */
function get_region_list()
{
    return Db::name('region')->cache(true)->getField('id,name');
}

/*
 * 获取用户地址列表
 */
function get_user_address_list($user_id)
{
    $lists = M('user_address')->where(array('user_id' => $user_id))->select();
    return $lists;
}

/*
 * 获取指定地址信息
 */
function get_user_address_info($user_id, $address_id)
{
    $data = M('user_address')->where(array('user_id' => $user_id, 'address_id' => $address_id))->find();
    return $data;
}

/*
 * 获取用户默认收货地址
 */
function get_user_default_address($user_id)
{
    $data = M('user_address')->where(array('user_id' => $user_id, 'is_default' => 1))->find();
    return $data;
}

/**
 * 获取订单状态的 中文描述名称
 * @param type $order_id 订单id
 * @param type $order 订单数组
 * @return string
 */
function orderStatusDesc($order_id = 0, $order = array())
{
    if (empty($order))
        $order = M('Order')->where("order_id", $order_id)->find();
    
    // 货到付款
    if ($order['pay_code'] == 'cod') {
        if (in_array($order['order_status'], array(0, 1)) && $order['shipping_status'] == 0)
            return 'WAITSEND'; //'待发货',
    } else // 非货到付款
    {
        if ($order['pay_status'] == 0 && $order['order_status'] == 0)
            return 'WAITPAY'; //'待支付',
        if ($order['pay_status'] == 1 && in_array($order['order_status'], array(0, 1)) && $order['shipping_status'] != 1)
            return 'WAITSEND'; //'待发货',
    }
    if (($order['shipping_status'] == 1) && ($order['order_status'] == 1))
        return 'WAITRECEIVE'; //'待收货',
    if ($order['order_status'] == 2 && $order['is_comment'] == 1) {
        return 'FINISH'; //'待评价',
    }
    if ($order['order_status'] == 2) {
        return 'WAITCCOMMENT'; //'待评价',
    }
    if ($order['order_status'] == 3)
        return 'CANCEL'; //'已取消',
    if ($order['order_status'] == 4)
        return 'FINISH'; //'已完成',
    return 'OTHER';
}

/**
 * 获取订单状态的 显示按钮
 * @param type $order_id 订单id
 * @param type $order 订单数组
 * @return array()
 */
function orderBtn($order_id = 0, $order = array())
{
    if (empty($order))
        $order = M('Order')->where("order_id", $order_id)->find();
    /**
     *  订单用户端显示按钮
     * 去支付     AND pay_status=0 AND order_status=0 AND pay_code ! ="cod"
     * 取消按钮  AND pay_status=0 AND shipping_status=0 AND order_status=0
     * 确认收货  AND shipping_status=1 AND order_status=0
     * 评价      AND order_status=1
     * 查看物流  if(!empty(物流单号))
     */
    $btn_arr = array(
        'pay_btn' => 0, // 去支付按钮
        'cancel_btn' => 0, // 取消按钮
        'receive_btn' => 0, // 确认收货
        'comment_btn' => 0, // 评价按钮
        'shipping_btn' => 0, // 查看物流
        'return_btn' => 0, // 退货按钮 (联系客服)
    );
    
    
    // 货到付款
    if ($order['pay_code'] == 'cod') {
        if (($order['order_status'] == 0 || $order['order_status'] == 1) && $order['shipping_status'] == 0) // 待发货
        {
            $btn_arr['cancel_btn'] = 1; // 取消按钮 (联系客服)
        }
        if ($order['shipping_status'] == 1 && $order['order_status'] == 1) //待收货
        {
            $btn_arr['receive_btn'] = 1;  // 确认收货
            $btn_arr['return_btn'] = 1; // 退货按钮 (联系客服)
        }
    } // 非货到付款
    else {
        if ($order['pay_status'] == 0 && $order['order_status'] == 0) // 待支付
        {
            $btn_arr['pay_btn'] = 1; // 去支付按钮
            $btn_arr['cancel_btn'] = 1; // 取消按钮
        }
        if ($order['pay_status'] == 1 && in_array($order['order_status'], array(0, 1)) && $order['shipping_status'] == 0) // 待发货
        {
            $btn_arr['return_btn'] = 1; // 退货按钮 (联系客服)
        }
        if ($order['pay_status'] == 1 && $order['order_status'] == 1 && $order['shipping_status'] == 1) //待收货
        {
            $btn_arr['receive_btn'] = 1;  // 确认收货
            $btn_arr['return_btn'] = 1; // 退货按钮 (联系客服)
        }
    }
    if ($order['order_status'] == 2) {
        if ($order['is_comment'] == 0) $btn_arr['comment_btn'] = 1;  // 评价按钮
        $btn_arr['return_btn'] = 1; // 退货按钮 (联系客服)
    }
    if ($order['shipping_status'] != 0) {
        $btn_arr['shipping_btn'] = 1; // 查看物流
    }
    if ($order['shipping_status'] == 2 && $order['order_status'] == 1) // 部分发货
    {
        $btn_arr['return_btn'] = 1; // 退货按钮 (联系客服)
    }
    
    return $btn_arr;
}

/**
 * 给订单数组添加属性  包括按钮显示属性 和 订单状态显示属性
 * @param type $order
 */
function set_btn_order_status($order)
{
    $order_status_arr = C('ORDER_STATUS_DESC');
    $order['order_status_code'] = $order_status_code = orderStatusDesc(0, $order); // 订单状态显示给用户看的
    $order['order_status_desc'] = $order_status_arr[$order_status_code];
    $orderBtnArr = orderBtn(0, $order);
    return array_merge($order, $orderBtnArr); // 订单该显示的按钮
}


/**
 * 支付完成修改订单
 * $order_sn 订单号
 * $transaction_id  第三方支付交易流水号
 */
function update_pay_status($order_sn, $transaction_id = '')
{
    if (stripos($order_sn, 'recharge') !== false) {
        //用户在线充值
        $count = M('recharge')->where(['order_sn' => $order_sn, 'pay_status' => 0])->count();   // 看看有没已经处理过这笔订单  支付宝返回不重复处理操作
        if ($count == 0) return false;
        $order = M('recharge')->where("order_sn", $order_sn)->find();
        M('recharge')->where("order_sn", $order_sn)->save(array('pay_status' => 1, 'pay_time' => time(), 'transaction_id' => $transaction_id));
        accountLog($order['user_id'], $order['account'], 0, '会员在线充值');
    } else {
        // 先查看一下 是不是 合并支付的主订单号
        $order_list = M('order')->where("master_order_sn", $order_sn)->select();
        if ($order_list) {
            foreach ($order_list as $key => $val)
                update_pay_status($val['order_sn'], $transaction_id);
            return;
        }
        // 如果这笔订单已经处理过了
        $count = M('order')->master()->where(['order_sn' => $order_sn, 'pay_status' => 0])->count();   // 看看有没已经处理过这笔订单  支付宝返回不重复处理操作
        if ($count == 0) return false;
        // 找出对应的订单
        $order = M('order')->master()->where("order_sn", $order_sn)->find();
        // 修改支付状态  已支付
        M('order')->where("order_sn", $order_sn)->save(array('pay_status' => 1, 'pay_time' => time(), 'transaction_id' => $transaction_id));
        // 减少对应商品的库存
        minus_stock($order);
        // 给他升级, 根据order表查看消费记录 给他会员等级升级 修改他的折扣 和 总金额
        update_user_level($order['user_id']);
        // 记录订单操作日志
        logOrder($order['order_id'], '订单付款成功', '付款成功', $order['user_id'], 2);
        //分销设置
        M('rebate_log')->where("order_id", $order['order_id'])->save(array('status' => 1));
        // 成为分销商条件
        //$distribut_condition = tpCache('distribut.condition');
        //if($distribut_condition == 1)  // 购买商品付款才可以成为分销商
        //M('users')->where("user_id = {$order['user_id']}")->save(array('is_distribut'=>1));
        // 给商家待结款字段加上
        $order_settlement = order_settlement($order['order_id']);
        M('store')->where("store_id", $order['store_id'])->setInc('pending_money', $order_settlement[0]['store_settlement']); // 店铺 待结算资金 累加
        //虚拟服务类商品支付
        if ($order['order_prom_type'] == 5) {
            make_virtual_code($order);
        }
        // 赠送积分
        order_give($order);// 调用送礼物方法, 给下单这个人赠送相应的礼物
        //用户支付, 发送短信给商家
        $store = M('store')->where("store_id", $order['store_id'])->find();
        if (empty($store['service_phone'])) {
            notifySms($store['service_phone'], 206940);
        }
    }
}

/**
 * 订单确认收货
 * @param $id   订单id
 */
function confirm_order($id, $user_id = 0)
{
    $where['order_id'] = $id;
    if ($user_id) {
        $where['user_id'] = $user_id;
    }
    $order = M('order')->where($where)->find();
    
    if ($order['order_status'] != 1 && $order['order_status'] != 0)
        return array('status' => -1, 'msg' => '该订单不能收货确认');
    
    $data['order_status'] = 2; // 已收货
    $data['pay_status'] = 1; // 已付款
    $data['confirm_time'] = time(); //  收货确认时间
    if ($order['pay_code'] == 'cod') {
        $data['pay_time'] = time();
    }
    $row = M('order')->where(array('order_id' => $id))->save($data);
    if (!$row)
        return array('status' => -3, 'msg' => '操作失败');
    
    //分销设置
    M('rebate_log')->where(['order_id' => $id, 'status' => ['lt', 4]])->save(array('status' => 2, 'confirm' => time()));
    //确认收货 佣金到账
    //推广佣金
    if($order['get_pcommis_uid'] > 0 && $order['promote_commission']>0){
        $re = M('users')->where(array('user_id'=>$order['get_pcommis_uid']))->setInc('promote_commission',$order['promote_commission']);
        //dump($re);
    }
    //销售佣金
    if($order['get_scommis_uid'] > 0 && $order['sales_commission'] > 0){
        //$info = M('users')->where(array('user_id'=>$order['get_scommis_uid']))->find();
        //dump($info);
        $re2 = M('users')->where(array('user_id'=>$order['get_scommis_uid']))->setInc('sales_commission',$order['sales_commission']);
        //dump($re2);
        //$info = M('users')->where(array('user_id'=>$order['get_scommis_uid']))->find();
        //dump($info);
    }
    
    return array('status' => 1, 'msg' => '操作成功');
}

/**
 * 下单赠送活动：优惠券，积分
 * @param $order |订单数组
 */
function order_give($order)
{
    //促销优惠订单商品
    $prom_order_goods = M('order_goods')->where(['order_id' => $order['order_id'], 'prom_type' => 3])->select();
    //获取用户会员等级
    $user_level = Db::name('users')->where(['user_id' => $order['user_id']])->getField('level');
    //查找购买商品送优惠券活动
    foreach ($prom_order_goods as $val) {
        $prom_goods = M('prom_goods')->where(['store_id' => $order['store_id'], 'type' => 3, 'id' => $val['prom_id']])->find();
        if ($prom_goods) {
            //查找优惠券模板
            $goods_coupon = M('coupon')->where("id", $prom_goods['expression'])->find();
            // 用户会员等级是否符合送优惠券活动
            if (array_key_exists($user_level, array_flip(explode(',', $prom_goods['group'])))) {
                //优惠券发放数量验证，0为无限制。发放数量-已领取数量>0
                if ($goods_coupon['createnum'] == 0 ||
                    ($goods_coupon['createnum'] > 0 && ($goods_coupon['createnum'] - $goods_coupon['send_num']) > 0)
                ) {
                    $data = array('cid' => $goods_coupon['id'], 'store_id' => $order['store_id'], 'type' => $goods_coupon['type'], 'uid' => $order['user_id'], 'send_time' => time(), 'over_time' => strtotime("+1 months"));
                    M('coupon_list')->add($data);
                    // 优惠券领取数量加一
                    M('Coupon')->where("id", $goods_coupon['id'])->setInc('send_num');
                }
            }
        }
    }
    
    //查找订单满额促销活动
    // $prom_order_where = [
    //     'store_id' => $order['store_id'],
    //     'type' => ['gt', 1],
    //     'end_time' => ['gt', $order['pay_time']],
    //     'start_time' => ['lt', $order['pay_time']],
    //     'money' => ['elt', $order['order_amount']]
    // ];
    
    //余额全部支付  &&  此为订单优惠送优惠券，上面是商品优惠送优惠券
    if ($order['order_amount'] == 0) {
        $prom_order_where = '(store_id = ' . $order['store_id'] . ' OR store_id = 0) and type > 1 and end_time > ' . time() . ' and start_time < ' . time() . ' and money <= ' . $order['goods_price'];
    } else {
        $prom_order_where = '(store_id = ' . $order['store_id'] . ' OR store_id = 0) and type > 1 and end_time > ' . $order['pay_time'] . ' and start_time < ' . $order['pay_time'] . ' and money <= ' . $order['goods_price'];
    }
    
    $prom_orders = M('prom_order')->where($prom_order_where)->order('money desc')->select();
    $prom_order_count = count($prom_orders);
    // var_dump($prom_orders);exit;
    // 用户会员等级是否符合送优惠券活动
    for ($i = 0; $i < $prom_order_count; $i++) {
        if (array_key_exists($user_level, array_flip(explode(',', $prom_orders[$i]['group'])))) {
            $prom_order = $prom_orders[$i];
            if ($prom_order['type'] == 3) {
                //查找订单送优惠券模板
                $order_coupon = M('coupon')->where("id", $prom_order['expression'])->find();
                if ($order_coupon) {
                    //优惠券发放数量验证，0为无限制。发放数量-已领取数量>0
                    if ($order_coupon['createnum'] == 0 ||
                        ($order_coupon['createnum'] > 0 && ($order_coupon['createnum'] - $order_coupon['send_num']) > 0)
                    ) {
                        $data = array(
                            'cid' => $order_coupon['id'],
                            'type' => $order_coupon['type'],
                            'uid' => $order['user_id'],
                            'send_time' => time(),
                            'over_time' => strtotime("+1 months"),
                            'store_id' => $order['store_id']);
                        M('coupon_list')->add($data);
                        M('Coupon')->where("id", $order_coupon['id'])->setInc('send_num'); // 优惠券领取数量加一
                    }
                }
            }
            //购买商品送积分
            if ($prom_order['type'] == 2) {
                accountLog($order['user_id'], 0, $prom_order['expression'], "订单活动赠送积分");
            }
            break;
        }
    }
    $points = M('order_goods')->where("order_id", $order['order_id'])->sum("give_integral * goods_num");
    $points && accountLog($order['user_id'], 0, $points, "下单赠送积分", 0, $order['order_id'], $order['order_sn']);
}


/**
 * 查看商品是否有活动
 * @param goods_id 商品ID
 */

function get_goods_promotion($goods_id, $user_id = 0)
{
    $now = time();
    $goods = M('goods')->where("goods_id", $goods_id)->find();
    $where = [
        'end_time' => ['gt', $now],
        'start_time' => ['lt', $now],
        'id' => $goods['prom_id'],
    ];
    $prom['price'] = $goods['shop_price'];
    $prom['prom_type'] = $goods['prom_type'];
    $prom['prom_id'] = $goods['prom_id'];
    $prom['status'] = 1;
    
    if ($goods['prom_type'] == 1) {//抢购
        $prominfo = M('flash_sale')->where($where)->find();
        if (!empty($prominfo)) {
            if ($prominfo['goods_num'] == $prominfo['buy_num']) {
                $prom['status'] = 4;//已售馨
                M('flash_sale')->where(array('id' => $prominfo['id']))->save(array('status' => 4));
            } else {
                //核查用户购买数量
                $order_where = [
                    'user_id' => $user_id,
                    'order_status' => ['<>', 3],
                    'add_time' => ['exp', '>' . $prominfo['start_time'] . ' and add_time<' . $prominfo['end_time']]
                ];
                $order_id_arr = M('order')->where($order_where)->getField('order_id', true);
                if ($order_id_arr) {
                    $order_goods_where = [
                        'prom_id' => $goods['prom_id'],
                        'prom_type' => $goods['prom_type'],
                        'order_id' => ['in', implode(',', $order_id_arr)],
                    ];
                    $goods_num = M('order_goods')->where($order_goods_where)->sum('goods_num');
                    if ($goods_num < $prominfo['buy_limit']) {
                        $prom['price'] = $prominfo['price'];
                    }
                } else {
                    $prom['price'] = $prominfo['price'];
                }
            }
        }
    }
    
    if ($goods['prom_type'] == 2) {//团购
        $prominfo = M('group_buy')->where($where)->find();
        if (!empty($prominfo)) {
            $prom['rebate'] = $prominfo['rebate'];//折扣
            $prom['virtual_num'] = $prominfo['virtual_num'];//参团人数
            if ($prominfo['goods_num'] == $prominfo['buy_num']) {
                $prom['status'] = 4;//已售馨
                M('group_buy')->where(array('id' => $prominfo['id']))->save(array('status' => 4));
            } else {
                $prom['price'] = $prominfo['price'];
            }
        }
    }
    
    if ($goods['prom_type'] == 3) {//优惠促销
        $parse_type = array('0' => '直接打折', '1' => '减价优惠', '2' => '固定金额出售', '3' => '买就赠优惠券', '4' => '买M件送N件');
        $prominfo = M('prom_goods')->where($where)->find();
        if (!empty($prominfo)) {
            if ($prominfo['type'] == 0) {
                $prom['price'] = $goods['shop_price'] * $prominfo['expression'] / 100;//打折优惠
            } elseif ($prominfo['type'] == 1) {
                $prom['price'] = $goods['shop_price'] - $prominfo['expression'];//减价优惠
            } elseif ($prominfo['type'] == 2) {
                $prom['price'] = $prominfo['expression'];//固定金额优惠
            }
        }
    }
    
    if (!empty($prominfo)) {
        $prom['start_time'] = $prominfo['start_time'];
        $prom['end_time'] = $prominfo['end_time'];
        $prom['status'] = $prominfo['status'];
    } else {
        $prom['prom_type'] = $prom['prom_id'] = 0;//活动已过期
    }
    if ($prom['prom_id'] == 0) {
        M('goods')->where(array('goods_id' => $goods_id))->save($prom);
    }
    return $prom;
}

/**
 * 查看订单是否满足条件参加活动
 * @param order_amount 订单应付金额
 * @param store_id  店铺id
 */
function get_order_promotion($order_amount, $store_id)
{
//	$parse_type = array('0'=>'满额打折','1'=>'满额优惠金额','2'=>'满额送倍数积分','3'=>'满额送优惠券','4'=>'满额免运费');
    $now = time();
    // $where = array(
    //     'store_id' => array('eq',array($store_id,0),'or'),
    //     'type' => array('lt', 2),
    //     'end_time' => array('gt', $now),
    //     'start_time' => array('lt', $now),
    //     'money' => array('elt', $order_amount)
    // );
    
    $where = '(store_id = 0 OR store_id =' . $store_id . ') and type < 2 and end_time > ' . $now . ' and start_time < ' . $now . ' and money <= ' . $order_amount;
    $prom = M('prom_order')->where($where)->order('money desc')->find();
    $res = array('order_amount' => $order_amount, 'order_prom_id' => 0, 'order_prom_amount' => 0, 'order_prom_type' => 0);
    if ($prom) {
        if ($prom['type'] == 0) {
            $res['order_amount'] = round($order_amount * $prom['expression'] / 100, 2);//满额打折
            $res['order_prom_amount'] = $order_amount - $res['order_amount'];
        } elseif ($prom['type'] == 1) {
            $res['order_amount'] = $order_amount - $prom['expression'];//满额优惠金额
            $res['order_prom_amount'] = $prom['expression'];
        }
        $res['order_prom_id'] = $prom['id'];
        $res['order_prom_type'] = $prom['type'];
        $res['order_prom_title'] = $prom['title'];
    }
    return $res;
}

/**
 * 计算订单金额
 * @param type $user_id 用户id
 * @param type $order_goods 购买的商品
 * @param type $shipping_code 物流code  数组
 * @param type $shipping_price 数组 物流费用, 如果传递了物流费用 就不在计算物流费
 * @param type $province 省份
 * @param type $city 城市
 * @param type $district 县
 * @param type $pay_points 积分   数组
 * @param type $user_money 余额
 * @param type $coupon_id 优惠券  数组
 * @param type $couponCode 优惠码 数组
 */
function calculate_price($user_id = 0, $order_goods, $shipping_code = array(), $shipping_price = array(), $province = 0, $city = 0, $district = 0, $pay_points = 0, $user_money = 0, $coupon_id = array(), $couponCode = array())
{
    $cartLogic = new app\home\logic\CartLogic();
    $user = M('users')->where("user_id", $user_id)->find();// 找出这个用户
    
    if (empty($order_goods))
        return array('status' => -9, 'msg' => '商品列表不能为空', 'result' => '');
    
    $use_percent_point = tpCache('shopping.point_use_percent');     //最大使用限制: 最大使用积分比例, 例如: 为50时, 未50% , 那么积分支付抵扣金额不能超过应付金额的50%
    if ($pay_points > 0 && $use_percent_point == 0) {
        return array('status' => -1, 'msg' => "该笔订单不能使用积分", 'result' => '积分'); // 返回结果状态
    }
    
    // 判断使用积分 余额
    if ($pay_points && ($pay_points > $user['pay_points']))
        return array('status' => -5, 'msg' => "你的账户可用积分为:" . $user['pay_points'], 'result' => ''); // 返回结果状态
    if ($user_money && ($user_money > $user['user_money']))
        return array('status' => -6, 'msg' => "你的账户可用余额为:" . $user['user_money'], 'result' => ''); // 返回结果状态
    
    $goods_id_arr = get_arr_column($order_goods, 'goods_id');
    $goods_arr = M('goods')
        ->where("goods_id", "in", implode(',', $goods_id_arr))
        ->cache(true, TPSHOP_CACHE_TIME)
        ->getField('goods_id,cat_id3,weight,market_price,is_free_shipping,sales_model'); // 商品id 和重量对应的键值对
    $cartLogic = new \app\home\logic\CartLogic();
    // 商品关税
    $total_custom_duty_price = 0.00;
    foreach ($order_goods as $key => $val) {
        // 如果商品不是包邮的
        if ($goods_arr[$val['goods_id']]['is_free_shipping'] == 0) {
            if ($goods_arr[$val['goods_id']]['weight'] == 0) {
                $store_goods_weight[$val['store_id']] += $val['goods_num']; //累积商品重量 每种商品的重量 * 数量
            } else {
                $store_goods_weight[$val['store_id']] += $goods_arr[$val['goods_id']]['weight'] * $val['goods_num']; //累积商品重量 每种商品的重量 * 数量
            }
        }
        $order_goods[$key]['goods_fee'] = $val['goods_num'] * $val['member_goods_price'];    // 小计
        $order_goods[$key]['store_count'] = getGoodNum($val['goods_id'], $val['spec_key']); // 最多可购买的库存数量
        
        if ($order_goods[$key]['store_count'] <= 0)
            return array('status' => -10, 'msg' => $order_goods[$key]['goods_name'] . "库存不足,请重新下单", 'result' => '');
        
        //TODO
        // 此处判断购买量是否大于库存量
        $a += 1;
        $cut_fee += $val['goods_num'] * $val['market_price'] - $val['goods_num'] * $val['member_goods_price']; // 共节约
        $anum += $val['goods_num']; // 购买数量
        $goods_price += $order_goods[$key]['goods_fee']; // 商品总价
        $store_goods_price[$val['store_id']] += $order_goods[$key]['goods_fee']; // 每个商家 的商品总价
        $goods_custom_duty = 0;
        if ($order_goods['sales_model'] == 3) {
            $goods_custom_duty = $cartLogic->calculate_custom_duty($val['goods_num'] * $val['member_goods_price'], $goods_arr[$val['goods_id']]['cat_id3']);
        }
        $total_custom_duty_price += $goods_custom_duty;
        $store_custom_duty_price[$val['store_id']] += $goods_custom_duty;
    }
    // 因为当前方法在没有user_id 的情况下也可以调用, 因此 需要判断用户id
    if ($user_id) {
        // 循环优惠券
        foreach ($coupon_id as $key => $value)
            $store_coupon_price[$key] = $cartLogic->getCouponMoney($user_id, $value, $key, 1); // 下拉框方式选择优惠券
        //循环优惠券码
        foreach ($couponCode as $key => $value) {
            if (empty($value))
                continue;
            $coupon_result = $cartLogic->getCouponMoneyByCode($value, $store_goods_price[$key], $key); // 根据 优惠券 号码获取的优惠券
            if ($coupon_result['status'] < 0)
                return $coupon_result;
            $store_coupon_price[$key] = $coupon_result['result'];
        }
    }
    //平台优惠券金额
    $platform_coupon = $store_coupon_price[0];
    // pingtai
    
    
    // 所有 商家优惠券抵消金额
    if (empty($store_coupon_price)) {
        $coupon_price = 0;
    } else {
        $coupon_price = array_sum($store_coupon_price);
    }
    
    // 计算每个商家的物流费
    foreach ($shipping_code as $key => $value) {
        // 默认免邮费
        $store_shipping_price[$key] = 0;
        // 超出该金额免运费， 店铺 设置 满多少 包邮 .
        $store_free_price = M('store')->where("store_id", $key)->cache(true, 100)->getField('store_free_price');
        
        // 如果没有设置满额包邮 或者 额度达不到包邮 则计算物流费
        if ($store_free_price == 0 || $store_goods_price[$key] < $store_free_price)
            $store_shipping_price[$key] = $cartLogic->cart_freight2($shipping_code[$key], $province, $city, $district, $store_goods_weight[$key], $key);
        
    }
    $shipping_price = array_sum($store_shipping_price); // 所有 商家物流费
    // 计算每个商家的应付金额
    foreach ($store_goods_price as $k => $v) {
        /**原始代码-start*/
        // $store_order_amount[$k] = $v + $store_shipping_price[$k] - $store_coupon_price[$k]; // 应付金额 = 商品价格 + 物流费 - 优惠券
        // $order_prom = get_order_promotion($store_order_amount[$k], $k); // 拿应付金额再去计算商家的订单活动  看看商家有没订单满额优惠活动
        // $store_order_prom_id[$k] = $order_prom['order_prom_id']; // 订单优惠活动id
        // $store_order_prom_amount[$k] = $order_prom['order_prom_amount']; // 订单优惠了多少钱
        // $store_order_amount[$k] = $order_prom['order_amount']; // 订单优惠后是多少钱 得出  应付金额
        /**原始代码-end*/
        $order_prom = get_order_promotion($v, $k); // 拿应付金额再去计算商家的订单活动  看看商家有没订单满额优惠活动
        $store_order_prom_id[$k] = $order_prom['order_prom_id']; // 订单优惠活动id
        $store_order_prom_type[$k] = $order_prom['order_prom_type']; // 订单优惠活动id
        $store_order_prom_amount[$k] = $order_prom['order_prom_amount']; // 订单优惠了多少钱
        $store_order_amount[$k] = $order_prom['order_amount'] + $store_shipping_price[$k] + $store_custom_duty_price[$k] - $store_coupon_price[$k]; // 订单优惠后是多少钱 得出  应付金额
    }
    
    // 应付金额 = 商品价格 + 物流费 + 商品关税 - 优惠券
    $order_amount = $goods_price + $shipping_price + $total_custom_duty_price - $coupon_price;
	if($order_amount < 0)	$order_amount = 0;
	
    // 订单总价 = 商品总价 + 物流总价 + 商品关税
    $total_amount = $goods_price + $shipping_price + $total_custom_duty_price;
    
    // 余额支付原理等同于积分
	if($order_amount > 0){
		 $user_money = ($user_money > $order_amount) ? $order_amount : $user_money;
		$order_amount = $order_amount - $user_money; //  余额支付抵应付金额
	}
   
    
    
    /*判断能否使用积分
     1..积分低于point_min_limit时,不可使用
     2.在不使用积分的情况下, 计算商品应付金额
     3.原则上, 积分支付不能超过商品应付金额的50%, 该值可在平台设置
     @{ */
    $point_rate = tpCache('shopping.point_rate'); //兑换比例: 如果拥有的积分小于该值, 不可使用
    $min_use_limit_point = tpCache('shopping.point_min_limit'); //最低使用额度: 如果拥有的积分小于该值, 不可使用
    
    
    if ($min_use_limit_point > 0 && $pay_points > 0 && $pay_points < $min_use_limit_point) {
        return array('status' => -1, 'msg' => "您使用的积分必须大于{$min_use_limit_point}才可以使用", 'result' => ''); // 返回结果状态
    }
    // 计算该笔订单最多使用多少积分
    $limit = $order_amount * ($use_percent_point / 100) * $point_rate;
    if (($use_percent_point != 100) && $pay_points > $limit) {
        return array('status' => -1, 'msg' => "该笔订单, 您使用的积分不能大于{$limit}", 'result' => '积分'); // 返回结果状态
    }
    // }
    
    // 积分支付 100 积分等于 1块钱
    $integral_money = ($pay_points / tpCache('shopping.point_rate'));
    $integral_money = ($integral_money > $order_amount) ? $order_amount : $integral_money; // 假设应付 1块钱 而用户输入了 200 积分 2块钱, 那么就让 $pay_points = 1块钱 等同于强制让用户输入1块钱
    
    $pay_points = $integral_money * tpCache('shopping.point_rate'); //以防用户使用过多积分的情况
    $order_amount = $order_amount - $integral_money; //  积分抵消应付金额
    
    // 计算每个商家平摊积分余额  和 余额
    $sum_store_order_amount = array_sum($store_order_amount);
    foreach ($store_order_amount as $k => $v) {
        // 当前的应付金额 除以所有商家累加的应付金额,  算出当前应付金额的占比
        $proportion = $v / $sum_store_order_amount;
        if ($pay_points > 0) {
            $store_point_count[$k] = (int)($proportion * $pay_points);
            $store_order_amount[$k] -= $store_point_count[$k] / tpCache('shopping.point_rate'); // 每个商家减去对应积分抵消的余额
        }
        if ($user_money > 0) {
            $store_balance[$k] = round($proportion * $user_money, 2); // 每个商家平摊用了多少余额  保留两位小数
            $store_order_amount[$k] -= $store_balance[$k]; // 每个商家减去余额支付抵消的
        }
        if ($platform_coupon > 0) {
            $platform_balance[$k] = round($proportion * $platform_coupon, 2); // 每个商家平摊用了多少平台优惠券金额  保留两位小数
            ($store_order_amount[$k] - $platform_balance[$k]) >= 0 ? ($store_order_amount[$k] -= $platform_balance[$k]) : ($store_order_amount[$k] = 0); // 平台优惠券金额处理
        }
        $store_order_amount[$k] = round($store_order_amount[$k], 2);
		if($order_amount <= 0){
			 $store_order_amount[$k] = 0;
		}
    }
    // 如果出现除数 除不尽的, 则最后一位加一
    if ($pay_points && array_sum($store_point_count) != $pay_points) {
        $store_point_count[$k] += 1;
        $store_order_amount[$k] -= (1 / tpCache('shopping.point_rate')); // 最后一个积分也算上去
    }
    
    //订单总价  应付金额  物流费  商品总价 节约金额 共多少件商品 积分  余额  优惠券
    $result = array(
        'total_amount' => $total_amount, // 订单总价
        'order_amount' => $order_amount, // 应付金额      只用于订单在没有参与优惠活动的时候价格是对的, 如果某个商家参与优惠活动 价格会有所变动
        'goods_price' => $goods_price, // 商品总价
        'cut_fee' => $cut_fee, // 共节约多少钱
        'anum' => $anum, // 商品总共数量
        'integral_money' => $integral_money,  // 积分抵消金额
        'user_money' => $user_money, // 使用余额
        'coupon_price' => $coupon_price,// 优惠券抵消金额
        'order_goods' => $order_goods, // 商品列表 多加几个字段原样返回
        'shipping_price' => $shipping_price, // 物流费
        'goods_custom_duty_price' => $total_custom_duty_price, // 商品关税
        'store_order_prom_amount' => $store_order_prom_amount,// 订单优惠了多少钱
        'store_order_prom_id' => $store_order_prom_id,// 订单优惠活动id
        'store_order_prom_type' => $store_order_prom_type,// 订单优惠活动类型
        'store_order_amount' => $store_order_amount, // 订单优惠后是多少钱
        'store_shipping_price' => $store_shipping_price, //每个商家的物流费
        'store_custom_duty_price' => $store_custom_duty_price, //每个商家的商品关税
        'store_coupon_price' => $store_coupon_price, //每个商家的优惠券金额
        'store_goods_price' => $store_goods_price,//  每个店铺的商品总价
        'store_point_count' => $store_point_count, // 每个商家平摊使用了多少积分
        'store_balance' => $store_balance, // 每个商家平摊用了多少余额
        'platform_balance' => $platform_balance //每个商家平摊用了多少平台优惠券金额
    );
    return array('status' => 1, 'msg' => "计算价钱成功", 'result' => $result); // 返回结果状态
}

/**
 * 计算订单金额
 * @param type $user_id 用户id
 * @param type $order_goods 购买的商品
 * @param type $shipping_code 物流code  数组
 * @param type $shipping_price 数组 物流费用, 如果传递了物流费用 就不在计算物流费
 * @param type $province 省份
 * @param type $city 城市
 * @param type $district 县
 * @param type $pay_points 积分   数组
 * @param type $user_money 余额
 * @param type $coupon_id 优惠券  数组
 * @param type $couponCode 优惠码 数组
 */
function calculate_price2($user_id = 0, $order_goods, $shipping_code = array(), $shipping_price = array(), $province = 0, $city = 0, $district = 0, $pay_points = 0, $user_money = 0, $coupon_id = array(), $couponCode = array(), $order)
{
    $cartLogic = new app\home\logic\CartLogic();
    $user = M('users')->where("user_id", $user_id)->find();// 找出这个用户
    
    if (empty($order_goods))
        return array('status' => -9, 'msg' => '商品列表不能为空', 'result' => '');
    
    $use_percent_point = tpCache('shopping.point_use_percent');     //最大使用限制: 最大使用积分比例, 例如: 为50时, 未50% , 那么积分支付抵扣金额不能超过应付金额的50%
    if ($pay_points > 0 && $use_percent_point == 0) {
        return array('status' => -1, 'msg' => "该笔订单不能使用积分", 'result' => '积分'); // 返回结果状态
    }
    
    // 判断使用积分 余额
    if ($pay_points && ($pay_points > $user['pay_points']))
        return array('status' => -5, 'msg' => "你的账户可用积分为:" . $user['pay_points'], 'result' => ''); // 返回结果状态
    if ($user_money && ($user_money > $user['user_money']))
        return array('status' => -6, 'msg' => "你的账户可用余额为:" . $user['user_money'], 'result' => ''); // 返回结果状态
    
    $goods_id_arr = get_arr_column($order_goods, 'goods_id');
    $goods_arr = M('goods')
        ->where("goods_id", "in", implode(',', $goods_id_arr))
        ->cache(true, TPSHOP_CACHE_TIME)
        ->getField('goods_id,cat_id3,weight,market_price,is_free_shipping,sales_model'); // 商品id 和重量对应的键值对
    $cartLogic = new \app\home\logic\CartLogic();
    // 商品关税
    $total_custom_duty_price = 0.00;
    foreach ($order_goods as $key => $val) {
        // 如果商品不是包邮的
        if ($goods_arr[$val['goods_id']]['is_free_shipping'] == 0) {
            if ($goods_arr[$val['goods_id']]['weight'] == 0) {
                $store_goods_weight[$val['store_id']] += $val['goods_num']; //累积商品重量 每种商品的重量 * 数量
            } else {
                $store_goods_weight[$val['store_id']] += $goods_arr[$val['goods_id']]['weight'] * $val['goods_num']; //累积商品重量 每种商品的重量 * 数量
            }
        }
        $order_goods[$key]['goods_fee'] = $val['goods_num'] * $val['member_goods_price'];    // 小计
        $order_goods[$key]['store_count'] = getGoodNum($val['goods_id'], $val['spec_key']); // 最多可购买的库存数量
        
        if ($order_goods[$key]['store_count'] <= 0)
            return array('status' => -10, 'msg' => $order_goods[$key]['goods_name'] . "库存不足,请重新下单", 'result' => '');
        
        //TODO
        // 此处判断购买量是否大于库存量
        $a += 1;
        $cut_fee += $val['goods_num'] * $val['market_price'] - $val['goods_num'] * $val['member_goods_price']; // 共节约
        $anum += $val['goods_num']; // 购买数量
        $goods_price += $order_goods[$key]['goods_fee']; // 商品总价
        $store_goods_price[$val['store_id']] += $order_goods[$key]['goods_fee']; // 每个商家 的商品总价
        $goods_custom_duty = 0;
        if ($order_goods['sales_model'] == 3) {
            $goods_custom_duty = $cartLogic->calculate_custom_duty($val['goods_num'] * $val['member_goods_price'], $goods_arr[$val['goods_id']]['cat_id3']);
        }
        $total_custom_duty_price += $goods_custom_duty;
        $store_custom_duty_price[$val['store_id']] += $goods_custom_duty;
    }
    // 因为当前方法在没有user_id 的情况下也可以调用, 因此 需要判断用户id
    if ($user_id) {
        // 循环优惠券
        foreach ($coupon_id as $key => $value)
            $store_coupon_price[$key] = $cartLogic->getCouponMoney($user_id, $value, $key, 1); // 下拉框方式选择优惠券
        //循环优惠券码
        foreach ($couponCode as $key => $value) {
            if (empty($value))
                continue;
            $coupon_result = $cartLogic->getCouponMoneyByCode($value, $store_goods_price[$key], $key); // 根据 优惠券 号码获取的优惠券
            if ($coupon_result['status'] < 0)
                return $coupon_result;
            $store_coupon_price[$key] = $coupon_result['result'];
        }
    }
    //平台优惠券金额
    $platform_coupon = $store_coupon_price[0];
    // pingtai
    
    
    // 所有 商家优惠券抵消金额
    if (empty($store_coupon_price)) {
        $coupon_price = 0;
    } else {
        $coupon_price = array_sum($store_coupon_price);
    }
    
    // 计算每个商家的物流费
    foreach ($shipping_code as $key => $value) {
        // 默认免邮费
        $store_shipping_price[$key] = 0;
        // 超出该金额免运费， 店铺 设置 满多少 包邮 .
        $store_free_price = M('store')->where("store_id", $key)->cache(true, 100)->getField('store_free_price');
        
        // 如果没有设置满额包邮 或者 额度达不到包邮 则计算物流费
        if ($store_free_price == 0 || $store_goods_price[$key] < $store_free_price)
            $store_shipping_price[$key] = $cartLogic->cart_freight2($shipping_code[$key], $province, $city, $district, $store_goods_weight[$key], $key);
        
    }
    $shipping_price = array_sum($store_shipping_price); // 所有 商家物流费
    // 计算每个商家的应付金额
    foreach ($store_goods_price as $k => $v) {
        /**原始代码-start*/
        // $store_order_amount[$k] = $v + $store_shipping_price[$k] - $store_coupon_price[$k]; // 应付金额 = 商品价格 + 物流费 - 优惠券
        // $order_prom = get_order_promotion($store_order_amount[$k], $k); // 拿应付金额再去计算商家的订单活动  看看商家有没订单满额优惠活动
        // $store_order_prom_id[$k] = $order_prom['order_prom_id']; // 订单优惠活动id
        // $store_order_prom_amount[$k] = $order_prom['order_prom_amount']; // 订单优惠了多少钱
        // $store_order_amount[$k] = $order_prom['order_amount']; // 订单优惠后是多少钱 得出  应付金额
        /**原始代码-end*/
        $order_prom = get_order_promotion($v, $k); // 拿应付金额再去计算商家的订单活动  看看商家有没订单满额优惠活动
        $store_order_prom_id[$k] = $order_prom['order_prom_id']; // 订单优惠活动id
        $store_order_prom_type[$k] = $order_prom['order_prom_type']; // 订单优惠活动id
        $store_order_prom_amount[$k] = $order_prom['order_prom_amount']; // 订单优惠了多少钱
        $store_order_amount[$k] = $order_prom['order_amount'] + $store_shipping_price[$k] + $store_custom_duty_price[$k] - $store_coupon_price[$k]; // 订单优惠后是多少钱 得出  应付金额
    }
    
    // 应付金额 = 商品价格 + 物流费 + 商品关税 - 优惠券
    $order_amount = $goods_price + $shipping_price + $total_custom_duty_price - $coupon_price;
    // 订单总价 = 商品总价 + 物流总价 + 商品关税
    $total_amount = $goods_price + $shipping_price + $total_custom_duty_price;
    
    // 余额支付原理等同于积分
    $user_money = ($user_money > $order_amount) ? $order_amount : $user_money;
    $order_amount = $order_amount - $user_money; //  余额支付抵应付金额
    
    
    /*判断能否使用积分
     1..积分低于point_min_limit时,不可使用
     2.在不使用积分的情况下, 计算商品应付金额
     3.原则上, 积分支付不能超过商品应付金额的50%, 该值可在平台设置
     @{ */
    $point_rate = tpCache('shopping.point_rate'); //兑换比例: 如果拥有的积分小于该值, 不可使用
    $min_use_limit_point = tpCache('shopping.point_min_limit'); //最低使用额度: 如果拥有的积分小于该值, 不可使用
    
    
    if ($min_use_limit_point > 0 && $pay_points > 0 && $pay_points < $min_use_limit_point) {
        return array('status' => -1, 'msg' => "您使用的积分必须大于{$min_use_limit_point}才可以使用", 'result' => ''); // 返回结果状态
    }
    // 计算该笔订单最多使用多少积分
    $limit = $order_amount * ($use_percent_point / 100) * $point_rate;
    if (($use_percent_point != 100) && $pay_points > $limit) {
        return array('status' => -1, 'msg' => "该笔订单, 您使用的积分不能大于{$limit}", 'result' => '积分'); // 返回结果状态
    }
    // }
    
    // 积分支付 100 积分等于 1块钱
    $integral_money = ($pay_points / tpCache('shopping.point_rate'));
    $integral_money = ($integral_money > $order_amount) ? $order_amount : $integral_money; // 假设应付 1块钱 而用户输入了 200 积分 2块钱, 那么就让 $pay_points = 1块钱 等同于强制让用户输入1块钱
    
    $pay_points = $integral_money * tpCache('shopping.point_rate'); //以防用户使用过多积分的情况
    $order_amount = $order_amount - $integral_money; //  积分抵消应付金额
    
    // 计算每个商家平摊积分余额  和 余额
    $sum_store_order_amount = array_sum($store_order_amount);
    foreach ($store_order_amount as $k => $v) {
        // 当前的应付金额 除以所有商家累加的应付金额,  算出当前应付金额的占比
        $proportion = $v / $sum_store_order_amount;
        if ($pay_points > 0) {
            $store_point_count[$k] = (int)($proportion * $pay_points);
            $store_order_amount[$k] -= $store_point_count[$k] / tpCache('shopping.point_rate'); // 每个商家减去对应积分抵消的余额
        }
        if ($user_money > 0) {
            $store_balance[$k] = round($proportion * $user_money, 2); // 每个商家平摊用了多少余额  保留两位小数
            $store_order_amount[$k] -= $store_balance[$k]; // 每个商家减去余额支付抵消的
        }
        if ($platform_coupon > 0) {
            $platform_balance[$k] = round($proportion * $platform_coupon, 2); // 每个商家平摊用了多少平台优惠券金额  保留两位小数
            ($store_order_amount[$k] - $platform_balance[$k]) >= 0 ? ($store_order_amount[$k] -= $platform_balance[$k]) : 0; // 每个商家减去余额支付抵消的
        }
        $store_order_amount[$k] = round($store_order_amount[$k], 2);
    }
    // 如果出现除数 除不尽的, 则最后一位加一
    if ($pay_points && array_sum($store_point_count) != $pay_points) {
        $store_point_count[$k] += 1;
        $store_order_amount[$k] -= (1 / tpCache('shopping.point_rate')); // 最后一个积分也算上去
    }
    
    //订单总价  应付金额  物流费  商品总价 节约金额 共多少件商品 积分  余额  优惠券
    $result = array(
        'total_amount' => $total_amount, // 订单总价
        'order_amount' => $order_amount, // 应付金额      只用于订单在没有参与优惠活动的时候价格是对的, 如果某个商家参与优惠活动 价格会有所变动
        'goods_price' => $goods_price, // 商品总价
        'cut_fee' => $cut_fee, // 共节约多少钱
        'anum' => $anum, // 商品总共数量
        'integral_money' => $integral_money,  // 积分抵消金额
        'user_money' => $user_money, // 使用余额
        'coupon_price' => $coupon_price,// 优惠券抵消金额
        'order_goods' => $order_goods, // 商品列表 多加几个字段原样返回
        'shipping_price' => $shipping_price, // 物流费
        'goods_custom_duty_price' => $total_custom_duty_price, // 商品关税
        'store_order_prom_amount' => $store_order_prom_amount,// 订单优惠了多少钱
        'store_order_prom_id' => $store_order_prom_id,// 订单优惠活动id
        'store_order_prom_type' => $store_order_prom_type,// 订单优惠活动类型
        'store_order_amount' => $store_order_amount, // 订单优惠后是多少钱
        'store_shipping_price' => $store_shipping_price, //每个商家的物流费
        'store_custom_duty_price' => $store_custom_duty_price, //每个商家的商品关税
        'store_coupon_price' => $store_coupon_price, //每个商家的优惠券金额
        'store_goods_price' => $store_goods_price,//  每个店铺的商品总价
        'store_point_count' => $store_point_count, // 每个商家平摊使用了多少积分
        'store_balance' => $store_balance, // 每个商家平摊用了多少余额
        'platform_balance' => $platform_balance //每个商家平摊用了多少平台优惠券金额
    );
    return array('status' => 1, 'msg' => "计算价钱成功", 'result' => $result); // 返回结果状态
}

/**
 * 订单结算
 * author:当燃
 * date:2016-08-28
 * @param $order_id  订单order_id
 * @param $rec_id 需要退款商品rec_id
 */

function order_settlement($order_id, $rec_id = 0)
{
    $point_rate = tpCache('shopping.point_rate');
    $point_rate = 1 / $point_rate; //积分换算比例
    
    $order = M('order')->where(array('order_id' => $order_id))->find();//订单详情
    if ($order && $order['pay_status'] == 1) {
        $order['store_settlement'] = $order['shipping_price'];//商家待结算初始金额
        $order_goods = M('order_goods')->where(array('order_id' => $order_id))->select();//订单商品列表
        $refund = $prom_and_coupon = $order['settlement'] = $order['store_settlement'] = $refund_integral = 0;
        /* 商家订单商品结算公式(独立商家一笔订单计算公式)
        *  均摊比例 = 这个商品总价/订单商品总价
        *  均摊优惠金额  = 均摊比例 *(代金券抵扣金额 + 优惠活动优惠金额)
        *  商品实际售卖金额  =  商品总价 - 购买此商品赠送积分 - 此商品分销分成 - 均摊优惠金额
        *  商品结算金额  = 商品实际售卖金额 - 商品实际售卖金额*此类商品平台抽成比例
        *  订单实际支付金额  =  订单商品总价 - 代金券抵扣金额 - 优惠活动优惠金额(跟用户使用积分抵扣，使用余额支付无关,积分在商家赠送时平台已经扣取)
        *
        *  整个订单商家结算所得金额  = 所有商品结算金额之和 + 物流费用(商家发货，物流费直接给商家)
        *  平台所得提成  = 所有商品提成之和
        *  商品退款说明 ：如果使用了积分，那么积分按商品均摊退回给用户，但使用优惠券抵扣和优惠活动优惠的金额此商品均摊的就不退了
        *  积分说明：积分在商家赠送时，直接从订单结算金中扣取该笔赠送积分可抵扣的金额
        *  优惠券赠送使用说明 ：优惠券在使用的时直接抵扣商家订单金额,无需跟平台结算，全场通用劵只有平台可以发放，所以由平台自付
        *  交易费率：例如支付宝，微信都会征收交易的千分之六手续费
        */
        foreach ($order_goods as $k => $val) {
            $settlement = $goods_amount = $val['member_goods_price'] * $val['goods_num']; //此商品该结算金额初始值
            
            $settlement_rate = round($goods_amount / $order['goods_price'], 4);//此商品占订单商品总价比例
            
            if ($val['give_integral'] > 0) {
                $settlement = $settlement - $val['goods_num'] * $val['give_integral'] * $point_rate;//减去购买该商品赠送积分
            }
            
            // if ($val['distribut'] > 0) {
            //     $settlement = $settlement - $val['distribut'] * $val['goods_num'];//减去分销分成金额
            // }
            
            if ($order['order_prom_amount'] > 0 || $order['coupon_price'] > 0) {
                $prom_and_coupon = $settlement_rate * ($order['order_prom_amount'] + $order['coupon_price']);//均摊优惠金额  = 此商品总价/订单商品总价*优惠总额
                $settlement = $settlement - $prom_and_coupon;//减去优惠券抵扣金额和优惠折扣
            }
            
            $order_goods[$k]['goods_settlement'] = round($settlement, 2) - round($settlement * $val['commission'] / 100, 2);//每件商品该结算金额
            
            $order_goods[$k]['settlement'] = round($settlement, 2) - $order_goods[$k]['goods_settlement'];//平台抽成所得
            
            if ($val['rec_id'] == $rec_id || $val['is_send'] == 3) {
                $val['refund_integral'] = intval($order['integral'] * $settlement_rate);//使用积分抵扣金额均摊  == 此商品需要退还用户积分
                $val['refund_settlement'] = $goods_amount - $prom_and_coupon - $val['refund_integral'] * $point_rate;//此商品实际需要退款金额
                if ($val['give_integral'] > 0) {
                    $user_integral = M('users')->where(array('user_id' => $order['user_id']))->getField('pay_points');//用户积分余额
                    if ($user_integral < $val['give_integral'] * $val['goods_num']) {
                        $val['refund_settlement'] = $val['refund_settlement'] - $val['give_integral'] * $val['goods_num'] * $point_rate;//如果赠送积分被使用，那么从退款中扣除积分金额
                        $val['give_integral'] = 0; //赠送积分已经从退款中扣除
                    } else {
                        $val['give_integral'] = $val['give_integral'] * $val['goods_num'];//需要追回的赠送积分
                    }
                }
                $refund += $val['refund_settlement']; //已经退款商品金额
                $refund_integral += $val['refund_integral'];//累计退还积分
                if ($rec_id > 0) {
                    return $val; //直接返回需要退款的商品退款信息
                }
            } else {
                $order['store_settlement'] += $order_goods[$k]['goods_settlement']; //订单所有商品结算所得金额之和
                $order['settlement'] += $order_goods[$k]['settlement'];//平台抽成之和
                $order['give_integral'] += $val['give_integral'] * $val['goods_num'];//订单赠送积分
                $order['distribut'] += $val['distribut'] * $val['goods_num'];//订单分销分成
                $order['integral'] = $order['integral'] - $refund_integral;//订单使用积分
                $order['goods_amount'] += $goods_amount;//订单商品总价
            }
        }
        $order['store_settlement'] += $order['shipping_price'] + $order['platform_coupon_price'] - $order['discount'];//整个订单商家结算所得金额【平台发出的优惠券需要把商家平摊的金额返回给商家】
        //$order['store_settlement'] = round($order['store_settlement']*(1-0.006),2);//支付手续费
    }
    
    return array($order, $order_goods);
}

function order_settlementTest($order_id, $rec_id = 0)
{
    $point_rate = tpCache('shopping.point_rate');
    $point_rate = 1 / $point_rate; //积分换算比例
    
    $order = M('order')->where(array('order_id' => $order_id))->find();//订单详情
    if ($order && $order['pay_status'] == 1) {
        $order['store_settlement'] = $order['shipping_price'];//商家待结算初始金额
        $order_goods = M('order_goods')->where(array('order_id' => $order_id))->select();//订单商品列表
        $refund = $prom_and_coupon = $order['settlement'] = $order['store_settlement'] = $refund_integral = 0;
        /* 商家订单商品结算公式(独立商家一笔订单计算公式)
        *  均摊比例 = 这个商品总价/订单商品总价
        *  均摊优惠金额  = 均摊比例 *(代金券抵扣金额 + 优惠活动优惠金额)
        *  商品实际售卖金额  =  商品总价 - 购买此商品赠送积分 - 此商品分销分成 - 均摊优惠金额
        *  商品结算金额  = 商品实际售卖金额 - 商品实际售卖金额*此类商品平台抽成比例
        *  订单实际支付金额  =  订单商品总价 - 代金券抵扣金额 - 优惠活动优惠金额(跟用户使用积分抵扣，使用余额支付无关,积分在商家赠送时平台已经扣取)
        *
        *  整个订单商家结算所得金额  = 所有商品结算金额之和 + 物流费用(商家发货，物流费直接给商家)
        *  平台所得提成  = 所有商品提成之和
        *  商品退款说明 ：如果使用了积分，那么积分按商品均摊退回给用户，但使用优惠券抵扣和优惠活动优惠的金额此商品均摊的就不退了
        *  积分说明：积分在商家赠送时，直接从订单结算金中扣取该笔赠送积分可抵扣的金额
        *  优惠券赠送使用说明 ：优惠券在使用的时直接抵扣商家订单金额,无需跟平台结算，全场通用劵只有平台可以发放，所以由平台自付
        *  交易费率：例如支付宝，微信都会征收交易的千分之六手续费
        */
        foreach ($order_goods as $k => $val) {
            $settlement = $goods_amount = $val['member_goods_price'] * $val['goods_num']; //此商品该结算金额初始值
            
            $settlement_rate = round($goods_amount / $order['goods_price'], 4);//此商品占订单商品总价比例
            
            if ($val['give_integral'] > 0) {
                $settlement = $settlement - $val['goods_num'] * $val['give_integral'] * $point_rate;//减去购买该商品赠送积分
            }
            
            if ($val['distribut'] > 0) {
                $settlement = $settlement - $val['distribut'] * $val['goods_num'];//减去分销分成金额
            }
            
            if ($order['order_prom_amount'] > 0 || $order['coupon_price'] > 0) {
                $prom_and_coupon = $settlement_rate * ($order['order_prom_amount'] + $order['coupon_price']);//均摊优惠金额  = 此商品总价/订单商品总价*优惠总额
                $settlement = $settlement - $prom_and_coupon;//减去优惠券抵扣金额和优惠折扣
            }
            
            $order_goods[$k]['goods_settlement'] = round($settlement, 2) - round($settlement * $val['commission'] / 100, 2);//每件商品该结算金额
            
            $order_goods[$k]['settlement'] = round($settlement, 2) - $order_goods[$k]['goods_settlement'];//平台抽成所得
            
            if ($val['rec_id'] == $rec_id || $val['is_send'] == 3) {
                $val['refund_integral'] = intval($order['integral'] * $settlement_rate);//使用积分抵扣金额均摊  == 此商品需要退还用户积分
                $val['refund_settlement'] = $goods_amount - $prom_and_coupon - $val['refund_integral'] * $point_rate;//此商品实际需要退款金额
                if ($val['give_integral'] > 0) {
                    $user_integral = M('users')->where(array('user_id' => $order['user_id']))->getField('pay_points');//用户积分余额
                    if ($user_integral < $val['give_integral'] * $val['goods_num']) {
                        $val['refund_settlement'] = $val['refund_settlement'] - $val['give_integral'] * $val['goods_num'] * $point_rate;//如果赠送积分被使用，那么从退款中扣除积分金额
                        $val['give_integral'] = 0; //赠送积分已经从退款中扣除
                    } else {
                        $val['give_integral'] = $val['give_integral'] * $val['goods_num'];//需要追回的赠送积分
                    }
                }
                $refund += $val['refund_settlement']; //已经退款商品金额
                $refund_integral += $val['refund_integral'];//累计退还积分
                if ($rec_id > 0) {
                    return $val; //直接返回需要退款的商品退款信息
                }
            } else {
                $order['store_settlement'] += $order_goods[$k]['goods_settlement']; //订单所有商品结算所得金额之和
                $order['settlement'] += $order_goods[$k]['settlement'];//平台抽成之和
                $order['give_integral'] += $val['give_integral'] * $val['goods_num'];//订单赠送积分
                $order['distribut'] += $val['distribut'] * $val['goods_num'];//订单分销分成
                $order['integral'] = $order['integral'] - $refund_integral;//订单使用积分
                $order['goods_amount'] += $goods_amount;//订单商品总价
            }
        }
        
        //整个订单商家结算所得金额【平台发出的优惠券需要把商家平摊的金额返回给商家】
        $order['store_settlement'] += $order['shipping_price'] + $order['platform_coupon_price'] - $order['discount'];
        //$order['store_settlement'] = round($order['store_settlement']*(1-0.006),2);//支付手续费
    }
    return array($order, $order_goods);
}

/**
 * 生成兑换码
 * 长度 =3位 + 4位 + 2位 + 3位  + 1位 + 5位随机  = 18位
 * @param string $perfix 前缀
 * @param int $store_id
 * @param int $member_id
 * @param unknown $num
 * @return multitype:string
 */
function make_virtual_code($order)
{
    $order_goods = M('order_goods')->where(array('order_id' => $order['order_id']))->find();
    $goods = M('goods')->where(array('goods_id' => $order_goods['goods_id']))->find();
    M('order')->where(array('order_id' => $order['order_id']))->save(array('order_status' => 2, 'shipping_time' => time()));
    $perfix = mt_rand(100, 999);
    $perfix .= sprintf('%04d', (int)$goods['store_id'] * $order['user_id'] % 10000)
        . sprintf('%02d', (int)$order['user_id'] % 100) . sprintf('%03d', (float)microtime() * 1000);
    
    for ($i = 0; $i < $order_goods['goods_num']; $i++) {
        $order_code[$i]['order_id'] = $order['order_id'];
        $order_code[$i]['store_id'] = $goods['store_id'];
        $order_code[$i]['user_id'] = $order['user_id'];
        $order_code[$i]['vr_code'] = $perfix . sprintf('%02d', (int)$i % 100) . rand(5, 1);
        $order_code[$i]['pay_price'] = $goods['shop_price'];
        $order_code[$i]['vr_indate'] = $goods['virtual_indate'];
        $order_code[$i]['vr_invalid_refund'] = $goods['virtual_refund'];
    }
    return M('vr_order_code')->insertAll($order_code);
}

/**
 * 获取商品一二三级分类
 * @return type
 */
function get_goods_category_tree()
{
    $tree = $arr = $brr = $crr = $hrr = $result = array();
    $cat_list = M('goods_category')->where("is_show", 1)->where("is_nav", 1)->order('sort_order')->cache(false)->select();//所有分类
    if ($cat_list) {
        foreach ($cat_list as $val) {
            if ($val['level'] == 2) {
                $arr[$val['parent_id']][] = $val;
                if ($val['is_hot'] == 1) {
                    $hrr[$val['parent_id']][] = $val;
                }
            }
            if ($val['level'] == 3) {
                $crr[$val['parent_id']][] = $val;
                $path = explode('_', $val['parent_id_path']);
                if ($val['is_hot'] == 0 && count($brr[$path[1]]) < 12) {
                    $brr[$path[1]][] = $val;//楼层左下方三级分类
                } else if (count($hrr[$path[1]]) < 6) {
                    $hrr[$path[1]][] = $val;//导航栏右边推荐分类
                }
            }
            
            if ($val['level'] == 1) {
                $tree[] = $val;
            }
        }
        
        foreach ($arr as $k => $v) {
            foreach ($v as $kk => $vv) {
                $arr[$k][$kk]['sub_menu'] = empty($crr[$vv['id']]) ? array() : $crr[$vv['id']];
            }
        }
        
        foreach ($tree as $val) {
            $val['hmenu'] = empty($hrr[$val['id']]) ? array() : $hrr[$val['id']];
            $val['smenu'] = empty($brr[$val['id']]) ? array() : $brr[$val['id']];
            $val['tmenu'] = empty($arr[$val['id']]) ? array() : $arr[$val['id']];
            $result[$val['id']] = $val;
        }
    }
    return $result;
}

/**
 * 写入静态页面缓存
 */
function write_html_cache($html)
{
    $html_cache_arr = C('HTML_CACHE_ARR');
    $request = think\Request::instance();
    $m_c_a_str = $request->module() . '_' . $request->controller() . '_' . $request->action(); // 模块_控制器_方法
    $m_c_a_str = strtolower($m_c_a_str);
    //exit('write_html_cache写入缓存<br/>');
    foreach ($html_cache_arr as $key => $val) {
        $val['mca'] = strtolower($val['mca']);
        if ($val['mca'] != $m_c_a_str) //不是当前 模块 控制器 方法 直接跳过
            continue;
        
        if (!is_dir(RUNTIME_PATH . 'html'))
            mkdir(RUNTIME_PATH . 'html');
        $filename = RUNTIME_PATH . 'html' . DIRECTORY_SEPARATOR . $m_c_a_str;
        // 组合参数  
        if (isset($val['p'])) {
            foreach ($val['p'] as $k => $v)
                $filename .= '_' . $_GET[$v];
        }
        $filename .= '.html';
        file_put_contents($filename, $html);
    }
}

/**
 * 读取静态页面缓存
 */
function read_html_cache()
{
    $html_cache_arr = C('HTML_CACHE_ARR');
    $request = think\Request::instance();
    $m_c_a_str = $request->module() . '_' . $request->controller() . '_' . $request->action(); // 模块_控制器_方法
    $m_c_a_str = strtolower($m_c_a_str);
    //exit('read_html_cache读取缓存<br/>');
    foreach ($html_cache_arr as $key => $val) {
        $val['mca'] = strtolower($val['mca']);
        if ($val['mca'] != $m_c_a_str) //不是当前 模块 控制器 方法 直接跳过
            continue;
        
        $filename = RUNTIME_PATH . 'html' . DIRECTORY_SEPARATOR . $m_c_a_str;
        // 组合参数        
        if (isset($val['p'])) {
            foreach ($val['p'] as $k => $v)
                $filename .= '_' . $_GET[$v];
        }
        $filename .= '.html';
        if (file_exists($filename)) {
            echo file_get_contents($filename);
            exit();
        }
    }
}

/**
 * 清空系统缓存
 */
function cleanCache()
{
    delFile(RUNTIME_PATH);
}


/**
 * URL参数拼接
 * @param string $k
 * @param string $v
 * @return mixed
 */
function geturlagr($k='',$v=''){
    $get = I('get.');
    foreach ($get as $key => $value) {
        $condition[$key] = $value;
    }
    unset($condition['p']);
    if (!$get || $k) $condition[$k] = $v;
    return $condition;
}

/**
 * 查询是否已认领该商品
 * @param $user_id 用户ID
 * @param $goods_id 商品ID
 * @return mixed
 */
function get_claim_record($user_id,$goods_id){
    $cruser_id = M('claim_record')->where(" user_id = {$user_id} and goods_id = {$goods_id}")->getField("user_id", '');
    return $cruser_id;
}

/**
 * 获取会员昵称
 * @param $user_id 用户ID
 * @return string
 */
function get_user_name($user_id){
    $data = '';
    if($user_id)$data = M('users')->where(" user_id = {$user_id}")->getField("mobile", '');
    return $data;
}
 
