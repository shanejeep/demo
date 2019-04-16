<?php
/**
 * app用户访问
 * @author: Spjiang<jiangshengping@outlook.com>
 * @time: 2017/6/7 10:27
 */

namespace app\common\logic;

use think\Exception;

class AppUser
{
    static protected $token;
    static protected $redis;
    static protected $_instance;
    
    
    private function __construct()
    {
    }
    
    static function getInstance()
    {
        if (!self::$_instance instanceof self) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }
    
    /**
     * 设置Token
     * @param $token
     * @return mixed
     */
    public function setToken($token)
    {
        self::$token = $token;
        return self::$_instance;
    }
    
    /**
     * 设置redis数据库
     * @param $index
     * @return mixed
     */
    public function setRedis($index)
    {
        self::$redis = RedisRegister::getIndex($index);
        return self::$_instance;
    }
    
    /**
     * 检查app用户是否登录
     * @return mixed
     * @throws Exception | mixed
     */
    public function isLogin()
    {
        if (!self::$token) {
            return false;
        }
        // $redis = RedisRegister::getIndex(0);
        $respose = self::$redis->get(self::$token);
        return $respose;
    }
    
    /**
     * 获取用户信息
     * session|a:1:{s:4:"user";a:37:{s:7:"user_id";i:47;s:5:"email";s:18:"spjiang@aliyun.com";s:8:"password";s:32:"a38dec8f209f597a64be3142a39a644c";s:3:"sex";i:0;s:8:"birthday";i:0;s:10:"user_money";s:4:"0.00";s:12:"frozen_money";s:4:"0.00";s:15:"distribut_money";s:4:"0.00";s:10:"pay_points";i:0;s:6:"paypwd";N;s:8:"reg_time";i:1494410200;s:10:"last_login";i:1496804165;s:7:"last_ip";s:0:"";s:2:"qq";s:0:"";s:6:"mobile";s:11:"18580119882";s:16:"mobile_validated";i:0;s:5:"oauth";s:0:"";s:6:"openid";N;s:7:"unionid";N;s:8:"head_pic";N;s:9:"bank_name";N;s:9:"bank_card";N;s:8:"realname";N;s:6:"idcard";N;s:15:"email_validated";i:0;s:8:"nickname";s:7:"spjiang";s:5:"level";i:1;s:8:"discount";s:4:"1.00";s:12:"total_amount";s:4:"0.00";s:7:"is_lock";i:0;s:12:"is_distribut";i:0;s:12:"first_leader";i:0;s:13:"seco
     * @return mixed
     */
    public function getUserInfo()
    {
        header("Content-type: text/html; charset=utf-8");
        $redisval = self::$redis->get(self::$token);
        $sessionCnf = C('session');
        if ($sessionCnf['prefix']) {
            $tmpValArr = explode($sessionCnf['prefix'] . '|', $redisval);
            $valStr = $tmpValArr[1];
        } else {
            $valStr = $redisval;
        }
        return unserialize($valStr);
    }
    
    function mb_unserialize($serial_str) {
        $serial_str= preg_replace('!s:(\d+):"(.*?)";!se', "'s:'.strlen('$2').':\"$2\";'", $serial_str );
        $serial_str= str_replace("\r", "", $serial_str);
        return unserialize($serial_str);
    }

    /**
     * 写入信息到redis，嘎嘎
     */
    public function setRedisVal($key,$val)
    {
        $respose = self::$redis->setex($key,864000,$val);
        return $respose;
    }


    /**销毁某个用户*/
    function desUser(){
        $rs = self::$redis->del(self::$token);
        return $rs;
    }
    
}