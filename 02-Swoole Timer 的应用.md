## 你好，Swoole

PHP 的协程高性能网络通信引擎，使用 C/C++ 语言编写，提供了多种通信协议的网络服务器和客户端模块。

Swoole 可应用于互联网、移动通信、企业软件、网络游戏、物联网、车联网、智能家庭等领域。

学习 Swoole 之前，最好先了解下底层知识，比如，线程/进程、IO、TCP/IP协议 等。

推荐大家读一下《Linux 高性能服务器编程》这本书。

这篇文章主要分享 Timer 毫秒精度的定时器。

本地版本：PHP 7.2.6、Swoole 4.3.1。

## Timer

主要有三个方法：

swoole_timer_tick 间隔的时钟控制器

swoole_timer_after 指定的时间后执行

swoole_timer_clear 删除定时器

示例代码：

```
//每隔3000ms触发一次
$timer_id = swoole_timer_tick(3000, function () {
    echo "tick 3000ms - ".date('Y-m-d H:i:s')."\n";
});

//9000ms后删除定时器
swoole_timer_after(9000, function () use ($timer_id) {
    echo "after 9000ms - ".date('Y-m-d H:i:s')."\n";
    swoole_timer_clear($timer_id);
});
```

运行结果：
```
tick 3000ms - 2019-04-07 21:36:56
tick 3000ms - 2019-04-07 21:36:59
tick 3000ms - 2019-04-07 21:37:02
after 9000ms - 2019-04-07 21:37:02

```

## 应用场景

**一、比如，每天凌晨跑业务脚本，脚本中包括了请求其他业务方或第三方的接口，如果接口超时无响应或没有数据返回，需要进行重试。**

重试机制为：每5隔分钟再发送一次请求，最多尝试5次，在5次内成功停止该任务，5次仍失败也停止该任务。

示例代码：

```
$api_url  = 'xxx'; //接口地址
$exec_num = 0;     //执行次数
swoole_timer_tick(5*60*1000, function($timer_id) use ($api_url, &$exec_num) {
    $exec_num ++ ;
    $result = $this->requestUrl($api_url);
    echo date('Y-m-d H:i:s'). " 执行任务中...(".$exec_num.")\n";
    if ($result) {
        //业务代码...
        swoole_timer_clear($timer_id); // 停止定时器
        echo date('Y-m-d H:i:s'). " 第（".$exec_num."）次请求接口任务执行成功\n";
    } else {
        if ($exec_num >= 5) {
            swoole_timer_clear($timer_id); // 停止定时器
            echo date('Y-m-d H:i:s'). " 请求接口失败，已失败5次，停止执行\n";
        } else {
            echo date('Y-m-d H:i:s'). " 请求接口失败，5分钟后再次尝试\n";
        }
    }
});
```
运行结果：

```
2019-04-07 21:40:48 执行任务中...(1)
2019-04-07 21:40:48 请求接口失败，5分钟后再次尝试
2019-04-07 21:45:48 执行任务中...(2)
2019-04-07 21:45:48 请求接口失败，5分钟后再次尝试
2019-04-07 21:50:48 执行任务中...(3)
2019-04-07 21:50:48 请求接口失败，5分钟后再次尝试
2019-04-07 21:55:48 执行任务中...(4)
2019-04-07 21:55:48 请求接口失败，5分钟后再次尝试
2019-04-07 22:00:48 执行任务中...(5)
2019-04-07 22:00:48 请求接口失败，已失败5次，停止执行
```

**二、比如，设计一个用WEB界面管理管理定时任务的系统。**

Linux Crontab 最小时间粒度为分钟。

PHP Swoole 最小时间粒度为毫秒。

    0   1   2   3   4   5
    |   |   |   |   |   |
    |   |   |   |   |   +------ day of week (0 - 6) (Sunday=0)
    |   |   |   |   +------ month (1 - 12)
    |   |   |   +-------- day of month (1 - 31)
    |   |   +---------- hour (0 - 23)
    |   +------------ min (0 - 59)
    +-------------- sec (0-59)

WEB界面管理

- 登录、权限管理
- 任务管理（增删改查）
- 脚本机管理（机器IP地址）
- 任务日志

架构图

![](https://github.com/xinliangnote/Swoole/blob/master/images/2_swoole_1.png)

项目地址

https://github.com/osgochina/Donkey

**三、比如，监控服务器状况。**

## 参考文档

- https://wiki.swoole.com/wiki/page/p-timer.html

