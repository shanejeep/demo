<?php
/**
 * Redis注册类
 * @author: Spjiang<jiangshengping@outlook.com>
 * @time: 2017/6/6 15:23
 */

namespace app\common\logic;

class RedisRegister{
    protected static $redisObj = array();
    static function set($alias,$object){
        self::$redisObj[$alias] = $object;
    }
    /**
     * 获取redis连接实例
     *
     * @param $index 数据库索引
     * @return bool|mixed
     */
    static function getIndex($index){
        if($index>12) return false;
        $key = 'index_'.$index;
        if(!isset(self::$redisObj[$key])){
            $RedisConnect = new RedisConnect();
            $RedisConnect->setSelect($index);
            $redis = $RedisConnect->getRedisConn();
            self::set($key,$redis);
        }
        return self::$redisObj[$key];
    }
    function _unset($index){
        $key = 'index_'.$index;
        unset(self::$redisObj[$key]);
    }
}

?>