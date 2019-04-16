<?php
/**
 * tpshop 支付宝插件
 * ============================================================================
 * 版权所有 2015-2027 深圳搜豹网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.tp-shop.cn
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * Author: IT宇宙人
 * Date: 2015-09-09
 */

//namespace plugins\payment\alipay;

use think\Model;
use think\Request;
use think\Log;


/**
 * 支付 逻辑定义
 * Class AlipayPayment
 * @package Home\Payment
 */
class alipayAppPatientsPay extends Model
{
    public $tableName = 'plugin'; // 插件表        
    public $alipay_config = array();// 支付宝支付配置参数
    
    /**
     * 析构流函数
     */
    public function __construct()
    {
        parent::__construct();

        $paymentPlugin = M('Plugin')->where("code='alipayAppPatientsPay' and  type = 'payment' ")->find(); // 找到支付插件的配置
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
        include "aop/AopClient.php";
        include "aop/request/AlipayTradeAppPayRequest.php";

        $aop = new AopClient;
        $aop->format = "json";
        $aop->charset = "UTF-8";
        $aop->signType = "RSA2";
        $aop->appId = $this->alipay_config['app_id'] ;
        $aop->gatewayUrl = $this->alipay_config['gatewayUrl'];
        $aop->rsaPrivateKey = $this->alipay_config['merchant_private_key'];
        $aop->alipayrsaPublicKey = $this->alipay_config['alipay_public_key'];
        //实例化具体API对应的request类,类名称和接口名称对应,当前调用接口名称：alipay.trade.app.pay
        $request = new AlipayTradeAppPayRequest();
        //SDK已经封装掉了公共参数，这里只需要传入业务参数

        $bizcontent = json_encode([
            'body'=>'商品购买',  
            'subject'=>'医之佳商城订单',  
            'out_trade_no'=>$order['order_sn'],//此订单号为商户唯一订单号  
            'total_amount'=> $order['order_amount'],//保留两位小数  
            'product_code'=>'QUICK_MSECURITY_PAY'  
        ]); 
        $request->setNotifyUrl($this->alipay_config['notify_url']);
        $request->setReturnUrl($this->alipay_config['return_url']);
        
        $request->setBizContent($bizcontent);
        //这里和普通的接口调用不同，使用的是sdkExecute
        // var_dump($request);exit;
        $response = $aop->sdkExecute($request);
        // Log::write('begin ================         '.htmlspecialchars($response).'       end');
        // var_dump(htmlspecialchars($response));exit;
        //htmlspecialchars是为了输出到页面时防止被浏览器将关键参数html转义，实际打印到日志以及http传输不会有这个问题
        return $response;//就是orderString 可以直接给客户端请求，无需再做处理。
    }
    
    /**
     * 服务器点对点响应操作给支付接口方调用
     *
     */
    function response()
    {
        // require_once("lib/alipay_notify.class.php");  // 请求返回
        include "aop/AopClient.php";
        //计算得出通知验证结果
        // $alipayNotify = new AlipayNotify($this->alipay_config); // 使用支付宝原生自带的累 和方法 这里只是引用了一下 而已
        // $verify_result = $alipayNotify->verifyNotify();
        $aop = new AopClient;
        $aop->alipayrsaPublicKey = $this->alipay_config['alipay_public_key'];
        $flag = $aop->rsaCheckV1($_POST, NULL, "RSA2");
        if ($flag) //验证成功
        {
            $order_sn = $out_trade_no = $_POST['out_trade_no']; //商户订单号
            $trade_no = $_POST['trade_no']; //支付宝交易号
            $trade_status = $_POST['trade_status']; //交易状态
            
            // 支付宝解释: 交易成功且结束，即不可再做任何操作。
            if ($_POST['trade_status'] == 'TRADE_FINISHED') {
                update_pay_status($order_sn,$trade_no); // 修改订单支付状态
            } //支付宝解释: 交易成功，且可对该交易做操作，如：多级分润、退款等。
            elseif ($_POST['trade_status'] == 'TRADE_SUCCESS') {
                update_pay_status($order_sn,$trade_no); // 修改订单支付状态
            }
            echo "success"; // 告诉支付宝处理成功
        } else {
            echo "fail"; //验证失败
        }
    }
    
    /**
     * 页面跳转响应操作给支付接口方调用
     */
    function respond2()
    {
        require_once("lib/alipay_notify.class.php");  // 请求返回
        //计算得出通知验证结果
        $alipayNotify = new AlipayNotify($this->alipay_config);
        $verify_result = $alipayNotify->verifyReturn();

        $aop = new AopClient;
        $aop->alipayrsaPublicKey = $this->alipay_config['alipay_public_key'];
        $flag = $aop->rsaCheckV1($_POST, NULL, "RSA2");
        
        if ($verify_result) //验证成功
        {
            $order_sn = $out_trade_no = $_GET['out_trade_no']; //商户订单号
            $trade_no = $_GET['trade_no']; //支付宝交易号
            $trade_status = $_GET['trade_status']; //交易状态
            
            if ($_GET['trade_status'] == 'TRADE_FINISHED' || $_GET['trade_status'] == 'TRADE_SUCCESS') {
                return array('status' => 1, 'order_sn' => $order_sn);//跳转至成功页面
            } else {
                return array('status' => 0, 'order_sn' => $order_sn); //跳转至失败页面
            }
        } else {
            return array('status' => 0, 'order_sn' => $_GET['out_trade_no']);//跳转至失败页面
        }
    }
    
}