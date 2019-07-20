<?php

define('SERVER_PATH', str_replace( '\\', '/', __DIR__.'/server'));

define('APP_PATH', str_replace( '\\', '/', __DIR__.'/controller'));

require_once SERVER_PATH."/core/Common.php";
require_once SERVER_PATH."/core/HandlerException.php";

spl_autoload_register(function ($className) {
    $classPath = SERVER_PATH . "/core/" . $className . ".php";
    if (strtolower(substr($className, 0,5)) == 'mysql') {
        $classPath = SERVER_PATH . "/core/mysql/" . $className . ".php";
    }
    if (strtolower(substr($className, 0,5)) == 'redis') {
        $classPath = SERVER_PATH . "/core/redis/" . $className . ".php";
    }
    if (strtolower(substr($className, 0,2)) == 'on') {
        $classPath = SERVER_PATH . "/core/callback/" . $className . ".php";
    }
    if (is_file($classPath)) {
        require_once "{$classPath}";
    }
});

$sw = new Core();
$sw::run();