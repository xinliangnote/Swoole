<?php

if (!defined('SERVER_PATH')) exit("No Access");

class OnWorkerStart
{
    public static function run($serv, $worker_id, $config)
    {
        try {
            if ($worker_id >= $config['set']['worker_num']) {
                swoole_set_process_name($config['task_process_name']);
                echo str_pad("Task", 18, ' ', STR_PAD_BOTH ).
                    str_pad($config['task_process_name'], 24, ' ', STR_PAD_BOTH ).
                    str_pad($serv->worker_pid, 20, ' ', STR_PAD_BOTH ).
                    str_pad(posix_getppid(), 12, ' ', STR_PAD_BOTH ).
                    str_pad(posix_user_name(), 20, ' ', STR_PAD_BOTH ).PHP_EOL;

                file_put_contents($config['task_pid_file'],
                    $serv->worker_pid."-".
                    posix_getppid()."-".
                    posix_user_name().'|', FILE_APPEND);

            } else {
                swoole_set_process_name($config['worker_process_name']);
                echo str_pad("Worker", 18, ' ', STR_PAD_BOTH ).
                    str_pad($config['worker_process_name'], 26, ' ', STR_PAD_BOTH ).
                    str_pad($serv->worker_pid, 16, ' ', STR_PAD_BOTH ).
                    str_pad(posix_getppid(), 16, ' ', STR_PAD_BOTH ).
                    str_pad(posix_user_name(), 16, ' ', STR_PAD_BOTH ).PHP_EOL;

                file_put_contents($config['worker_pid_file'],
                    $serv->worker_pid."-".
                    posix_getppid()."-".
                    posix_user_name().'|', FILE_APPEND);
            }

            spl_autoload_register(function ($className) {
                $classPath = APP_PATH . "/" . $className . ".php";
                if (is_file($classPath)) {
                    require_once "{$classPath}";
                    return;
                }
            });

        } catch(Exception $e) {
        }
    }
}
