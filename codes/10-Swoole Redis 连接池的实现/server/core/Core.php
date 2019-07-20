<?php

if (!defined('SERVER_PATH')) exit("No Access");

class Core
{
    private static $serv;

    public function __construct() {
        set_error_handler(['HandlerException', 'appError']);
        register_shutdown_function(['HandlerException', 'fatalError']);
    }

    public static function run() {
        static::checkCli();
        static::checkExtension();
        static::showUsageUI();
        static::parseCommand();
    }

    protected static function checkCli() {
        if (php_sapi_name() !== 'cli') {
            exit(output('服务只能运行在cli sapi模式下'));
        }
    }

    protected static function checkExtension() {
        if (!extension_loaded('swoole')) {
            exit(output('请安装swoole扩展'));
        }
    }

    protected static function showUsageUI() {
        global $argc;
        if ($argc <= 1 || $argc >3) {
            echo PHP_EOL;
            echo "----------------------------------------".PHP_EOL;
            echo "|               Swoole                 |".PHP_EOL;
            echo "|--------------------------------------|".PHP_EOL;
            echo '|    USAGE: php index.php commond      |'.PHP_EOL;
            echo '|--------------------------------------|'.PHP_EOL;
            echo '|    1. start    以debug模式开启服务   |'.PHP_EOL;
            echo '|    2. start -d 以daemon模式开启服务  |'.PHP_EOL;
            echo '|    3. status   查看服务状态          |'.PHP_EOL;
            echo '|    4. reload   热加载                |'.PHP_EOL;
            echo '|    5. stop     关闭服务              |'.PHP_EOL;
            echo "----------------------------------------".PHP_EOL;
            echo PHP_EOL;
            exit;
        }
    }

    protected static function parseCommand() {
        global $argv;
        $command = $argv[1];
        $option  = isset( $argv[2] ) ? $argv[2] : '' ;
        switch ($command) {
            case 'start':
                if ($option === '-d') { //以daemon形式启动
                    get_config('config', ['set@daemonize' => true]);
                }
                self::workerStart();
                break;
            case 'status':
                self::workerStatus();
                break;
            case 'reload':
                self::workerReload();
                break;
            case 'stop':
                self::workerStop();
                break;
            default:
                echo "Bad Command.".PHP_EOL;
        }
    }

    protected static function workerStart() {
        $config = get_config();

        self::$serv = new swoole_websocket_server($config['ip'], $config['websocket_port']);
        self::$serv->set($config['set']);
        self::$serv->on('Start', function ($serv) use ($config) {
            $start = new OnStart();
            $start::run($serv, $config);
        });

        self::$serv->on('ManagerStart', function ($serv) use ($config) {
            $manager_start = new OnManagerStart();
            $manager_start::run($serv, $config);
        });

        self::$serv->on('WorkerStart', function ($serv, $worker_id) use ($config) {
            $worker_start = new OnWorkerStart();
            $worker_start::run($serv, $worker_id, $config);
        });

        //TCP
        $tcp = self::$serv->listen($config['ip'], $config['tcp_port'], SWOOLE_SOCK_TCP);
        $tcp->set($config['tcp_set']);
        $tcp->on('Receive', function ($serv, $fd, $reactor_id, $data) {
            $receive = new OnReceive();
            $receive::run($serv, $fd, $reactor_id, $data);
        });

        //UDP
        $udp = self::$serv->listen($config['ip'], $config['udp_port'], SWOOLE_SOCK_UDP);
        $udp->set($config['udp_set']);
        $udp->on('Packet', function ($serv, $data, $client_info) {
            $packet = new OnPacket();
            $packet::run($serv, $data, $client_info);
        });

        self::$serv->on('Task', function ($serv, $task) use ($config) {
            $task_obj = new OnTask();
            $dataArr = json_decode($task->data, true);
            $rs = '';
            switch ($dataArr['server']) {
                case "tcp":
                    $rs = $task_obj::tcp_task_run($serv, $task);
                    break;
                case "ws":
                    $rs = $task_obj::ws_task_run($serv, $task);
                    break;
                case "http":
                    $rs = $task_obj::http_task_run($serv, $task);
                    break;
            }
            return $rs;
        });

        self::$serv->on('Open', function ($serv, $request) {
            $open = new OnOpen();
            $open::run($serv, $request);
        });

        self::$serv->on('Message', function ($serv, $frame) {
            $message = new OnMessage();
            $message::run($serv, $frame);
        });

        self::$serv->on('Request', function ($request, $response) {
            $req = new OnRequest();
            $req::run(self::$serv, $request, $response);
        });

        self::$serv->on('Finish', function ($serv, $task_id, $data) {
            $finish = new OnFinish();
            $finish::run($serv, $task_id, $data);
        });

        self::$serv->on('Close', function ($serv, $fd, $reactor_id){
            try {
                echo output('客户端关闭');
            } catch(Exception $e) {
            }
        });

        self::$serv->on('Shutdown', function ($serv) {
           echo output("服务关闭");
        });

        self::showProcessUI();

        self::$serv->start();
    }

    protected static function workerStatus() {
        $config = get_config();

        if (!file_exists($config['master_pid_file']) ||
            !file_exists($config['manager_pid_file']) ||
            !file_exists($config['worker_pid_file']) ) {
            echo output("暂无启动的服务");
            return false;
        }

        self::showProcessUI($config);

        $masterPidString = trim(@file_get_contents($config['master_pid_file']));
        $masterPidArr    = explode( '-', $masterPidString);

        echo str_pad("Master", 18, ' ', STR_PAD_BOTH ).
            str_pad($config['master_process_name'], 26, ' ', STR_PAD_BOTH ).
            str_pad($masterPidArr[0], 16, ' ', STR_PAD_BOTH ).
            str_pad($masterPidArr[1], 16, ' ', STR_PAD_BOTH ).
            str_pad($masterPidArr[2], 16, ' ', STR_PAD_BOTH ).PHP_EOL;

        $managerPidString = trim(@file_get_contents($config['manager_pid_file']));
        $managerPidArr    = explode( '-', $managerPidString);

        echo str_pad("Manager", 20, ' ', STR_PAD_BOTH ).
            str_pad($config['manager_process_name'], 24, ' ', STR_PAD_BOTH ).
            str_pad($managerPidArr[0], 16, ' ', STR_PAD_BOTH ).
            str_pad($managerPidArr[1], 16, ' ', STR_PAD_BOTH ).
            str_pad($managerPidArr[2], 16, ' ', STR_PAD_BOTH ).PHP_EOL;


        $workerPidString = rtrim(@file_get_contents($config['worker_pid_file']), '|' );
        $workerPidArr    = explode( '|', $workerPidString );
        if (isset($workerPidArr) && !empty($workerPidArr)) {
            foreach ($workerPidArr as $key => $val) {
                $v = explode( '-', $val);
                echo str_pad("Worker", 18, ' ', STR_PAD_BOTH ).
                     str_pad($config['worker_process_name'], 26, ' ', STR_PAD_BOTH ).
                     str_pad($v[0], 16, ' ', STR_PAD_BOTH ).
                     str_pad($v[1], 16, ' ', STR_PAD_BOTH ).
                     str_pad($v[2], 16, ' ', STR_PAD_BOTH ).PHP_EOL;
            }
        }

        $taskPidString = rtrim(@file_get_contents($config['task_pid_file']), '|' );
        $taskPidArr  = explode( '|', $taskPidString );
        if (isset($taskPidArr) && !empty($taskPidArr)) {
            foreach ($taskPidArr as $key => $val) {
                $v = explode( '-', $val);
                echo str_pad("Task", 18, ' ', STR_PAD_BOTH ).
                     str_pad($config['task_process_name'], 24, ' ', STR_PAD_BOTH ).
                     str_pad($v[0], 20, ' ', STR_PAD_BOTH ).
                     str_pad($v[1], 12, ' ', STR_PAD_BOTH ).
                     str_pad($v[2], 20, ' ', STR_PAD_BOTH ).PHP_EOL;
            }
        }
    }

    protected static function workerReload() {
        $config = get_config();

        if (!file_exists($config['master_pid_file'])) {
            echo output("暂无启动的服务");
            return false;
        }

        $masterPidString = trim(file_get_contents($config['master_pid_file']));
        $masterPidArr    = explode( '-', $masterPidString);

        if (!swoole_process::kill($masterPidArr[0], 0)) {
            echo output("PID:{$masterPidArr[0]} 不存在");
            return false;
        }

        swoole_process::kill($masterPidArr[0], SIGUSR1);

        @unlink($config['worker_pid_file']);
        @unlink($config['task_pid_file']);

        echo output("热加载成功");
        return true;
    }

    protected static function workerStop() {
        $config = get_config();

        if (!file_exists($config['master_pid_file'])) {
            echo output("暂无启动的服务");
            return false;
        }

        $masterPidString = trim(file_get_contents($config['master_pid_file']));
        $masterPidArr    = explode( '-', $masterPidString);

        if (!swoole_process::kill($masterPidArr[0], 0)) {
            echo output("PID:{$masterPidArr[0]} 不存在");
            return false;
        }

        swoole_process::kill($masterPidArr[0]);

        $time = time();
        while (true) {
            usleep(2000);
            if (!swoole_process::kill($masterPidArr[0], 0)) {
                unlink($config['master_pid_file']);
                unlink($config['manager_pid_file']);
                unlink($config['worker_pid_file']);
                unlink($config['task_pid_file']);
                echo output("服务关闭成功");
                break;
            } else {
                if (time() - $time > 5) {
                    echo output("服务关闭失败，请重试");
                    break;
                }
            }
        }
        return true;
    }

    protected static function showProcessUI() {
        $config = get_config();
        if ($config['set']['daemonize'] == true) {
            return false;
        }
        echo str_pad("-", 90, '-', STR_PAD_BOTH) . PHP_EOL;
        echo "|" . str_pad("启动/关闭", 92, ' ', STR_PAD_BOTH) . "|" . PHP_EOL;
        echo str_pad("-", 90, '-', STR_PAD_BOTH) . PHP_EOL;

        echo str_pad("Start success.", 50, ' ', STR_PAD_BOTH) .
            str_pad("php index.php stop", 50, ' ', STR_PAD_BOTH) . PHP_EOL;

        echo str_pad("-", 90, '-', STR_PAD_BOTH) . PHP_EOL;
        echo "|" . str_pad("版本信息", 92, ' ', STR_PAD_BOTH) . "|" . PHP_EOL;
        echo str_pad("-", 90, '-', STR_PAD_BOTH) . PHP_EOL;

        echo str_pad("Swoole Version:" . SWOOLE_VERSION, 50, ' ', STR_PAD_BOTH) .
            str_pad("PHP Version:" . PHP_VERSION, 50, ' ', STR_PAD_BOTH) . PHP_EOL;

        echo str_pad("-", 90, '-', STR_PAD_BOTH) . PHP_EOL;
        echo "|" . str_pad("IP 信息", 90, ' ', STR_PAD_BOTH) . "|" . PHP_EOL;
        echo str_pad("-", 90, '-', STR_PAD_BOTH) . PHP_EOL;

        echo str_pad("IP:" . $config['ip'], 50, ' ', STR_PAD_BOTH) .
            str_pad("PORT:" . $config['websocket_port'], 50, ' ', STR_PAD_BOTH) . PHP_EOL;

        echo str_pad("-", 90, '-', STR_PAD_BOTH) . PHP_EOL;
        echo "|" . str_pad("进程信息", 92, ' ', STR_PAD_BOTH) . "|" . PHP_EOL;
        echo str_pad("-", 90, '-', STR_PAD_BOTH) . PHP_EOL;

        echo str_pad("Swoole进程", 20, ' ', STR_PAD_BOTH) .
            str_pad('进程别名', 30, ' ', STR_PAD_BOTH) .
            str_pad('进程ID', 18, ' ', STR_PAD_BOTH) .
            str_pad('父进程ID', 18, ' ', STR_PAD_BOTH) .
            str_pad('用户', 18, ' ', STR_PAD_BOTH) . PHP_EOL;
    }

    protected static function signalHandler() {
        //TODO 未完成
        //swoole_process::signal(SIGINT, function ($signal) { // 监听子进程退出信号
        //    echo $signal;
        //    return;
        //});
    }
}
