<?php

if (!defined('SERVER_PATH')) exit("No Access");

class OnReceive
{
    public static function run($serv, $fd, $reactor_id, $data)
    {
        try {
            $length = unpack('N', $data)[1];
            $param = [
                'fd'     => $fd,
                'email'  => substr($data, -$length),
                'server' => 'tcp'
            ];
            $rs = $serv->task(json_encode($param));
            if ($rs === false) {
                echo output("onReceive: 收到任务，分配失败 Task ".$rs);
            } else {
                echo output("onReceive: 收到任务，分配成功 Task ".$rs);
            }
        } catch(Exception $e) {
        }
    }
}
