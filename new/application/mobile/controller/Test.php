<?php
namespace app\mobile\controller;

use think\Controller;

class Test extends Controller
{
    public $redis;
    /**
     * Redis的超时时间
     *
     * @var int
     */
    const REDISTIMEOUT = 0;
    
    public function redisTest(){
        $this->config = C('redis');
        // 链接数据库
        $this->redis = new \Redis();
        $this->redis->connect($this->config['host'], $this->config['port'], self::REDISTIMEOUT);
        $this->redis->auth($this->config['password']);
        $this->redis->select(8);
        //存储数据到列表中
        /*$this->redis->lpush("tutorial-list", "Redis");
        $this->redis->lpush("tutorial-list", "Mongodb");
        $this->redis->lpush("tutorial-list", "Mysql");*/
        $this->redis->setex('spjiang','30','spjiang_val');
        $arList = $this->redis->lrange("tutorial-list", 0 ,5);
        print_r($arList);
        echo $this->redis->lSize('tutorial-list');
        echo $this->redis->lGet("tutorial-list", 0);
    }
    public function test2(){
        encryptUserPasswd('12345');
    }
    
    function test_success(){
        return $this->fetch('payment/success');
    }
    function test_error(){
        return $this->fetch('payment/error');
    }
    
}
