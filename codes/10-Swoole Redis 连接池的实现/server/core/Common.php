<?php

if (!defined('SERVER_PATH')) exit("No Access");

if (!function_exists('get_config')) {
    function get_config($name = 'config', $replace = []) {
        static $_config;
        if (isset($_config[$name])) {
            return $_config[$name][0];
        }
        $config_path = SERVER_PATH.'/config/'.$name.'.php';
        if (!file_exists($config_path)) {
            exit('The configuration file does not exist.');
        }
        require_once "{$config_path}";
        if (!isset($config) || !is_array($config)) {
            exit('Your config file does not appear to be formatted correctly.');
        }
        if (count($replace) > 0) {
            foreach ($replace as $key => $val) {
                if (strstr($key, '@')) {
                    $arr = explode( '@', $key);
                    //TODO 目前仅支持二维数组
                    if (isset($config[$arr[0]][$arr[1]])) {
                        $config[$arr[0]][$arr[1]] = $val;
                    }
                } else {
                    if (isset($config[$key])) {
                        $config[$key] = $val;
                    }
                }
            }
        }
        $_config[$name][0] = &$config;
        return $_config[$name][0];
    }
}

if (!function_exists('posix_user_name')) {
    function posix_user_name() {
        $posix = posix_getpwuid(posix_getuid());
        return $posix['name'];
    }
}

if (!function_exists('output')) {
    function output($msg = '') {
        return "[".date("Y-m-d H:i:s")."] ".$msg.PHP_EOL;
    }
}

if (!function_exists('encrypt')) {
    function encrypt($data = []) {
        return json_encode($data);
    }
}

if (!function_exists('decrypt')) {
    function decrypt($data = '') {
        return json_decode($data, true);
    }
}