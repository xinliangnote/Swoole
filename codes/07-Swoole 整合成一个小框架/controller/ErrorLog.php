<?php

class ErrorLog
{
    public function index($request)
    {
        $config = get_config();
        $html = "<h1>错误日志路径：{$config['error_log_file']}</h1>";
        $html .= "<h1>Swoole日志路径：{$config['set']['log_file']}</h1>";
        return $html;
    }
}