<?php
/*
 *@Tác giả: bibo318
 *
 *@LastEditors: bibo318
 *
 *@Mô tả: github: /bibo318
 */

namespace app\service;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class Mail extends Base
{
    private $mail;

    function __construct($parm = [])
    {
        parent::__construct($parm);

        $this->mail = new PHPMailer(true);
        $this->mail->setLanguage('zh_cn', BASE_PATH . '/extension/PHPMailer-6.6.0/language/'); //Tải gói dịch thông báo lỗi
    }

    /**
     *发送邮件的模板函数
     *
     *@param chuỗi $host
     *@param bool $auth
     *@param chuỗi $người dùng
     *@param chuỗi $pass
     *Chuỗi @param $mã hóa ['' | 'không có' | 'SSL' | 'TLS']
     *@param bool $autotls
     *@param int $ cổng
     *@param chuỗi $ chủ đề
     *@param chuỗi $body
     *@param mảng $to
     *@param mảng $cc
     *@param mảng $bcc
     *Mảng @param $reply
     *@param mảng $from
     *Chuỗi @param $fromName
     *@param số nguyên $timeout
*@return vô hiệu
     */
    private function Send($host, $auth, $user, $pass, $encryption, $autotls, $port, $subject, $body, $to = [], $cc = [], $bcc = [], $reply = ['address' => '', 'name' => ''], $from = ['address' => '', 'name' => ''], $timeout = 5)
    {
        try {
            //Không được phép xuất thông tin gỡ lỗi
            $this->mail->SMTPDebug = SMTP::DEBUG_OFF;
            //$this->mail->SMTPDebug = SMTP::DEBUG_SERVER;

            //sử dụng SMTP
            $this->mail->isSMTP();

            //Định cấu hình máy chủ SMTP smtp.example.com
            $this->mail->Host = $host;

            if ($auth) {
                //Cho phép xác thực SMTP
                $this->mail->SMTPAuth = $auth;

                //tên người dùng SMTP user@example.com
                $this->mail->Username = $user;

                //Mật khẩu SMTP
                $this->mail->Password = $pass;
            }

            if ($encryption == 'none' || $encryption == '') {
                //không được mã hóa
                $this->mail->SMTPSecure = "";
                //Có nên cấu hình để tự động kích hoạt TLS không
                $this->mail->SMTPAutoTLS = $autotls;
            } elseif ($encryption == 'SSL') {
                //Phương thức mã hóa là SSL
                $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                //Có nên cấu hình để tự động kích hoạt TLS không
                $this->mail->SMTPAutoTLS = $autotls;
            } elseif ($encryption == 'TLS') {
                //Phương thức mã hóa là TLS
                $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }

            //Hải cảng
            $this->mail->Port = $port;

            //Đặt thời gian chờ gửi
            $this->mail->Timeout = $timeout;

            //người nhận
            foreach ($to as $value) {
                $this->mail->addAddress($value['address'], $value['name']);
            }

            //cc
            foreach ($cc as $value) {
                $this->mail->addCC($value['address'], $value['name']);
            }

            //bcc
            foreach ($bcc as $value) {
                $this->mail->addBCC($value['address'], $value['name']);
            }

            //hồi đáp
            if ($reply != [] && $reply['address'] != '') {
                $this->mail->addReplyTo($reply['address'], $reply['name']);
            }

            //người gửi
            if ($from['address'] != '') {
                $this->mail->setFrom($from['address'], $from['name']);
            }

            //Có nên gửi ở định dạng tài liệu HTML hay không Sau khi gửi, khách hàng có thể hiển thị trực tiếp nội dung được phân tích cú pháp HTML tương ứng
            $this->mail->isHTML(false);

            //Chủ đề email
            $this->mail->Subject = $subject;

            //nội dung email
            $this->mail->Body = $body;

            //gửi
            $this->mail->send();

            return true;
        } catch (Exception $e) {
            return $this->mail->ErrorInfo;
        }
    }

    /**
     *Nhận thông tin cấu hình thư
     */
    public function GetMailInfo()
    {
        $mail_smtp = $this->database->get('options', [
            'option_value'
        ], [
            'option_name' => 'mail_smtp'
        ]);

        $mail_smtp_null = [
            //máy chủ SMTP
            'host' => '',

            //Phương thức mã hóa Đối với hầu hết các máy chủ, nên sử dụng TLS. Nếu nhà cung cấp SMTP của bạn cung cấp cả tùy chọn SSL và TLS, chúng tôi khuyên bạn nên sử dụng TLS.
            'encryption' => 'none',

            //Cổng SMTP
            'port' => 25,

            //TLS tự động Theo mặc định, mã hóa TLS được sử dụng tự động nếu máy chủ hỗ trợ nó (được khuyến nghị). Trong một số trường hợp, nó cần phải bị vô hiệu hóa do máy chủ cấu hình sai có thể gây ra sự cố.
            'autotls' => true,

            //xác thực
            'auth' => true,

            //Tên người dùng SMTP có thể không ở định dạng email, chẳng hạn như smtp.qq.com có ​​thể sử dụng số QQ
            'user' => '',

            //Mật khẩu SMTP
            'pass' => '',

            //Người gửi thường giống với tên người dùng SMTP và cần phải ở định dạng email
            'from' => ['address' => '', 'name' => ''],

            //Trạng thái kích hoạt
            'status' => false,

            //người nhận E-mail
            'to' => [],

            //gửi thời gian chờ
            'timeout' => 5
        ];

        if ($mail_smtp == null) {
            $this->database->insert('options', [
                'option_name' => 'mail_smtp',
                'option_value' => serialize($mail_smtp_null),
                'option_description' => ''
            ]);
            return message(200, 1, 'thành công', $mail_smtp_null);
        }
        if ($mail_smtp['option_value'] == '') {
            $this->database->update('options', [
                'option_value' => serialize($mail_smtp_null),
            ], [
                'option_name' => 'mail_smtp',
            ]);
            return message(200, 1, 'thành công', $mail_smtp_null);
        }

        return message(200, 1, 'thành công', unserialize($mail_smtp['option_value']));
    }

    /**
     *Sửa đổi thông tin cấu hình thư
     */
    public function UpdMailInfo()
    {
        $this->database->update('options', [
            'option_value' => serialize([
                'host' => $this->payload['host'],
                'encryption' => $this->payload['encryption'],
                'port' => $this->payload['port'],
                'autotls' => $this->payload['autotls'],
                'auth' => $this->payload['auth'],
                'user' => $this->payload['user'],
                'pass' => $this->payload['pass'],
                'from' => $this->payload['from'],
                'status' => $this->payload['status'],
                'to' => $this->payload['to'],
                'timeout' => $this->payload['timeout']
            ])
        ], [
            'option_name' => 'mail_smtp'
        ]);
        return message();
    }

    /**
     *Gửi email kiểm tra
     */
    public function SendMailTest()
    {
        $checkResult = funCheckForm($this->payload, [
            'host' => ['type' => 'string', 'notNull' => true],
            'auth' => ['type' => 'boolean'],
            'user' => ['type' => 'string', 'notNull' => true],
            'pass' => ['type' => 'string', 'notNull' => true],
            'encryption' => ['type' => 'string', 'notNull' => true],
            'autotls' => ['type' => 'boolean'],
            'port' => ['type' => 'integer', 'notNull' => true],
            'from' => ['type' => 'array'],
            'test' => ['type' => 'string', 'notNull' => true],
            'timeout' => ['type' => 'integer', 'notNull' => true],
            'user' => ['type' => 'string', 'notNull' => true],
        ]);
        if ($checkResult['status'] == 0) {
            return message($checkResult['code'], $checkResult['status'], $checkResult['message'] . ': ' . $checkResult['data']['column']);
        }

        $host = $this->payload['host'];
        $auth = $this->payload['auth'];
        $user = $this->payload['user'];
        $pass = $this->payload['pass'];
        $encryption = $this->payload['encryption'];
        $autotls = $this->payload['autotls'];
        $port = $this->payload['port'];
        $subject = "Email kiểm tra của SVNAdmin";
        $body = "Email này là email test do hệ thống SVNAdmin gửi đến, khi bạn nhận được email này nghĩa là dịch vụ email của bạn đã được cấu hình đúng.";
        $to = [
            ['address' => $this->payload['test'], 'name' => '']
        ];
        $cc = [];
        $bcc = [];
        $reply = [];
        $from =  $this->payload['from'];
        $timeout = $this->payload['timeout'];
        $result = $this->Send(
            $host,
            $auth,
            $user,
            $pass,
            $encryption,
            $autotls,
            $port,
            $subject,
            $body,
            $to,
            $cc,
            $bcc,
            $reply,
            $from,
            $timeout
        );

        return message(200, $result === true ? 1 : 0, $result === true ? 'gửi thành công' : $result);
    }

    /**
     *Gửi email thông báo
     */
    public function SendMail($trigger, $subject, $body)
    {
        $mail_smtp = $this->GetMailInfo();
        $mail_smtp = $mail_smtp['data'];

        //Kiểm tra xem dịch vụ thư đã được bật chưa
        if (!$mail_smtp['status']) {
            return message(200, 0, 'Dịch vụ thư chưa được bật');
        }

        //kiểm tra điều kiện kích hoạt
        $message_push = $this->GetPushInfo();
        $message_push = $message_push['data'];

        $triggers = array_column($message_push, 'trigger');
        if (!in_array($trigger, $triggers)) {
            return message(200, 0, 'điều kiện kích hoạt không tồn tại');
        }
        $options = array_combine($triggers, array_column($message_push, 'enable'));
        if (!$options[$trigger]) {
            return message(200, 0, 'Điều kiện kích hoạt không được kích hoạt');
        }

        $host = $mail_smtp['host'];
        $auth = $mail_smtp['auth'];
        $user = $mail_smtp['user'];
        $pass = $mail_smtp['pass'];
        $encryption = $mail_smtp['encryption'];
        $autotls = $mail_smtp['autotls'];
        $port = $mail_smtp['port'];
        $to = $mail_smtp['to'];
        $cc = [];
        $bcc = [];
        $reply = [];
        $from = $mail_smtp['from'];
        $timeout = $mail_smtp['timeout'];
        $result = $this->Send(
            $host,
            $auth,
            $user,
            $pass,
            $encryption,
            $autotls,
            $port,
            $subject,
            $body,
            $to,
            $cc,
            $bcc,
            $reply,
            $from,
            $timeout
        );

        return message(200, $result === true ? 1 : 0, $result === true ? 'gửi thành công' : $result);
    }

    /**
     *Email thông báo kích hoạt kế hoạch nhiệm vụ
     */
    public function SendMail2($subject, $body)
    {
        $mail_smtp = $this->GetMailInfo();
        $mail_smtp = $mail_smtp['data'];

        //Kiểm tra xem dịch vụ thư đã được bật chưa
        if (!$mail_smtp['status']) {
            return message(200, 0, 'Dịch vụ thư chưa được bật');
        }

        $host = $mail_smtp['host'];
        $auth = $mail_smtp['auth'];
        $user = $mail_smtp['user'];
        $pass = $mail_smtp['pass'];
        $encryption = $mail_smtp['encryption'];
        $autotls = $mail_smtp['autotls'];
        $port = $mail_smtp['port'];
        $to = $mail_smtp['to'];
        $cc = [];
        $bcc = [];
        $reply = [];
        $from = $mail_smtp['from'];
        $timeout = $mail_smtp['timeout'];
        $result = $this->Send(
            $host,
            $auth,
            $user,
            $pass,
            $encryption,
            $autotls,
            $port,
            $subject,
            $body,
            $to,
            $cc,
            $bcc,
            $reply,
            $from,
            $timeout
        );

        return message(200, $result === true ? 1 : 0, $result === true ? 'gửi thành công' : $result);
    }

    /**
     *Nhận cấu hình thông tin đẩy tin nhắn
     */
    public function GetPushInfo()
    {
        $message_push = $this->database->get('options', [
            'option_value'
        ], [
            'option_name' => 'message_push'
        ]);

        $message_push_null = [
            [
                'trigger' => 'Common/Login',
                'type' => 'mail',
                'note' => 'Đăng nhập người dùng',
                'enable' => false,
            ],
            [
                'trigger' => 'Personal/EditAdminUserName',
                'type' => 'mail',
                'note' => 'Quản trị viên sửa đổi tên tài thư mụcản',
                'enable' => false,
            ],
            [
                'trigger' => 'Personal/EditAdminUserPass',
                'type' => 'mail',
                'note' => 'Quản trị viên đổi mật khẩu',
                'enable' => false,
            ],
            [
                'trigger' => 'Personal/EditSvnUserPass',
                'type' => 'mail',
                'note' => 'Người dùng SVN thay đổi mật khẩu',
                'enable' => false,
            ],
        ];

        if ($message_push == null) {
            $this->database->insert('options', [
                'option_name' => 'message_push',
                'option_value' => serialize($message_push_null),
                'option_description' => ''
            ]);

            return message(200, 1, 'thành công', $message_push_null);
        }
        if ($message_push['option_value'] == '') {
            $this->database->update('options', [
                'option_value' => serialize($message_push_null),
            ], [
                'option_name' => 'message_push',
            ]);

            return message(200, 1, 'thành công', $message_push_null);
        }

        return message(200, 1, 'thành công', unserialize($message_push['option_value']));
    }

    /**
     *Sửa đổi tùy chọn đẩy
     */
    function UpdPushInfo()
    {
        $this->database->update('options', [
            'option_value' => serialize($this->payload['listPush'])
        ], [
            'option_name' => 'message_push'
        ]);

        return message();
    }
}
