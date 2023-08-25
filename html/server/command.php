<?php

if (!preg_match('/cli/i', php_sapi_name())) {
    exit('require php-cli mode');
}

//Phù hợp với tệp mục nhập web
define('BASE_PATH', __DIR__ . '/..');

date_default_timezone_set('PRC');

auto_require(BASE_PATH . '/config/');

auto_require(BASE_PATH . '/extension/Medoo-1.7.10/src/Medoo.php');

auto_require(BASE_PATH . '/app/service/base/Base.php');
auto_require(BASE_PATH . '/app/service/');

function auto_require($path, $recursively = false)
{
    if (is_file($path)) {
        if (substr($path, -4) == '.php') {
            require_once $path;
        }
    } else {
        $files = scandir($path);
        foreach ($files as $file) {
            if ($file != '.' && $file != '..') {
                if (is_dir($path . '/' . $file)) {
                    $recursively ? auto_require($path . '/' . $file, true) : '';
                } else {
                    if (substr($file, -4) == '.php') {
                        require_once $path . '/' . $file;
                    }
                }
            }
        }
    }
}

use app\service\Mail;
use Medoo\Medoo;

use app\service\Svnuser as ServiceSvnuser;
use app\service\Svngroup as ServiceSvngroup;
use app\service\Svnrep as ServiceSvnrep;


class Command
{
    private $database;

    private $configDb;

    private $configSvn;

    private $configBin;

    private $argc;

    private $argv;

    private $crond;

    private $code = 0;

    private $currentRep = '';

    private $taskType = [
        '1' => 'Sao lưu thư mục [đầy khối lượng]',
        '2' => 'Sao lưu thư mục [dump-incremental-deltas]',
        '3' => 'Sao lưu thư mục [hotcopy-đầy đủ số lượng]',
        '4' => 'sao lưu thư mục [hotcopy-gia tăng]',
        '5' => 'kiểm tra thư mục',
        '6' => 'tập lệnh shell',
        '7' => 'Đồng bộ hóa người dùng SVN',
        '8' => 'Đồng bộ hóa nhóm SVN',
        '9' => 'Đồng bộ hóa thư mục SVN'
    ];

    function __construct($argc, $argv)
    {
        //Nhận tham số kiểm tra tham số
        if (!isset($argv[1]) || !isset($argv[2])) {
            print_r(sprintf('Tham số không đầy đủ -tự động thoát %s', PHP_EOL));
            exit;
        }

        $this->argc = $argc;
        $this->argv = $argv;

        Config::load(BASE_PATH . '/config/');

        //cấu hình
        $this->configDb = Config::get('database');
        $this->configSvn = Config::get('svn');
        $this->configBin =  Config::get('bin');

        //Kết nối cơ sở dữ liệu
        if (array_key_exists('database_file', $this->configDb)) {
            $this->configDb['database_file'] = sprintf($this->configDb['database_file'], $this->configSvn['home_path']);
        }
        try {
            $this->database = new Medoo($this->configDb);
        } catch (\Exception $e) {
            print_r(sprintf('Kết nối cơ sở dữ liệu không thành công [%s]', $e->getMessage()));
            exit;
        }


        //Chi tiết tham số truy vấn
        $this->crond = $this->database->get('crond', '*', [
            'sign' => $this->argv[2]
        ]);

        if (empty($this->crond)) {
            print_r(sprintf('Không có kế hoạch nhiệm vụ nào khớp với mã định danh [%s] trong cơ sở dữ liệu -tự động thoát %s', $this->argv[2], PHP_EOL));
            exit;
        }

        //Cập nhật thời gian thực hiện
        $this->database->update('crond', [
            'last_exec_time' => date('Y-m-d H:i:s'),
        ], [
            'sign' => $this->argv[2]
        ]);
    }

    /**
     *Kiểm tra dung lượng đĩa
     *
     *@return void
     */
    private function CheckDisk()
    {
        //tất cả
    }

    /**
     *gửi email
     *
     *@return void
     */
    private function SendMail()
    {
        if ($this->crond['notice'] == 1 || $this->crond['notice'] == 3) {
            if ($this->code == 0) {
                $subject = $this->code == 0 ? 'Thông báo thực hiện kế hoạch nhiệm vụ thành công' : 'Thông báo lỗi thực hiện kế hoạch nhiệm vụ';
                $body = sprintf("tên nhiệm vụ: %s\nThời điểm hiện tại: %s\n", $this->crond['task_name'], date('Y-m-d H:i:s'));

                $result = (new Mail())->SendMail2($subject, $body);
                if ($result['status'] == 1) {
                    print_r(sprintf('Thư đã được gửi thành công%s', PHP_EOL));
                } else {
                    print_r(sprintf('Gửi email không thành công[%s]%s', $result['message'], PHP_EOL));
                }
            }
        }

        if ($this->crond['notice'] == 2 || $this->crond['notice'] == 3) {
            if ($this->code != 0) {
                $subject = $this->code == 0 ? 'Thông báo thực hiện kế hoạch nhiệm vụ thành công' : 'Thông báo lỗi thực hiện kế hoạch nhiệm vụ';
                $body = sprintf("Tên nhiệm vụ: %s\nthời điểm hiện tại: %s\n", $this->crond['task_name'], date('Y-m-d H:i:s'));

                $result = (new Mail())->SendMail2($subject, $body);
                if ($result['status'] == 1) {
                    print_r(sprintf('Email đã được gửi thành công %s', PHP_EOL));
                } else {
                    print_r(sprintf('Gửi email không thành công[%s]%s', $result['message'], PHP_EOL));
                }
            }
        }
    }

    /**
     *Sao lưu thư mục [đầy khối lượng]
     *
     *@return void
     */
    public function RepDumpAll()
    {
        $repList = json_decode($this->crond['rep_name'], true);

        if (in_array('-1', $repList)) {
            $repList = $this->database->select('svn_reps', 'rep_name');
            print_r(sprintf('Chế độ hiện tại là backup toàn bộ thư mục -danh sách thư mục [%s]%s', implode('|', $repList), PHP_EOL));
        } else {
            print_r(sprintf('Chế độ hiện tại là backup một số thư mục -danh sách thư mục[%s]%s', implode('|', $repList), PHP_EOL));
        }

        foreach ($repList as $rep) {
            $this->currentRep = $rep;

            clearstatcache();
            if (!is_dir($this->configSvn['rep_base_path'] .  $rep)) {
                print_r(sprintf('Kho [%s] không tồn tại trên đĩa -tự động bỏ qua %s', $rep, PHP_EOL));
                continue;
            }

            $prefix = 'rep_' . $rep . '_';
            $backupName = $prefix . date('YmdHis') . '.dump';

            //kiểm tra số lượng
            $backupList = [];
            $fileList = scandir($this->configSvn['backup_base_path']);
            foreach ($fileList as $key => $value) {
                if ($value == '.' || $value == '..' || is_dir($this->configSvn['backup_base_path'] . '/' . $value)) {
                    continue;
                }
                if (substr($value, 0, strlen($prefix)) == $prefix) {
                    $backupList[] = $value;
                }
            }
            if ($this->crond['save_count'] <= count($backupList)) {
                rsort($backupList);
                for ($i = $this->crond['save_count']; $i <= count($backupList); $i++) {
                    print_r(sprintf('Xóa tệp sao lưu dư thừa [%s]%s khỏi thư mục [%s]', $rep, $backupList[$i - 1], PHP_EOL));
                    @unlink($this->configSvn['backup_base_path'] . '/' . $backupList[$i - 1]);
                }
            }

            print_r(sprintf('Kho [%s] bắt đầu thực hiện chương trình sao lưu %s', $rep, PHP_EOL));

            $stderrFile = tempnam(sys_get_temp_dir(), 'svnadmin_');

            $cmd = sprintf("'%s' dump '%s' --quiet  > '%s'", $this->configBin['svnadmin'], $this->configSvn['rep_base_path'] .  $rep, $this->configSvn['backup_base_path'] .  $backupName);
            passthru($cmd . " 2>$stderrFile", $this->code);

            //$cmd = sprintf("'%s' dump '%s' > '%s'", $this->configBin['svnadmin'], $this->configSvn['rep_base_path'] . $rep, $ this->configSvn['backup_base_path'] .$backupName);
            //passthru($cmd . " 2>$stderrFile", $this->code);

            if ($this->code == 0) {
                print_r(sprintf('Quá trình sao lưu thư mục [%s] đã kết thúc %s', $rep, PHP_EOL));
            } else {
                print_r(sprintf('Đã hoàn tất sao lưu thư mục [%s] -thông báo lỗi [%s]%s', $rep, file_get_contents($stderrFile), PHP_EOL));
            }

            @unlink($stderrFile);

            $this->SendMail();
        }
    }

    /**
     *Sao lưu thư mục [dump-incremental-deltas]
     *
     *@return void
     */
    public function RepDumpDeltas()
    {
        $repList = json_decode($this->crond['rep_name'], true);

        if (in_array('-1', $repList)) {
            $repList = $this->database->select('svn_reps', 'rep_name');
            print_r(sprintf('Chế độ hiện tại là sao lưu gia tăng deltas của tất cả các thư mục -danh sách thư mục [%s]%s', implode('|', $repList), PHP_EOL));
        } else {
            print_r(sprintf('Chế độ hiện tại là sao lưu gia tăng deltas của một số thư mục -danh sách thư mục [%s]%s', implode('|', $repList), PHP_EOL));
        }

        foreach ($repList as $rep) {
            $this->currentRep = $rep;

            clearstatcache();
            if (!is_dir($this->configSvn['rep_base_path'] .  $rep)) {
                print_r(sprintf('Kho [%s] không tồn tại trên đĩa -tự động bỏ qua %s', $rep, PHP_EOL));
                continue;
            }

            $prefix = 'rep_' . $rep . '_deltas_';
            $backupName = $prefix . date('YmdHis') . '.dump';

            //kiểm tra số lượng
            $backupList = [];
            $fileList = scandir($this->configSvn['backup_base_path']);
            foreach ($fileList as $key => $value) {
                if ($value == '.' || $value == '..' || is_dir($this->configSvn['backup_base_path'] . '/' . $value)) {
                    continue;
                }
                if (substr($value, 0, strlen($prefix)) == $prefix) {
                    $backupList[] = $value;
                }
            }
            if ($this->crond['save_count'] <= count($backupList)) {
                rsort($backupList);
                for ($i = $this->crond['save_count']; $i <= count($backupList); $i++) {
                    print_r(sprintf('Xóa tệp sao lưu gia tăng deltas dư thừa [%s]%s trong thư mục [%s]', $rep, $backupList[$i - 1], PHP_EOL));
                    @unlink($this->configSvn['backup_base_path'] . '/' . $backupList[$i - 1]);
                }
            }

            print_r(sprintf('Kho [%s] bắt đầu thực hiện chương trình sao lưu gia tăng deltas %s', $rep, PHP_EOL));

            $stderrFile = tempnam(sys_get_temp_dir(), 'svnadmin_');

            $cmd = sprintf("'%s' dump '%s' --deltas --quiet > '%s'", $this->configBin['svnadmin'], $this->configSvn['rep_base_path'] .  $rep, $this->configSvn['backup_base_path'] .  $backupName);
            passthru($cmd . " 2>$stderrFile", $this->code);

            //$cmd = sprintf("'%s' dump '%s' > '%s'", $this->configBin['svnadmin'], $this->configSvn['rep_base_path'] . $rep, $ this->configSvn['backup_base_path'] .$backupName);
            //passthru($cmd . " 2>$stderrFile", $this->code);

            if ($this->code == 0) {
                print_r(sprintf('Quá trình sao lưu gia tăng thư mục [%s] delta đã kết thúc %s', $rep, PHP_EOL));
            } else {
                print_r(sprintf('Sao lưu gia tăng thư mục [%s] delta đã kết thúc -thông báo lỗi [%s]%s', $rep, file_get_contents($stderrFile), PHP_EOL));
            }

            @unlink($stderrFile);

            $this->SendMail();
        }
    }

    /**
     *Sao lưu thư mục [hotcopy-đầy đủ tập]
     *
     *@return void
     */
    public function RepHotcopyAll()
    {
    }

    /**
     *Sao lưu thư mục [hotcopy-gia tăng]
     *
     *@return void
     */
    public function RepHotcopyPart()
    {
    }

    /**
     *Kiểm tra thư mục
     *
     *@return void
     */
    public function RepCheck()
    {
        $repList = json_decode($this->crond['rep_name'], true);

        if (in_array('-1', $repList)) {
            $repList = $this->database->select('svn_reps', 'rep_name');
            print_r(sprintf('Chế độ hiện tại là kiểm tra tất cả các thư mục -danh sách thư mục [%s]%s', implode('|', $repList), PHP_EOL));
        } else {
            print_r(sprintf('Chế độ hiện tại là kiểm tra một số thư mục -danh sách thư mục[%s]%s', implode('|', $repList), PHP_EOL));
        }

        foreach ($repList as $rep) {
            $this->currentRep = $rep;

            clearstatcache();
            if (!is_dir($this->configSvn['rep_base_path'] .  $rep)) {
                print_r(sprintf('仓库[%s] không tồn tại trên đĩa -tự động bỏ qua %s', $rep, PHP_EOL));
                continue;
            }

            print_r(sprintf('Kho [%s] bắt đầu thực hiện chương trình kiểm tra %s', $rep, PHP_EOL));

            $stderrFile = tempnam(sys_get_temp_dir(), 'svnadmin_');

            //$cmd = sprintf("'%s' verify --quiet '%s'", $this->configBin['svnadmin'], $this->configSvn['rep_base_path'] . $rep);
            //passthru($cmd . " 2>$stderrFile", $this->code);

            $cmd = sprintf("'%s' verify '%s'", $this->configBin['svnadmin'], $this->configSvn['rep_base_path'] .  $rep);
            passthru($cmd, $this->code);

            if ($this->code == 0) {
                print_r(sprintf('Việc kiểm tra thư mục [%s] đã hoàn thành 2%s', $rep, PHP_EOL));
            } else {
                print_r(sprintf('Đã hoàn tất kiểm tra thư mục [%s] -thông báo lỗi [%s]%s', $rep, file_get_contents($stderrFile), PHP_EOL));
            }

            @unlink($stderrFile);

            $this->SendMail();
        }
    }

    /**
     *tập lệnh shell
     *
     *@return void
     */
    public function Shell()
    {
        print_r(sprintf('Tập lệnh [%s] đã bắt đầu thực thi %s', $this->crond['task_name'], PHP_EOL));

        $stderrFile = tempnam(sys_get_temp_dir(), 'svnadmin_');

        $shellFile = tempnam(sys_get_temp_dir(), 'svnadmin_');
        file_put_contents($shellFile, $this->crond['shell']);
        shell_exec(sprintf("chmod 755 '%s'", $shellFile));

        passthru($shellFile . " 2>$stderrFile", $this->code);

        if ($this->code == 0) {
            print_r(sprintf('Việc thực thi tập lệnh [%s] đã kết thúc %s', $this->crond['task_name'], PHP_EOL));
        } else {
            print_r(sprintf('Việc thực thi tập lệnh [%s] đã kết thúc -với thông báo lỗi [%s]%s', $this->crond['task_name'], file_get_contents($stderrFile), PHP_EOL));
        }

        @unlink($stderrFile);
        @unlink($shellFile);

        $this->SendMail();
    }

    /**
     *Đồng bộ hóa người dùng
     *
     *@return void
     */
    public function SyncUser()
    {
        (new ServiceSvnuser())->SyncUser();
    }

    /**
     *Nhóm đồng bộ
     *
     *@return void
     */
    public function SyncGroup()
    {
        (new ServiceSvngroup())->SyncGroup();
    }

    /**
     *Đồng bộ hóa thư mục
     *
     *@return void
     */
    public function SyncRep()
    {
        $serviceSvnrep = new ServiceSvnrep();
        $serviceSvnrep->SyncRep2Authz();
        $result = $serviceSvnrep->SyncRep2Db();
        if ($result['status'] != 1) {
            print_r($result['message']);
        }
    }
}

$command = new Command($argc, $argv);

switch ($argv[1]) {
    case 1: //Sao lưu thư mục [đầy đủ số lượng]
        $command->RepDumpAll();
        break;
    case 2: //Sao lưu thư mục [dump-incremental-deltas]
        $command->RepDumpDeltas();
        break;
    case 3: //Sao lưu thư mục [hotcopy-đầy đủ số lượng]
        $command->RepHotcopyAll();
        break;
    case 4: //Sao lưu thư mục [hotcopy-gia tăng]
        $command->RepHotcopyPart();
        break;
    case 5: //kiểm tra thư mục
        $command->RepCheck();
        break;
    case 6: //tập lệnh shell
        $command->Shell();
        break;
    case 7: //Đồng bộ hóa người dùng SVN
        $command->SyncUser();
        break;
    case 8: //Đồng bộ hóa nhóm SVN
        $command->SyncGroup();
        break;
    case 9: //Đồng bộ hóa thư mục SVN
        $command->SyncRep();
        break;
    default:
        break;
}
