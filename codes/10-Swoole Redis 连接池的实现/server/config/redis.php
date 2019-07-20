<?php

if (!defined('SERVER_PATH')) exit("No Access");

$db['default']['pool_size']        = 3; //连接池个数
$db['default']['pool_get_timeout'] = 0.5; //获取连接池超时时间

$config['master']             = $db['default'];
$config['master']['host']     = '127.0.0.1';
$config['master']['port']     = 6379;
$config['master']['user']     = '';
$config['master']['password'] = '';

$config['slave']             = $db['default'];
$config['slave']['host']     = '127.0.0.1';
$config['slave']['port']     = 6379;
$config['slave']['user']     = '';
$config['slave']['password'] = '';
