## 项目介绍

[Swoole RPC 的实现](https://github.com/xinliangnote/Swoole/blob/master/08-Swoole%20RPC%20的实现.md)

## 配置

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

## 效果图

![](https://github.com/xinliangnote/Swoole/blob/master/images/8_swoole_1.png)
