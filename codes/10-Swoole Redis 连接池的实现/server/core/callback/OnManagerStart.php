<?php

if (!defined('SERVER_PATH')) exit("No Access");

class OnManagerStart
{
    public static function run($serv, $config)
    {
        try {
            swoole_set_process_name($config['manager_process_name']);
            echo str_pad("Manager", 20, ' ', STR_PAD_BOTH ).
                str_pad($config['manager_process_name'], 24, ' ', STR_PAD_BOTH ).
                str_pad($serv->manager_pid, 16, ' ', STR_PAD_BOTH ).
                str_pad(posix_getppid(), 16, ' ', STR_PAD_BOTH ).
                str_pad(posix_user_name(), 16, ' ', STR_PAD_BOTH ).PHP_EOL;

            file_put_contents($config['manager_pid_file'],
                $serv->manager_pid."-".
                posix_getppid()."-".
                posix_user_name());

        } catch(Exception $e) {
        }
    }
}
