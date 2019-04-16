<?php
/**
 * Redis实例类
 *
 * @author: Spjiang<jiangshengping@outlook.com>
 * @time: 2017/6/6 15:23
 */

namespace app\common\logic;
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
    public $redis;
    
    public function __construct ()
    {
        $this->config = C('redis');
        // 链接数据库
        $this->redis = new \Redis();
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
        $this->redis->select($index);
    }
    /**
     * 需要在单例切换的时候做清理工作
     */
    public function close ()
    {
        $this->redis->close();
    }
}

?>