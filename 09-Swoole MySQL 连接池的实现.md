## 概述

收到读者的咨询，这情况大家可能也会有，所以就在这说说：

“亮哥，我今年30岁了，有点中年危机，最近有点焦虑，发现工作虽然很忙，但是没感觉能力有所提升，整天都有写不完的业务代码，时间紧有时代码质量也不怎么样，知道还有很多改进空间，但一直没时间改，主要是后面项目压着，马上又要进入开发了，这种情况怎么办？”

首先，我是菜鸡，观点不喜勿喷，那我就说下自己的看法：

上面的描述比较主观，人呀有时候发现不了自己的能力很正常，有时候有能力了并不是马上就能显现的，而是到了某个阶段后突然发现，哇塞，原来自己这么厉害。

当然能力也分很多种，比如专业能力，快速学习能力，进度把控能力，还有自信也是一种能力，不要脸是一种能力，坚持不要脸更是一种能力。

其实能力提升最快的还是靠工作实践，悄悄问问自己加入了很多大牛的微信群，能力提升了吗？看书自学不实践是不是吸收的也不多。

如果非要给一个具体的方案，那就是在团队内多分享吧，因为在分享前你会做充分的准备来避免分享时出丑，即使有时候自己知道，当讲出来的时候就不是那么回事了。

前期分享可以是看稿，后期练习无稿分享。

然后，再多说一点，30了给自己一个目标，不要盲目每天就是学学学，比如目标是技术专家，目标是业务专家，都很好呀，当然目标与自己性格有关也不是一成不变的。

围绕着目标设置一些计划，不要以为每天的学学学，就觉得其他的一切就自然而来，其中还有很多机遇和人脉的因素。

最后，如果实在感觉压得喘不过气，就换个环境吧，别和自己过不去。

开始今天的文章，这篇文章实现了 Swoole MySQL 连接池，代码是在《Swoole RPC 的实现》文章的基础上进行开发的。

先回顾上篇文章的内容：

- 实现了 HTTP / TCP 请求
- 实现了 同步 / 异步 请求
- 分享了 OnRequest.php、OnReceive.php 源码
- 业务逻辑 Order.php 中返回的是假数据

本篇文章主要的功能点：

- 业务逻辑 Order.php 中返回 MySQL 数据库中的数据。
- Task 启用了协程
- 支持 主/从 数据库配置
- 实现数据库连接池
- 实现数据库 CURD

## 代码

#### Order.php

```
<?php

if (!defined('SERVER_PATH')) exit("No Access");

class Order
{
    public function get_list($uid = 0, $type = 0)
    {
        //TODO 业务代码

        $rs[0]['order_code'] = '1';
        $rs[0]['order_name'] = '订单1';

        $rs[1]['order_code'] = '2';
        $rs[1]['order_name'] = '订单2';

        $rs[2]['order_code'] = '3';
        $rs[2]['order_name'] = '订单3';

        return $rs;
    }
}

修改成：

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
```

#### Task 启用协程

一、需要新增两项配置：

```
enable_coroutine      = true
task_enable_coroutine = true
```

二、回调参数发生改变

```
$serv->on('Task', function ($serv, $task_id, $src_worker_id, $data) {
   ...
});

修改成：

$serv->on('Task', function ($serv, $task) {
    $task->worker_id; //来自哪个`Worker`进程
    $task->id; //任务的编号
    $task->data; //任务的数据
});
```

#### 数据库 主/从 配置

Mysql.php

```
<?php

if (!defined('SERVER_PATH')) exit("No Access");

$db['default']['pool_size']        = 3; //连接池个数
$db['default']['pool_get_timeout'] = 0.5; //获取连接池超时时间
$db['default']['timeout']          = 0.5; //数据库建立连接超时时间
$db['default']['charset']          = 'utf8'; //字符集
$db['default']['strict_type']      = false; //开启严格模式
$db['default']['fetch_mode']       = true; //开启fetch模式

$config['master']             = $db['default'];
$config['master']['host']     = '127.0.0.1';
$config['master']['port']     = 3306;
$config['master']['user']     = 'root';
$config['master']['password'] = '123456';
$config['master']['database'] = 'demo';

$config['slave']             = $db['default'];
$config['slave']['host']     = '127.0.0.1';
$config['slave']['port']     = 3306;
$config['slave']['user']     = 'root';
$config['slave']['password'] = '123456';
$config['slave']['database'] = 'demo';
```

#### 数据库连接池

MysqlPool.php 

```
<?php

if (!defined('SERVER_PATH')) exit("No Access");

class MysqlPool
{
    private static $instance;
    private $pool;
    private $config;

    public static function getInstance($config = null)
    {
        if (empty(self::$instance)) {
            if (empty($config)) {
                throw new RuntimeException("MySQL config empty");
            }
            self::$instance = new static($config);
        }
        return self::$instance;
    }

    public function __construct($config)
    {
        if (empty($this->pool)) {
            $this->config = $config;
            $this->pool = new chan($config['master']['pool_size']);
            for ($i = 0; $i < $config['master']['pool_size']; $i++) {
                go(function() use ($config) {
                    $mysql = new MysqlDB();
                    $res = $mysql->connect($config);
                    if ($res === false) {
                        throw new RuntimeException("Failed to connect mysql server");
                    } else {
                        $this->pool->push($mysql);
                    }
                });
            }
        }
    }

    public function get()
    {
        if ($this->pool->length() > 0) {
            $mysql = $this->pool->pop($this->config['master']['pool_get_timeout']);
            if (false === $mysql) {
                throw new RuntimeException("Pop mysql timeout");
            }
            defer(function () use ($mysql) { //释放
                $this->pool->push($mysql);
            });
            return $mysql;
        } else {
            throw new RuntimeException("Pool length <= 0");
        }
    }
}
```

#### 数据库 CURD

MysqlDB.php

```
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
```

#### OnWorkerStart 中调用

```
try {
    MysqlPool::getInstance(get_config('mysql'));
} catch (\Exception $e) {
    $serv->shutdown();
} catch (\Throwable $throwable) {
    $serv->shutdown();
}
```

#### 客户端发送请求

```
<?php

//新增
$demo = [
    'type'  => 'SW',
    'token' => 'Bb1R3YLipbkTp5p0',
    'param' => [
        'class'  => 'Order',
        'method' => 'add',
        'param' => [
            'code' => 'C'.mt_rand(1000,9999),
            'name' => '订单-'.mt_rand(1000,9999),
        ],
    ],
];

//编辑
$demo = [
    'type'  => 'SW',
    'token' => 'Bb1R3YLipbkTp5p0',
    'param' => [
        'class'  => 'Order',
        'method' => 'edit',
        'param' => [
            'id' => '4',
            'name' => '订单-'.mt_rand(1000,9999),
        ],
    ],
];

//删除
$demo = [
    'type'  => 'SW',
    'token' => 'Bb1R3YLipbkTp5p0',
    'param' => [
        'class'  => 'Order',
        'method' => 'del',
        'param' => [
            'id' => '1',
        ],
    ],
];


//查询
$demo = [
    'type'  => 'SW',
    'token' => 'Bb1R3YLipbkTp5p0',
    'param' => [
        'class'  => 'Order',
        'method' => 'info',
        'param' => [
            'code' => 'C4649'
        ],
    ],
];

$ch = curl_init();
$options = [
    CURLOPT_URL  => 'http://10.211.55.4:9509/',
    CURLOPT_POST => 1,
    CURLOPT_POSTFIELDS => json_encode($demo),
];
curl_setopt_array($ch, $options);
curl_exec($ch);
curl_close($ch);
```

## 扩展

官方协程 MySQL 客户端手册：

https://wiki.swoole.com/wiki/page/p-coroutine_mysql.html

大家可以尝试使用官方提供的其他方法。

## 小结

Demo 代码仅供参考，里面有很多不严谨的地方。

根据自己的需要进行修改，比如业务代码相关验证，CURD 方法封装 ...

推荐一个完善的产品，Swoole 开发的 MySQL 数据库连接池（SMProxy）：

https://github.com/louislivi/smproxy

就到这了。

## 源码

[查看源码](https://github.com/xinliangnote/Swoole/blob/master/codes/09-Swoole%20MySQL%20的实现)