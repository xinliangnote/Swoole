<!DOCTYPE html>
<html lang="zh-CN">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="">
        <meta name="keywords" content="">
        <script src="js/canvasBarrage.js"></script>
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
