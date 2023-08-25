<?php
/*
 *@Tác giả: bibo318
 *
 *@LastEditors: bibo318
 *
 *@Mô tả: github: /bibo318
 */

/**
 *Cài đặt và nâng cấp chương trình
 */

/**
 *Giới hạn chế độ làm việc ở chế độ cli
 */
if (!preg_match('/cli/i', php_sapi_name())) {
    exit('require php-cli mode');
}

define('BASE_PATH', __DIR__);

auto_require(BASE_PATH . '/../app/util/Config.php');

auto_require(BASE_PATH . '/../config/');

auto_require(BASE_PATH . '/../app/function/');

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

class Install
{
    private $configDb;
    private $configReg;
    private $configSvn;
    private $configUpdate;
    private $configVersion;
    private $configBin;

    private $scripts = [
        [
            'index' => 1,
            'note' => 'Help me install and configure Subversion'
        ],
        [
            'index' => 2,
            'note' => 'Initialize Subversion according to the requirements of this system (for Subversion installed in other ways)'
        ],
        [
            'index' => 3,
            'note' => 'Detect new version of SVNAdmin'
        ],
        [
            'index' => 4,
            'note' => 'Modify the current datastore home directory'
        ]
    ];

    function __construct()
    {
        Config::load(BASE_PATH . '/../config/');

        $this->configDb = Config::get('database');
        $this->configReg = Config::get('reg');
        $this->configSvn = Config::get('svn');
        $this->configUpdate = Config::get('update');
        $this->configVersion = Config::get('version');
        $this->configBin = Config::get('bin');
    }

    /**
     *Phát hiện phiên bản mới của SVNAdmin và chọn cập nhật
     */
    function DetectUpdate()
    {
        foreach ($this->configUpdate['update_server'] as $key1 => $value1) {

            $result = funCurlRequest(sprintf($value1['url'], $this->configVersion['version']));

            if (empty($result)) {
                echo sprintf('Node [%s] access timed out -switch to next node %s', $value1['nodeName'], PHP_EOL);
                echo '===============================================' . PHP_EOL;
                continue;
            }

            //json => mảng
            $result = json_decode($result, true);

            if (!isset($result['code'])) {
                echo sprintf('Node [%s] returned information error -switch to next node %s', $value1['nodeName'], PHP_EOL);
                echo '===============================================' . PHP_EOL;
                continue;
            }

            if ($result['code'] != 200) {
                echo sprintf('Node [%s] returned status code [%s] status [%s] error message [%s] -switch to the next node %s', $value1['nodeName'], $result['status'], $result['message'], $result['code'], PHP_EOL);
                echo '===============================================' . PHP_EOL;
                continue;
            }

            if (empty($result['data'])) {
                echo sprintf('Currently the latest version [%s]%s', $this->configVersion['version'], PHP_EOL);
                echo '===============================================' . PHP_EOL;
                exit();
            }

            echo sprintf('There is a new version [%s]%s', $result['data']['version'], PHP_EOL);

            echo sprintf('The repair content is as follows: %s', PHP_EOL);
            foreach ($result['data']['fixed']['con'] as $cons) {
                echo sprintf('    [%s] %s%s', $cons['title'], $cons['content'], PHP_EOL);
                //echo ' [' . $cons['title'] . ']' . ' ' . $cons['content'] . PHP_EOL;
            }

            echo sprintf('The new content is as follows: %s', PHP_EOL);
            foreach ($result['data']['add']['con'] as $cons) {
                echo sprintf('    [%s] %s%s', $cons['title'], $cons['content'], PHP_EOL);
                //echo ' [' . $cons['title'] . ']' . ' ' . $cons['content'] . PHP_EOL;
            }

            echo sprintf('Remove the content as follows: %s', PHP_EOL);
            foreach ($result['data']['remove']['con'] as $cons) {
                echo sprintf('    [%s] %s%s', $cons['title'], $cons['content'], PHP_EOL);
                //echo ' [' . $cons['title'] . ']' . ' ' . $cons['content'] . PHP_EOL;
            }

            echo sprintf('Are you sure you want to upgrade to [%s] version[y/n]:', $result['data']['version']);

            $answer = strtolower(trim(fgets(STDIN)));

            if (!in_array($answer, ['y', 'n'])) {
                echo sprintf('Incorrect option %s', PHP_EOL);
                echo '===============================================' . PHP_EOL;
                exit();
            }

            if ($answer == 'n') {
                echo sprintf('%s canceled', PHP_EOL);
                echo '===============================================' . PHP_EOL;
                exit();
            }

            //Tải xuống và thực thi tập lệnh nâng cấp
            echo sprintf('Start downloading upgrade package %s', PHP_EOL);
            echo '===============================================' . PHP_EOL;
            $packages = isset($result['data']['update']['download'][$key1]['packages']) ? $result['data']['update']['download'][$key1]['packages'] : [];
            $forList = array_column($packages, 'for');
            $current = [
                'source' => $this->configVersion['version'],
                'dest' => $result['data']['version']
            ];
            if (!in_array($current, $forList)) {
                echo sprintf('There is no suitable upgrade package -please try to manually install the latest version of %s', PHP_EOL);
                echo '===============================================' . PHP_EOL;
                exit();
            }
            $index = array_search($current, $forList);
            $update_download_url = $packages[$index]['url'];
            $update_zip = funCurlRequest($update_download_url);
            if ($update_zip == null) {
                echo sprintf('Downloading upgrade package from node [%s] timed out -switch to the next node %s', $value1['nodeName'], PHP_EOL);
                echo '===============================================' . PHP_EOL;
                continue;
            }
            file_put_contents(BASE_PATH . '/update.zip', $update_zip);
            echo sprintf('Upgrade package download completed %s', PHP_EOL);
            echo '===============================================' . PHP_EOL;

            echo sprintf('Start to decompress the upgrade package [overwrite decompression]%s', PHP_EOL);
            echo '===============================================' . PHP_EOL;
            passthru('unzip -o ' . BASE_PATH . '/update.zip');
            if (!is_dir(BASE_PATH . '/update')) {
                echo sprintf('Error decompressing the upgrade package -please try to manually decompress and execute the upgrade program [php update/index.php]%s', PHP_EOL);
                echo '===============================================' . PHP_EOL;
                exit();
            }
            echo sprintf('Upgrade package decompression completed %s', PHP_EOL);
            echo '===============================================' . PHP_EOL;

            echo sprintf('Are you sure you want to execute the upgrade program [y/n]: ');

            $answer = strtolower(trim(fgets(STDIN)));

            if (!in_array($answer, ['y', 'n'])) {
                echo sprintf('Incorrect option %s', PHP_EOL);
                echo '===============================================' . PHP_EOL;
                exit();
            }

            if ($answer == 'n') {
                echo sprintf('%s canceled', PHP_EOL);
                echo '===============================================' . PHP_EOL;
                exit();
            }

            echo sprintf('Executing upgrade program %s', PHP_EOL);
            echo '===============================================' . PHP_EOL;

            passthru('php ' . BASE_PATH . '/update/index.php');

            passthru(sprintf("cd '%s' && rm -rf ./update && rm -f update.zip", BASE_PATH));

            echo '===============================================' . PHP_EOL;

            echo sprintf('Upgrade succeeded -please restart the daemon process to make some configuration files take effect %s', PHP_EOL);
            echo '===============================================' . PHP_EOL;
            exit();
        }
    }

    /**
     *Thêm SVNAdmin để tự khởi động boot
     */
    function InitlSVNAdmin()
    {
    }

    /**
     *Vô hiệu hóa SVNAdmin khởi động tự động
     */
    function UninitSVNAdmin()
    {
    }

    /**
     *Thêm SVNAdmin vào giám sát, nếu phát hiện thoát bất thường sẽ tự động khởi động lại
     */
    function Monitor()
    {
    }

    /**
     *Nhận loại hệ điều hành Linux
     *
     */etc/redhat-release redhat hoặc centos hoặc rock
     */etc/debian_version debian hoặc ubuntu
     */etc/slackware_version Phần mềm Slack
     */etc/lsb-release ubuntu
     */
    private function GetOS()
    {
        if (PHP_OS != 'Linux') {
            return false;
        }
        if (file_exists('/etc/redhat-release')) {
            $readhat_release = file_get_contents('/etc/redhat-release');
            $readhat_release = strtolower($readhat_release);
            if (strstr($readhat_release, 'hundreds')) {
                if (strstr($readhat_release, '8.')) {
                    return 'hundreds 8';
                } elseif (strstr($readhat_release, '7.')) {
                    return 'hundreds 7';
                } else {
                    return false;
                }
            } elseif (strstr($readhat_release, 'rocky')) {
                return 'rocky';
            } else {
                return false;
            }
        } elseif (file_exists('/etc/lsb-release')) {
            return 'ubuntu';
        } else {
            return false;
        }
    }

    /**
     *Kiểm tra xem đường dẫn đích có trống không
     *
     *@return bool
     */
    private function IsDirEmpty($path)
    {
        clearstatcache();

        $filename = scandir($path);

        foreach ($filename as $key => $value) {
            if ($value != '.' && $value != '..') {
                return false;
            }
        }

        return true;
    }

    /**
     *Sửa đổi cấu hình Subversion đã cài đặt cho phù hợp với sự quản lý của SVNAdmin
     */
    function ConfigSubversion()
    {
        /**
         *1. Phát hiện xem công cụ nào đã được cài đặt
         */
        if (trim(shell_exec('which which 2>/dev/null')) == '') {
            echo 'The which tool is not installed in the current environment and will not automatically detect the location of the software installation! ' . PHP_EOL;
            echo '===============================================' . PHP_EOL;
        }

        echo PHP_EOL . '===============================================' . PHP_EOL;
        echo 'Are you sure you want to start configuring the Subversion program [y/n]:';
        $continue = strtolower(trim(fgets(STDIN)));

        if (!in_array($continue, ['y', 'n'])) {
            echo 'Incorrect option! '  . PHP_EOL;
            echo '===============================================' . PHP_EOL;
            exit();
        }

        if ($continue == 'n') {
            echo 'Cancelled! ' . PHP_EOL;
            echo '===============================================' . PHP_EOL;
            exit();
        }

        /**
         *2. Phát hiện cài đặt Subversion
         */
        //kiểm tra xem có tiến trình nào đang chạy không
        if (shell_exec('ps auxf|grep -v "grep"|grep svnserve') != '') {
            echo 'Please manually stop the running svnserve program and try again! ' . PHP_EOL;
            echo '===============================================' . PHP_EOL;
            exit();
        }

        /**
         *3. Cho phép người dùng tự chọn đường dẫn của chương trình cấu hình
         */
        $needBin = [
            'svn' => '',
            'svnadmin' => '',
            'svnlook' => '',
            'svnserve' => '',
            'svnversion' => '',
            'svnsync' => '',
            'svnrdump' => '',
            'svndumpfilter' => '',
            'Savanmukkah' => '',
            'svnauthz-validate' => '',
            'saslauthd' => '',
            'httpd' => '',
            'htpasswd' => ''
        ];

        echo '===============================================' . PHP_EOL;
        echo 'Start configuring the Subversion program! ' . PHP_EOL;
        echo '===============================================' . PHP_EOL;

        foreach ($needBin as $key => $value) {
            echo "please enter[$key] Program location: " . PHP_EOL;
            if ($key == 'svnauthz-validate') {
                echo 'The location of svnauthz-validate under CentOS is usually /usr/bin/svn-tools/svnauthz-validate' . PHP_EOL;
            }
            echo 'The following program paths were automatically detected:' . PHP_EOL;
            passthru("which$key2>/dev/null");
            echo 'Please enter Enter to use the default detection path or enter manually:';
            $binPath = fgets(STDIN);
            if ($binPath == '') {
                echo 'Input can not be empty! ' . PHP_EOL;
                echo '===============================================' . PHP_EOL;
                exit();
            }
            if ($binPath == "\n") {
                $binPath = trim(shell_exec("which$key2>/dev/null"));
                if ($binPath == '') {
                    if (in_array($key, [
                        'Savanmukkah',
                        'svnauthz-validate',
                        'saslauthd',
                        'httpd',
                        'htpasswd'
                    ])) {
                        echo "Not detected$key, please enter the program path manually! " . PHP_EOL;
                        echo "because$keyNot necessary in the current version, so no installation can be ignored" . PHP_EOL;
                        echo '===============================================' . PHP_EOL;
                    } else {
                        echo "Not detected$key, please enter the program path manually! " . PHP_EOL;
                        echo '===============================================' . PHP_EOL;
                        exit();
                    }
                }
            } else {
                $binPath = trim($binPath);
            }
            echo "$keyProgram location:$binPath" . PHP_EOL;
            echo '===============================================' . PHP_EOL;
            $needBin[$key] = $binPath;
        }

        $binCon = <<<CON
<?php
        
        return [
            'svn' => '{$needBin['svn']}',
            'svnadmin' => '{$needBin['svnadmin']}',
            'svnlook' => '{$needBin['svnlook']}',
            'svnserve' => '{$needBin['svnserve']}',
            'svnversion' => '{$needBin['svnversion']}',
            'svnsync' => '{$needBin['svnsync']}',
            'svnrdump' => '{$needBin['svnrdump']}',
            'svndumpfilter' => '{$needBin['svndumpfilter']},
            'svnmucc' => '{$needBin['Savanmukkah']}',
            'svnauthz-validate' => '{$needBin['svnauthz-validate']}',
            'saslauthd' => '{$needBin['saslauthd']}',
            'httpd' => '{$needBin['httpd']}',
            'htpasswd' => '{$needBin['htpasswd']}'
        ];
CON;

        file_put_contents(BASE_PATH . '/../config/bin.php', $binCon);

        /**
         *4. Cấu hình file liên quan
         */
        $templete_path = BASE_PATH . '/../templete/';

        echo 'Create relative directories' . PHP_EOL;

        clearstatcache();

        //Tạo thư mục chính chứa thông tin cấu hình phần mềm SVNAdmin
        is_dir($this->configSvn['home_path']) ? '' : mkdir($this->configSvn['home_path'], 0754, true);

        //Tạo thư mục mẹ của kho SVN
        is_dir($this->configSvn['rep_base_path']) ? '' : mkdir($this->configSvn['rep_base_path'], 0754, true);

        //Tạo thư mục hook được đề xuất
        is_dir($this->configSvn['recommend_hook_path']) ? '' : mkdir($this->configSvn['recommend_hook_path'], 0754, true);
        shell_exec(sprintf("cp -r '%s' '%s'", $templete_path . 'hooks', $this->configSvn['home_path']));

        //Tạo thư mục sao lưu
        is_dir($this->configSvn['backup_base_path']) ? '' : mkdir($this->configSvn['backup_base_path'], 0754, true);

        //Tạo thư mục nhật ký
        is_dir($this->configSvn['log_base_path']) ? '' : mkdir($this->configSvn['log_base_path'], 0754, true);

        //Tạo thư mục mẫu cấu trúc kho
        is_dir($this->configSvn['templete_base_path'] . 'initStruct/01/branches') ? '' : mkdir($this->configSvn['templete_base_path'].'initStruct/01/branches', 0754, true);
        is_dir($this->configSvn['templete_base_path'] . 'initStruct/01/tags') ? '' : mkdir($this->configSvn['templete_base_path'].'initStruct/01/tags', 0754, true);
        is_dir($this->configSvn['templete_base_path'] . 'initStruct/01/trunk') ? '' : mkdir($this->configSvn['templete_base_path'].'initStruct/01/trunk', 0754, true);

        //Tạo thư mục sasl
        is_dir($this->configSvn['sasl_home']) ? '' : mkdir($this->configSvn['sasl_home'], 0754, true);

        //Tạo thư mục ldap
        is_dir($this->configSvn['ldap_home']) ? '' : mkdir($this->configSvn['ldap_home'], 0754, true);

        //Tạo thư mục crond
        is_dir($this->configSvn['crond_base_path']) ? '' : mkdir($this->configSvn['crond_base_path'], 0754, true);

        echo '===============================================' . PHP_EOL;

        echo 'Create related files' . PHP_EOL;

        //Ghi vào tệp biến môi trường svnserve
        $con_svnserve_env_file = file_get_contents($templete_path . 'svnserve/svnserve');
        $con_svnserve_env_file = sprintf($con_svnserve_env_file, $this->configSvn['rep_base_path'], $this->configSvn['svn_conf_file'], $this->configSvn['svnserve_log_file']);
        file_put_contents($this->configSvn['svnserve_env_file'], $con_svnserve_env_file);

        //Viết file cấu hình quyền kho SVN
        $con_svn_conf_file = file_get_contents($templete_path . 'svnserve/svnserve.conf');
        file_put_contents($this->configSvn['svn_conf_file'], $con_svn_conf_file);

        //tập tin cấu hình máy chủ ldap
        file_put_contents($this->configSvn['ldap_config_file'], '');

        //Ghi vào file authz
        $con_svn_authz_file = file_get_contents($templete_path . 'svnserve/authz');
        if (file_exists($this->configSvn['svn_authz_file'])) {
            echo PHP_EOL . '===============================================' . PHP_EOL;
            echo 'Do you want to overwrite the original authority configuration file authz? [y/n]:';
            $continue = strtolower(trim(fgets(STDIN)));

            if (!in_array($continue, ['y', 'n'])) {
                echo 'Incorrect option! '  . PHP_EOL;
                echo '===============================================' . PHP_EOL;
                exit();
            }

            if ($continue == 'y') {
                //hỗ trợ
                copy($this->configSvn['svn_authz_file'], $this->configSvn['home_path'] . time() . 'authz');
                //vận hành
                file_put_contents($this->configSvn['svn_authz_file'], $con_svn_authz_file);
            }
        } else {
            file_put_contents($this->configSvn['svn_authz_file'], $con_svn_authz_file);
        }

        //Write file httpPasswd
        if (!file_exists($this->configSvn['http_passwd_file'])) {
            file_put_contents($this->configSvn['http_passwd_file'], '');
        }

        //Ghi vào file passwd
        $con_svn_passwd_file = file_get_contents($templete_path . 'svnserve/passwd');
        if (file_exists($this->configSvn['svn_passwd_file'])) {
            echo PHP_EOL . '===============================================' . PHP_EOL;
            echo 'Do you want to overwrite the original permission configuration file passwd？[y/n]：';
            $continue = strtolower(trim(fgets(STDIN)));

            if (!in_array($continue, ['y', 'n'])) {
                echo 'Option is incorrect!'  . PHP_EOL;
                echo '===============================================' . PHP_EOL;
                exit();
            }

            if ($continue == 'y') {
                //hỗ trợ
                copy($this->configSvn['svn_passwd_file'], $this->configSvn['home_path'] . time() . 'passwd');
                //vận hành
                file_put_contents($this->configSvn['svn_passwd_file'], $con_svn_passwd_file);
            }
        } else {
            file_put_contents($this->configSvn['svn_passwd_file'], $con_svn_passwd_file);
        }

        //Tạo tệp nhật ký hoạt động svnserve
        file_put_contents($this->configSvn['svnserve_log_file'], '');

        //Tạo tập tin pid
        file_put_contents($this->configSvn['svnserve_pid_file'], '');

        echo '===============================================' . PHP_EOL;

        /**
         *5. Đóng selinux
         *Bao gồm việc đóng cửa tạm thời và vĩnh viễn
         */
        echo 'Temporarily shut down and permanently shut down seliux' . PHP_EOL;

        //Tạm thời đóng selinux
        shell_exec('setenforce 0');

        //Đóng vĩnh viễn selinux
        shell_exec("sed -i 's/SELINUX=enforcing/SELINUX=disabled/g' /etc/selinux/config");

        echo '===============================================' . PHP_EOL;

        /**
         *6. Cấu hình file cơ sở dữ liệu SQLite
         */
        echo 'Configure and enable SQLite database' . PHP_EOL;

        if (file_exists($this->configSvn['home_path'] . 'svnadmin.db')) {
            echo PHP_EOL . '===============================================' . PHP_EOL;
            echo 'Do you want to overwrite the original SQLite database file svnadmin.db?[y/n]：';
            $continue = strtolower(trim(fgets(STDIN)));

            if (!in_array($continue, ['y', 'n'])) {
                echo 'Option is incorrect!'  . PHP_EOL;
                echo '===============================================' . PHP_EOL;
                exit();
            }

            if ($continue == 'y') {
                //hỗ trợ
                copy($this->configSvn['home_path'] . 'svnadmin.db', $this->configSvn['home_path'] . time() . 'svnadmin.db');
                //vận hành
                copy($templete_path . 'database/sqlite/svnadmin.db', $this->configSvn['home_path'] . 'svnadmin.db');
            }
        } else {
            copy($templete_path . 'database/sqlite/svnadmin.db', $this->configSvn['home_path'] . 'svnadmin.db');
        }

        echo '===============================================' . PHP_EOL;

        /**
         *8. Đăng ký svnserve làm dịch vụ hệ thống
         */
        echo 'Clean up the previously registered svnserve service' . PHP_EOL;

        passthru('systemctl stop svnserve.service');
        passthru('systemctl disable svnserve.service');
        passthru('systemctl daemon-reload');

        echo '===============================================' . PHP_EOL;

        echo 'Register new svnserve service' . PHP_EOL;

        $os = $this->GetOS();
        $con_svnserve_service_file = file_get_contents($templete_path . 'svnserve/svnserve.service');
        $con_svnserve_service_file = sprintf($con_svnserve_service_file, $this->configSvn['svnserve_env_file'], $needBin['svnserve'], $this->configSvn['svnserve_pid_file']);
        if ($os == 'hundreds 7' || $os == 'hundreds 8') {
            file_put_contents($this->configSvn['svnserve_service_file']['hundreds'], $con_svnserve_service_file);
        } elseif ($os == 'ubuntu') {
            file_put_contents($this->configSvn['svnserve_service_file']['ubuntu'], $con_svnserve_service_file);
        } elseif ($os == 'rocky') {
            file_put_contents($this->configSvn['svnserve_service_file']['hundreds'], $con_svnserve_service_file);
        } else {
            file_put_contents($this->configSvn['svnserve_service_file']['hundreds'], $con_svnserve_service_file);
            echo '===============================================' . PHP_EOL;
            echo 'warn! The current operating system version has not been tested, and you may encounter problems during use! ' . PHP_EOL;
            echo '===============================================' . PHP_EOL;
        }

        echo '===============================================' . PHP_EOL;

        //khởi động
        echo 'Start the svnserve service' . PHP_EOL;

        passthru('systemctl daemon-reload');
        passthru('systemctl start svnserve');

        echo '===============================================' . PHP_EOL;

        //Khởi động tự động khi khởi động
        echo 'Add svnserve service to boot self-start' . PHP_EOL;

        passthru('systemctl enable svnserve');

        echo '===============================================' . PHP_EOL;

        //kiểm tra trạng thái
        echo 'Svnserve installed successfully, print running status:' . PHP_EOL;

        passthru('systemctl status svnserve');

        echo '===============================================' . PHP_EOL;
    }

    /**
     *Sửa đổi thư mục chính lưu trữ dữ liệu hiện tại
     */
    function MoveHome()
    {
        //Kiểm tra xem svnserve có bị dừng không
        if (shell_exec('ps auxf|grep -v "grep"|grep svnserve') != '') {
            echo 'Please manually stop the running svnserve program and try again! ' . PHP_EOL;
            echo '===============================================' . PHP_EOL;
            exit();
        }

        //đường dẫn đầu vào
        echo 'Please enter the absolute path of the target directory:';
        $newHomePath = trim(fgets(STDIN));
        if ($newHomePath == '') {
            echo '===============================================' . PHP_EOL;
            echo 'Input can not be empty! ' . PHP_EOL;
            echo '===============================================' . PHP_EOL;
            exit();
        }

        //Kiểm tra xem đường dẫn đích cần sửa có tồn tại không
        clearstatcache();
        if (!is_dir($newHomePath)) {
            echo '===============================================' . PHP_EOL;
            echo 'The target directory does not exist! ' . PHP_EOL;
            echo '===============================================' . PHP_EOL;
            exit();
        }

        //Đường dẫn có giống nhau không
        if ($newHomePath == $this->configSvn['home_path']) {
            echo '===============================================' . PHP_EOL;
            echo 'No change in path! '  . PHP_EOL;
            echo '===============================================' . PHP_EOL;
            exit();
        }

        //Kiểm tra xem đường dẫn đích có trống không
        if (!$this->IsDirEmpty($newHomePath)) {
            echo '===============================================' . PHP_EOL;
            echo 'The target directory needs to be empty! ' . PHP_EOL;
            echo '===============================================' . PHP_EOL;
            exit();
        }

        echo '===============================================' . PHP_EOL;
        echo 'remind! This step applies to the situation where you have performed [1] or [2] steps for initial configuration' . PHP_EOL;

        echo '===============================================' . PHP_EOL;
        echo 'remind! It is not recommended to move the datastore home directory to the root directory, as this will cause problems with read permissions (unless the root directory is set to 777 , but this is not a good idea either)' . PHP_EOL;

        //Bình thường hóa đường dẫn đầu vào, nếu không có /ở cuối sẽ tự động hoàn thành
        if (substr($newHomePath, -1) != '/') {
            $newHomePath .= '/';
        }

        //xác nhận hai lần
        echo '===============================================' . PHP_EOL;
        echo sprintf('Modify datastore home directory from %s to %s', $this->configSvn['home_path'], $newHomePath) . PHP_EOL;
        echo '===============================================' . PHP_EOL;
        echo 'Are you sure you want to continue [y/n]:';
        $continue = strtolower(trim(fgets(STDIN)));
        echo '===============================================' . PHP_EOL;

        if (!in_array($continue, ['y', 'n'])) {
            echo 'Incorrect option! '  . PHP_EOL;
            echo '===============================================' . PHP_EOL;
            exit();
        }

        if ($continue == 'n') {
            echo 'Cancelled! ' . PHP_EOL;
            echo '===============================================' . PHP_EOL;
            exit();
        }

        //nội dung cũ
        $oldConfigSvn = $this->configSvn;

        //Sửa file cấu hình svn.php
        $con = file_get_contents(BASE_PATH . '/../config/svn.php');
        $con  = preg_replace("/\\\$home[\s]*=[\s]*(['\"])(.*)\\1[;]/", sprintf("\$home = '%s';", $newHomePath), $con);
        //Xác định trận đấu có thành công hay không
        file_put_contents(BASE_PATH . '/../config/svn.php', $con);

        //Nội dung mới
        $newConfigSvn = Config::get('svn');

        //Sửa đổi đường dẫn kho, đường dẫn tệp cấu hình và đường dẫn tệp nhật ký trong tệp svnserve
        echo 'Modify the svnserve environment variable file' . PHP_EOL;

        $templete_path = BASE_PATH . '/../templete/';
        $con_svnserve_env_file = file_get_contents($templete_path . 'svnserve/svnserve');
        $con_svnserve_env_file = sprintf($con_svnserve_env_file, $newConfigSvn['rep_base_path'], $newConfigSvn['svn_conf_file'], $newConfigSvn['svnserve_log_file']);
        file_put_contents($oldConfigSvn['svnserve_env_file'], $con_svnserve_env_file);

        echo '===============================================' . PHP_EOL;

        //Bắt đầu di chuyển thư mục chính
        echo 'Start moving home directory' . PHP_EOL;

        passthru(sprintf("mv %s*%s", $oldConfigSvn['home_path'], $newConfigSvn['home_path']));

        echo '===============================================' . PHP_EOL;

        echo 'Clean up the previously registered svnserve service' . PHP_EOL;

        passthru('systemctl stop svnserve.service');
        passthru('systemctl disable svnserve.service');
        passthru('systemctl daemon-reload');

        echo '===============================================' . PHP_EOL;

        echo 'Register new svnserve service' . PHP_EOL;

        $os = $this->GetOS();
        $con_svnserve_service_file = file_get_contents($templete_path . 'svnserve/svnserve.service');
        $con_svnserve_service_file = sprintf($con_svnserve_service_file, $newConfigSvn['svnserve_env_file'], $this->configBin['svnserve'], $newConfigSvn['svnserve_pid_file']);
        if ($os == 'hundreds 7' || $os == 'hundreds 8') {
            file_put_contents($newConfigSvn['svnserve_service_file']['hundreds'], $con_svnserve_service_file);
        } elseif ($os == 'ubuntu') {
            file_put_contents($newConfigSvn['svnserve_service_file']['ubuntu'], $con_svnserve_service_file);
        } elseif ($os == 'rocky') {
            file_put_contents($newConfigSvn['svnserve_service_file']['hundreds'], $con_svnserve_service_file);
        } else {
            file_put_contents($newConfigSvn['svnserve_service_file']['hundreds'], $con_svnserve_service_file);
            echo '===============================================' . PHP_EOL;
            echo 'warn! The current operating system version has not been tested, and you may encounter problems during use! ' . PHP_EOL;
            echo '===============================================' . PHP_EOL;
        }

        echo '===============================================' . PHP_EOL;

        //khởi động
        echo 'Start the svnserve service' . PHP_EOL;

        passthru('systemctl daemon-reload');
        passthru('systemctl start svnserve');

        echo '===============================================' . PHP_EOL;

        //Khởi động tự động khi khởi động
        echo 'Add svnserve service to boot self-start' . PHP_EOL;

        passthru('systemctl enable svnserve');

        echo '===============================================' . PHP_EOL;

        //kiểm tra trạng thái
        echo 'svnserve reconfiguration succeeded, print running status:' . PHP_EOL;

        passthru('systemctl status svnserve');

        echo '===============================================' . PHP_EOL;

        //khởi động lại daemon
        echo 'Please run the svnadmind.php program to manually restart the daemon! ' . PHP_EOL;

        passthru('php svnadmind.php stop');

        echo '===============================================' . PHP_EOL;

        //cơ sở dữ liệu sqlite
    }

    /**
     *Nhập chương trình
     */
    function Run()
    {
        echo '===============SVNAdmin==================' . PHP_EOL;

        foreach ($this->scripts as $value) {
            echo '[' . $value['index'] . '] ' . $value['note'] . PHP_EOL;
        }

        echo '===============================================' . PHP_EOL;

        echo 'Please enter the command number:';

        $answer = trim(fgets(STDIN));

        echo '===============================================' . PHP_EOL;

        if (!in_array($answer, array_column($this->scripts, 'index'))) {
            exit('Bad command number:' . PHP_EOL);
        }

        if ($answer == 1) {
            //Giúp tôi cài đặt và cấu hình Subversion

            $shellPath = BASE_PATH . '/../templete/install/WANdisco/';

            if (!is_dir($shellPath)) {
                exit('The install script directory does not exist! ' . PHP_EOL);
            }

            $shell = scandir($shellPath);

            echo '| Subversion installation script from WANdiso' . PHP_EOL;

            echo '| The currently provided installation scripts may not be suitable for all operating systems! Such as part of ubuntu and rokcy, etc.' . PHP_EOL;

            echo '| If the Subversion version provided by the current operating system platform is lower (<1.8), it is recommended to use this method to install Subversion! ' . PHP_EOL;

            echo '| If the installation fails due to network delay, you can manually stop it and try a few more times' . PHP_EOL;

            echo '| In the process of installing Subversion through the script, please pay attention to the information interaction! ' . PHP_EOL;

            echo '===============================================' . PHP_EOL;

            echo 'The available Subversion versions are as follows:' . PHP_EOL;

            echo '===============================================' . PHP_EOL;

            $noShell = true;
            foreach ($shell as $value) {
                if ($value == '.' || $value == '..') {
                    continue;
                }
                $noShell = false;
                echo $value . PHP_EOL;
            }

            if ($noShell) {
                exit('There are no optional install scripts! ' . PHP_EOL);
            }

            echo '===============================================' . PHP_EOL;

            echo 'Please note that the Subversion version supported by SVNAdmin is 1.8+! ' . PHP_EOL;

            echo '===============================================' . PHP_EOL;

            echo 'Please enter the Subversion version to install (Subversion-1.10 is recommended):';

            $answer = trim(fgets(STDIN));

            echo '===============================================' . PHP_EOL;

            if (!file_exists($shellPath . 'subversion_installer_' . $answer . '.sh')) {
                exit('Please select the correct version! ' . PHP_EOL);
            }

            echo 'Now execute the script:' . 'subversion_installer_' . $answer . '.sh' . PHP_EOL;

            echo '===============================================' . PHP_EOL;

            passthru('sh ' . $shellPath . 'subversion_installer_' . $answer . '.sh');

            $this->ConfigSubversion();
        } elseif ($answer == 2) {
            //Khởi tạo Subversion theo yêu cầu của hệ thống này (đối với Subversion được cài đặt theo cách khác)
            $this->ConfigSubversion();
        } elseif ($answer == 3) {
            //Phát hiện phiên bản mới của SVNAdmin
            $this->DetectUpdate();
        } elseif ($answer == 4) {
            //Sửa đổi thư mục chính lưu trữ dữ liệu hiện tại
            $this->MoveHome();
        }
    }
}

//chức năng phát hiện bị vô hiệu hóa
$require_functions = ['shell_exec', 'passthru'];
$disable_functions = explode(',', ini_get('disable_functions'));
foreach ($disable_functions as $disable) {
    if (in_array(trim($disable), $require_functions)) {
        echo "needs$disablefunction is disabled" . PHP_EOL;
        exit();
    }
}

(new Install())->Run();
