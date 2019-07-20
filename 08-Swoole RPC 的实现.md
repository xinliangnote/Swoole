## 概述

有位读者说 “上篇文章，下载代码后直接运行成功，代码简洁明了，简直是 Swoole 入门最好的 Demo ”。

“哈哈哈...”

还有读者说 “有一起学习的组织群吗，可以在里面进行疑难答疑？”

这个还真没有，总觉得维护一个微信群不容易，因为自己本身就不爱在群里说话，另外，自己也在很多微信群中，开始氛围挺好的，大家都聊聊技术，后来技术聊的少了改成聊八卦啦，再后来慢慢就安静了，还有在群里起冲突的...

当然我也知道维护一个微信群的好处是非常大的，如果有这方面经验的同学，咱们一起交流交流 ~ 

还有出版社找我写书的.

他们也真是放心，我自己肚子里几滴墨水还是知道的，目前肯定是不行，以后嘛，再说。

还有一些大佬加了微信，可能是出于对晚辈的提携吧，偷偷告诉你，从大佬的朋友圈能学到很多东西。

我真诚的建议，做技术的应该自己多总结总结，将自己会的东西写出来分享给大家，先不说给别人带来太多的价值，反正对自己的帮助是非常非常大的，这方面想交流的同学，可以加我，我可以给你无私分享。

可能都会说时间少，时间只要挤，总会有的，每个人都 24 小时，时间对每个人是最公平的。说到这推荐大家读一下《暗时间》这本书，这是我这本书的 [读书笔记](https://mp.weixin.qq.com/s/RY3hnxZk0vxq5UovNeSIeg)，大家可以瞅瞅。

开始今天的文章吧，这篇文章实现了一个简单的 RPC 远程调用，在实现之前需要先了解什么是 RPC，不清楚的可以看下之前发的这篇文章 [《我眼中的 RPC》](https://mp.weixin.qq.com/s/na3uygRGXli_Uvw3fmaNaQ)。

下面的演示代码主要使用了 Swoole 的 Task 任务池，通过 OnRequest/OnReceive 获得信息交给 Task 去处理。

举个工作中的例子吧，在电商系统中的两个模块，个人中心模块和订单管理模块，这两个模块是独立部署的，可能不在一个机房，可能不是一个域名，现在个人中心需要通过 用户ID 和 订单类型 获取订单数据。

## 实现效果

#### 客户端

- HTTP 请求

```
//代码片段
<?php
$demo = [
    'type'  => 'SW',
    'token' => 'Bb1R3YLipbkTp5p0',
    'param' => [
        'class'  => 'Order',
        'method' => 'get_list',
        'param' => [
            'uid'  => 1,
            'type' => 2,
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
- TCP 请求

```
//代码片段
$demo = [
    'type'  => 'SW',
    'token' => 'Bb1R3YLipbkTp5p0',
    'param' => [
        'class'  => 'Order',
        'method' => 'get_list',
        'param' => [
            'uid'  => 1,
            'type' => 2,
        ],
    ],
];
$this->client->send(json_encode($demo));
```

#### 请求方式

- SW 单个请求，等待结果

    发出请求后，分配给 Task ，并等待 Task 执行完成后，再返回。
    
- SN 单个请求，不等待结果

    发出请求后，分配给 Task 之后，就直接返回。

#### 发送数据

```
$demo = [
    'type'  => 'SW',
    'token' => 'Bb1R3YLipbkTp5p0',
    'param' => [
        'class'  => 'Order',
        'method' => 'get_list',
        'param' => [
            'uid'  => 1,
            'type' => 2,
        ],
    ],
];
```

- type 同步/异步设置
- token 可进行权限验证
- class 请求的类名
- method 请求的方法名
- uid 参数一
- type 参数二

#### 返回数据

![](https://github.com/xinliangnote/Swoole/blob/master/images/8_swoole_1.png)

- request_method 请求方式
- request_time 请求开始时间
- response_time 请求结束时间
- code 标识
- msg 标识值
- data 约定数据
- query 请求参数

## 代码

#### OnRequest.php

```
<?php

if (!defined('SERVER_PATH')) exit("No Access");

class OnRequest
{
    private static $query;
    private static $code;
    private static $msg;
    private static $data;

    public static function run($serv, $request, $response)
    {
        try {
            $data = decrypt($request->rawContent());
            self::$query   = $data;
            if (empty($data)) {
                self::$code = '-1';
                self::$msg  = '非法请求';
                self::end($request, $response);
            }

            //TODO 验证Token

            switch ($data['type']) {
                case 'SW': //单个请求,等待结果
                    $task = [
                        'request' => $data,
                        'server'  => 'http'
                    ];
                    $rs = $serv->task(json_encode($task), -1, function ($serv, $task_id, $rs_data) use ($request, $response) {
                        self::$code = '1';
                        self::$msg  = '成功';
                        self::$data = $rs_data['response'];
                        self::end($request, $response);
                    });
                    if ($rs === false) {
                        self::$code = '-1';
                        self::$msg  = '失败';
                        self::end($request, $response);
                    }
                    break;

                case 'SN': //单个请求,不等待结果
                    $task = [
                        'request' => $data,
                        'server'  => 'http'
                    ];
                    $rs = $serv->task(json_encode($task));
                    if ($rs === false) {
                        self::$code = '-1';
                        self::$msg  = '失败';
                    } else {
                        self::$code = '1';
                        self::$msg  = '成功';
                    }
                    self::end($request, $response);
                    break;
                default:
                    self::$code = '-1';
                    self::$msg  = '非法请求';
                    self::end($request, $response);
            }
        } catch(Exception $e) {
        }
    }

    private static function end($request = null, $response = null)
    {
        $rs['request_method'] = $request->server['request_method'];
        $rs['request_time']   = $request->server['request_time'];
        $rs['response_time']  = time();
        $rs['code']           = self::$code;
        $rs['msg']            = self::$msg;
        $rs['data']           = self::$data;
        $rs['query']          = self::$query;
        $response->end(json_encode($rs));
        self::$data = [];
        return;
    }
}
```

#### OnReceive.php

```
<?php

if (!defined('SERVER_PATH')) exit("No Access");

class OnReceive
{
    private static $request_time;
    private static $query;
    private static $code;
    private static $msg;
    private static $data;

    public static function run($serv, $fd, $reactor_id, $data)
    {
        try {
            self::$request_time = time();
            $data = decrypt($data);
            self::$query = $data;

            //TODO 验证Token

            
            switch ($data['type']) {
                case 'SW': //单个请求,等待结果
                    $task = [
                        'fd'           => $fd,
                        'request'      => $data,
                        'server'       => 'tcp',
                        'request_time' => self::$request_time,
                    ];
                    $rs = $serv->task(json_encode($task));
                    if ($rs === false) {
                        self::$code = '-1';
                        self::$msg  = '失败';
                        self::handlerTask($serv, $fd);
                    }
                    break;

                case 'SN': //单个请求,不等待结果
                    $task = [
                        'fd'           => $fd,
                        'request'      => $data,
                        'server'       => 'tcp',
                        'request_time' => self::$request_time,
                    ];
                    $rs = $serv->task(json_encode($task));
                    if ($rs === false) {
                        self::$code = '-1';
                        self::$msg  = '失败';
                    } else {
                        self::$code = '1';
                        self::$msg  = '成功';
                    }
                    self::handlerTask($serv, $fd);
                    break;
                default:
                    self::$code = '-1';
                    self::$msg  = '非法请求';
                    self::handlerTask($serv, $fd);
            }
        } catch(Exception $e) {
        }
    }

    private static function handlerTask($serv, $fd)
    {
        $rs['request_method'] = 'TCP';
        $rs['request_time']   = self::$request_time;
        $rs['response_time']  = time();
        $rs['code']           = self::$code;
        $rs['msg']            = self::$msg;
        $rs['data']           = self::$data;
        $rs['query']          = self::$query;
        $serv->send($fd, json_encode($rs));
    }
}
```

## 小结

Demo 代码仅供参考，里面有很多不严谨的地方！

服务的调用方与提供方中间需要有一个服务注册中心，很显然上面的代码中没有，需要自己去实现。

服务注册中心，负责管理 IP、Port 信息，提供给调用方使用，还要能负载均衡和故障切换。

根据自己的情况，服务注册中心实现可容易可复杂，用 Redis 也行，用 Zookeeper、Consul 也行。

感兴趣的也可以了解下网关 Kong ，包括 身份认证、权限认证、流量控制、监控预警...

再推荐一个 Swoole RPC 框架 Hprose，支持多语言。

就到这了。

## 源码

[查看源码](https://github.com/xinliangnote/Swoole/blob/master/codes/08-Swoole%20RPC%20的实现)
