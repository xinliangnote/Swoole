<?php

class Login
{
    public function index($request)
    {
        $post = isset($request->post) ? $request->post : [];

        //@TODO 业务代码

        return "<h1>登录成功。</h1>";
    }
}