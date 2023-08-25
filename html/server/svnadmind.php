<?php
/*
 * @Author: bibo318
 * 
 * @LastEditors: bibo318
 * 
 * @Description: github: /bibo318
 */

/**
 * 将工作模式限制在cli模式
 */
if (!preg_match('/cli/i', php_sapi_name())) {
    exit('require php-cli mode' . PHP_EOL);
}

/**
 * 开启错误信息 如需要调试 可取消注释
 */
// ini_set('display_errors', '1');
// error_reporting(E_ALL);

define('BASE_PATH', __DIR__ . '/..');

define('IPC_SVNADMIN', BASE_PATH . '/server/svnadmind.socket');

require_once BASE_PATH . '/app/util/Config.php';
require_once BASE_PATH . '/extension/Medoo-1.7.10/src/Medoo.php';

use Medoo\Medoo;

class Daemon
{
    private $masterPidFile;
    private $taskPidFile;
    private $workMode;
    private $scripts = [
        'start',
        'stop',
        'console'
    ];
    private $configDaemon;
    private $configSvn;

    function __construct()
    {
        $this->masterPidFile = dirname(__FILE__) . '/svnadmind.pid';
        $this->taskPidFile = dirname(__FILE__) . '/task_svnadmind.pid';

        Config::load(BASE_PATH . '/config/');
        $this->configDaemon = Config::get('daemon');
        $this->configSvn = Config::get('svn');
    }

    /**
     * 创建TCP套接字并监听指定端口
     */
    private function InitSocket()
    {
        if (file_exists(IPC_SVNADMIN)) {
            unlink(IPC_SVNADMIN);
        }

        //创建套接字
        $socket = @socket_create(AF_UNIX, SOCK_STREAM, 0) or die(sprintf('Không tạo được ổ cắm[%s]%s', socket_strerror(socket_last_error()), PHP_EOL));

        //绑定地址和端口
        @socket_bind($socket, IPC_SVNADMIN, 0) or die(sprintf('绑定失败[%s][%s]%s', socket_strerror(socket_last_error()), IPC_SVNADMIN, PHP_EOL));

        //监听 设置并发队列的最大长度
        @socket_listen($socket, $this->configDaemon['socket_listen_backlog']) or die(sprintf('giám sát thất bại[%s]%s', socket_strerror(socket_last_error()), PHP_EOL));

        //使其它用户可用
        shell_exec('chmod 777 ' . IPC_SVNADMIN);

        // 创建任务进程 用于处理任务
        $pid = pcntl_fork();
        if ($pid == -1) {
            die(sprintf('pcntl_fork không thành công[%s]%s', socket_strerror(socket_last_error()), PHP_EOL));
        } elseif ($pid == 0) {
            file_put_contents($this->taskPidFile, getmypid());

            $this->ClearTask();
            while (true) {
                $this->HandleTask();
                sleep(3);
            }
            exit();
        }

        while (true) {
            //非阻塞式回收僵尸进程
            pcntl_wait($status, WNOHANG);

            $client = @socket_accept($socket) or die(sprintf('Nhận kết nối không thành công[%s]%s', socket_strerror(socket_last_error()), PHP_EOL));

            //非阻塞式回收僵尸进程
            pcntl_wait($status, WNOHANG);

            $pid = pcntl_fork();
            if ($pid == -1) {
                die(sprintf('pcntl_fork không thành công[%s]%s', socket_strerror(socket_last_error()), PHP_EOL));
            } elseif ($pid == 0) {
                $this->HandleRequest($client);
            } else {
            }
        }
    }

    /**
     * 清理垃圾任务
     *
     * @return void
     */
    private function ClearTask()
    {
        $task_error_log_file = $this->configSvn['log_base_path'] . 'task_error.log';

        $configDatabase = Config::get('database');
        $configSvn = Config::get('svn');
        if (array_key_exists('database_file', $configDatabase)) {
            $configDatabase['database_file'] = sprintf($configDatabase['database_file'], $configSvn['home_path']);
        }
        try {
            $database = new Medoo($configDatabase);
        } catch (\Exception $e) {
            $message = sprintf('[%s][Ngoại lệ daemon dọn dẹp tác vụ][%s]%s', date('Y-m-d H:i:s'), $e->getMessage(), PHP_EOL);
            file_put_contents($task_error_log_file, $message, FILE_APPEND);
            return;
        }

        $tasks = $database->select('tasks', [
            'task_id [Int]',
            'task_name',
            'task_status [Int]',
            'task_cmd',
            'task_unique',
            'task_log_file',
            'task_optional',
            'task_create_time',
            'task_update_time'
        ], [
            'OR' => [
                'task_status' => [
                    2
                ]
            ],
            'ORDER' => [
                'task_id'  => 'ASC'
            ]
        ]);

        foreach ($tasks as $task) {
            $pid = trim(shell_exec(sprintf("ps aux | grep -v grep | grep %s | awk 'NR==1' | awk '{print $2}'", $task['task_unique'])));

            if (empty($pid)) {
                $database->update('tasks', [
                    'task_status' => 5
                ], [
                    'task_id' => $task['task_id']
                ]);

                continue;
            }

            clearstatcache();
            if (!is_dir("/proc/$pid")) {
                $database->update('tasks', [
                    'task_status' => 4
                ], [
                    'task_id' => $task['task_id']
                ]);

                continue;
            }

            shell_exec(sprintf("kill -15 %s && kill -9 %s", $pid, $pid));

            $database->update('tasks', [
                'task_status' => 5
            ], [
                'task_id' => $task['task_id']
            ]);
        }
    }

    /**
     * 处理任务
     */
    private function HandleTask()
    {
        $task_error_log_file = $this->configSvn['log_base_path'] . 'task_error.log';

        $configDatabase = Config::get('database');
        $configSvn = Config::get('svn');
        if (array_key_exists('database_file', $configDatabase)) {
            $configDatabase['database_file'] = sprintf($configDatabase['database_file'], $configSvn['home_path']);
        }
        try {
            $database = new Medoo($configDatabase);
        } catch (\Exception $e) {
            $message = sprintf('[%s][Xử lý tác vụ ngoại lệ Daemon][Thử lại sau 120 giây][%s]', date('Y-m-d H:i:s'), $e->getMessage(), PHP_EOL);
            file_put_contents($task_error_log_file, $message, FILE_APPEND);
            sleep(120);
            return;
        }

        $tasks = $database->select('tasks', [
            'task_id',
            'task_name',
            'task_status',
            'task_cmd',
            'task_type',
            'task_unique',
            'task_log_file',
            'task_optional',
            'task_create_time',
            'task_update_time'
        ], [
            'task_status' => 1
        ]);

        foreach ($tasks as $task) {
            //开始执行
            $database->update('tasks', [
                'task_status' => 2
            ], [
                'task_id' => $task['task_id']
            ]);

            file_put_contents($task['task_log_file'], sprintf('%s%s', $task['task_name'], PHP_EOL), FILE_APPEND);
            file_put_contents($task['task_log_file'], sprintf('%s------------------thực hiện nhiệm vụ------------------%s', PHP_EOL, PHP_EOL), FILE_APPEND);

            ob_start();
            if ($task['task_type'] == 'svnadmin:load') {
                passthru(sprintf("%s &>> '%s'", $task['task_cmd'], $task['task_log_file']));
            } else {
                passthru(sprintf("%s", $task['task_cmd'], $task['task_log_file']));
            }
            $buffer = ob_get_contents();
            ob_end_clean();
            file_put_contents($task_error_log_file, $buffer, FILE_APPEND);
            file_put_contents($task['task_log_file'], $buffer, FILE_APPEND);

            //执行结束
            $database->update('tasks', [
                'task_status' => 3,
                'task_update_time' => date('Y-m-d H:i:s')
            ], [
                'task_id' => $task['task_id']
            ]);

            file_put_contents($task['task_log_file'], sprintf('%s------------------kết thúc nhiệm vụ------------------%s', PHP_EOL, PHP_EOL), FILE_APPEND);
        }
    }

    /**
     * 接收TCP连接并处理指令
     */
    private function HandleRequest($client)
    {
        $length = $this->configDaemon['socket_data_length'];

        //接收客户端发送的数据
        if (empty($receive = socket_read($client, $length))) {
            exit();
        }

        $receive = json_decode($receive, true);

        if (!isset($receive['type']) || !isset($receive['content'])) {
            exit();
        }

        $type = $receive['type'];
        $content = $receive['content'];

        //console模式
        if ($this->workMode == 'console') {
            echo PHP_EOL . '---------receive---------' . PHP_EOL;
            print_r($receive);
        }

        if ($type == 'passthru') {
            //定义错误输出文件路径
            $stderrFile = tempnam(sys_get_temp_dir(), 'svnadmin_');

            //将标准错误重定向到文件
            //使用状态码来标识错误信息
            ob_start();
            passthru($content . " 2>$stderrFile", $code);
            $buffer = ob_get_contents();
            ob_end_clean();

            //将错误信息和正确信息分类收集
            $result = [
                'code' => $code,
                'result' => trim($buffer),
                'error' => file_get_contents($stderrFile)
            ];

            @unlink($stderrFile);
        } else {
            $result = [
                'code' => 0,
                'result' => '',
                'error' => ''
            ];
        }

        //console模式
        if ($this->workMode == 'console') {
            echo PHP_EOL . '---------result---------' . PHP_EOL;
            echo 'code: ' . $result['code'] . PHP_EOL;
            echo 'result: ' . $result['result'] . PHP_EOL;
            echo 'error: ' . $result['error'] . PHP_EOL;
        }

        //返回json格式
        @socket_write($client, json_encode($result), $length) or die(sprintf('socket_write không thành công[%s]%s', socket_strerror(socket_last_error()), PHP_EOL));

        //关闭会话
        socket_close($client);

        //退出进程
        exit();
    }

    /**
     * 检查操作系统是否符合要求
     */
    private function CheckSysType()
    {
        if (PHP_OS != 'Linux') {
            die(sprintf('Hệ điều hành hiện tại không phải là Linux%s', PHP_EOL));
        }
        if (file_exists('/etc/redhat-release')) {
            $readhat_release = file_get_contents('/etc/redhat-release');
            $readhat_release = strtolower($readhat_release);
            if (strstr($readhat_release, 'centos')) {
                if (strstr($readhat_release, '8.')) {
                    // return 'centos 8';
                } elseif (strstr($readhat_release, '7.')) {
                    // return 'centos 7';
                } else {
                    echo '===============================================' . PHP_EOL;
                    echo 'cảnh báo! Phiên bản hệ điều hành hiện tại chưa được thử nghiệm và bạn có thể gặp sự cố trong quá trình sử dụng!' . PHP_EOL;
                    echo '===============================================' . PHP_EOL;
                }
            } elseif (strstr($readhat_release, 'rocky')) {
                // return 'rocky';
            } else {
                echo '===============================================' . PHP_EOL;
                echo 'cảnh báo! Phiên bản hệ điều hành hiện tại chưa được thử nghiệm và bạn có thể gặp sự cố trong quá trình sử dụng!' . PHP_EOL;
                echo '===============================================' . PHP_EOL;
            }
        } elseif (file_exists('/etc/lsb-release')) {
            // return 'ubuntu';
        } else {
            echo '===============================================' . PHP_EOL;
            echo 'cảnh báo! Phiên bản hệ điều hành hiện tại chưa được thử nghiệm và bạn có thể gặp sự cố trong quá trình sử dụng!' . PHP_EOL;
            echo '===============================================' . PHP_EOL;
        }
    }

    /**
     * 检查php版本是否符合要求
     */
    private function CheckPhpVersion()
    {
        $version = Config::get('version');
        if (isset($version['php']['lowest']) && !empty($version['php']['lowest'])) {
            if (PHP_VERSION < $version['php']['lowest']) {
                die(sprintf('Phiên bản PHP được hỗ trợ tối thiểu là [%s] Phiên bản PHP hiện tại là[%s]%s', $version['php']['lowest'], PHP_VERSION, PHP_EOL));
            }
        }
        if (isset($version['php']['highest']) && !empty($version['php']['highest'])) {
            if (PHP_VERSION >= $version['php']['highest']) {
                die(sprintf('Phiên bản PHP được hỗ trợ cao nhất là [%s] Phiên bản PHP hiện tại là [%s]%s', $version['php']['highest'], PHP_VERSION, PHP_EOL));
            }
        }
    }

    /**
     * 检查cli模式需要的函数是否被禁用
     */
    private function CheckDisabledFun()
    {
        $require_functions = ['shell_exec', 'passthru', 'pcntl_signal', 'pcntl_fork', 'pcntl_wait'];
        $disable_functions = explode(',', ini_get('disable_functions'));
        foreach ($disable_functions as $disable) {
            if (in_array(trim($disable), $require_functions)) {
                exit("Không khởi động được: bắt buộc $disable chức năng bị vô hiệu hóa" . PHP_EOL);
            }
        }
    }

    /**
     * 更新密钥
     */
    private function UpdateSign()
    {
        Config::load(BASE_PATH . '/config/');
        $sign = Config::get('sign');

        $content = "<?php\n\nreturn [\n";
        foreach ($sign as $key => $value) {
            $content .= sprintf("'%s' => '%s',%s", $key, $key == 'signature' ? uniqid() : $value, "\n");
        }
        $content .= "];\n";

        file_put_contents(BASE_PATH . '/config/sign.php', $content);
    }

    /**
     * 停止
     */
    private function Stop()
    {
        clearstatcache();

        if (file_exists($this->masterPidFile)) {
            $pid = file_get_contents($this->masterPidFile);
            posix_kill((int)$pid, 9);
            @unlink($this->masterPidFile);
        }

        if (file_exists($this->taskPidFile)) {
            $pid = file_get_contents($this->taskPidFile);
            posix_kill((int)$pid, 9);
            @unlink($this->taskPidFile);
        }

        //如果为 Linux 平台，且安装了 ps 程序，检测是否正确关闭了相关程序
        if (PHP_OS == 'Linux') {
            $result = shell_exec('which ps 2>/dev/null');
            if (!empty($result)) {
                $result2 = shell_exec("ps auxf | grep -v 'grep' | grep -v " . getmypid() . " | grep svnadmind.php");
                if (!empty($result2)) {
                    echo 'Hãy chắc chắn rằng bạn đã tắt daemon thành công!' . PHP_EOL;
                    echo 'Bởi vì các quy trình bị nghi ngờ sau đây được phát hiện đang chạy:' . PHP_EOL;
                    echo $result2;
                }
            }
        }
    }

    /**
     * 启动
     */
    private function Start()
    {
        file_put_contents($this->masterPidFile, getmypid());
        $this->UpdateSign();

        echo PHP_EOL;
        echo '----------------------------------------' . PHP_EOL;
        echo 'Daemon (svnadmind) đã khởi động thành công' . PHP_EOL;
        echo 'Khóa mã hóa hệ thống đã được thay đổi tự động, người dùng trực tuyến đã đăng xuất' . PHP_EOL;
        echo '----------------------------------------' . PHP_EOL;
        echo PHP_EOL;

        chdir('/');
        umask(0);
        if (defined('STDIN')) {
            fclose(STDIN);
        }
        if (defined('STDOUT')) {
            fclose(STDOUT);
        }
        if (defined('STDERR')) {
            fclose(STDERR);
        }
        $this->InitSocket();
    }

    /**
     * 调试
     */
    private function Console()
    {
        if (file_exists($this->masterPidFile)) {
            $pid = file_get_contents($this->masterPidFile);
            $result = trim(shell_exec("ps -ax | awk '{ print $1 }' | grep -e \"^$pid$\""));
            if (strstr($result, $pid)) {
                exit('Không thể vào chế độ gỡ lỗi, vui lòng dừng chương trình nền trước' . PHP_EOL);
            }
        }
        $this->InitSocket();
    }

    public function Run($argv)
    {
        if (isset($argv[1])) {
            $this->workMode = $argv[1];
            if (!in_array($this->workMode, $this->scripts)) {
                exit('Cách sử dụng: php svnadmin.php [' . implode(' | ', $this->scripts) . ']' . PHP_EOL);
            }
            if ($this->workMode == 'stop') {
                $this->Stop();
            } else {
                $this->CheckSysType();
                $this->CheckPhpVersion();
                $this->CheckDisabledFun();
                if ($this->workMode == 'console') {
                    $this->Console();
                } elseif ($this->workMode == 'start') {
                    $this->Start();
                }
            }
        } else {
            exit('Cách sử dụng: php svnadmin.php [' . implode(' | ', $this->scripts) . ']' . PHP_EOL);
        }
    }
}

$deamon = new Daemon();
$deamon->Run($argv);
