<?php

if (!defined('SERVER_PATH')) exit("No Access");

//进程名称
$config['master_process_name']  = 'swoole_server_master';
$config['manager_process_name'] = 'swoole_server_manager';
$config['worker_process_name']  = 'swoole_server_worker';
$config['task_process_name']    = 'swoole_server_task';

//错误日志
$config['error_log_file'] = SERVER_PATH.'/log/error.log';

//进程ID存储的文件
$config['master_pid_file']  = SERVER_PATH.'/log/master.pid';
$config['manager_pid_file'] = SERVER_PATH.'/log/manager.pid';
$config['worker_pid_file']  = SERVER_PATH.'/log/worker.pid';
$config['task_pid_file']    = SERVER_PATH.'/log/task.pid';

//Swoole - IP信息
$config['ip']   = '0.0.0.0';

$config['websocket_port'] = 9509; //onMessage + onRequest(HTTP)
$config['tcp_port']       = 9510; //onReceive
$config['udp_port']       = 9511; //onPacke

//Swoole - 配置项(默认)
$config['set']['worker_num']      = 3;
$config['set']['task_worker_num'] = 4;
$config['set']['max_request']     = 100;
$config['set']['dispatch_mode']   = 2;
$config['set']['daemonize']       = false;
$config['set']['log_file']        = SERVER_PATH.'/log/swoole.log';


$config['tcp_set']['worker_num']            = 2;
$config['tcp_set']['max_request']           = 200;
$config['tcp_set']['dispatch_mode']         = 2;

//$config['tcp_set']['open_length_check']     = true;
//$config['tcp_set']['package_max_length']    = 8192;
//$config['tcp_set']['package_length_type']   = 'N';
//$config['tcp_set']['package_length_offset'] = '0';
//$config['tcp_set']['package_body_offset']   = '4';


$config['udp_set']['worker_num']    = 2;
$config['udp_set']['max_request']   = 4;
$config['udp_set']['dispatch_mode'] = 2;



