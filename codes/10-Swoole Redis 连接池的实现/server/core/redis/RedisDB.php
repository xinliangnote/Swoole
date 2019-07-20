<?php

if (!defined('SERVER_PATH')) exit("No Access");

class RedisDB
{
    private $master;
    private $slave;
    private $config;

    public function __call($name, $arguments)
    {
        // TODO 主库的操作
        $command_master = ['set', 'hset', 'sadd'];

        if (!in_array($name, $command_master)) {
            $db = $this->_get_usable_db('slave');
        } else {
            $db = $this->_get_usable_db('master');
        }
        $result = call_user_func_array([$db, $name], $arguments);
        return $result;
    }

    public function connect($config)
    {
        //主库
        $master = new Swoole\Coroutine\Redis();
        $res = $master->connect($config['master']['host'], $config['master']['port']);
        if ($res === false) {
            throw new RuntimeException($master->errCode, $master->errMsg);
        } else {
            $this->master = $master;
        }

        //从库
        $slave = new Swoole\Coroutine\Redis();
        $res = $slave->connect($config['slave']['host'], $config['slave']['port']);
        if ($res === false) {
            throw new RuntimeException($slave->errCode, $slave->errMsg);
        } else {
            $this->slave = $slave;
        }

        $this->config = $config;
        return $res;
    }

    private function _get_usable_db($type)
    {
        if ($type == 'master') {
            if (!$this->master->connected) {
                $master = new Swoole\Coroutine\Redis();
                $res = $master->connect($this->config['master']['host'], $this->config['master']['port']);
                if ($res === false) {
                    throw new RuntimeException($master->errCode, $master->errMsg);
                } else {
                    $this->master = $master;
                }
            }
            return $this->master;
        } elseif ($type == 'slave') {
            if (!$this->slave->connected) {
                $slave = new Swoole\Coroutine\Redis();
                $res = $slave->connect($this->config['slave']['host'], $this->config['slave']['port']);
                if ($res === false) {
                    throw new RuntimeException($slave->errCode, $slave->errMsg);
                } else {
                    $this->slave = $slave;
                }
            }
            return $this->slave;
        }
    }
}
