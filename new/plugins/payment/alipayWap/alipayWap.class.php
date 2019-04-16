<?php

use think\Model;

/**
 * 支付 逻辑定义
 * Class alipayWap
 */
class alipayWap extends Model
{
    public $tableName = 'plugin'; // 插件表        
    public $alipay_config = array();// 支付宝支付配置参数
    
    /**
     * 析构流函数
     */
    public function __construct()
    {
        parent::__construct();
        $paymentPlugin = M('Plugin')->where("code='alipayWap' and  type = 'payment' ")->find(); // 找到支付插件的配置
        $config_value = unserialize($paymentPlugin['config_value']); // 配置反序列化        
        $this->alipay_config['app_id'] = $config_value['app_id'];
        $this->alipay_config['merchant_private_key'] = $config_value['merchant_private_key'];
        $this->alipay_config['notify_url'] = $config_value['notify_url'];
        $this->alipay_config['return_url'] = $config_value['return_url'];
        $this->alipay_config['charset'] = $config_value['charset'];
        $this->alipay_config['sign_type'] = $config_value['sign_type'];
        $this->alipay_config['gatewayUrl'] = $config_value['gatewayUrl'];
        $this->alipay_config['alipay_public_key'] = $config_value['alipay_public_key'];
        
    }
    
    /**
     * 生成支付代码
     * @param array $order 订单信息
     * @param array $config_value 支付方式信息
     * @return 提交表单HTML文本
     */
    function get_code($order, $config_value)
    {
        require_once("wappay/service/AlipayTradeService.php");
        require_once("wappay/buildermodel/AlipayTradeWapPayContentBuilder.php");
        header("Content-type: text/html; charset=utf-8");
        
        //商户订单号，商户网站订单系统中唯一订单号，必填
        $out_trade_no = $order['order_sn'];
    
        //订单名称，必填
        $subject = "医之佳订单";
    
        //付款金额，必填
        $total_amount = $order['order_amount'];
        
        //商品描述，可空
        $body = "";
        //超时时间
        $timeout_express = "1m";
        $payRequestBuilder = new AlipayTradeWapPayContentBuilder();
        $payRequestBuilder->setBody($body);
        $payRequestBuilder->setSubject($subject);
        $payRequestBuilder->setOutTradeNo($out_trade_no);
        $payRequestBuilder->setTotalAmount($total_amount);
        $payRequestBuilder->setTimeExpress($timeout_express);
        $payResponse = new AlipayTradeService($this->alipay_config);
        $result = $payResponse->wapPay($payRequestBuilder, $this->alipay_config['return_url'], $this->alipay_config['notify_url']);
        return $result;
    }
    
    /**
     * 服务器点对点响应操作给支付接口方调用
     *
     */
    function response()
    {
        //返回数据保存
        $content = serialize($_REQUEST);
        $data = array();
        $data['content'] = $content;
        $data['create_time'] = time();
        M('pay_respose')->add($data);
        
        require_once 'wappay/service/AlipayTradeService.php';
        $arr = $_POST;
        $alipaySevice = new AlipayTradeService($this->alipay_config);
        $alipaySevice->writeLog(var_export($_POST, true));
        $verify_result = $alipaySevice->check($arr);
        if ($verify_result) {//验证成功
            /////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
            //请在这里加上商户的业务逻辑程序代
            
            
            //——请根据您的业务逻辑来编写程序（以下代码仅作参考）——
            
            //获取支付宝的通知返回参数，可参考技术文档中服务器异步通知参数列表
            
            //商户订单号
            
            $order_sn = $out_trade_no = $_POST['out_trade_no']; //商户订单号
            
            //支付宝交易号
            
            $trade_no = $_POST['trade_no'];
            
            //交易状态
            $trade_status = $_POST['trade_status'];
            
            if ($trade_status == 'TRADE_FINISHED') {
                
                //判断该笔订单是否在商户网站中已经做过处理
                //如果没有做过处理，根据订单号（out_trade_no）在商户网站的订单系统中查到该笔订单的详细，并执行商户的业务程序
                //请务必判断请求时的total_amount与通知时获取的total_fee为一致的
                //如果有做过处理，不执行商户的业务程序
                update_pay_status($order_sn,$trade_no);
                //注意：
                //退款日期超过可退款期限后（如三个月可退款），支付宝系统发送该交易状态通知
            } else if ($trade_status == 'TRADE_SUCCESS') {
                
                //判断该笔订单是否在商户网站中已经做过处理
                //如果没有做过处理，根据订单号（out_trade_no）在商户网站的订单系统中查到该笔订单的详细，并执行商户的业务程序
                //请务必判断请求时的total_amount与通知时获取的total_fee为一致的
                //如果有做过处理，不执行商户的业务程序
                //注意：
                //付款完成后，支付宝系统发送该交易状态通知
                update_pay_status($order_sn,$trade_no);
            }
            //——请根据您的业务逻辑来编写程序（以上代码仅作参考）——
            
            echo "success";        //请不要修改或删除
            
        } else {
            //验证失败
            echo "fail";    //请不要修改或删除
        }
        
    }
    
    /**
     * 页面跳转响应操作给支付接口方调用
     */
    function respond2()
    {
        require_once 'wappay/service/AlipayTradeService.php';
        $respose = $_GET;
        $alipaySevice = new AlipayTradeService($this->alipay_config);
        $alipaySevice->writeLog(var_export($respose, true));
        $verify_result = $alipaySevice->check($respose);
        if ($verify_result) //验证成功
        {
            $order_sn = $out_trade_no = htmlspecialchars($respose['out_trade_no']); //商户订单号
            $trade_no = htmlspecialchars($respose['trade_no']); //支付宝交易号
            return array('status' => 1, 'order_sn' => $order_sn,'trade_no'=>$trade_no);//跳转至成功页面
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