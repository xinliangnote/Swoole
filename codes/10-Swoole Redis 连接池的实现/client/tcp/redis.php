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