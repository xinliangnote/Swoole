<?php

if (!defined('SERVER_PATH')) exit("No Access");

class Product
{
    private $redis;

    public function __construct()
    {
        $pool = RedisPool::getInstance();
        $this->redis = $pool->get();
    }

    public function set($key = '', $value = '')
    {
        return $this->redis->set($key, $value);
    }

    public function get($key = '')
    {
        return $this->redis->get($key);
    }
}
