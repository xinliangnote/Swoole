<?php

if (!defined('SERVER_PATH')) exit("No Access");

class OnTask
{
    public static function tcp_task_run($serv, $task)
    {
        try {
            echo output("#{$task->worker_id} onTask: [PID={$serv->worker_pid}] Task_id={$task->id}");

            $data = json_decode($task->data, true);

            $class  = $data['request']['param']['class'];
            $method = $data['request']['param']['method'];
            $params = $data['request']['param']['param'];

            require_once  APP_PATH . "/" . $class . ".php";
            $classObj = new $class;
            $return_data = call_user_func_array([$classObj, $method], $params);
            $task->finish(['swoole_server' => 'TCP', 'request' => $data, 'response' => $return_data]);

        } catch(Exception $e) {
        }
    }

    public static function http_task_run($serv, $task)
    {
        try {
            echo output("#{$task->worker_id} onTask: [PID={$serv->worker_pid}] Task_id={$task->id}");

            $data = json_decode($task->data, true);

            $class  = $data['request']['param']['class'];
            $method = $data['request']['param']['method'];
            $params = $data['request']['param']['param'];

            require_once  APP_PATH . "/" . $class . ".php";
            $classObj = new $class;
            $return_data = call_user_func_array([$classObj, $method], $params);
            $task->finish(['swoole_server' => 'HTTP', 'request' => $data, 'response' => $return_data]);
        } catch(Exception $e) {
        }
    }

    public static function ws_task_run($serv, $task)
    {
        try {

        } catch(Exception $e) {
        }
    }
}