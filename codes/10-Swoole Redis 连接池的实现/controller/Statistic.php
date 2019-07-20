<?php
if (!defined('SERVER_PATH')) exit("No Access");

class Statistic
{
    private $redis;

    public function __construct()
    {
        $pool = RedisPool::getInstance();
        $this->redis = $pool->get();
    }

    public function init ()
    {
        $end_time   = time();
        $start_time = time() - 100;
        $rs = [];
        for ($i = $start_time, $j = 0; $i <= $end_time; $i+=1, $j++) {
            $rs['time'][$j]  = date('H:i:s', $i);
            $value = $this->redis->get($rs['time'][$j]);
            if ($value) {
                $rs['value'][$j] = $value;
            } else {
                $rs['value'][$j] = mt_rand(1000,2000);
                $this->redis->set($rs['time'][$j], $rs['value'][$j], 3*60);
            }
        }
        return $rs;
    }
}
