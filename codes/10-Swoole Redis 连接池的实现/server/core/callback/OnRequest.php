<?php

if (!defined('SERVER_PATH')) exit("No Access");

class OnRequest
{
    private static $query;
    private static $code;
    private static $msg;
    private static $data;

    public static function run($serv, $request, $response)
    {
        try {
            $response->header('Access-Control-Allow-Origin', '*');
            $data = decrypt($request->rawContent()); //获取 rawContent 数据
            if (empty($data)) {
                $data = $request->post; //获取 Ajax Post 数据
            }
            self::$query   = $data;
            if (empty($data)) {
                self::$code = '-1';
                self::$msg  = '非法请求';
                self::end($request, $response);
            }

            //TODO 验证Token

            switch ($data['type']) {
                case 'SW': //单个请求,等待结果
                    $task = [
                        'request' => $data,
                        'server'  => 'http'
                    ];

                    $rs = $serv->task(json_encode($task), -1, function ($serv, $task_id, $rs_data) use ($request, $response) {
                        self::$code = '1';
                        self::$msg  = '成功';
                        self::$data = $rs_data['response'];
                        self::end($request, $response);
                    });
                    if ($rs === false) {
                        self::$code = '-1';
                        self::$msg  = '失败';
                        self::end($request, $response);
                    }
                    break;

                case 'SN': //单个请求,不等待结果
                    $task = [
                        'request' => $data,
                        'server'  => 'http'
                    ];
                    $rs = $serv->task(json_encode($task));
                    if ($rs === false) {
                        self::$code = '-1';
                        self::$msg  = '失败';
                    } else {
                        self::$code = '1';
                        self::$msg  = '成功';
                    }
                    self::end($request, $response);
                    break;
                default:
                    self::$code = '-1';
                    self::$msg  = '非法请求';
                    self::end($request, $response);
            }
        } catch(Exception $e) {
        }
    }

    private static function end($request = null, $response = null)
    {
        $rs['request_method'] = $request->server['request_method'];
        $rs['request_time']   = $request->server['request_time'];
        $rs['response_time']  = time();
        $rs['code']           = self::$code;
        $rs['msg']            = self::$msg;
        $rs['data']           = self::$data;
        $rs['query']          = self::$query;
        $response->end(json_encode($rs));
        self::$data = [];
        return;
    }
}
