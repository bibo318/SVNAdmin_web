<?php
/*
 *@Tác giả: bibo318
 *
 *@LastEditors: bibo318
 *
 *@Mô tả: github: /bibo318
 */

namespace app\service;

use Verifycode;
use app\service\Ldap as ServiceLdap;
use app\service\Apache as ServiceApache;

class Common extends Base
{
    /**
     *Các đối tượng lớp dịch vụ khác
     *
     *Đối tượng @var
     */
    private $Logs;
    private $Mail;
    private $Setting;
    private $ServiceLdap;
    private $ServiceApache;

    function __construct($parm = [])
    {
        parent::__construct($parm);

        $this->Logs = new Logs($parm);
        $this->Mail = new Mail($parm);
        $this->Setting = new Setting($parm);
        $this->ServiceLdap = new ServiceLdap($parm);
        $this->ServiceApache = new ServiceApache($parm);
    }

    /**
     *Đăng nhập
     */
    public function Login()
    {
        $checkResult = funCheckForm($this->payload, [
            'user_name' => ['type' => 'string', 'notNull' => true],
            'user_pass' => ['type' => 'string', 'notNull' => true],
            'user_role' => ['type' => 'string', 'notNull' => true],
        ]);
        if ($checkResult['status'] == 0) {
            return message($checkResult['code'], $checkResult['status'], $checkResult['message'] . ': ' . $checkResult['data']['column']);
        }

        $userName = $this->payload['user_name'];
        $userPass = $this->payload['user_pass'];
        $userRole = $this->payload['user_role'];
        $userRoleName = $userRole == 1 ? 'administrator' : ($userRole == 2 ? 'Người dùng ' : ($userRole == 3 ? 'Quản trị viên phụ' : 'unknown'));

        //Xóa mã thông báo đã hết hạn
        $this->CleanBlack();

        $verifyOptionResult = $this->Setting->GetVerifyOption();

        if ($verifyOptionResult['status'] != 1) {
            return message(200, 0, $verifyOptionResult['message']);
        }

        $verifyOption = $verifyOptionResult['data'];

        if ($verifyOption['enable'] == true) {
            $endTime = $this->database->get('verification_code', 'end_time', [
                'uuid' => $this->payload['uuid'],
                'code' => $this->payload['code'],
            ]);
            if (empty($endTime)) {
                //Mỗi uuid chỉ được sử dụng một lần để tránh bị nổ
                $this->database->update('verification_code', [
                    'end_time' => 0
                ], [
                    'uuid' => $this->payload['uuid']
                ]);
                return message(200, 0, 'Đăng nhập không thành công[Lỗi mã xác minh]', $endTime);
            }
            if ($endTime == 0) {
                return message(200, 0, 'Đăng nhập không thành công[Mã xác minh không hợp lệ]');
            }
            if ($endTime < time()) {
                return message(200, 0, 'Đăng nhập không thành công [mã xác minh đã hết hạn]');
            }
        }

        $token = '';
        if ($userRole == 1) {
            $result = $this->database->get('admin_users', [
                'admin_user_id',
                'admin_user_name',
                'admin_user_phone',
                'admin_user_email'
            ], [
                'admin_user_name' => $userName,
                'admin_user_password' => $userPass
            ]);
            if (empty($result)) {
                return message(200, 0, 'Đăng nhập không thành công [lỗi tài khoản hoặc mật khẩu]');
            }

            //cập nhật mã thông báo
            $this->database->update('admin_users', [
                'admin_user_token' => $token = $this->CreateToken($userRole, $userName)
            ], [
                'admin_user_name' => $userName
            ]);
        } elseif ($userRole == 2) {
            if ($this->enableCheckout == 'svn') {
                $dataSource = $this->svnDataSource;
            } else {
                $dataSource = $this->httpDataSource;
            }

            if ($dataSource['user_source'] == 'ldap') {
                $result = $this->database->get('svn_users', 'svn_user_id', [
                    'svn_user_name' => $userName,
                ]);
                if (empty($result)) {
                    return message(200, 0, 'Login failed [ldap account not synchronized]');
                }

                if (!$this->ServiceLdap->LdapUserLogin($userName, $userPass)) {
                    return message(200, 0, 'Đăng nhập không thành công [xác thực tài khoản ldap không thành công]');
                }

                $this->database->update('svn_users', [
                    'svn_user_pass' => $userPass
                ], [
                    'svn_user_name' => $userName
                ]);

                if (strstr($userName, '|')) {
                    return message(200, 0,'Đăng nhập không thành công [tên tài khoản ldap không hợp lệ]');
                }
            } else {
                if ($this->enableCheckout == 'svn') {
                    $result = $this->database->get('svn_users', [
                        'svn_user_id',
                        'svn_user_status'
                    ], [
                        'svn_user_name' => $userName,
                        'svn_user_pass' => $userPass
                    ]);
                    if (empty($result)) {
                        return message(200, 0, 'Đăng nhập không thành công [lỗi tài khoản hoặc mật khẩu]');
                    }
                    if ($result['svn_user_status'] == 0) {
                        return message(200, 0, 'Đăng nhập không thành công [Người dùng đã hết hạn]');
                    }
                } else {
                    $result = $this->ServiceApache->Auth($userName, $userPass);
                    if ($result['status'] != 1) {
                        return message2($result);
                    }

                    $result = $this->database->get('svn_users', [
                        'svn_user_id',
                        'svn_user_status'
                    ], [
                        'svn_user_name' => $userName
                    ]);
                    if (empty($result)) {
                        return message(200, 0, 'Đăng nhập không thành công [người dùng không được đồng bộ hóa]');
                    }
                    if ($result['svn_user_status'] == 0) {
                        return message(200, 0, 'Login failed [User has expired]');
                    }

                    $this->database->update('svn_users', [
                        'svn_user_pass' => $userPass
                    ], [
                        'svn_user_name' => $userName
                    ]);
                }
            }

            //Cập nhật thời gian đăng nhập
            $this->database->update('svn_users', [
                'svn_user_last_login' => date('Y-m-d H:i:s')
            ], [
                'svn_user_name' => $userName
            ]);

            //cập nhật mã thông báo
            $this->database->update('svn_users', [
                'svn_user_token' => $token = $this->CreateToken($userRole, $userName)
            ], [
                'svn_user_name' => $userName
            ]);
        } elseif ($userRole == 3) {
            $result = $this->database->get('subadmin', [
                'subadmin_id',
                'subadmin_name',
                'subadmin_password',
                'subadmin_status'
            ], [
                'subadmin_name' => $userName,
                'subadmin_password' => md5($userPass)
            ]);
            if (empty($result)) {
                return message(200, 0, 'Đăng nhập không thành công [lỗi tài khoản hoặc mật khẩu]');
            }
            if ($result['subadmin_status'] == 0) {
                return message(200, 0, 'Đăng nhập không thành công [Người dùng đã hết hạn]');
            }

            //Cập nhật thời gian đăng nhập
            $this->database->update('subadmin', [
                'subadmin_last_login' => date('Y-m-d H:i:s')
            ], [
                'subadmin_name' => $userName
            ]);

            //cập nhật mã thông báo
            $this->database->update('subadmin', [
                'subadmin_token' => $token = $this->CreateToken($userRole, $userName)
            ], [
                'subadmin_name' => $userName
            ]);
        }

        //nhật ký
        $this->Logs->InsertLog(
            'Đăng nhập người dùng',
            sprintf("Tài thư mụcản: %s Địa chỉ IP: %s", $userName, funGetCip()),
            $userName
        );

        //thư
        $this->Mail->SendMail('Common/Login', 'Thông báo người dùng đăng nhập thành công', 'account:' . $userName . ' ' . 'IP address:' . funGetCip() . ' ' . 'time:' . date('Y-m-d H:i:s'));

        $info = $this->GetDynamicRouting($userName, $userRole);
        return message(200, 1, 'Landed successfully', [
            'token' => $token,
            'user_name' => $userName,
            'user_role_name' => $userRoleName,
            'user_role_id' => $userRole,
            'route' => $info['route'],
            'functions' => $info['functions'],
        ]);
    }

    /**
     *生成mã thông báo
     *
     *@param int $userRoleId
     *@param chuỗi $userName
     *@return chuỗi
     */
    private function CreateToken($userRoleId, $userName)
    {
        $nowTime = time();

        $startTime = $nowTime;

        //Định cấu hình thời gian hết hạn thông tin đăng nhập là 6 giờ
        $endTime = $nowTime + 60 * 60 * 6;

        $part1 = $userRoleId . $this->configSign['signSeparator'] . $userName . $this->configSign['signSeparator'] . $startTime . $this->configSign['signSeparator'] . $endTime;

        $part2 = hash_hmac('md5', $part1, $this->configSign['signature']);

        return $part1 . $this->configSign['signSeparator'] . $part2;
    }

    /**
     *đăng xuất
     *
     *Thao tác đăng xuất là thêm mã thông báo chưa hết hạn của người dùng vào cái gọi là danh sách đen
     *Mỗi lần đăng xuất sẽ kích hoạt quét danh sách đen và xóa các mã thông báo đã hết hạn trong danh sách
     *Mục đích: Để nhận ra rằng token chưa hết hạn sau khi người dùng đăng xuất thì không thể tiếp tục sử dụng
     */
    public function Logout()
    {
        if ($this->userRoleId == 1) {
            $this->database->update('admin_users', [
                'admin_user_token' => '-'
            ], [
                'admin_user_name' => $this->userName
            ]);
        } elseif ($this->userRoleId == 2) {
            $this->database->update('svn_users', [
                'svn_user_token' => '-'
            ], [
                'svn_user_name' => $this->userName
            ]);
        } elseif ($this->userRoleId == 3) {
            $this->database->update('subadmin', [
                'subadmin_token' => '-'
            ], [
                'subadmin_name' => $this->userName
            ]);
        }

        //Thêm mã thông báo này
        $this->AddBlack();

        //nhật ký
        $this->Logs->InsertLog(
            'user logout',
            sprintf("account:%s IP address:%s", $this->userName, funGetCip()),
            $this->userName
        );

        //từ bỏ
        return message(200, 1, 'Đăng xuất thành công');
    }

    /**
     *Xóa mã xác minh đã hết hạn
     */
    private function Clean()
    {
        $this->database->delete('verification_code', [
            'end_time[<]' => time()
        ]);
    }

    /**
     *lấy mã xác minh
     */
    public function GetVerifyCode()
    {
        //Xóa mã xác minh đã hết hạn
        $this->Clean();

        //Tạo mã xác minh
        $code = funGetRandStrL(4);

        //Tạo một ID duy nhất
        $uuid = time() . funGetRandStr() . funGetRandStr();

        //
        $prefix = time();

        //Thời gian hiệu quả
        $startTime = $prefix;

        //Thời gian hiệu quả là 60s
        $endTime = $prefix + 60;

        //ghi vào cơ sở dữ liệu
        $this->database->insert('verification_code', [
            'uuid' => $uuid,
            'code' => $code,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'insert_time' => date('Y-m-d H:i:s')
        ]);

        //Truy vấn từ cơ sở dữ liệu để xác minh dữ liệu được ghi bình thường
        $codeId = $this->database->get('verification_code', 'code_id', [
            'uuid' => $uuid
        ]);

        if (empty($codeId)) {
            return message(200, 0, 'Không thể ghi vào cơ sở dữ liệu, nếu là SQLite, vui lòng ủy quyền cho tệp cơ sở dữ liệu và thư mục cấp cao hơn');
        }

        $varification = new Verifycode(134, 32, $code);

        $imageString = $varification->CreateVerifacationImage();

        //Trả về mã hóa base64 của hình ảnh
        return message(200, 1, 'success', [
            'uuid' => $uuid,
            'base64' => $imageString,
        ]);
    }

    /**
     *Thêm mã thông báo vào danh sách đen
     *
     *@return void
     */
    private function AddBlack()
    {
        $arr = explode($this->configSign['signSeparator'], $this->token);
        $this->database->insert('black_token', [
            'token' => $this->token,
            'start_time' => $arr[2],
            'end_time' => $arr[3],
            'insert_time' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     *Quét mã thông báo trong danh sách đen và xóa nó nếu phát hiện nó đã hết hạn
     *
     *Mục đích: không gây thêm áp lực cho việc tìm kiếm
     */
    private function CleanBlack()
    {
        $this->database->delete('black_token', [
            'end_time[<]' => time()
        ]);
    }
}
