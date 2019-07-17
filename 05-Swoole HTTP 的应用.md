## 概述

我们都知道 HTTP 是一种协议，允许 WEB 服务器和浏览器通过互联网进行发送和接受数据。

想对 HTTP 进行详细的了解，可以找下其他文章。

我们在网上能看到的界面，图片，动画，音频，视频 等，都有依赖这个协议的。

在做 WEB 系统的时候，都使用过 IIS、Apache、Nginx 吧，我们利用 Swoole 也可以 简单的实现一个 WEB 服务器。

主要使用了 HTTP 的两大对象：Request 请求对象、Response 响应对象。

Request，包括 GET、POST、COOKIE、Header等。

Response，包括 状态、响应体、跳转、发送文件等。

不多说，分享两个程序：

- 一、实现一个基础的 Demo：“你好，Swoole.” 
- 二、实现一个简单的 路由控制

本地版本：

- PHP 7.2.6
- Swoole 4.3.1

## 代码

**一、Demo：“你好，Swoole.”**

示例效果：

![](https://github.com/xinliangnote/Swoole/blob/master/images/5_swoole_1.png)

备注：IP 地址是我的虚拟机。

示例代码：

```
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
```

**二、路由控制**

示例效果：

![](https://github.com/xinliangnote/Swoole/blob/master/images/5_swoole_2.gif)

目录结构：

```
├─ swoole_http  -- 代码根目录
│  ├─ server.php
│  ├─ controller
│     ├── Index.php
│     ├── Login.php
```

示例代码：

server.php

```
<?php

class Server
{
    private $serv;

    public function __construct() {
        $this->serv = new swoole_http_server("0.0.0.0", 9501);
        $this->serv->set([
            'worker_num'      => 2, //开启2个worker进程
            'max_request'     => 4, //每个worker进程 max_request设置为4次
            'document_root'   => '',
            'enable_static_handler' => true,
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
        swoole_set_process_name('swoole_process_server_master');

        echo "SWOOLE ".SWOOLE_VERSION . " 服务已启动".PHP_EOL;
        echo "master_pid: {$serv->master_pid}".PHP_EOL;
        echo "manager_pid: {$serv->manager_pid}".PHP_EOL;
        echo "########".PHP_EOL.PHP_EOL;
    }

    public function onManagerStart($serv) {
        echo "#### onManagerStart ####".PHP_EOL.PHP_EOL;
        swoole_set_process_name('swoole_process_server_manager');
    }

    public function onWorkStart($serv, $worker_id) {
        echo "#### onWorkStart ####".PHP_EOL.PHP_EOL;
        swoole_set_process_name('swoole_process_server_worker');

        spl_autoload_register(function ($className) {
            $classPath = __DIR__ . "/controller/" . $className . ".php";
            if (is_file($classPath)) {
                require "{$classPath}";
                return;
            }
        });
    }

    public function onRequest($request, $response) {
        $response->header("Server", "SwooleServer");
        $response->header("Content-Type", "text/html; charset=utf-8");
        $server = $request->server;
        $path_info    = $server['path_info'];
        $request_uri  = $server['request_uri'];

        if ($path_info == '/favicon.ico' || $request_uri == '/favicon.ico') {
            return $response->end();
        }

        $controller = 'Index';
        $method     = 'home';


        if ($path_info != '/') {
            $path_info = explode('/',$path_info);
            if (!is_array($path_info)) {
                $response->status(404);
                $response->end('URL不存在');
            }

            if ($path_info[1] == 'favicon.ico') {
                return;
            }

            $count_path_info = count($path_info);
            if ($count_path_info > 4) {
                $response->status(404);
                $response->end('URL不存在');
            }

            $controller = (isset($path_info[1]) && !empty($path_info[1])) ? $path_info[1] : $controller;
            $method = (isset($path_info[2]) && !empty($path_info[2])) ? $path_info[2] : $method;
        }

        $result = "class 不存在";

        if (class_exists($controller)) {
            $class = new $controller();
            $result = "method 不存在";
            if (method_exists($controller, $method)) {
                $result = $class->$method($request);
            }
        }

        $response->end($result);
    }
}

$server = new Server();
```

Index.php

```
<?php

class Index
{
    public function home($request)
    {
        $get = isset($request->get) ? $request->get : [];

        //@TODO 业务代码

        $result = "<h1>你好，Swoole。</h1>";
        $result.= "GET参数：".json_encode($get);
        return $result;
    }
}
```

Login.php

```
<?php

class Login
{
    public function index($request)
    {
        $post = isset($request->post) ? $request->post : [];

        //@TODO 业务代码

        return "<h1>登录成功。</h1>";
    }
}
```

## 小结

**一、Swoole 可以替代 Nginx 吗？**

暂时不能，随着 Swoole 越来越强大，以后说不准。

官方建议 Swoole 与 Nginx 结合使用。

> Http\Server 对 Http 协议的支持并不完整，建议仅作为应用服务器。并且在前端增加Nginx作为代理。

根据自己的 Nginx 配置文件，可以自行调整。

比如：新增一个配置文件

enable-swoole-php.conf

```
location ~ [^/]\.php(/|$)
{
    proxy_http_version 1.1;
    proxy_set_header Connection "keep-alive";
    proxy_set_header X-Real-IP $remote_addr;
    proxy_pass http://127.0.0.1:9501;
}
```
我们都习惯会将虚拟域名的配置文件放在 vhost 文件夹中。

比如，虚拟域名的配置文件为：local.swoole.com.conf，可以选择加载 enable-php.conf ，也可以选择加载 enable-swoole-php.conf。

配置文件供参考：

```
server
    {
        listen 80;
        #listen [::]:80;
        server_name local.swoole.com ;
        index index.html index.htm index.php default.html default.htm default.php;
        root  /home/wwwroot/project/swoole;

        #include rewrite/none.conf;
        #error_page   404   /404.html;

        #include enable-php.conf;
        include enable-swoole-php.conf;

        location ~ .*\.(gif|jpg|jpeg|png|bmp|swf)$
        {
            expires      30d;
        }

        location ~ .*\.(js|css)?$
        {
            expires      12h;
        }

        location ~ /.well-known {
            allow all;
        }

        location ~ /\.
        {
            deny all;
        }

        access_log  /home/wwwlogs/local.swoole.com.log;
    }
```

如果直接编辑 server 段的代码也是可以的。

**二、修改了 controller 文件夹中的业务代码，每次都是重启服务才生效吗？**

不是，每次重启服务会影响到正常用户使用的，正常处理的请求会被强制关闭。

在本地运行路由的代码时，试试这个命令：

```
ps aux | grep swoole_process_server_master | awk '{print $2}' | xargs kill -USR1
```

给 master 进程发送一个 USR1 的信号，当 Swoole Server 接到这个信号后，就会让所有 worker 在处理完当前的请求后，进行重启。

如果查看所有的进程，试试这个命令：

```
ps -ef | grep 'swoole_process_server' | grep -v 'grep'
```

## 扩展

- 可以试着上传文件，做一个小的FTP服务器。
- 可以试着整合到目前正在使用的PHP框架中。
- 可以学习一些Swoole开源框架：EasySwoole、Swoft、One。

## 源码

[查看源码](https://github.com/xinliangnote/Swoole/blob/master/codes/05-Swoole%20HTTP%20的应用)
