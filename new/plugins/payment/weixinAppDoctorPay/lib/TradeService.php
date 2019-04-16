<?php

/**
 * 微信app支付交易服务
 *
 * @author spjiang
 * Class TradeService
 */
class TradeService
{
    //网关地址
    public $gateway_url;
    //应用id
    public $appid;
    //商户号
    public $mch_id;
    //商户支付密钥
    public $api_key;
    //回调地址
    public $notify_url;
    
    function __construct($config)
    {
        $this->gateway_url = $config['gateway_url'];
        $this->appid = $config['appid'];
        $this->mch_id = $config['mch_id'];
        $this->api_key = $config['api_key'];
        $this->notify_url = $config['notify_url'];
        if (empty($this->appid) || trim($this->appid) == "") {
            throw new Exception("appid should not be NULL!");
        }
        if (empty($this->mch_id) || trim($this->mch_id) == "") {
            throw new Exception("mch_id should not be NULL!");
        }
        if (empty($this->api_key) || trim($this->api_key) == "") {
            throw new Exception("api_key should not be NULL!");
        }
        if (empty($this->notify_url) || trim($this->notify_url) == "") {
            throw new Exception("notify_url should not be NULL!");
        }
        if (empty($this->gateway_url) || trim($this->gateway_url) == "") {
            throw new Exception("gateway_url should not be NULL!");
        }
    }
    
    /**
     * alipay.trade.wap.pay
     * @param $builder 业务参数，使用buildmodel中的对象生成。
     * @param $return_url 同步跳转地址，公网可访问
     * @param $notify_url 异步通知地址，公网可以访问
     * @return $response 微信返回的信息
     */
    function pay($builder, $return_url, $notify_url)
    {
        
        $biz_content = $builder->getBizContent();
        //打印业务参数
        $this->writeLog($biz_content);
        require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . './../../aop/request/AlipayTradeWapPayRequest.php';
        $request = new AlipayTradeWapPayRequest();
        $request->setNotifyUrl($notify_url);
        $request->setReturnUrl($return_url);
        $request->setBizContent($biz_content);
        // 首先调用支付api
        $response = $this->aopclientRequestExecute($request, true);
        // $response = $response->alipay_trade_wap_pay_response;
        return $response;
    }
    
    /**
     * 生成签名
     * @param TradeUnifiedorderBuilder $obj
     * @return string
     */
    function getSign(TradeUnifiedorderBuilder $obj)
    {
        foreach ($obj->params as $k => $v) {
            $Parameters[strtolower($k)] = $v;
        }
        //签名步骤一：按字典序排序参数
        ksort($Parameters);
        $String = $this->formatBizQueryParaMap($Parameters, false);
        //签名步骤二：在string后加入KEYformatBizQueryParaMap
        $String = $String . "&key=" . $this->api_key;
        //签名步骤三：MD5加密
        $result = strtoupper(md5($String));
        return $result;
    }
    
    function getTwoSign($data)
    {
        foreach ($data as $k => $v) {
            $Parameters[strtolower($k)] = $v;
        }
        //签名步骤一：按字典序排序参数
        ksort($Parameters);
        $String = $this->formatBizQueryParaMap($Parameters, false);
        //签名步骤二：在string后加入KEYformatBizQueryParaMap
        $String = $String . "&key=" . $this->api_key;
        //签名步骤三：MD5加密
        $result = strtoupper(md5($String));
        return $result;
    }
    
    /**
     * xml转成数组
     * @param $xmlstr
     * @return mixed
     */
    function xmlstr_to_array($xmlstr)
    {
        $doc = new DOMDocument();
        $doc->loadXML($xmlstr);
        return $this->domnode_to_array($doc->documentElement);
    }
    
    /**
     * @param $node
     * @return array|string
     */
    function domnode_to_array($node)
    {
        $output = array();
        switch ($node->nodeType) {
            case XML_CDATA_SECTION_NODE:
            case XML_TEXT_NODE:
                $output = trim($node->textContent);
                break;
            case XML_ELEMENT_NODE:
                for ($i = 0, $m = $node->childNodes->length; $i < $m; $i++) {
                    $child = $node->childNodes->item($i);
                    $v = $this->domnode_to_array($child);
                    if (isset($child->tagName)) {
                        $t = $child->tagName;
                        if (!isset($output[$t])) {
                            $output[$t] = array();
                        }
                        $output[$t][] = $v;
                    } elseif ($v) {
                        $output = (string)$v;
                    }
                }
                if (is_array($output)) {
                    if ($node->attributes->length) {
                        $a = array();
                        foreach ($node->attributes as $attrName => $attrNode) {
                            $a[$attrName] = (string)$attrNode->value;
                        }
                        $output['@attributes'] = $a;
                    }
                    foreach ($output as $t => $v) {
                        if (is_array($v) && count($v) == 1 && $t != '@attributes') {
                            $output[$t] = $v[0];
                        }
                    }
                }
                break;
        }
        return $output;
    }
    
    // 获取当前服务器的IP
    function client_ip()
    {
        if ($_SERVER['REMOTE_ADDR']) {
            $cip = $_SERVER['REMOTE_ADDR'];
        } elseif (getenv("REMOTE_ADDR")) {
            $cip = getenv("REMOTE_ADDR");
        } elseif (getenv("HTTP_CLIENT_IP")) {
            $cip = getenv("HTTP_CLIENT_IP");
        } else {
            $cip = "unknown";
        }
        return $cip;
    }
    
    // 获取指定长度的随机字符串
    function rand_char($length = 16)
    {
        $str = null;
        $strPol = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz";
        $max = strlen($strPol) - 1;
        for ($i = 0; $i < $length; $i++) {
            $str .= $strPol[rand(0, $max)];//rand($min,$max)生成介于min和max两个数之间的一个随机整数
        }
        return $str;
    }
    
    /**
     * 执行第二次签名，才能返回给客户端使用
     *
     * @param $prepayId
     * @return mixed
     */
    public function getOrder($prepayId)
    {
        $data = array();
        $data["appid"] = $this->appid;
        $data["noncestr"] = $this->rand_char(32);
        $data["package"] = "Sign=WXPay";
        $data["partnerid"] = $this->mch_id;
        $data["prepayid"] = $prepayId;
        $data["timestamp"] = time();
        $data["sign"] = $this->getTwoSign($data);
        return $data;
    }
    
    //post https请求，CURLOPT_POSTFIELDS xml格式
    function postXmlCurl($xml, $second = 30)
    {
        //初始化curl
        $ch = curl_init();
        //超时时间
        curl_setopt($ch, CURLOPT_TIMEOUT, $second);
        //这里设置代理，如果有的话
        //curl_setopt($ch,CURLOPT_PROXY, '8.8.8.8');
        //curl_setopt($ch,CURLOPT_PROXYPORT, 8080);
        curl_setopt($ch, CURLOPT_URL, $this->gateway_url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        //设置header
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        //要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        //post提交方式
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        //运行curl
        $data = curl_exec($ch);
        //返回结果
        if ($data) {
            curl_close($ch);
            return $data;
        } else {
            $error = curl_errno($ch);
            echo "curl出错，错误码:$error" . "<br>";
            echo "<a href='http://curl.haxx.se/libcurl/c/libcurl-errors.html'>错误原因查询</a></br>";
            curl_close($ch);
            return false;
        }
    }
    
    /**
     * 数组转xml
     * @param TradeUnifiedorderBuilder $obj
     * @return string
     */
    function arrayToXml(TradeUnifiedorderBuilder $obj)
    {
        $xml = "<xml>";
        foreach ($obj->params as $key=>$val)
        {
            if (is_numeric($val)){
                $xml.="<".$key.">".$val."</".$key.">";
            }else{
                $xml.='<'.$key.'><![CDATA['.$val.']]></'.$key.'>';
            }
        }
        $xml.="</xml>";
        return $xml;
    }
    
    /**
     * 将数组转成uri字符串
     *
     * @param $paraMap
     * @param $urlencode
     * @return bool|string
     */
    function formatBizQueryParaMap($paraMap, $urlencode)
    {
        $buff = "";
        ksort($paraMap);
        foreach ($paraMap as $k => $v) {
            if ($urlencode) {
                $v = urlencode($v);
            }
            $buff .= strtolower($k) . "=" . $v . "&";
        }
        $reqPar = '';
        if (strlen($buff) > 0) {
            $reqPar = substr($buff, 0, strlen($buff) - 1);
        }
        return $reqPar;
    }
    
    //请确保项目文件有可写权限，不然打印不了日志。
    function writeLog($text)
    {
        file_put_contents(dirname(__FILE__) . DIRECTORY_SEPARATOR . "./../log.txt", date("Y-m-d H:i:s") . "  " . $text . "\r\n", FILE_APPEND);
    }
}

?>                      