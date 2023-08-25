<?php
/*
 *@Tác giả: bibo318
 *
 *@LastEditors: bibo318
 *
 *@Mô tả: github: /bibo318
 */

namespace app\controller;

use app\service\Personal as ServicePersonal;

class Personal extends Base
{
    /**
     *Đối tượng lớp dịch vụ
     *
     *Đối tượng @var
     */
    private $ServicePersonal;

    function __construct($parm)
    {
        parent::__construct($parm);

        $this->ServicePersonal = new ServicePersonal($parm);
    }

    /**
     *Quản trị viên sửa đổi tài khoản của chính họ
     */
    public function EditAdminUserName()
    {
        $result = $this->ServicePersonal->EditAdminUserName();
        json2($result);
    }

    /**
     *Quản trị viên sửa đổi mật khẩu của riêng họ
     */
    public function EditAdminUserPass()
    {
        $result = $this->ServicePersonal->EditAdminUserPass();
        json2($result);
    }

    /**
     *Người dùng SVN sửa đổi mật khẩu của riêng họ
     */
    public function EditSvnUserPass()
    {
        $result = $this->ServicePersonal->EditSvnUserPass();
        json2($result);
    }

    /**
     *Quản trị viên phụ sửa đổi mật khẩu của riêng họ
     */
    public function UpdSubadminUserPass()
    {
        $result = $this->ServicePersonal->UpdSubadminUserPass();
        json2($result);
    }
}
