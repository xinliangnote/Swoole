<?php

if (!defined('SERVER_PATH')) exit("No Access");

class OnReceive
{
    private static $request_time;
    private static $query;
    private static $code;
    private static $msg;
    private static $data;

    public static function run($serv, $fd, $reactor_id, $data)
    {
        try {
            self::$request_time = time();
            $data = decrypt($data);
            self::$query = $data;

            //TODO 验证Token


            switch ($data['type']) {
                case 'SW': //单个请求,等待结果
                    $task = [
                        'fd'           => $fd,
                        'request'      => $data,
                        'server'       => 'tcp',
                        'request_time' => self::$request_time,
                    ];
                    $rs = $serv->task(json_encode($task));
                    if ($rs === false) {
                        self::$code = '-1';
                        self::$msg  = '失败';
                        self::handlerTask($serv, $fd);
                    }
                    break;

                case 'SN': //单个请求,不等待结果
                    $task = [
                        'fd'           => $fd,
                        'request'      => $data,
                        'server'       => 'tcp',
                        'request_time' => self::$request_time,
                    ];
                    $rs = $serv->task(json_encode($task));
                    if ($rs === false) {
                        self::$code = '-1';
                        self::$msg  = '失败';
                    } else {
                        self::$code = '1';
                        self::$msg  = '成功';
                    }
                    self::handlerTask($serv, $fd);
                    break;
                default:
                    self::$code = '-1';
                    self::$msg  = '非法请求';
                    self::handlerTask($serv, $fd);
            }
        } catch(Exception $e) {
        }
    }

    private static function handlerTask($serv, $fd)
    {
        $rs['request_method'] = 'TCP';
        $rs['request_time']   = self::$request_time;
        $rs['response_time']  = time();
        $rs['code']           = self::$code;
        $rs['msg']            = self::$msg;
        $rs['data']           = self::$data;
        $rs['query']          = self::$query;
        $serv->send($fd, json_encode($rs));
    }
}
