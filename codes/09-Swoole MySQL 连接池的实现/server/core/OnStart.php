<?php

if (!defined('SERVER_PATH')) exit("No Access");

class OnStart
{
    public static function run($serv, $config)
    {
        try {
            swoole_set_process_name($config['master_process_name']);
            echo str_pad("Master", 18, ' ', STR_PAD_BOTH ).
                str_pad($config['master_process_name'], 26, ' ', STR_PAD_BOTH ).
                str_pad($serv->master_pid, 16, ' ', STR_PAD_BOTH ).
                str_pad(posix_getppid(), 16, ' ', STR_PAD_BOTH ).
                str_pad(posix_user_name(), 16, ' ', STR_PAD_BOTH ).PHP_EOL;

            file_put_contents($config['master_pid_file'],
                $serv->master_pid."-".
                posix_getppid()."-".
                posix_user_name());

        } catch(Exception $e) {
        }
    }
}
