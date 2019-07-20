## 概述

收到读者反馈，“亮哥，文章能多点图片吗？就是将运行结果以图片的形式展示...”

我个人觉得这是比较懒、动手能力差的表现，恩... 要勤快些。

但谁让文章是写给你们看的那，我以后尽量文章写的图文并茂一点。

上篇文章 分享了 MySQL 连接池，这篇文章 咱们来分享下 Redis 连接池。

在上篇文章的基础上进行简单调整即可，将实例化 MySQL 的地方，修改成实例化 Redis 即可，还要注意一些方法的调整。

这篇文章仅仅只实现一个 Redis 连接池，篇幅就太少了，顺便将前几篇整合一下。

大概 Demo 中包含这些点：

- 实现 MySQL 连接池
- 实现 MySQL CURD 方法的定义
- 实现 Redis 连接池
- 实现 Redis 方法的定义
- 满足 HTTP、TCP、WebSocket 调用
- 提供 Demo 供测试
- 调整 目录结构

HTTP 调用：

- 实现 读取 MySQL 中数据的 Demo
- 实现 读取 Redis 中数据的 Demo

![](https://github.com/xinliangnote/Swoole/blob/master/images/10_swoole_1.png)

TCP 调用：

- 实现 读取 MySQL 中数据的 Demo
- 实现 读取 Redis 中数据的 Demo

![](https://github.com/xinliangnote/Swoole/blob/master/images/10_swoole_2.png)

WebSocket 调用：

- 实现 每秒展示 API 调用量 Demo

![](https://github.com/xinliangnote/Swoole/blob/master/images/10_swoole_3.gif)

## 目录结构

```
├─ client
│  ├─ http
│     ├── mysql.php //测试 MySQL 连接
│     ├── redis.php //测试 Redis 连接
│  ├─ tcp
│     ├── mysql.php //测试 MySQL 连接
│     ├── redis.php //测试 Redis 连接
│  ├─ websocket
│     ├── index.html //实现 API 调用量展示
├─ controller
│  ├─ Order.php     //实现 MySQL CURD
│  ├─ Product.php   //实现 Redis 调用
│  ├─ Statistic.php //模拟 API 调用数据
├─ server
│  ├─ config
│     ├── config.php //默认配置
│     ├── mysql.php  //MySQL 配置
│     ├── redis.php  //Redis 配置
│  ├─ core
│     ├── Common.php //公共方法
│     ├── Core.php   //核心文件
│     ├── HandlerException.php //异常处理
│     ├── callback //回调处理
│         ├── OnRequest.php
│         ├── OnReceive.php
│         ├── OnTask.php
│         ├── ...
│     ├── mysql
│         ├── MysqlDB.php
│         ├── MysqlPool.php
│     ├── redis
│         ├── RedisDB.php
│         ├── RedisPool.php
│  ├─ log  -- 需要 读/写 权限
│     ├── ...
├─ index.php //入口文件
```

## 代码

#### server/core/redis/RedisPool.php

```
<?php

if (!defined('SERVER_PATH')) exit("No Access");

class RedisPool
{
    private static $instance;
    private $pool;
    private $config;

    public static function getInstance($config = null)
    {
        if (empty(self::$instance)) {
            if (empty($config)) {
                throw new RuntimeException("Redis config empty");
            }
            self::$instance = new static($config);
        }
        return self::$instance;
    }

    public function __construct($config)
    {
        if (empty($this->pool)) {
            $this->config = $config;
            $this->pool = new chan($config['master']['pool_size']);
            for ($i = 0; $i < $config['master']['pool_size']; $i++) {
                go(function() use ($config) {
                    $redis = new RedisDB();
                    $res = $redis->connect($config);
                    if ($res === false) {
                        throw new RuntimeException("Failed to connect redis server");
                    } else {
                        $this->pool->push($redis);
                    }
                });
            }
        }
    }

    public function get()
    {
        if ($this->pool->length() > 0) {
            $redis = $this->pool->pop($this->config['master']['pool_get_timeout']);
            if (false === $redis) {
                throw new RuntimeException("Pop redis timeout");
            }
            defer(function () use ($redis) { //释放
                $this->pool->push($redis);
            });
            return $redis;
        } else {
            throw new RuntimeException("Pool length <= 0");
        }
    }
}
```

#### server/core/redis/RedisDB.php

```
<?php

if (!defined('SERVER_PATH')) exit("No Access");

class RedisDB
{
    private $master;
    private $slave;
    private $config;

    public function __call($name, $arguments)
    {
        // TODO 主库的操作
        $command_master = ['set', 'hset', 'sadd'];

        if (in_array($name, $command_master)) {
            $db = $this->_get_usable_db('slave');
        } else {
            $db = $this->_get_usable_db('master');
        }
        $result = call_user_func_array([$db, $name], $arguments);
        return $result;
    }

    public function connect($config)
    {
        //主库
        $master = new Swoole\Coroutine\Redis();
        $res = $master->connect($config['master']['host'], $config['master']['port']);
        if ($res === false) {
            throw new RuntimeException($master->errCode, $master->errMsg);
        } else {
            $this->master = $master;
        }

        //从库
        $slave = new Swoole\Coroutine\Redis();
        $res = $slave->connect($config['slave']['host'], $config['slave']['port']);
        if ($res === false) {
            throw new RuntimeException($slave->errCode, $slave->errMsg);
        } else {
            $this->slave = $slave;
        }

        $this->config = $config;
        return $res;
    }

    private function _get_usable_db($type)
    {
        if ($type == 'master') {
            if (!$this->master->connected) {
                $master = new Swoole\Coroutine\Redis();
                $res = $master->connect($this->config['master']['host'], $this->config['master']['port']);
                if ($res === false) {
                    throw new RuntimeException($master->errCode, $master->errMsg);
                } else {
                    $this->master = $master;
                }
            }
            return $this->master;
        } elseif ($type == 'slave') {
            if (!$this->slave->connected) {
                $slave = new Swoole\Coroutine\Redis();
                $res = $slave->connect($this->config['slave']['host'], $this->config['slave']['port']);
                if ($res === false) {
                    throw new RuntimeException($slave->errCode, $slave->errMsg);
                } else {
                    $this->slave = $slave;
                }
            }
            return $this->slave;
        }
    }
}
```

#### client/http/redis.php

```
<?php

$demo = [
    'type'  => 'SW',
    'token' => 'Bb1R3YLipbkTp5p0',
    'param' => [
        'class'  => 'Product',
        'method' => 'set',
        'param' => [
            'key'   => 'C4649',
            'value' => '订单-C4649'
        ],
    ],
];

$ch = curl_init();
$options = [
    CURLOPT_URL  => 'http://10.211.55.4:9509/',
    CURLOPT_POST => 1,
    CURLOPT_POSTFIELDS => json_encode($demo),
];
curl_setopt_array($ch, $options);
curl_exec($ch);
curl_close($ch);
```

#### client/tcp/redis.php

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
        if(!$fp = $this->client->connect("0.0.0.0", 9510, 1)) {
            echo "Error: {$fp->errMsg}[{$fp->errCode}]".PHP_EOL;
            return;
        }
    }

    public function onConnect() {

        fwrite(STDOUT, "测试RPC (Y or N):");
        swoole_event_add(STDIN, function() {
            $msg = trim(fgets(STDIN));
            if ($msg == 'y') {
                $this->send();
            }
            fwrite(STDOUT, "测试RPC (Y or N):");
        });
    }

    public function onReceive($cli, $data) {
        echo '[Received]:'.$data;
    }

    public function send() {
        $demo = [
            'type'  => 'SW',
            'token' => 'Bb1R3YLipbkTp5p0',
            'param' => [
                'class'  => 'Product',
                'method' => 'get',
                'param' => [
                    'code' => 'C4649'
                ],
            ],
        ];
        $this->client->send(json_encode($demo));
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

#### client/websocket/index.html

```
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="keywords" content="">
    <title>Demo</title>
    <script src="https://cdn.bootcss.com/jquery/3.4.1/jquery.js"></script>
    <script src="http://echarts.baidu.com/gallery/vendors/echarts/echarts.min.js"></script>
</head>
<body>
<!-- 为ECharts准备一个具备大小（宽高）的Dom -->
<div id="main" style="width: 900px;height:400px;"></div>
<script type="text/javascript">
    if ("WebSocket" in window) {
        // 基于准备好的dom，初始化echarts实例
        var myChart = echarts.init(document.getElementById('main'));
        var wsServer = 'ws://10.211.55.4:9509';
        var ws = new WebSocket(wsServer);

        ws.onopen = function (evt) {
            if (ws.readyState == 1) {
                console.log('WebSocket 连接成功...');
            } else {
                console.log('WebSocket 连接失败...');
            }

            if (ws.readyState == 1) {
                ws.send('开始请求...');
            } else {
                alert('WebSocket 连接失败');
            }
        };

        ws.onmessage = function (evt) {
            console.log('Retrieved data from server: ' + evt.data);
            var evt_data = jQuery.parseJSON(evt.data);
            myChart.setOption({
                xAxis: {
                    data : evt_data.time
                },
                series: [{
                    data: evt_data.value
                }]
            });

        };

        ws.onerror = function (evt) {
            alert('WebSocket 发生错误');
            console.log(evt);
        };

        ws.onclose = function() {
            alert('WebSocket 连接关闭');
            console.log('WebSocket 连接关闭...');
        };

        // 指定图表的配置项和数据
        $.ajax({
            url      : 'http://10.211.55.4:9509/', // 请求url
            type     : "post", // 提交方式
            dataType : "json", // 数据类型
            data : {
                'type'  : 'SW',
                'token' : 'Bb1R3YLipbkTp5p0',
                'param' : {
                    'class'  : 'Statistic',
                    'method' : 'init'
                }
            },
            beforeSend:function() {

            },
            success : function(rs) {
                if (rs.code != 1) {
                    alert('获取数据失败');
                } else {
                    var option = {
                        title: {
                            text: 'API 调用量',
                            x:'center'
                        },
                        tooltip: {
                            trigger: 'axis',
                            axisPointer: {
                                animation: false
                            }
                        },
                        xAxis: {
                            type : 'category',
                            data : rs.data.time
                        },
                        yAxis: {
                            type: 'value',
                            boundaryGap: [0, '100%'],
                            name: '使用量',
                            splitLine: {
                                show: false
                            }
                        },
                        series: [{
                            name: '使用量',
                            type: 'line',
                            showSymbol: false,
                            hoverAnimation: false,
                            data: rs.data.value
                        }]
                    };

                    // 使用刚指定的配置项和数据显示图表。
                    if (option && typeof option === "object") {
                        myChart.setOption(option, true);
                    }
                }
            },
            error : function(){
                alert('服务器请求异常');
            }
        });
    } else {
        alert("您的浏览器不支持 WebSocket!");
    }
</script>
</body>
</html>
```

还涉及到，OnMessage.php、OnTask.php 、OnWorkerStart.php 等，就不贴代码了。

## 运行

小框架的启动/关闭/热加载，看看这篇文章： [Swoole 整合成一个小框架](https://mp.weixin.qq.com/s/7nCUTqVrM0GvkJpLwsImfg)

里面 Demo 在 client 文件夹下。

http 目录下的文件，放到自己虚拟目录下，用浏览器访问。

tcp 目录下的文件，在 CLI 下运行。

websocket 目录下的文件，直接点击在浏览器访问。

## 扩展

官方协程 Redis 客户端手册：

https://wiki.swoole.com/wiki/page/589.html

大家可以尝试使用官方提供的其他方法。

## 源码

[查看源码](https://github.com/xinliangnote/Swoole/blob/master/codes/10-Swoole%20Redis%20连接池的实现)