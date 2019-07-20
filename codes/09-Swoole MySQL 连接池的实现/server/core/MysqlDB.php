<?php

if (!defined('SERVER_PATH')) exit("No Access");

class MysqlDB
{
    private $master;
    private $slave;
    private $config;

    public function __call($name, $arguments)
    {
        if ($name != 'query') {
            throw new RuntimeException($name."：This command is not supported");
        } else {
            return $this->_execute($arguments[0]);
        }
    }

    public function connect($config)
    {
        //主库
        $master = new Swoole\Coroutine\MySQL();
        $res = $master->connect($config['master']);
        if ($res === false) {
            throw new RuntimeException($master->connect_error, $master->errno);
        } else {
            $this->master = $master;
        }

        //从库
        $slave = new Swoole\Coroutine\MySQL();
        $res = $slave->connect($config['slave']);
        if ($res === false) {
            throw new RuntimeException($slave->connect_error, $slave->errno);
        } else {
            $this->slave = $slave;
        }

        $this->config = $config;
        return $res;
    }

    public function insert($table = '', $data = [])
    {
        $fields = '';
        $values = '';
        $keys = array_keys($data);
        foreach ($keys as $k) {
            $fields .= "`".addslashes($k)."`, ";
            $values .= "'".addslashes($data[$k])."', ";
        }
        $fields = substr($fields, 0, -2);
        $values = substr($values, 0, -2);
        $sql = "INSERT INTO `{$table}` ({$fields}) VALUES ({$values})";
        return $this->_execute($sql);
    }

    public function update($table = '', $set = [], $where = [])
    {
        $arr_set = [];
        foreach ($set as $k => $v) {
            $arr_set[] = '`'.$k . '` = ' . $this->_escape($v);
        }
        $set = implode(', ', $arr_set);
        $where = $this->_where($where);
        $sql = "UPDATE `{$table}` SET {$set} {$where}";
        return $this->_execute($sql);
    }

    public function delete($table = '', $where = [])
    {
        $where = $this->_where($where);
        $sql = "DELETE FROM `{$table}` {$where}";
        return $this->_execute($sql);
    }

    public function select($table = '',$where = [])
    {
        $where = $this->_where($where);
        $sql = "SELECT * FROM `{$table}` {$where}";
        return $this->_execute($sql);
    }

    private function _where($where = [])
    {
        $str_where = '';
        foreach ($where as $k => $v) {
            $str_where .= " AND `{$k}` = ".$this->_escape($v);
        }
        return "WHERE 1 ".$str_where;
    }

    private function _escape($str)
    {
        if (is_string($str)) {
            $str = "'".$str."'";
        } elseif (is_bool($str)) {
            $str = ($str === FALSE) ? 0 : 1;
        } elseif (is_null($str)) {
            $str = 'NULL';
        }
        return $str;
    }

    private function _execute($sql)
    {
        if (strtolower(substr($sql, 0, 6)) == 'select') {
            $db = $this->_get_usable_db('slave');
        } else {
            $db = $this->_get_usable_db('master');
        }
        $result = $db->query($sql);
        if ($result === true) {
            return [
                'affected_rows' => $db->affected_rows,
                'insert_id'     => $db->insert_id,
            ];
        }
        return $result;
    }

    private function _get_usable_db($type)
    {
        if ($type == 'master') {
            if (!$this->master->connected) {
                $master = new Swoole\Coroutine\MySQL();
                $res = $master->connect($this->config['master']);
                if ($res === false) {
                    throw new RuntimeException($master->connect_error, $master->errno);
                } else {
                    $this->master = $master;
                }
            }
            return $this->master;
        } elseif ($type == 'slave') {
            if (!$this->slave->connected) {
                $slave = new Swoole\Coroutine\MySQL();
                $res = $slave->connect($this->config['slave']);
                if ($res === false) {
                    throw new RuntimeException($slave->connect_error, $slave->errno);
                } else {
                    $this->slave = $slave;
                }
            }
            return $this->slave;
        }
    }
}
