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