<?php

if (!defined('SERVER_PATH')) exit("No Access");

$db['default']['pool_size']        = 3; //连接池个数
$db['default']['pool_get_timeout'] = 0.5; //获取连接池超时时间
$db['default']['timeout']          = 0.5; //数据库建立连接超时时间
$db['default']['charset']          = 'utf8'; //字符集
$db['default']['strict_type']      = false; //开启严格模式
$db['default']['fetch_mode']       = true; //开启fetch模式

$config['master']             = $db['default'];
$config['master']['host']     = '127.0.0.1';
$config['master']['port']     = 3306;
$config['master']['user']     = 'root';
$config['master']['password'] = '123456';
$config['master']['database'] = 'demo';

$config['slave']             = $db['default'];
$config['slave']['host']     = '127.0.0.1';
$config['slave']['port']     = 3306;
$config['slave']['user']     = 'root';
$config['slave']['password'] = '123456';
$config['slave']['database'] = 'demo';
