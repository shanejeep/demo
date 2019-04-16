<?php

/* *
 * 功能：支付接口(alipay.trade.wap.pay)接口业务参数封装
 * 版本：2.0
 * 修改日期：2016-11-01
 * 说明：
 * 以下代码只是为了方便商户测试而提供的样例代码，商户可以根据自己网站的需要，按照技术文档编写,并非一定要使用该代码。
 */

class TradeUnifiedorderBuilder
{
    private $params = array();
    /*//网关地址
    private $gateway_url = "https://api.mch.weixin.qq.com/pay/unifiedorder";*/
    //应用ID
    private $appid;
    //商户号
    private $mch_id;
/*    //商户支付密钥
    private $api_key;*/
    //回调地址
    private $notify_url;
    //设备号
    private $device_info;
    //随机字符串
    private $nonce_str;
    //签名
    private $sign;
    //签名类型
    private $sign_type;
    //商品描述
    private $body;
    //商品描述
    private $detail;
    //附加数据
    private $attach;
    //商户订单号
    private $out_trade_no;
    //货币类型
    private $fee_type = 'CNY';
    //总金额(分)
    private $total_fee;
    //终端IP
    private $spbill_create_ip;
    //交易起始时间
    private $time_start;
    //交易结束时间
    private $time_expire;
    //订单优惠标记
    private $goods_tag;
    //交易类型
    private $trade_type = 'APP';
    //指定支付方式
    private $limit_pay;
    
    public function __construct($config)
    {
        $this->params['appid'] = $this->appid = $config['appid'];
        $this->params['mch_id'] = $this->mch_id = $config['mch_id'];
        $this->params['notify_url'] = $this->notify_url = $config['notify_url'];
        if (empty($this->appid) || trim($this->appid) == "") {
            throw new Exception("appid should not be NULL!");
        }
        if (empty($this->mch_id) || trim($this->mch_id) == "") {
            throw new Exception("mch_id should not be NULL!");
        }
        if (empty($this->notify_url) || trim($this->notify_url) == "") {
            throw new Exception("notify_url should not be NULL!");
        }
    }
    
    public function __get($name)
    {
        if (isset($this->$name)) {
            return ($this->$name);
        } else {
            return (NULL);
        }
    }
    
    public function __set($name, $value)
    {
        $this->params[$name] = $value;
        $this->$name = $value;
    }
    
    public function __isset($name)
    {
        return isset($this->$name);
    }
}

?>