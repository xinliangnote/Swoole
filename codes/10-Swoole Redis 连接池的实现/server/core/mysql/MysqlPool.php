<?php

if (!defined('SERVER_PATH')) exit("No Access");

class MysqlPool
{
    private static $instance;
    private $pool;
    private $config;

    public static function getInstance($config = null)
    {
        if (empty(self::$instance)) {
            if (empty($config)) {
                throw new RuntimeException("MySQL config empty");
            }
            self::$instance = new static($config);
        }
        return self::$instance;
    }

    public function __construct($config)
    {
        if (empty($this->pool)) {
            $this->config = $config;
            $this->pool = new chan($config['master']['pool_size']);
            for ($i = 0; $i < $config['master']['pool_size']; $i++) {
                go(function() use ($config) {
                    $mysql = new MysqlDB();
                    $res = $mysql->connect($config);
                    if ($res === false) {
                        throw new RuntimeException("Failed to connect mysql server");
                    } else {
                        $this->pool->push($mysql);
                    }
                });
            }
        }
    }

    public function get()
    {
        if ($this->pool->length() > 0) {
            $mysql = $this->pool->pop($this->config['master']['pool_get_timeout']);
            if (false === $mysql) {
                throw new RuntimeException("Pop mysql timeout");
            }
            defer(function () use ($mysql) { //释放
                $this->pool->push($mysql);
            });
            return $mysql;
        } else {
            throw new RuntimeException("Pool length <= 0");
        }
    }
}
