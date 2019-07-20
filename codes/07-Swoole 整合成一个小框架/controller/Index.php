<?php

class Index
{
    public function home($request)
    {
        $get = isset($request->get) ? $request->get : [];

        //@TODO 业务代码

        $result  = "<h1>你好，Swoole。</h1>";
        $result .= "GET参数：".json_encode($get);


        $result .= "<h2>共有如下链接：</h2>";
        $result .= "<h3>/index/home</h3>";
        $result .= "<h3>/login/index</h3>";
        $result .= "<h3>/errorlog/index</h3>";

        return $result;
    }
}