<?php

if (!defined('SERVER_PATH')) exit("No Access");

class OnOpen
{
    public static function run($serv, $request)
    {
        try {
            echo output("onOpen: handshake success with fd={$request->fd}");

        } catch(Exception $e) {
        }
    }
}
