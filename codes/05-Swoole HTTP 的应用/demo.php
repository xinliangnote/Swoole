<?php

class Server
{
    private $serv;

    public function __construct() {
        $this->serv = new swoole_http_server("0.0.0.0", 9502);
        $this->serv->set([
            'worker_num'      => 2, //开启2个worker进程
            'max_request'     => 4, //每个worker进程 max_request设置为4次
            'daemonize'       => false, //守护进程(true/false)
        ]);

        $this->serv->on('Start', [$this, 'onStart']);
        $this->serv->on('WorkerStart', [$this, 'onWorkStart']);
        $this->serv->on('ManagerStart', [$this, 'onManagerStart']);
        $this->serv->on("Request", [$this, 'onRequest']);

        $this->serv->start();
    }

    public function onStart($serv) {
        echo "#### onStart ####".PHP_EOL;
        echo "SWOOLE ".SWOOLE_VERSION . " 服务已启动".PHP_EOL;
        echo "master_pid: {$serv->master_pid}".PHP_EOL;
        echo "manager_pid: {$serv->manager_pid}".PHP_EOL;
        echo "########".PHP_EOL.PHP_EOL;
    }

    public function onManagerStart($serv) {
        echo "#### onManagerStart ####".PHP_EOL.PHP_EOL;
    }

    public function onWorkStart($serv, $worker_id) {
        echo "#### onWorkStart ####".PHP_EOL.PHP_EOL;
    }

    public function onRequest($request, $response) {
        $response->header("Content-Type", "text/html; charset=utf-8");
        $html = "<h1>你好 Swoole.</h1>";
        $response->end($html);
    }
}

$server = new Server();