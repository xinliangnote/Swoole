<?php

if (!defined('SERVER_PATH')) exit("No Access");

class OnFinish
{
    public static function run($serv, $task_id, $data)
    {
        try {
            if ($data['swoole_server'] == 'TCP') {
                $rs['request_method'] = 'TCP';
                $rs['request_time']   = $data['request']['request_time'];
                $rs['response_time']  = time();
                $rs['code']           = '1';
                $rs['msg']            = '成功';
                $rs['data']           = $data['response'];
                $rs['query']          = $data['request']['request'];
                $serv->send($data['request']['fd'], json_encode($rs));
            }
            if ($data['request']['request']['type'] == 'SN') {
                echo output("onFinish: 异步执行 Task {$task_id} 已完成，任务数据如下：".json_encode($data['response']));
            } else {
                echo output("onFinish: 同步执行 Task {$task_id} 已完成。");
            }
        } catch(Exception $e) {
        }
    }
}
