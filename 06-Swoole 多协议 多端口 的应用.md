## 概述

主要参考官方的这两篇文章，进行实现的 Demo。

- 网络通信协议设计：https://wiki.swoole.com/wiki/page/484.html
- 多端口监听的使用：https://wiki.swoole.com/wiki/page/161.html

希望通过我提供的 Demo，能够对文档有更加深刻的理解。

## 网络通信协议设计

**为什么需要通信协议？**

> 官方：TCP协议在底层机制上解决了UDP协议的顺序和丢包重传问题。但相比UDP又带来了新的问题，TCP协议是流式的，数据包没有边界。应用程序使用TCP通信就会面临这些难题。因为TCP通信是流式的，在接收1个大数据包时，可能会被拆分成多个数据包发送。多次Send底层也可能会合并成一次进行发送。这里就需要2个操作来解决：分包 和 合包，所以TCP网络通信时需要设定通信协议。

Swoole 支持了2种类型的自定义网络通信协议 ：EOF结束符协议、固定包头+包体协议。

#### EOF结束符协议

![](https://github.com/xinliangnote/Swoole/blob/master/images/6_swoole_1.png)

先看下，未设置协议的效果：

![](https://github.com/xinliangnote/Swoole/blob/master/images/6_swoole_3.gif)

发送的每条数据长度都是 23，但在 onReceive 接收数据的时候每次接收的长度不一样，并没有按照想象的方式进行分包。

再看下，设置了EOF结束符协议的效果：

![](https://github.com/xinliangnote/Swoole/blob/master/images/6_swoole_4.gif)

发送的每条数据长度都是 23，在 onReceive 接收数据的时候每次接收的也是 23 ，完美。

主要设置项如下：

```
'package_max_length' => '8192',
'open_eof_split'     => true,
'package_eof'        => "\r\n"
```

不做解释，官方文档已经写的很清楚。

**示例代码如下：**

server.php

```
<?php

class Server
{
    private $serv;

    public function __construct() {
        $this->serv = new swoole_server('0.0.0.0', 9501);
        $this->serv->set([
            'worker_num'      => 2, //开启2个worker进程
            'max_request'     => 4, //每个worker进程 max_request设置为4次
            'dispatch_mode'   => 2, //数据包分发策略 - 固定模式

            //EOF结束符协议
            'package_max_length' => '8192',
            'open_eof_split'     => true,
            'package_eof'        => "\r\n"
        ]);

        $this->serv->on('Start', [$this, 'onStart']);
        $this->serv->on('Connect', [$this, 'onConnect']);
        $this->serv->on("Receive", [$this, 'onReceive']);
        $this->serv->on("Close", [$this, 'onClose']);

        $this->serv->start();
    }

    public function onStart($serv) {
        echo "#### onStart ####".PHP_EOL;
        echo "SWOOLE ".SWOOLE_VERSION . " 服务已启动".PHP_EOL;
        echo "swoole_cpu_num:".swoole_cpu_num().PHP_EOL;
        echo "master_pid: {$serv->master_pid}".PHP_EOL;
        echo "manager_pid: {$serv->manager_pid}".PHP_EOL;
        echo "########".PHP_EOL.PHP_EOL;
    }

    public function onConnect($serv, $fd) {
        echo "#### onConnect ####".PHP_EOL;
        echo "客户端:".$fd." 已连接".PHP_EOL;
        echo "########".PHP_EOL.PHP_EOL;
    }

    public function onReceive($serv, $fd, $from_id, $data) {
        echo "#### onReceive ####".PHP_EOL;
        var_dump($data);
    }

    public function onClose($serv, $fd) {
        echo "Client Close.".PHP_EOL;
    }
}

$server = new Server();
```

client.php

```
<?php

class Client
{
    private $client;

    public function __construct() {
        $this->client = new swoole_client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_ASYNC);

        $this->client->on('Connect', [$this, 'onConnect']);
        $this->client->on('Close', [$this, 'onClose']);
        $this->client->on('Error', [$this, 'onError']);
    }

    public function connect() {
        if(!$fp = $this->client->connect("127.0.0.1", 9501)) {
            echo "Error: {$fp->errMsg}[{$fp->errCode}]".PHP_EOL;
            return;
        }
    }

    public function onConnect() {

        fwrite(STDOUT, "发送测试数据(Y or N):");
        swoole_event_add(STDIN, function() {
            $msg = trim(fgets(STDIN));
            if ($msg == 'y') {
                $this->send();
            }
            fwrite(STDOUT, "发送测试数据(Y or N):");
        });
    }

    public function send() {
        $msg_info =  "客户端发信息...\r\n";

        $i = 0;
        while ($i < 50) {
            var_dump($msg_info);
            $this->client->send($msg_info);
            $i++;
        }
    }

    public function onClose() {
        echo "Client close connection".PHP_EOL;
    }

    public function onError() {

    }
}

$client = new Client();
$client->connect();
```

#### 固定包头+包体协议

![](https://github.com/xinliangnote/Swoole/blob/master/images/6_swoole_2.png)

先看下，未设置协议的效果：

![](https://github.com/xinliangnote/Swoole/blob/master/images/6_swoole_5.gif)

很明显，在 onReceive 接收到的数据，是少的。

再看下，设置协议的效果：

![](https://github.com/xinliangnote/Swoole/blob/master/images/6_swoole_6.gif)

主要设置项如下：

```
'open_length_check'     => true,
'package_max_length'    => '8192',
'package_length_type'   => 'N',
'package_length_offset' => '0',
'package_body_offset'   => '4',
```

不做解释，官方文档已经写的很清楚。

**示例代码如下：**

server.php

```
<?php

class Server
{
    private $serv;

    public function __construct() {
        $this->serv = new swoole_server('0.0.0.0', 9501);
        $this->serv->set([
            'worker_num'      => 2, //开启2个worker进程
            'max_request'     => 4, //每个worker进程 max_request设置为4次
            'dispatch_mode'   => 2, //数据包分发策略 - 固定模式

            //固定包头+包体协议
            'open_length_check'     => true,
            'package_max_length'    => '8192',
            'package_length_type'   => 'N',
            'package_length_offset' => '0',
            'package_body_offset'   => '4',
        ]);

        $this->serv->on('Start', [$this, 'onStart']);
        $this->serv->on('Connect', [$this, 'onConnect']);
        $this->serv->on("Receive", [$this, 'onReceive']);
        $this->serv->on("Close", [$this, 'onClose']);

        $this->serv->start();
    }

    public function onStart($serv) {
        echo "#### onStart ####".PHP_EOL;
        echo "swoole_cpu_num:".swoole_cpu_num().PHP_EOL;
        echo "SWOOLE ".SWOOLE_VERSION . " 服务已启动".PHP_EOL;
        echo "master_pid: {$serv->master_pid}".PHP_EOL;
        echo "manager_pid: {$serv->manager_pid}".PHP_EOL;
        echo "########".PHP_EOL.PHP_EOL;
    }

    public function onConnect($serv, $fd) {
        echo "#### onConnect ####".PHP_EOL;
        echo "客户端:".$fd." 已连接".PHP_EOL;
        echo "########".PHP_EOL.PHP_EOL;
    }

    public function onReceive($serv, $fd, $from_id, $data) {
        echo "#### onReceive ####".PHP_EOL;
        $length = unpack('N', $data)[1];
        echo "Length:".$length.PHP_EOL;
        $msg = substr($data, -$length);
        echo "Msg:".$msg.PHP_EOL;
    }

    public function onClose($serv, $fd) {
        echo "Client Close.".PHP_EOL;
    }
}

$server = new Server();
```

client.php

```
<?php

class Client
{
    private $client;

    public function __construct() {
        $this->client = new swoole_client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_ASYNC);

        $this->client->on('Connect', [$this, 'onConnect']);
        $this->client->on('Close', [$this, 'onClose']);
        $this->client->on('Error', [$this, 'onError']);
    }

    public function connect() {
        if(!$fp = $this->client->connect("127.0.0.1", 9501, 1)) {
            echo "Error: {$fp->errMsg}[{$fp->errCode}]".PHP_EOL;
            return;
        }
    }

    public function onConnect() {

        fwrite(STDOUT, "发送测试数据(Y or N):");
        swoole_event_add(STDIN, function() {
            $msg = trim(fgets(STDIN));
            if ($msg == 'y') {
                $this->send();
            }
            fwrite(STDOUT, "发送测试数据(Y or N):");
        });
    }

    public function send() {
        $msg = '客户端发的信息...';
        $msg_info = pack('N', strlen($msg)).$msg;

        $i = 0;
        while ($i < 50) {
            var_dump($msg_info);
            $this->client->send($msg_info);
            $i++;
        }
    }

    public function onClose() {
        echo "Client close connection".PHP_EOL;
    }

    public function onError() {

    }
}

$client = new Client();
$client->connect();
```

## 多端口监听的使用

![](https://github.com/xinliangnote/Swoole/blob/master/images/6_swoole_7.png)

上图，是示例代码中的端口监听：

- 9501 onMessage 处理 WebSocket。
- 9501 onRequest 处理 HTTP。
- 9502 onReceive 处理 TCP。
- 9503 onPacket  处理 UDP。

不多说，看下效果图：

![](https://github.com/xinliangnote/Swoole/blob/master/images/6_swoole_8.gif)

**示例代码如下：**

server.php

```
<?php

class Server
{
    private $serv;

    public function __construct() {
        $this->serv = new swoole_websocket_server("0.0.0.0", 9501);
        $this->serv->set([
            'worker_num'      => 2, //开启2个worker进程
            'max_request'     => 4, //每个worker进程 max_request设置为4次
            'task_worker_num' => 4, //开启4个task进程
            'dispatch_mode'   => 4, //数据包分发策略 - IP分配
            'daemonize'       => false, //守护进程(true/false)
        ]);

        $this->serv->on('Start', [$this, 'onStart']);
        $this->serv->on('Open', [$this, 'onOpen']);
        $this->serv->on("Message", [$this, 'onMessage']);
        $this->serv->on("Request", [$this, 'onRequest']);
        $this->serv->on("Close", [$this, 'onClose']);
        $this->serv->on("Task", [$this, 'onTask']);
        $this->serv->on("Finish", [$this, 'onFinish']);

        //监听 9502 端口
        $tcp = $this->serv->listen("0.0.0.0", 9502, SWOOLE_SOCK_TCP);
        $tcp->set([
            'worker_num'      => 2, //开启2个worker进程
            'max_request'     => 4, //每个worker进程 max_request设置为4次
            'dispatch_mode'   => 2, //数据包分发策略 - 固定模式

            //固定包头+包体协议
            'open_length_check'     => true,
            'package_max_length'    => '8192',
            'package_length_type'   => 'N',
            'package_length_offset' => '0',
            'package_body_offset'   => '4',
        ]);
        $tcp->on("Receive", [$this, 'onReceive']);

        //监听 9503 端口
        $udp = $this->serv->listen("0.0.0.0", 9503, SWOOLE_SOCK_UDP);
        $udp->set([
            'worker_num'      => 2, //开启2个worker进程
            'max_request'     => 4, //每个worker进程 max_request设置为4次
            'dispatch_mode'   => 2, //数据包分发策略 - 固定模式
        ]);
        $udp->on("Packet", [$this, 'onPacket']);

        $this->serv->start();
    }

    public function onStart($serv) {
        echo "#### onStart ####".PHP_EOL;
        echo "SWOOLE ".SWOOLE_VERSION . " 服务已启动".PHP_EOL;
        echo "master_pid: {$serv->master_pid}".PHP_EOL;
        echo "manager_pid: {$serv->manager_pid}".PHP_EOL;
        echo "########".PHP_EOL.PHP_EOL;
    }

    public function onOpen($serv, $request) {
        echo "#### onOpen ####".PHP_EOL;
        echo "server: handshake success with fd{$request->fd}".PHP_EOL;
        $serv->task([
            'type' => 'login'
        ]);
        echo "########".PHP_EOL.PHP_EOL;
    }

    public function onTask($serv, $task_id, $from_id, $data) {
        echo "#### onTask ####".PHP_EOL;
        echo "#{$serv->worker_id} onTask: [PID={$serv->worker_pid}]: task_id={$task_id}".PHP_EOL;
        $msg = '';
        switch ($data['type']) {
            case 'login':
                $msg = '我来了...';
                break;
            case 'speak':
                $msg = $data['msg'];
                break;
        }
        foreach ($serv->connections as $fd) {
            $connectionInfo = $serv->connection_info($fd);
            if (isset($connectionInfo['websocket_status']) && $connectionInfo['websocket_status'] == 3) {
                $serv->push($fd, $msg); //长度最大不得超过2M
            }
        }
        $serv->finish($data);
        echo "########".PHP_EOL.PHP_EOL;
    }

    public function onFinish($serv,$task_id, $data) {
        echo "#### onFinish ####".PHP_EOL;
        echo "Task {$task_id} 已完成".PHP_EOL;
        echo "########".PHP_EOL.PHP_EOL;
    }

    public function onClose($serv, $fd) {
        echo "#### onClose ####".PHP_EOL;
        echo "client {$fd} closed".PHP_EOL;
        echo "########".PHP_EOL.PHP_EOL;
    }

    public function onMessage($serv, $frame) {
        echo "#### onMessage ####".PHP_EOL;
        echo "receive from fd{$frame->fd}:{$frame->data},opcode:{$frame->opcode},fin:{$frame->finish}".PHP_EOL;
        $serv->task(['type' => 'speak', 'msg' => $frame->data]);
        echo "########".PHP_EOL.PHP_EOL;
    }

    public function onRequest($request, $response) {
        echo "#### onRequest ####".PHP_EOL;
        $response->header("Content-Type", "text/html; charset=utf-8");
        $server = $request->server;
        $path_info    = $server['path_info'];
        $request_uri  = $server['request_uri'];

        echo "PATH_INFO:".$path_info.PHP_EOL;

        if ($path_info == '/favicon.ico' || $request_uri == '/favicon.ico') {
            return $response->end();
        }

        $html = "<h1>你好 Swoole.</h1>";
        $response->end($html);
    }

    public function onReceive($serv, $fd, $from_id, $data) {
        echo "#### onReceive ####".PHP_EOL;

        $length = unpack('N', $data)[1];
        echo "Length:".$length.PHP_EOL;
        $msg = substr($data, -$length);
        echo "Msg:".$msg.PHP_EOL;
    }

    public function onPacket($serv, $data, $clientInfo) {
        echo "#### onPacket ####".PHP_EOL;
        $serv->sendto($clientInfo['address'], $clientInfo['port'], "Server ".$data);
        var_dump($clientInfo);
    }
}

$server = new Server();
```

4 个客户端连接的代码分别是：

1、9501 onMessage 处理 WebSocket。可以参考原来文章 [Swoole WebSocket 的应用](https://mp.weixin.qq.com/s/I7544nfW06-fEueeUAYULg) 中的代码即可。
  
2、9501 onRequest 处理 HTTP。可以参考原来文章 [Swoole HTTP 的应用](https://mp.weixin.qq.com/s/6-UunScxZ5Zs34YvXjZQng) 中的代码即可。

3、9502 onReceive 处理 TCP。可以参考原来文章 [Swoole Task 的应用](https://mp.weixin.qq.com/s/PpawMkCnqZRJZKvTskItoA) 中的代码即可。

4、9503 onPacket  处理 UDP。

示例代码：

```
netcat -u 10.211.55.4 9503
```

## 小结

**一、多端口的应用场景是什么？**

比如，开发一个直播网站，直播用一个端口，IM聊天用一个端口。

比如，开发一个RPC服务，数据通讯用一个端口，统计界面用一个端口。

