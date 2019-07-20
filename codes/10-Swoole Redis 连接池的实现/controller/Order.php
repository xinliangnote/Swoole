<?php

if (!defined('SERVER_PATH')) exit("No Access");

class Order
{
    private $mysql;
    private $table;

    public function __construct()
    {
        $pool = MysqlPool::getInstance();
        $this->mysql = $pool->get();
        $this->table = 'order';
    }

    public function add($code = '', $name = '')
    {
        //TODO 验证
        return $this->mysql->insert($this->table, ['code' => $code, 'name' => $name]);
    }

    public function edit($id = 0,  $name='')
    {
        //TODO 验证
        return $this->mysql->update($this->table, ['name' => $name], ['id' => $id]);
    }

    public function del($id = 0)
    {
        //TODO 验证
        return $this->mysql->delete($this->table, ['id' => $id]);
    }

    public function info($code = '')
    {
        //TODO 验证
        return $this->mysql->select($this->table, ['code' => $code]);
    }
}
