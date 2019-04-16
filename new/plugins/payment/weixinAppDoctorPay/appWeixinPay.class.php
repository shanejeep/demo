<?php
use think\Model;

require_once("function.php");

/**
 * 微信app支付
 *
 * @author spjiang
 * Class appWeixinPay
 */
class appWeixinPay extends Model
{
    public $tableName = 'plugin'; // 插件表
    // 支付配置参数
    public $config = array(
        'body' => '医之佳商城订单', //医之佳商城订单
        'trade_type' => 'APP',
        'gateway_url' => 'https://api.mch.weixin.qq.com/pay/unifiedorder',
    );
    
    /**
     * 析构流函数
     */
    public function __construct()
    {
        $paymentPlugin = M('Plugin')->where("code='weixinAppDoctorPay' and  type = 'payment' ")->find(); // 找到支付插件的配置
        $config_value = unserialize($paymentPlugin['config_value']); // 配置反序列化
        $this->config['appid'] = $config_value['appid'];
        $this->config['mch_id'] = $config_value['mch_id'];
        $this->config['api_key'] = $config_value['api_key'];
        $this->config['notify_url'] = $config_value['notify_url'];
        isset($config_value['gateway_url']) ? $this->config['gateway_url'] = $config_value['gateway_url'] : false;
        isset($config_value['body']) ? $this->config['body'] = $config_value['body'] : false;
    }
    
    /**
     * 生成支付代码
     *
     * @param array $order 订单信息
     * @return 提交表单HTML文本
     */
    function get_code($order)
    {
        if (!$order) return false;
        require_once("lib/TradeService.php");
        require_once("builder/TradeUnifiedorderBuilder.php");
        header("Content-type: text/html; charset=utf-8");
        $TradeService = new TradeService($this->config);
        $TradeUnifiedorderBuilder = new TradeUnifiedorderBuilder($this->config);
        $TradeUnifiedorderBuilder->body = $this->config['body'];
        // 商户订单号，商户网站订单系统中唯一订单号，必填
        $TradeUnifiedorderBuilder->out_trade_no = $order['order_sn'];
        // 付款金额，必填
        $TradeUnifiedorderBuilder->total_fee = $order['order_amount'] * 100;
        $TradeUnifiedorderBuilder->trade_type = $this->config['trade_type'];
        $TradeUnifiedorderBuilder->spbill_create_ip = $TradeService->client_ip();
        $TradeUnifiedorderBuilder->nonce_str = $TradeService->rand_char(32);
        $TradeUnifiedorderBuilder->sign = $TradeService->getSign($TradeUnifiedorderBuilder);
        
        $xml = $TradeService->arrayToXml($TradeUnifiedorderBuilder);
        //print_r($TradeUnifiedorderBuilder->params);
        //header("Content-type: text/xml");
        /*echo "<xmp>";
        echo ($xml);die;
        echo "</xmp>";*/
        $response = $TradeService->postXmlCurl($xml);
        //将微信返回的结果xml转成数组
        $res = $TradeService->xmlstr_to_array($response);
        $sign2 = $TradeService->getOrder($res['prepay_id']);
        if (!empty($sign2)) {
            return $sign2;
        } else {
            return false;
        }
    }
    
    /**
     * 服务器点对点响应操作给支付接口方调用
     *
     */
    function response()
    {
        require_once("lib/TradeService.php");
        $TradeService = new TradeService($this->config);
        // 回调数据
        $resposeArr = $TradeService->xmlstr_to_array($GLOBALS["HTTP_RAW_POST_DATA"]);
        //返回数据保存
        $content = serialize($resposeArr);
        $data = array();
        $data['content'] = $content;
        $data['create_time'] = time();
        M('pay_respose')->add($data);
        if (($resposeArr['total_fee']) && ($resposeArr['result_code'] == 'SUCCESS')) {
            $order_sn = trim($resposeArr['out_trade_no']);
            update_pay_status($order_sn,$resposeArr['transaction_id']);
            echo 'success';
            exit;
        } else {
            echo 'error';
            exit;
        }
    }
    
    /**
     * 页面跳转响应操作给支付接口方调用
     */
    function respond2()
    {
        require_once 'wappay/service/TradeService.php';
        $respose = $_GET;
        $alipaySevice = new AlipayTradeService($this->alipay_config);
        $alipaySevice->writeLog(var_export($respose, true));
        $verify_result = $alipaySevice->check($respose);
        if ($verify_result) //验证成功
        {
            $order_sn = $out_trade_no = htmlspecialchars($respose['out_trade_no']); //商户订单号
            $trade_no = htmlspecialchars($respose['trade_no']); //支付宝交易号
            return array('status' => 1, 'order_sn' => $order_sn);//跳转至成功页面
            //$trade_status = $respose['trade_status']; //交易状态
            /*if ($trade_status == 'TRADE_FINISHED' || $trade_status == 'TRADE_SUCCESS') {
                return array('status' => 1, 'order_sn' => $order_sn);//跳转至成功页面
            } else {
                return array('status' => 0, 'order_sn' => $order_sn); //跳转至失败页面
            }*/
        } else {
            return array('status' => 0, 'order_sn' => $respose['out_trade_no']);//跳转至失败页面
        }
    }
    
}