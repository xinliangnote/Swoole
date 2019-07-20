<?php

if (!defined('SERVER_PATH')) exit("No Access");

class OnPacket
{
    public static function run($serv, $data, $client_info)
    {
        try {
            echo output("onPacket: Address:{$client_info['address']} Port:{$client_info['port']} Data:{$data}");
            $serv->sendto($client_info['address'], $client_info['port'], "Server Return:".$data);
        } catch(Exception $e) {
        }
    }
}
