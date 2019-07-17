## 概述

**什么是 WebSocket ？**

> WebSocket 是一种在单个TCP连接上进行全双工通信的协议。

WebSocket 使得客户端和服务器之间的数据交换变得更加简单，允许服务端主动向客户端推送数据。

在 WebSocket API 中，浏览器和服务器只需要完成一次握手，两者之间就直接可以创建持久性的连接，并进行双向数据传输。

我们利用 WebSocket 进行及时通讯，今天实现一个 **视频弹幕效果**。

实现弹幕其实就和群聊类似，将消息推送给所有的客户端，只不过前端的展示所有不同。

本地版本：

- 后端 PHP 7.2.6、Swoole 4.3.1。
- 前端 HTML5 WebSocket、Canvas。

废话不多说，先看效果。

批量版：

![](https://github.com/xinliangnote/Swoole/blob/master/images/4_swoole_1.gif)

![](https://github.com/xinliangnote/Swoole/blob/master/images/4_swoole_2.gif)

手动版：

![](https://github.com/xinliangnote/Swoole/blob/master/images/4_swoole_3.gif)

## 代码

**server.php**

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
            if ($connectionInfo['websocket_status'] == 3) {
                $serv->push($fd, $msg); //长度最大不得超过2M
            }
        }
        $serv->finish($data);
        echo "########".PHP_EOL.PHP_EOL;
    }

    public function onMessage($serv, $frame) {
        echo "#### onMessage ####".PHP_EOL;
        echo "receive from fd{$frame->fd}:{$frame->data},opcode:{$frame->opcode},fin:{$frame->finish}".PHP_EOL;
        $serv->task(['type' => 'speak', 'msg' => $frame->data]);
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
}

$server = new Server();
```

**index.php**

```
<!DOCTYPE html>
<html lang="zh-CN">
	<head>
	    <meta charset="utf-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<meta name="description" content="">
		<meta name="keywords" content="">
		<script src="js/canvasBarrage.js?v=17"></script>
		<title>视频弹幕Demo</title>
        <style>
            .canvas-barrage {
                position: absolute;
                width: 960px;
                height: 540px;
                pointer-events: none;
                z-index: 1;
            }
            .ui-input {
                height: 20px;
                width: 856px;
                line-height: 20px;
                border: 1px solid #d0d0d5;
                border-radius: 4px;
                padding: 9px 8px;
            }
            .ui-button {
                display: inline-block;
                background-color: #2486ff;
                line-height: 28px;
                text-align: center;
                border-radius: 4px;
                color: #fff;
                font-size: 14px;
            }
        </style>
	</head>
	<body>
        <canvas id="canvasBarrage" class="canvas-barrage"></canvas>
        <video id="videoBarrage" width="960" height="540" src="./video/video.mp4" controls></video>
            <p>
                <input class="ui-input" id="msg" name="value" value="发送弹幕" required>
                <input class="ui-button" type="button" id="sendBtn" value="发送弹幕">
            </p>
        <script>
            if ("WebSocket" in window) {
                // 弹幕数据
                var dataBarrage = [{
                    value: '',
                    time: 0, // 单位秒
                    speed: 0,
                    fontSize: 0
                }];

                var itemsColor = ['#FFA54F','#FF4040','#EE1289', '#8E8E38', '#3A5FCD', '#00EE76', '#388E8E', '#76EEC6', '#87CEFF', '#7FFFD4'];

                var eleCanvas = document.getElementById('canvasBarrage');
                var eleVideo = document.getElementById('videoBarrage');

                var barrage = new CanvasBarrage(eleCanvas, eleVideo, {
                    data: dataBarrage
                });

                var wsServer = 'ws://10.211.55.3:9501';
                var ws = new WebSocket(wsServer);

                ws.onopen = function (evt) {
                    if (ws.readyState == 1) {
                        console.log('WebSocket 连接成功...');
                    } else {
                        console.log('WebSocket 连接失败...');
                    }
                };

                ws.onmessage = function (evt) {

                    barrage.add({
                        value: evt.data,
                        time: eleVideo.currentTime,
                        speed: 5,
                        color: itemsColor[Math.floor(Math.random()*itemsColor.length)]
                        // 其它如 fontSize, opacity等可选
                    });
                    console.log('Retrieved data from server: ' + evt.data);
                };

                ws.onerror = function (evt) {
                    alert('WebSocket 发生错误');
                    console.log(evt);
                };

                ws.onclose = function() {
                    alert('WebSocket 连接关闭');
                    console.log('WebSocket 连接关闭...');
                };

                var msg;
                var sendBtn = document.getElementById('sendBtn');
                sendBtn.onclick = function(){
                    if (ws.readyState == 1) {
                        msg = document.getElementById('msg').value;
                        ws.send(msg);
                    } else {
                        alert('WebSocket 连接失败');
                    }
                };
            } else {
                alert("您的浏览器不支持 WebSocket!");
            }
            </script>
	</body>
</html>
```
## 小结

**一、单聊提供了方法，群聊提供方法了吗？**

官方没有提供群聊的方法，使用循环实现的。

单聊：
```
$serv->push($fd, $msg);
```

群聊：

```
foreach ($serv->connections as $fd) {
    $serv->push($fd, $msg);
}
```

**二、发送消息为什么要放到Task中，封装一个普通方法不行吗？**

不能封装成一个普通的方法，要放在Task中使用多进程执行。

如果想了解 Swoole Task 的知识，请看： [第二篇：Swoole Timer 的应用](https://mp.weixin.qq.com/s/9ek_Ol-mLRQCPOTyl8v9Zg)。

**三、如何模拟批量弹幕效果？**

可以使用 swoole_timer_tick ，比如：

```
swoole_timer_tick(50, function () use($serv){
    $serv->task([
        'type' => 'login'
    ]);
});
```

**四、前端使用的哪个弹幕插件？还有没有其他的？**

canvasBarrage.js：

http://www.zhangxinxu.com/wordpress/?p=6386

有其他的，比如：

- Jquery.barrager.js
- Jquery.danmu.js
- DanMuer.js

根据自己喜欢风格，进行尝试吧。

**五、Demo 中视频全屏后，还显示弹幕吗？**

不显示。

![](https://github.com/xinliangnote/Swoole/blob/master/images/4_swoole_3.gif)

当点击如上图中的 “全屏” 时，弹幕就不显示了，因为这时全屏的视频已经脱离了HTML文档，具体能否实现大家可以研究研究（记得考虑 PC、Android、iOS 等兼容性）。

也可以用“伪全屏”进行实现，自定义一个全屏按钮，点击时让当前页面全屏，同时让视频尺寸变大。

**六、看了这篇文章，单聊和群聊都会了，能实现一个在线IM吗？**

不能。

真正使用的在线IM系统，需求落地时比我们想象到要复杂的多，自己深入研究没问题，想开发一套生产环境用的IM系统，需要慎重，特别是后端用PHP。

如果急需在线IM系统，可以使用市面上专业的IM系统。

**七、弹幕有什么应用场景？**

比如，办年会或活动开场时大家可以利用弹幕活跃气氛，使用微信扫码登录后进行发送实时弹幕，还可以应用到直播，只要觉得合理都可以使用。

**八、Swoole WebSocket 入门还可以实现什么案例？**

可以实现聊天室功能、直播功能、扫码登录功能等。

**温馨提示**

本 Demo 仅仅是简单的实现，如需应用到真实场景中还要多做优化。

## 源码

[查看源码](https://github.com/xinliangnote/Swoole/blob/master/codes/04-Swoole%20WebSocket%20的应用)

