<?php

if (!defined('SERVER_PATH')) exit("No Access");

class OnTask
{
    public static function tcp_task_run($serv, $task_id, $src_worker_id, $data)
    {
        try {
            echo output("onTask: [PID={$serv->worker_pid}] Task_id={$task_id}");

            //业务代码
            for($i = 1 ; $i <= 5 ; $i ++ ) {
                sleep(1);
                echo output("onTask: Task {$task_id} 已完成了 {$i}/5 的任务");
            }

            $data_arr = json_decode($data, true);
            $serv->send($data_arr['fd'] , output($data_arr['email'].',发送成功'));
            $serv->finish($data);

        } catch(Exception $e) {
            var_dump($e);
        }
    }

    public static function ws_task_run($serv, $task_id, $src_worker_id, $data)
    {
        try {
            echo output("#{$serv->worker_id} onTask: [PID={$serv->worker_pid}] Task_id={$task_id}");
            $dataArr = json_decode($data, true);
            $msg = '';
            switch ($dataArr['type']) {
                case 'login':
                    $msg = '我来了...';
                    break;
                case 'speak':
                    $msg = $dataArr['msg'];
                    break;
            }
            foreach ($serv->connections as $fd) {
                $connectionInfo = $serv->connection_info($fd);
                if ($connectionInfo['websocket_status'] == 3) {
                    $serv->push($fd, $msg); //长度最大不得超过2M
                    $serv->finish($msg);
                }
            }
        } catch(Exception $e) {
        }
    }
}
