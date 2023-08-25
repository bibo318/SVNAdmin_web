<?php
/*
 *@Tác giả: bibo318
 *
 *@LastEditors: bibo318
 *
 *@Mô tả: github: /bibo318
 */

namespace app\service;

use app\service\Apache as ServiceApache;

class Svn extends Base
{
    /**
     *Các đối tượng lớp dịch vụ khác
     *
     *Đối tượng @var
     */
    private $ServiceApache;

    function __construct($parm = [])
    {
        parent::__construct($parm);

        $this->ServiceApache = new ServiceApache($parm);
    }

    /**
     *Nhận thông tin chi tiết về svnserve
     */
    public function GetSvnInfo()
    {
        return message(200, 1, '成功', [
            'enable' => $this->enableCheckout == 'svn',

            'version' => $this->GetSvnserveInfo(),
            'status' => $this->GetSvnserveStatus()['data'],
            'listen_port' => $this->localSvnPort,
            'listen_host' => $this->localSvnHost,
            'svnserve_log' => $this->configSvn['svnserve_log_file'],
            'password_db' => $this->configSvn['svn_passwd_file'],

            'sasl' => $this->GetSaslInfo(),

            'user_source' => $this->svnDataSource['user_source'],
            'group_source' => $this->svnDataSource['group_source'],

            'ldap' => $this->svnDataSource['ldap']
        ]);
    }

    /**
     *Nhận thông tin svnserve
     *
     *@return void
     */
    private function GetSvnserveInfo()
    {
        $version = '-';
        $result = funShellExec(sprintf("'%s' --version", $this->configBin['svnserve']));
        if ($result['code'] == 0) {
            preg_match_all($this->configReg['REG_SUBVERSION_VERSION'], $result['result'], $versionInfoPreg);
            if (preg_last_error() != 0) {
                $version = 'PREG_ERROR';
            }
            if (array_key_exists(0, $versionInfoPreg[0])) {
                $version = trim($versionInfoPreg[1][0]);
            } else {
                $version = '--';
            }
        }

        return $version;
    }

    /**
     *Lưu cấu hình liên quan đến svnserve
     *
     *@return void
     */
    public function UpdSvnUsersource()
    {
        $checkResult = funCheckForm($this->payload, [
            'data_source' => ['type' => 'array', 'notNull' => true],
        ]);
        if ($checkResult['status'] == 0) {
            return message($checkResult['code'], $checkResult['status'], $checkResult['message'] . ': ' . $checkResult['data']['column']);
        }

        $dataSource = $this->payload['data_source'];

        if ($dataSource['user_source'] == 'ldap') {

            if (substr($dataSource['ldap']['ldap_host'], 0, strlen('ldap://')) != 'ldap://' && substr($dataSource['ldap']['ldap_host'], 0, strlen('ldaps://')) != 'ldaps://') {
                return message(200, 0, 'Tên máy chủ ldap phải kết thúc bằng ldap:// 或者 ldaps:// bắt đầu');
            }

            if (preg_match('/\:[0-9]+/', $dataSource['ldap']['ldap_host'], $matches)) {
                return message(200, 0, 'tên máy chủ ldap không mang cổng');
            }

            if ($dataSource['group_source'] == 'ldap') {
                $checkResult = funCheckForm($dataSource['ldap'], [
                    'ldap_host' => ['type' => 'string', 'notNull' => true],
                    'ldap_port' => ['type' => 'integer'],
                    'ldap_version' => ['type' => 'integer'],
                    'ldap_bind_dn' => ['type' => 'string', 'notNull' => true],
                    'ldap_bind_password' => ['type' => 'string', 'notNull' => true],

                    'group_base_dn' => ['type' => 'string', 'notNull' => true],
                    'group_search_filter' => ['type' => 'string', 'notNull' => true],
                    'group_attributes' => ['type' => 'string', 'notNull' => true],
                    'groups_to_user_attribute' => ['type' => 'string', 'notNull' => true],
                    'groups_to_user_attribute_value' => ['type' => 'string', 'notNull' => true],
                ]);
                if ($checkResult['status'] == 0) {
                    return message($checkResult['code'], $checkResult['status'], $checkResult['message'] . ': ' . $checkResult['data']['column']);
                }
            } else {
                $checkResult = funCheckForm($dataSource['ldap'], [
                    'ldap_host' => ['type' => 'string', 'notNull' => true],
                    'ldap_port' => ['type' => 'integer'],
                    'ldap_version' => ['type' => 'integer'],
                    'ldap_bind_dn' => ['type' => 'string', 'notNull' => true],
                    'ldap_bind_password' => ['type' => 'string', 'notNull' => true],

                    'user_base_dn' => ['type' => 'string', 'notNull' => true],
                    'user_search_filter' => ['type' => 'string', 'notNull' => true],
                    'user_attributes' => ['type' => 'string', 'notNull' => true],
                ]);
                if ($checkResult['status'] == 0) {
                    return message($checkResult['code'], $checkResult['status'], $checkResult['message'] . ': ' . $checkResult['data']['column']);
                }
            }

            //Xóa người dùng cơ sở dữ liệu
            $this->database->delete('svn_users', [
                'svn_user_id[>]' => 0
            ]);

            if ($dataSource['group_source'] == 'ldap') {
                //Xóa nhóm cơ sở dữ liệu
                $this->database->delete('svn_groups', [
                    'svn_group_id[>]' => 0
                ]);
            }

            //Mở use-sasl
            $result = $this->UpdSvnSaslStart();
            if ($result['status'] != 1) {
                return message($result['code'], $result['status'], $result['message'], $result['data']);
            }

            //Ghi vào /etc/sasl2/svn.conf
            $sasl2 = '/etc/sasl2/svn.conf';
            funShellExec(sprintf("mkdir -p /etc/sasl2 && touch '%s' && chmod o+w '%s'", $sasl2, $sasl2), true);
            if (!is_writable($sasl2)) {
                return message(200, 0, sprintf('Tệp [%s] không thể đọc được hoặc không tồn tại', $sasl2));
            }
            file_put_contents($sasl2, "pwcheck_method: saslauthd\nmech_list: PLAIN LOGIN\n");

            //ghi vào sasl/ldap/saslauthd.conf
            $templeteSaslauthdPath = BASE_PATH . '/templete/sasl/ldap/saslauthd.conf';
            if (!is_readable($templeteSaslauthdPath)) {
                return message(200, 0, sprintf('Tệp [%s] không thể đọc được hoặc không tồn tại', $templeteSaslauthdPath));
            }
            $new = file_get_contents($templeteSaslauthdPath);

            $ldap = $dataSource['ldap'];

            $ldap_servers = rtrim(trim($ldap['ldap_host']), '/') . ':' . $ldap['ldap_port'] . '/';
            $ldap_bind_dn = $ldap['ldap_bind_dn'];
            $ldap_bind_pw = $ldap['ldap_bind_password'];
            $ldap_search_base = $ldap['user_base_dn'];

            if (substr($ldap['user_search_filter'], 0, 1) == '(' && substr($ldap['user_search_filter'], -1) == ')') {
                $ldap_filter = $ldap['user_search_filter'];
            } else {
                $ldap_filter = '(' . $ldap['user_search_filter'] . ')';
            }

            $ldap_filter =  '(&(' . explode(',', $ldap['user_attributes'])[0] . '=%U)' . $ldap_filter . ')';
            $ldap_version = $ldap['ldap_version'];
            $ldap_password_attr = 'userPassword';

            $new = sprintf(
                $new,
                $ldap_servers,
                $ldap_bind_dn,
                $ldap_bind_pw,
                $ldap_search_base,
                $ldap_filter,
                $ldap_version,
                $ldap_password_attr
            );

            if (!is_writable($this->configSvn['ldap_config_file'])) {
                return message(200, 0, sprintf('Tệp [%s] không thể ghi hoặc không tồn tại', $this->configSvn['ldap_config_file']));
            }
            $old = file_get_contents($this->configSvn['ldap_config_file']);

            if ($new != $old) {
                file_put_contents($this->configSvn['ldap_config_file'], $new);

                //khởi động lại saslauthd
                $result = $this->UpdSaslStatusStop();
                //if ($result['status'] != 1) {
                //trả về tin nhắn($result['code'], $result['status'], $result['message'], $result['data']);
                //}
                sleep(1);
                $result = $this->UpdSaslStatusStart();
                if ($result['status'] != 1) {
                    return message($result['code'], $result['status'], $result['message'], $result['data']);
                }
            }

            $this->database->update('options', [
                'option_value' => serialize([
                    'user_source' => $dataSource['user_source'],
                    'group_source' => $dataSource['group_source'],
                    'ldap' => $dataSource['ldap']
                ])
            ], [
                'option_name' => '24_svn_datasource'
            ]);
        } else {
            //Đóng use-sasl
            $result = $this->UpdSvnSaslStop();
            if ($result['status'] != 1) {
                return message($result['code'], $result['status'], $result['message'], $result['data']);
            }

            $this->database->update('options', [
                'option_value' => serialize([
                    'user_source' => 'passwd',
                    'group_source' => 'authz',
                    'ldap' => $dataSource['ldap']
                ])
            ], [
                'option_name' => '24_svn_datasource'
            ]);
        }

        //khởi động lại svnserve
        $result = $this->UpdSvnserveStatusStop();
        //if ($result['status'] != 1) {
        //trả về tin nhắn($result['code'], $result['status'], $result['message'], $result['data']);
        //}
        sleep(1);
        $result = $this->UpdSvnserveStatusStart();
        if ($result['status'] != 1) {
            return message($result['code'], $result['status'], $result['message'], $result['data']);
        }

        return message();
    }

    /**
     *Nhận thông tin sasl
     *
     *@return mảng
     */
    private function GetSaslInfo()
    {
        $result = funShellExec(sprintf("'%s' -v", $this->configBin['saslauthd']));

        $result = $result['error'];

        $version = '-';
        if (preg_match('/^[\s]*saslauthd[\s]+(.*)/m', $result, $pregResult)) {
            $version = trim($pregResult[1]);
        }

        $mechanisms = '-';
        if (preg_match('/^[\s]*authentication mechanisms:[\s]+(.*)/m', $result, $pregResult)) {
            $mechanisms = trim($pregResult[1]);
        }

        $statusRun = true;
        if (!file_exists($this->configSvn['saslauthd_pid_file'])) {
            $statusRun = false;
        } else {
            $pid = trim(file_get_contents($this->configSvn['saslauthd_pid_file']));
            clearstatcache();
            if (is_dir("/proc/$pid")) {
                $statusRun = true;
            } else {
                $statusRun = false;
            }
        }

        return [
            'version' => $version,
            'mechanisms' => $mechanisms,
            'status' => $statusRun
        ];
    }

    /**
     *Bắt đầu dịch vụ saslauthd
     *
     *@return void
     */
    public function UpdSaslStatusStart()
    {
        if (empty($this->configBin['saslauthd'])) {
            return message(200, 0, 'Đường dẫn saslauthd không được cấu hình trong file config/bin.php');
        }

        if (file_exists($this->configSvn['saslauthd_pid_file'])) {
            $pid = trim(file_get_contents($this->configSvn['saslauthd_pid_file']));
            clearstatcache();
            if (is_dir("/proc/$pid")) {
                return message(200, 0, 'dịch vụ đang chạy');
            }
        }

        $unique = uniqid('saslauthd_');

        $cmdStart = sprintf(
            "'%s' -a '%s' -O '%s' -O '%s'",
            $this->configBin['saslauthd'],
            'ldap',
            $unique,
            $this->configSvn['ldap_config_file']
        );

        $result = funShellExec($cmdStart, true);

        if ($result['code'] != 0) {
            return message(200, 0, 'Không thể bắt đầu quá trình: ' . $result['error']);
        }

        sleep(1);

        $result = funShellExec(sprintf("ps aux | grep -v grep | grep %s | awk 'NR==1' | awk '{print $2}'", $unique));
        if ($result['code'] != 0) {
            return message(200, 0, 'nhận được quá trình thất bại: ' . $result['error']);
        }

        funFilePutContents($this->configSvn['saslauthd_pid_file'], trim($result['result']), true);
        if (!file_exists($this->configSvn['saslauthd_pid_file'])) {
            return message(200, 0, sprintf('Không thể buộc ghi vào tệp [%s] -vui lòng ủy quyền cho thư mục dữ liệu', $this->configSvn['saslauthd_pid_file']));
        }
        if (file_get_contents($this->configSvn['saslauthd_pid_file']) !== trim($result['result'])) {
            return message(200, 0, 'Quá trình bắt đầu thành công -nhưng không ghi được tệp pid -vui lòng liên hệ với quản trị viên');
        }

        return message();
    }

    /**
     *Đóng dịch vụ saslauthd
     *
     *@return void
     */
    public function UpdSaslStatusStop()
    {
        if (!file_exists($this->configSvn['saslauthd_pid_file'])) {
            return message();
        }

        $pid = trim(file_get_contents($this->configSvn['saslauthd_pid_file']));
        if (empty($pid)) {
            return message();
        }

        clearstatcache();
        if (!is_dir("/proc/$pid")) {
            return message();
        }

        $result = funShellExec(sprintf("kill -15 %s", $pid), true);

        if ($result['code'] != 0) {
            return message(200, 0, 'lỗi dừng dịch vụ saslauthd: ' . $result['error']);
        }

        sleep(1);

        clearstatcache();
        if (is_dir("/proc/$pid")) {
            return message(200, 0, 'lỗi dừng dịch vụ saslauthd');
        }

        return message();
    }

    /**
     *Lấy trạng thái hoạt động của svnserve
     */
    public function GetSvnserveStatus()
    {
        if ($this->enableCheckout == 'svn') {
            clearstatcache();

            $statusRun = true;

            if (!file_exists($this->configSvn['svnserve_pid_file'])) {
                $statusRun = false;
            } else {
                $pid = trim(file_get_contents($this->configSvn['svnserve_pid_file']));
                clearstatcache();
                if (is_dir("/proc/$pid")) {
                    $statusRun = true;
                } else {
                    $statusRun = false;
                }
            }

            return message(200, 1, $statusRun ? 'Dịch vụ bình thường' : 'Dịch vụ svnserve không chạy. Vì lý do bảo mật, người dùng SVN sẽ không thể sử dụng chức năng duyệt nội dung trực tuyến của thư mục hệ thống và các chức năng khác sẽ không bị ảnh hưởng', $statusRun);
        } else {
            return message();
        }
    }

    /**
     *bắt đầu svnserve
     */
    public function UpdSvnserveStatusStart()
    {
        $svnserveLog = false;
        if ($svnserveLog) {
            $cmdStart = sprintf(
                "'%s' --daemon --pid-file '%s' -r '%s' --config-file '%s' --log-file '%s' --listen-port %s --listen-host %s",
                $this->configBin['svnserve'],
                $this->configSvn['svnserve_pid_file'],
                $this->configSvn['rep_base_path'],
                $this->configSvn['svn_conf_file'],
                $this->configSvn['svnserve_log_file'],
                $this->localSvnPort,
                $this->localSvnHost
            );
        } else {
            $cmdStart = sprintf(
                "'%s' --daemon --pid-file '%s' -r '%s' --config-file '%s' --listen-port %s --listen-host %s",
                $this->configBin['svnserve'],
                $this->configSvn['svnserve_pid_file'],
                $this->configSvn['rep_base_path'],
                $this->configSvn['svn_conf_file'],
                $this->localSvnPort,
                $this->localSvnHost
            );
        }

        $result = funShellExec($cmdStart, true);

        if ($result['code'] == 0) {
            return message();
        } else {
            return message(200, 0, $result['error']);
        }
    }

    /**
     *dừng svnserve
     */
    public function UpdSvnserveStatusStop()
    {
        if (!file_exists($this->configSvn['svnserve_pid_file'])) {
            return message();
        }

        $pid = trim(file_get_contents($this->configSvn['svnserve_pid_file']));
        if (empty($pid)) {
            return message();
        }

        clearstatcache();
        if (!is_dir("/proc/$pid")) {
            return message();
        }

        $result = funShellExec(sprintf("kill -15 %s", $pid), true);

        if ($result['code'] != 0) {
            return message(200, 0, $result['error']);
        }

        sleep(1);

        clearstatcache();
        if (is_dir("/proc/$pid")) {
            return message(200, 0, 'dừng dịch vụ svnserve không thành công');
        }

        return message();
    }

    /**
     *Kích hoạt svn để sử dụng tùy chọn sasl
     *
     *@return void
     */
    private function UpdSvnSaslStart()
    {
        $con = file_get_contents($this->configSvn['svn_conf_file']);

        $result = $this->UpdUsesaslStatus($con, 'true');
        if (is_numeric($result)) {
            return message(200, 0, 'svn không kích hoạt được use-sasl');
        }
        if ($result == $con) {
            return message();
        }
        $result = file_put_contents($this->configSvn['svn_conf_file'], $result);
        if (!$result) {
            return message(200, 0, sprintf('Không thể ghi tệp [%s]', $this->configSvn['svn_conf_file']));
        }

        return message();
    }

    /**
     *Vô hiệu hóa tùy chọn svn sử dụng sasl
     *
     *@return void
     */
    private function UpdSvnSaslStop()
    {
        $con = file_get_contents($this->configSvn['svn_conf_file']);

        $result = $this->UpdUsesaslStatus($con, 'false');
        if (is_numeric($result)) {
            return message(200, 0, 'svn không kích hoạt được use-sasl');
        }
        if ($result == $con) {
            return message();
        }
        $result = file_put_contents($this->configSvn['svn_conf_file'], $result);
        if (!$result) {
            return message(200, 0, sprintf('Không thể ghi tệp [%s]', $this->configSvn['svn_conf_file']));
        }

        return message();
    }

    /**
     *Sửa đổi giá trị use-sasl của file svnserve.conf
     *
     *@param chuỗi $con
     *@param chuỗi $status
     *@return string|số nguyên
     */
    private function UpdUsesaslStatus($con, $status)
    {
        $status = ($status === true || $status === 'true') ? 'true' : 'false';
        preg_match_all("/^[ \t]*\[sasl\](((?!\n[ \t]*\[)[\s\S])*)/m", $con, $conPreg);
        if (preg_last_error() != 0) {
            return preg_last_error();
        }
        if (array_key_exists(0, $conPreg[0])) {
            $temp1 = trim($conPreg[1][0]);
            if (empty($temp1)) {
                return preg_replace("/^[ \t]*\[sasl\](((?!\n[ \t]*\[)[\s\S])*)/m", "[sasl]\nuse-sasl = $status\n", $con);
            } else {
                preg_match_all("/^[ \t]*(use-sasl)[ \t]*=[ \t]*(.*)[ \t]*$/m", $conPreg[1][0], $resultPreg);
                if (preg_last_error() != 0) {
                    return preg_last_error();
                }
                if (array_key_exists(0, $resultPreg[0])) {
                    foreach ($resultPreg[1] as $key => $valueStr) {
                        $value = trim($resultPreg[2][$key]);
                        if ($value === $status) {
                            return $con;
                        } else {
                            return preg_replace("/^[ \t]*\[sasl\](((?!\n[ \t]*\[)[\s\S])*)/m", "[sasl]\n" . trim(preg_replace("/^[ \t]*(use-sasl)[ \t]*=[ \t]*(.*)[ \t]*$/m", "use-sasl = $status", $conPreg[1][0])) . "\n", $con);
                        }
                    }
                } else {
                    return preg_replace("/^[ \t]*\[sasl\](((?!\n[ \t]*\[)[\s\S])*)/m", trim($conPreg[0][0]) . "\nuse-sasl = $status\n", $con);
                }
            }
        } else {
            return trim($con) . "\n[sasl]\nuse-sasl = $status\n";
        }
    }

    /**
     *Kích hoạt tính năng kiểm tra giao thức svn
     *
     *@return void
     */
    public function UpdSvnEnable()
    {
        //khởi động lại svnserve
        $result = $this->UpdSvnserveStatusStop();
        //if ($result['status'] != 1) {
        //trả về tin nhắn($result['code'], $result['status'], $result['message'], $result['data']);
        //}
        sleep(1);
        $result = $this->UpdSvnserveStatusStart();
        if ($result['status'] != 1) {
            return message($result['code'], $result['status'], $result['message'], $result['data']);
        }

        //Xóa người dùng cơ sở dữ liệu
        $this->database->delete('svn_users', [
            'svn_user_id[>]' => 0
        ]);

        //chuyển trạng thái hiện tại
        $this->database->update('options', [
            'option_value' => 'svn',
        ], [
            'option_name' => '24_enable_checkout',
        ]);

        //vô hiệu hóa kiểm tra giao thức http
        $result = $this->ServiceApache->UpdSubversionDisable();

        //khởi động lại httpd
        funShellExec(sprintf("'%s' -k graceful", $this->configBin['httpd']), true);

        return message();
    }

    /**
     *Vô hiệu hóa kiểm tra giao thức svn
     *
     *@return void
     */
    public function UpdSvnDisable()
    {
        $this->UpdSvnserveStatusStop();

        return message();
    }
}
