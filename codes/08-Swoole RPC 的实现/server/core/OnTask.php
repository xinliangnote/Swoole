<?php

if (!defined('SERVER_PATH')) exit("No Access");

class OnTask
{
    public static function tcp_task_run($serv, $task_id, $src_worker_id, $data)
    {
        try {
            echo output("#{$serv->worker_id} onTask: [PID={$serv->worker_pid}] Task_id={$task_id}");

            $data = json_decode($data, true);

            $class  = $data['request']['param']['class'];
            $method = $data['request']['param']['method'];
            $params = $data['request']['param']['param'];

            require_once  APP_PATH . "/" . $class . ".php";
            $classObj = new $class;
            $return_data = call_user_func_array([$classObj, $method], $params);
            return ['swoole_server' => 'TCP', 'request' => $data, 'response' => $return_data];

        } catch(Exception $e) {
        }
    }

    public static function http_task_run($serv, $task_id, $src_worker_id, $data)
    {
        try {
            echo output("#{$serv->worker_id} onTask: [PID={$serv->worker_pid}] Task_id={$task_id}");

            $data = json_decode($data, true);

            $class  = $data['request']['param']['class'];
            $method = $data['request']['param']['method'];
            $params = $data['request']['param']['param'];

            require_once  APP_PATH . "/" . $class . ".php";
            $classObj = new $class;
            $return_data = call_user_func_array([$classObj, $method], $params);
            return ['swoole_server' => 'HTTP', 'request' => $data, 'response' => $return_data];
        } catch(Exception $e) {
        }
    }

    public static function ws_task_run($serv, $task_id, $src_worker_id, $data)
    {
        try {

        } catch(Exception $e) {
        }
    }
}
