## 概述

Swoole 启动一个服务，开启了哪些进程和线程？

为了解决这个问题，咱们启动一个最简单的服务，一起看看究竟启动了哪些进程和线程？

然后结合官网运行流程图，对每个进程和线程进行归类。

服务启动后打印出当前 Swoole 版本 和 当前 CPU 核数。

打印 Swoole 版本，是让大家可以下载这个版本 去运行代码。

打印 CPU 核数，是因为这个参数下面会用到。

废话不多说，直接看代码吧。

## 代码

**serv.php**

```
<?php

class Server
{
    private $serv;

    public function __construct() {
        $this->serv = new swoole_server("0.0.0.0", 9502);
        $this->serv->set([
            'worker_num'      => 3,
            'task_worker_num' => 3,
        ]);
        $this->serv->on('Start', function ($serv) {
            echo "SWOOLE:".SWOOLE_VERSION . " 服务已启动".PHP_EOL;
            echo "SWOOLE_CPU_NUM:".swoole_cpu_num().PHP_EOL;
        });
        $this->serv->on('Receive', function ($serv, $fd, $from_id, $data) { });
        $this->serv->on('Task', function ($serv, $task) { });
        $this->serv->on('Finish', function ($serv, $task_id, $data) {});
        $this->serv->start();
    }
}
$server = new Server();
```

上面的代码简单说下，创建了一个 TCP 服务器，启动了 3 个 worker 进程， 3 个 task 进程，因为启用了 task 功能，所以必须注册 onTask、onFinish 2 个事件的回调函数。

咱们运行一下：

![](https://github.com/xinliangnote/Swoole/blob/master/images/1_swoole_1.png)

使用 ps 查看下：

![](https://github.com/xinliangnote/Swoole/blob/master/images/1_swoole_2.png)

16390 的父进程是 16389。

16393、16394、16395、16396、16397、16398 的父进程是 16390。

有没有发现，16391、16392 去哪啦？是不是很奇怪。

再用 pstree 查看下：

![](https://github.com/xinliangnote/Swoole/blob/master/images/1_swoole_3.png)

出来了吧，16391、16392 是线程 与 16390 进程一个层级。

现在我们了解了，启动的这个服务使用了 8 个进程、2 个线程。

我们一起看下官方 Swoole Server 的文档：

https://wiki.swoole.com/wiki/page/p-server.html

看下这张图：

![](https://github.com/xinliangnote/Swoole/blob/master/images/1_swoole_4.png)

通过上面的图，我们可以得到结论：

16389 是 Master 进程。

16390 是 Manager 进程。

16391、16392 是 Reactor 线程。

16393、16394、16395、16396、16397、16398 包括 3 个 Worker 进程，3 个 Task 进程。

## 小结

**一、为什么是 3 个 Worker 进程、3 个 Task 进程？**

因为，在创建服务的时候我们进行了设置 worker_num = 3, task_worker_num = 3。

worker_num 如果不进行设置，默认为 SWOOLE_CPU_NUM，在上面咱们打印出来了，默认为 2，最大不超过，SWOOLE_CPU_NUM * 1000，具体详情，看官方文档。

worker_num 文档：

https://wiki.swoole.com/wiki/page/275.html

task_worker_num 文档：

https://wiki.swoole.com/wiki/page/276.html

**二、为什么是 2 个 Reactor 线程？它是干什么的？**

因为，Reactor 线程数，默认为 SWOOLE_CPU_NUM，也可以通过 reactor_num 参数进行设置。

reactor_num 文档：

https://wiki.swoole.com/wiki/page/281.html

它是真正处理 TCP 连接，收发数据的线程。

Reactor线程 文档：

https://wiki.swoole.com/wiki/page/347.html

**三、Reactor、Worker、TaskWorker 的关系是什么样的？**

> 一个通俗的比喻，假设Server就是一个工厂，那Reactor就是销售，接受客户订单。而Worker就是工人，当销售接到订单后，Worker去工作生产出客户要的东西。而TaskWorker可以理解为行政人员，可以帮助Worker干些杂事，让Worker专心工作。

官方已经解释的很详细了，看官方文档吧：

https://wiki.swoole.com/wiki/page/163.html
