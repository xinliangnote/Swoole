<?php

if (!defined('SERVER_PATH')) exit("No Access");

class OnFinish
{
    public static function run($serv, $task_id, $data)
    {
        try {
            echo output("onFinish: Task {$task_id} 已完成");
        } catch(Exception $e) {
        }
    }
}
