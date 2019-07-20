## 概述

收到读者提问 “使用 Swoole 开发的群聊功能，想知道并发情况，也就是想压测下 QPS，一直未找到方法 ...”

对 swoole_http_server 压测，咱们可以使用 Apache 的 ab 命令。

对 swoole_websocket_server 压测，使用 ab 命令是不能压测的，我从网上一直也没找到合适的方法，看官方提供的代码 `benchmark/async.php` 中，使用的异步模块 `swoole\http\client` 方法进行压测的，但在 Swoole 4.3 版本就移除了异步模块，让使用 `Coroutine` 协程模块。

在本地我用 `Coroutine` 协程实现了一下， 测的差不多的时候，一直不确定是否正确，就在 segmentfault 发了个提问，没想到韩老师回答了，'如果的如果'老师也回答了，非常感谢两位老师的答案，然后整理出文章分享给大家。

## 测试机

Mac 上安装的 Parallels Desktop 虚拟机

系统：Ubuntu 16.04.3 LTS

内存：

![](https://github.com/xinliangnote/Swoole/blob/master/images/11_swoole_1.png)

- 数量：1
- 核数：2

CPU：

![](https://github.com/xinliangnote/Swoole/blob/master/images/11_swoole_2.png)

- 数量：1
- 大小：2G

## Server 代码

```
<?php

class Server
{
    private $serv;
    public function __construct() {
        $this->serv = new Swoole\WebSocket\Server("0.0.0.0", 9501);
        $this->serv->set([
            'task_worker_num'       => 10,
            'enable_coroutine'      => true,
            'task_enable_coroutine' => true
        ]);
        $this->serv->on('open', function ($serv, $request) {});
        $this->serv->on('message', function ($serv, $frame) {
            $serv->task($frame->data);
        });
        $this->serv->on('task', function ($serv, $task) {
            foreach ($serv->connections as $fd) {
                $connectionInfo = $serv->connection_info($fd);
                if (isset($connectionInfo['websocket_status']) && intval($connectionInfo['websocket_status']) == 3) {
                    $serv->push($fd, $task->data);
                }
            }
        });
        $this->serv->on('finish', function ($serv, $task_id, $data) {});
        $this->serv->on('close', function ($serv, $fd) {});
        $this->serv->start();
    }
}

$server = new Server();
```

## 压测脚本

```
class Test
{
    protected $concurrency; //并发量
    protected $request;     //请求量
    protected $requested = 0;
    protected $start_time;

    function __construct()
    {
        $this->concurrency = 100;
        $this->request     = 10000;
    }

    protected function webSocket()
    {
        go(function () {
            for ($c = 1; $c <= $this->concurrency; $c++ ) {
                $cli = new \Swoole\Coroutine\Http\Client('127.0.0.1', 9501);
                $cli->set(['websocket_mask' => false]);
                $ret = $cli->upgrade('/');
                if ($ret) {
                    $i = $this->request / $this->concurrency;
                    while ($i >= 1) {
                        $this->push($cli);
                        $cli->recv();
                        $i--;
                    }
                }
            }
            $this->finish();
        });
    }

    protected function push($cli)
    {
        $ret = $cli->push('Hello World');
        if ($ret === true) {
            $this->requested ++ ;
        }
    }

    protected function finish()
    {
        $cost_time = round(microtime(true) - $this->start_time, 4);
        echo "Concurrency:".$this->concurrency.PHP_EOL;
        echo "Request num:".$this->request.PHP_EOL;
        echo "Success num:".$this->requested.PHP_EOL;
        echo "Total time:".$cost_time.PHP_EOL;
        echo "Request per second:" . intval($this->request / $cost_time).PHP_EOL;
    }

    public function run()
    {
        $this->start_time = microtime(true);
        $this->webSocket();
    }
}

$test = new Test();
$test->run();
```

## 压测结果

```
第 1 次：
Concurrency:100
Request num:10000
Success num:10000
Total time:0.846
Request per second:11820

第 2 次：
Concurrency:100
Request num:10000
Success num:10000
Total time:0.9097
Request per second:10992

第 3 次：
Concurrency:100
Request num:10000
Success num:10000
Total time:0.903
Request per second:11074
```

以上是压测结果，供参考。

## 小结

通过这个压测结果，表明 Swoole 的执行效率是杠杠的！

当然还有一些参数是可以调优的，比如：worker_num、max_request、task_worker_num 等。

在真实的业务场景中，肯定会有逻辑处理，也会使用到 MySQL、Redis。

那么问题来了，前两篇文章已经分享了，[Swoole Redis 连接池](https://mp.weixin.qq.com/s/Z2kEc8vDaqaS7psGOYo4Ag)、[Swoole MySQL 连接池](https://mp.weixin.qq.com/s/1CJ6cdE_h_x2kaYQVPbo4Q)，感兴趣的同学，可以使用上两种连接池，然后再进行压测。

不知不觉，Swoole 入门文章已经写了 11 篇了，非常感谢大家的捧场，喷的不多，真心希望能够对 Swoole 入门学习的同学，有点帮助。
