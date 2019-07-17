## 概述

Swoole 异步Task，主要实现调用异步任务的执行。

常用的场景：异步支付处理、异步订单处理、异步日志处理、异步发送邮件/短信等。

Swoole 的实现方式是 worker 进程处理数据请求，分配给 task 进程执行。

官方介绍：

> task 底层使用Unix Socket管道通信，是全内存的，没有IO消耗。单进程读写性能可达100万/s，不同的进程使用不同的管道通信，可以最大化利用多核。

本地版本：PHP 7.2.6、Swoole 4.3.1。

不多说，先看效果图：

![](https://github.com/xinliangnote/Swoole/blob/master/images/3_swoole_1.gif)

## 代码

#### server.php

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
            'task_worker_num' => 4, //开启4个task进程
            'dispatch_mode'   => 2, //数据包分发策略 - 固定模式
        ]);

        $this->serv->on('Start', [$this, 'onStart']);
        $this->serv->on('Connect', [$this, 'onConnect']);
        $this->serv->on("Receive", [$this, 'onReceive']);
        $this->serv->on("Close", [$this, 'onClose']);
        $this->serv->on("Task", [$this, 'onTask']);
        $this->serv->on("Finish", [$this, 'onFinish']);

        $this->serv->start();
    }

    public function onStart($serv) {
        echo "#### onStart ####".PHP_EOL;
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
        echo "worker_pid: {$serv->worker_pid}".PHP_EOL;
        echo "客户端:{$fd} 发来的Email:{$data}".PHP_EOL;
        $param = [
            'fd'    => $fd,
            'email' => $data
        ];
        $rs = $serv->task(json_encode($param));
        if ($rs === false) {
            echo "任务分配失败 Task ".$rs.PHP_EOL;
        } else {
            echo "任务分配成功 Task ".$rs.PHP_EOL;
        }
        echo "########".PHP_EOL.PHP_EOL;
    }

    public function onTask($serv, $task_id, $from_id, $data) {
        echo "#### onTask ####".PHP_EOL;
        echo "#{$serv->worker_id} onTask: [PID={$serv->worker_pid}]: task_id={$task_id}".PHP_EOL;

        //业务代码
        for($i = 1 ; $i <= 5 ; $i ++ ) {
            sleep(2);
            echo "Task {$task_id} 已完成了 {$i}/5 的任务".PHP_EOL;
        }

        $data_arr = json_decode($data, true);
        $serv->send($data_arr['fd'] , 'Email:'.$data_arr['email'].',发送成功');
        $serv->finish($data);
        echo "########".PHP_EOL.PHP_EOL;
    }

    public function onFinish($serv,$task_id, $data) {
        echo "#### onFinish ####".PHP_EOL;
        echo "Task {$task_id} 已完成".PHP_EOL;
        echo "########".PHP_EOL.PHP_EOL;
    }

    public function onClose($serv, $fd) {
        echo "Client Close.".PHP_EOL;
    }
}

$server = new Server();
```

#### client.php

```
<?php

class Client
{
    private $client;

    public function __construct() {
        $this->client = new swoole_client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_ASYNC);

        $this->client->on('Connect', [$this, 'onConnect']);
        $this->client->on('Receive', [$this, 'onReceive']);
        $this->client->on('Close', [$this, 'onClose']);
        $this->client->on('Error', [$this, 'onError']);
    }

    public function connect() {
        if(!$fp = $this->client->connect("127.0.0.1", 9501 , 1)) {
            echo "Error: {$fp->errMsg}[{$fp->errCode}]".PHP_EOL;
            return;
        }
    }

    public function onConnect($cli) {
        fwrite(STDOUT, "输入Email:");
        swoole_event_add(STDIN, function() {
            fwrite(STDOUT, "输入Email:");
            $msg = trim(fgets(STDIN));
            $this->send($msg);
        });
    }

    public function onReceive($cli, $data) {
        echo PHP_EOL."Received: ".$data.PHP_EOL;
    }

    public function send($data) {
        $this->client->send($data);
    }

    public function onClose($cli) {
        echo "Client close connection".PHP_EOL;
    }

    public function onError() {

    }
}

$client = new Client();
$client->connect();
```

## 小结

**一、上面的配置总共开启了几个进程？**

总共8个进程（1个master进程、1个manager进程、4个task进程、2个worker进程）

重新运行的可能与上图进程号不一致：

![](https://github.com/xinliangnote/Swoole/blob/master/images/3_swoole_2.png)

master进程：22481

manager进程：22485

task进程：22488、22489、22490、22491

worker进程：22492、22493

参考官方提供的进程图：

![](https://github.com/xinliangnote/Swoole/blob/master/images/3_swoole_3.png)

**二、为什么执行了5次后，worker进程号发生了改变？**

因为我们设了置worker进程的max_request=4，一个worker进程在完成最大请求次数任务后将自动退出，进程退出会释放所有的内存和资源，这样的机制主要是解决PHP进程内存溢出的问题。

**三、当task执行任务异常，我们kill一个task进程，会再新增一个吗？**

会。

**四、如何设置 task_worker_num ？**

最大值不得超过 SWOOLE_CPU_NUM * 1000。

查看本机 CPU 核数：

```
echo "swoole_cpu_num:".swoole_cpu_num().PHP_EOL;
```

根据项目的任务量决定的，比如：1秒会产生200个任务，执行每个任务需要500ms。

想在1s中执行完成200个任务，需要100个task进程。

100 = 200/(1/0.5)

**五、如何设置 worker_num ？**

默认设置为本机的CPU核数，最大不得超过 SWOOLE_CPU_NUM * 1000。

比如：1个请求耗时10ms，要提供1000QPS的处理能力，那就必须配置10个进程。

10 = 0.01*1000

假设每个进程占用40M内存，10个进程就需要占用400M的内存。

## 扩展

- Server->taskwait
- Server->taskWaitMulti
- Server->taskCo

## 参考文档

- https://wiki.swoole.com/wiki/page/134.html






