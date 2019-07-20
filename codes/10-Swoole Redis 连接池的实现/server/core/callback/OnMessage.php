<?php

if (!defined('SERVER_PATH')) exit("No Access");

class OnMessage
{
    public static function run($serv, $frame)
    {
        try {
            echo output("onMessage: FD={$frame->fd} Msg={$frame->data}");
            $pool = RedisPool::getInstance();
            $redis = $pool->get();

            require_once  APP_PATH . "/Statistic.php";
            $classObj = new Statistic;

            $timer_id = swoole_timer_tick(1000, function () use ($serv, $redis, $classObj) {

                $return_data = call_user_func_array([$classObj, 'init'], []);
                $param = [
                    'type'   => 'speak',
                    'msg'    => json_encode($return_data),
                    'server' => 'ws'
                ];
                $serv->task(json_encode($param));
            });

            $hash_key = 'HASH_FDD';

            $redis->HSET($hash_key, "FD{$frame->fd}", $timer_id);
            $fds = $redis->HKEYS($hash_key); //所有的FD

            $used_fd = [];
            foreach ($serv->connections as $fd) {
                $used_fd[] = "FD".$fd;
            }

            foreach ($fds as $v) {
                if (!in_array($v, $used_fd)) {
                    $redis_timer_id = $redis->HGET($hash_key, $v);
                    $redis->HDEL($hash_key, $v);
                    if (!empty($redis_timer_id)) {
                        swoole_timer_clear($redis_timer_id);
                    }
                }
            }
        } catch(Exception $e) {

        }
    }
}
