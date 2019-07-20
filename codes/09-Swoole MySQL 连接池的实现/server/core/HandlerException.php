<?php

if (!defined('SERVER_PATH')) exit("No Access");

class HandlerException
{
    public static function appError($err_no = '', $err_msg = '', $err_file = '', $err_line = 0) {
        $err_str = "[{$err_no}] 文件：{$err_file} 中第 {$err_line} 行：{$err_msg} ";
        static::logRecord($err_str);
    }

    public static function fatalError() {
        $error = error_get_last();
        if (isset($error['type'])) {
            switch ($error['type'])
            {
                case E_ERROR :
                case E_PARSE :
                case E_CORE_ERROR :
                case E_COMPILE_ERROR :
                $err_str = "[{$error['type']}] 文件：{$error['file']} 中第 {$error['line']} 行：{$error['message']} ";
                static::logRecord($err_str);
            }
        }
    }

    protected static function logRecord($err_str = '') {
        $config = get_config();
        $error_log_path = $config['error_log_file'];
        $log_file_size = 0;
        if (is_file($error_log_path)) {
            $log_file_size = filesize($config['error_log_file']);
        }
        if ($log_file_size > 1024*20) { //超过20M进行清理
            file_put_contents($error_log_path,'');
        }
        $msg = "[".date("Y-m-d H:i:s")."] ".$err_str.PHP_EOL;
        file_put_contents($error_log_path, $msg, FILE_APPEND);
    }
}
