<?php
/**
 * @author: Spjiang<jiangshengping@outlook.com>
 * @time: 2017/6/6 15:23
 */

namespace app\common\Util;

class Redis{
    protected static $redisObj = array();
    private function set($alias,RedisConnect $object){
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
class RedisConnect
{
    /**
     * Redis的超时时间
     *
     * @var int
     */
    const REDISTIMEOUT = 0;
    
    private $config = array();
    /**
     * Redis的连接句柄
     *
     * @var object
     */
    private $redis;
    
    public function __construct ()
    {
        $this->config = C('redis');
    }
    
    /**
     *
     * @return object
     */
    public function connect ()
    {
        // 链接数据库
        $this->redis = new Redis();
        $this->redis->connect($this->config['host'], $this->config['port'], self::REDISTIMEOUT);
        $this->redis->auth($this->config['password']);
    }
    
    /**
     * 获取redis的连接实例
     *
     * @return Redis
     */
    public function getRedisConn ()
    {
        return $this->redis;
    }
    
    /**
     * 选择库
     * @param $index
     */
    public function setSelect($index){
        $this->config['select'] = $index;
    }
    /**
     * 需要在单例切换的时候做清理工作
     */
    public function __destruct ()
    {
        $this->redis->close();
    }
}

?>