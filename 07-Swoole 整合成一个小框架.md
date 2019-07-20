## 概述

写了关于 Swoole 入门的 6 篇文章后，增加了不少的关注者，也得到了一些大佬的鼓励，也有很多关注者都加了微信好友，交流之后发现一些朋友比我优秀还比我努力，也得到了一些大佬的建议。

发现持续写文章真的不是件容易的事，担心别人认为没价值，担心想法太幼稚或有漏洞被别人笑话，担心脑子里墨水太少，写不出来... 知道自己思路还不够清晰，逻辑还不够严谨，告诉自己没关系，一些都会好起来的，逆境才能成长嘛，敢写就是好的开始，以此来激励自己持续的学习和思考。

跑题了，说回正题。

这篇文章其实是读者的小小要求，事情是这样的：

读者：“亮哥，看了你的文章很有收获，将文章 Demo 放在本地直接就能运行了，太感谢了”

本人：“哈哈。。。有收获就好，感谢支持 ~ ”

读者：“我有一个小小的要求，现在每个文件都是独立的，我想部署到生产环境，想操作上更便捷，有日志...”

本人：“你说的不是框架吗？现在有很多现成的，看 Swoole 官网推荐的 Swoft、EasySwoole、MixPHP 等。详细的参考这个地址：https://wiki.swoole.com/wiki/page/p-open_source.html”

读者：“看了，发现文件太多了，看不懂，你能帮忙讲解下吗？”

本人：“What？我也是入门呀，要不我搞个简单的轮子吧”

......

于是就有了这篇文章，正好也是对前面 6 篇文章的复习吧。

## 效果

![](https://github.com/xinliangnote/Swoole/blob/master/images/7_swoole_1.png)

![](https://github.com/xinliangnote/Swoole/blob/master/images/7_swoole_2.png)

**命令如下：**

- php index.php 可以查看到上图
- php index.php start 开启服务（Debug模式）
- php index.php start -d 开启服务（Daemon模式）
- php index.php status 查看服务状态
- php index.php reload 服务热加载
- php index.php stop   关闭服务

index.php 这是文件名称，大家叫什么都可以。

**目录结构如下：**

```
├─ controller
│     ├── ...
├─ client
│  ├─ websocket
│     ├── ...
│  ├─ tcp
│     ├── ...
├─ server
│  ├─ config
│     ├── config.php
│  ├─ core
│     ├── Common.php
│     ├── Core.php
│     ├── HandlerException.php
│  ├─ log  -- 需要 读/写 权限
│     ├── ...
├─ index.php
```

目前就这几个文件，后期研究新的知识点会直接集成到这里面。

说说实现了什么：

1、启动了 WebSocket、HTTP、TCP、UDP 等服务。

2、WebSocket 例子，在 client/websocket 文件夹，实现了视频弹幕。

3、HTTP 例子，在浏览器直接访问：http://ip:port，逻辑代码在 controller 文件夹。

4、TCP 例子，在 client/tcp 文件夹。

5、UDP 例子，直接运行 `netcat -u ip port` 即可。

6、相关配置，在 server/config 文件夹。

## 代码

放不全，就放一个主要的文件吧（Core.php）。

```
<?php

if (!defined('SERVER_PATH')) exit("No Access");

class Core
{
    private static $serv;

    public function __construct() {
        set_error_handler(['HandlerException', 'appError']);
        register_shutdown_function(['HandlerException', 'fatalError']);
    }

    public static function run() {
        static::checkCli();
        static::checkExtension();
        static::showUsageUI();
        static::parseCommand();
    }

    protected static function checkCli() {
        if (php_sapi_name() !== 'cli') {
            exit(output('服务只能运行在 cli sapi 模式下'));
        }
    }

    protected static function checkExtension() {
        if (!extension_loaded('swoole')) {
            exit(output('请安装 swoole 扩展'));
        }
    }

    protected static function showUsageUI() {
        global $argc;
        if ($argc <= 1 || $argc >3) {
            echo PHP_EOL;
            echo "----------------------------------------".PHP_EOL;
            echo "|               Swoole                 |".PHP_EOL;
            echo "|--------------------------------------|".PHP_EOL;
            echo '|    USAGE: php index.php commond      |'.PHP_EOL;
            echo '|--------------------------------------|'.PHP_EOL;
            echo '|    1. start    以debug模式开启服务   |'.PHP_EOL;
            echo '|    2. start -d 以daemon模式开启服务  |'.PHP_EOL;
            echo '|    3. status   查看服务状态          |'.PHP_EOL;
            echo '|    4. reload   热加载                |'.PHP_EOL;
            echo '|    5. stop     关闭服务              |'.PHP_EOL;
            echo "----------------------------------------".PHP_EOL;
            echo PHP_EOL;
            exit;
        }
    }

    protected static function parseCommand() {
        global $argv;
        $command = $argv[1];
        $option  = isset( $argv[2] ) ? $argv[2] : '' ;
        switch ($command) {
            case 'start':
                if ($option === '-d') { //以daemon形式启动
                    get_config(['set@daemonize' => true]);
                }
                self::workerStart();
                break;
            case 'status':
                self::workerStatus();
                break;
            case 'reload':
                self::workerReload();
                break;
            case 'stop':
                self::workerStop();
                break;
            default:
                echo "Bad Command.".PHP_EOL;
        }
    }

    protected static function workerStart() {
        $config = get_config();

        self::$serv = new swoole_websocket_server($config['ip'], $config['websocket_port']);
        self::$serv->set($config['set']);
        self::$serv->on('Start', function ($serv) use ($config) {
            $start = new OnStart();
            $start::run($serv, $config);
        });

        self::$serv->on('ManagerStart', function ($serv) use ($config) {
            $manager_start = new OnManagerStart();
            $manager_start::run($serv, $config);
        });

        self::$serv->on('WorkerStart', function ($serv, $worker_id) use ($config) {
            $worker_start = new OnWorkerStart();
            $worker_start::run($serv, $worker_id, $config);
        });

        //TCP
        $tcp = self::$serv->listen($config['ip'], $config['tcp_port'], SWOOLE_SOCK_TCP);
        $tcp->set($config['tcp_set']);
        $tcp->on('Receive', function ($serv, $fd, $reactor_id, $data) {
            $receive = new OnReceive();
            $receive::run($serv, $fd, $reactor_id, $data);
        });

        //UDP
        $udp = self::$serv->listen($config['ip'], $config['udp_port'], SWOOLE_SOCK_UDP);
        $udp->set($config['udp_set']);
        $udp->on('Packet', function ($serv, $data, $client_info) {
            $packet = new OnPacket();
            $packet::run($serv, $data, $client_info);
        });

        self::$serv->on('Task', function ($serv, $task_id, $src_worker_id, $data) use ($config) {
            $task = new OnTask();
            $dataArr = json_decode($data, true);
            switch ($dataArr['server']) {
                case "tcp":
                    $task::tcp_task_run($serv, $task_id, $src_worker_id, $data);
                    break;
                case "ws":
                    $task::ws_task_run($serv, $task_id, $src_worker_id, $data);
                    break;
            }
        });

        self::$serv->on('Open', function ($serv, $request) {
            echo output("onOpen: handshake success with fd={$request->fd}");
        });

        self::$serv->on('Message', function ($serv, $frame) {
            $message = new OnMessage();
            $message::run($serv, $frame);
        });

        self::$serv->on('Request', function ($request, $response) {
            $req = new OnRequest();
            $req::run($request, $response);
        });

        self::$serv->on('Finish', function ($serv, $task_id, $data) {
            $finish = new OnFinish();
            $finish::run($serv, $task_id, $data);
        });

        self::$serv->on('Close', function ($serv, $fd, $reactor_id){
            try {
                echo output('客户端关闭');
            } catch(Exception $e) {
            }
        });

        self::$serv->on('Shutdown', function ($serv) {
           echo output("服务关闭");
        });

        self::showProcessUI();

        self::$serv->start();
    }

    protected static function workerStatus() {
        $config = get_config();

        if (!file_exists($config['master_pid_file']) ||
            !file_exists($config['manager_pid_file']) ||
            !file_exists($config['worker_pid_file']) ) {
            echo output("暂无启动的服务");
            return false;
        }

        self::showProcessUI($config);

        $masterPidString = trim(@file_get_contents($config['master_pid_file']));
        $masterPidArr    = explode( '-', $masterPidString);

        echo str_pad("Master", 18, ' ', STR_PAD_BOTH ).
            str_pad($config['master_process_name'], 26, ' ', STR_PAD_BOTH ).
            str_pad($masterPidArr[0], 16, ' ', STR_PAD_BOTH ).
            str_pad($masterPidArr[1], 16, ' ', STR_PAD_BOTH ).
            str_pad($masterPidArr[2], 16, ' ', STR_PAD_BOTH ).PHP_EOL;

        $managerPidString = trim(@file_get_contents($config['manager_pid_file']));
        $managerPidArr    = explode( '-', $managerPidString);

        echo str_pad("Manager", 20, ' ', STR_PAD_BOTH ).
            str_pad($config['manager_process_name'], 24, ' ', STR_PAD_BOTH ).
            str_pad($managerPidArr[0], 16, ' ', STR_PAD_BOTH ).
            str_pad($managerPidArr[1], 16, ' ', STR_PAD_BOTH ).
            str_pad($managerPidArr[2], 16, ' ', STR_PAD_BOTH ).PHP_EOL;


        $workerPidString = rtrim(@file_get_contents($config['worker_pid_file']), '|' );
        $workerPidArr    = explode( '|', $workerPidString );
        if (isset($workerPidArr) && !empty($workerPidArr)) {
            foreach ($workerPidArr as $key => $val) {
                $v = explode( '-', $val);
                echo str_pad("Worker", 18, ' ', STR_PAD_BOTH ).
                     str_pad($config['worker_process_name'], 26, ' ', STR_PAD_BOTH ).
                     str_pad($v[0], 16, ' ', STR_PAD_BOTH ).
                     str_pad($v[1], 16, ' ', STR_PAD_BOTH ).
                     str_pad($v[2], 16, ' ', STR_PAD_BOTH ).PHP_EOL;
            }
        }

        $taskPidString = rtrim(@file_get_contents($config['task_pid_file']), '|' );
        $taskPidArr  = explode( '|', $taskPidString );
        if (isset($taskPidArr) && !empty($taskPidArr)) {
            foreach ($taskPidArr as $key => $val) {
                $v = explode( '-', $val);
                echo str_pad("Task", 18, ' ', STR_PAD_BOTH ).
                     str_pad($config['task_process_name'], 24, ' ', STR_PAD_BOTH ).
                     str_pad($v[0], 20, ' ', STR_PAD_BOTH ).
                     str_pad($v[1], 12, ' ', STR_PAD_BOTH ).
                     str_pad($v[2], 20, ' ', STR_PAD_BOTH ).PHP_EOL;
            }
        }
    }

    protected static function workerReload() {
        $config = get_config();

        if (!file_exists($config['master_pid_file'])) {
            echo output("暂无启动的服务");
            return false;
        }

        $masterPidString = trim(file_get_contents($config['master_pid_file']));
        $masterPidArr    = explode( '-', $masterPidString);

        if (!swoole_process::kill($masterPidArr[0], 0)) {
            echo output("PID:{$masterPidArr[0]} 不存在");
            return false;
        }

        swoole_process::kill($masterPidArr[0], SIGUSR1);

        @unlink($config['worker_pid_file']);
        @unlink($config['task_pid_file']);

        echo output("热加载成功");
        return true;
    }

    protected static function workerStop() {
        $config = get_config();

        if (!file_exists($config['master_pid_file'])) {
            echo output("暂无启动的服务");
            return false;
        }

        $masterPidString = trim(file_get_contents($config['master_pid_file']));
        $masterPidArr    = explode( '-', $masterPidString);

        if (!swoole_process::kill($masterPidArr[0], 0)) {
            echo output("PID:{$masterPidArr[0]} 不存在");
            return false;
        }

        swoole_process::kill($masterPidArr[0]);

        $time = time();
        while (true) {
            usleep(2000);
            if (!swoole_process::kill($masterPidArr[0], 0)) {
                unlink($config['master_pid_file']);
                unlink($config['manager_pid_file']);
                unlink($config['worker_pid_file']);
                unlink($config['task_pid_file']);
                echo output("服务关闭成功");
                break;
            } else {
                if (time() - $time > 5) {
                    echo output("服务关闭失败，请重试");
                    break;
                }
            }
        }
        return true;
    }

    protected static function showProcessUI() {
        $config = get_config();
        if ($config['set']['daemonize'] == true) {
            return false;
        }
        echo str_pad("-", 90, '-', STR_PAD_BOTH) . PHP_EOL;
        echo "|" . str_pad("启动/关闭", 92, ' ', STR_PAD_BOTH) . "|" . PHP_EOL;
        echo str_pad("-", 90, '-', STR_PAD_BOTH) . PHP_EOL;

        echo str_pad("Start success.", 50, ' ', STR_PAD_BOTH) .
            str_pad("php index.php stop", 50, ' ', STR_PAD_BOTH) . PHP_EOL;

        echo str_pad("-", 90, '-', STR_PAD_BOTH) . PHP_EOL;
        echo "|" . str_pad("版本信息", 92, ' ', STR_PAD_BOTH) . "|" . PHP_EOL;
        echo str_pad("-", 90, '-', STR_PAD_BOTH) . PHP_EOL;

        echo str_pad("Swoole Version:" . SWOOLE_VERSION, 50, ' ', STR_PAD_BOTH) .
            str_pad("PHP Version:" . PHP_VERSION, 50, ' ', STR_PAD_BOTH) . PHP_EOL;

        echo str_pad("-", 90, '-', STR_PAD_BOTH) . PHP_EOL;
        echo "|" . str_pad("IP 信息", 90, ' ', STR_PAD_BOTH) . "|" . PHP_EOL;
        echo str_pad("-", 90, '-', STR_PAD_BOTH) . PHP_EOL;

        echo str_pad("IP:" . $config['ip'], 50, ' ', STR_PAD_BOTH) .
            str_pad("PORT:" . $config['websocket_port'], 50, ' ', STR_PAD_BOTH) . PHP_EOL;

        echo str_pad("-", 90, '-', STR_PAD_BOTH) . PHP_EOL;
        echo "|" . str_pad("进程信息", 92, ' ', STR_PAD_BOTH) . "|" . PHP_EOL;
        echo str_pad("-", 90, '-', STR_PAD_BOTH) . PHP_EOL;

        echo str_pad("Swoole进程", 20, ' ', STR_PAD_BOTH) .
            str_pad('进程别名', 30, ' ', STR_PAD_BOTH) .
            str_pad('进程ID', 18, ' ', STR_PAD_BOTH) .
            str_pad('父进程ID', 18, ' ', STR_PAD_BOTH) .
            str_pad('用户', 18, ' ', STR_PAD_BOTH) . PHP_EOL;
    }

    protected static function signalHandler() {
        //TODO 未完成
        //swoole_process::signal(SIGINT, function ($signal) {
        //    echo $signal;
        //    return;
        //});
    }
}

```

## 小结

耗费了 3 个晚上的时间，终于完成了一个初版，比较初级，希望可以给入门的同学一个参考吧。

当然我自己也会继续完善它，后期的一些新知识点会集成到这里面，做成自己迭代的小项目。

初版比较糙，不喜勿喷。

后期会新增：

- RPC
- Coroutine - MySQL
- Coroutine - Redis
- Process
- ...

## 源码

[查看源码](https://github.com/xinliangnote/Swoole/blob/master/codes/07-Swoole%20整合成一个小框架)
