<?php
/*
 *@Author: bibo318
 *
 *@LastEditors: bibo318
 *
 *@Description: github: /bibo318
 */

namespace app\service;

use app\service\Apache as ServiceApache;

class Personal extends Base
{
    /**
     *Other service layer objects
     *
     *@var object
     */
    private $Mail;
    private $ServiceApache;

    function __construct($parm = [])
    {
        parent::__construct($parm);

        $this->Mail = new Mail($parm);
        $this->ServiceApache = new ServiceApache($parm);
    }

    /**
     *Administrators modify their own accounts
     */
    public function EditAdminUserName()
    {
        if ($this->payload['userName'] != $this->payload['confirm']) {
            return message(200, 0, 'Inconsistent input');
        }

        if (trim($this->payload['userName']) == '') {
            return message(200, 0, 'Username is invalid');
        }

        $info = $this->database->get('admin_users', [
            'admin_user_name'
        ], [
            'admin_user_name' => $this->payload['userName']
        ]);
        if ($info != null) {
            return message(200, 0, 'conflict with existing user');
        }

        $this->database->update('admin_users', [
            'admin_user_name' => $this->payload['userName']
        ], [
            'admin_user_name' => $this->userName
        ]);

        //mail
        $this->Mail->SendMail('Personal/EditAdminUserName', 'Notification of account modification by administrators', 'Original account:' . $this->userName . ' ' . 'New account:' . $this->payload['userName'] . ' ' . 'time:' . date('Y-m-d H:i:s'));

        return message(200, 1, 'password has been updated');
    }

    /**
     *Administrators modify their own passwords
     */
    public function EditAdminUserPass()
    {
        if ($this->payload['password'] != $this->payload['confirm']) {
            return message(200, 0, 'Inconsistent input');
        }

        if (trim($this->payload['password']) == '') {
            return message(200, 0, 'Password is invalid');
        }

        $this->database->update('admin_users', [
            'admin_user_password' => $this->payload['password']
        ], [
            'admin_user_name' => $this->userName
        ]);

        //mail
        $this->Mail->SendMail('Personal/EditAdminUserPass', 'Admin change password notification', 'account:' . $this->userName . ' '  . 'time:' . date('Y-m-d H:i:s'));

        return message(200, 1, 'password has been updated');
    }

    /**
     *SVN users modify their own passwords
     */
    public function EditSvnUserPass()
    {
        if ($this->enableCheckout == 'svn') {
            $dataSource = $this->svnDataSource;
        } else {
            $dataSource = $this->httpDataSource;
        }

        if ($dataSource['user_source'] == 'ldap') {
            return message(200, 0, 'The current SVN user source is LDAP -this operation is not supported');
        }

        if ($this->payload['newPassword'] != $this->payload['confirm']) {
            return message(200, 0, 'Inconsistent input');
        }

        if (trim($this->payload['newPassword']) == '') {
            return message(200, 0, 'Password is invalid');
        }

        if ($this->enableCheckout == 'svn') {
            $result = $this->SVNAdmin->UpdUserPass($this->passwdContent, $this->userName, $this->payload['newPassword']);
            if (is_numeric($result)) {
                if ($result == 621) {
                    return message(200, 0, 'File format error ([users] identifier does not exist)');
                } elseif ($result == 710) {
                    return message(200, 0, 'The user does not exist, please try again after synchronizing the user by the administrator');
                } else {
                    return message(200, 0, "error code$result");
                }
            }

            funFilePutContents($this->configSvn['svn_passwd_file'], $result);
        } else {
            $result = $this->ServiceApache->UpdUserPass($this->userName, $this->payload['newPassword']);
            if ($result['status'] != 1) {
                return message2($result);
            }
        }

        $this->database->update('svn_users', [
            'svn_user_pass' => $this->payload['newPassword']
        ], [
            'svn_user_name' => $this->userName
        ]);

        //mail
        $this->Mail->SendMail('Personal/EditSvnUserPass', 'SVN user password change notification', 'account:' . $this->userName . ' ' . 'New Password:' . $this->payload['newPassword'] . ' ' . 'time:' . date('Y-m-d H:i:s'));

        return message(200, 1, 'password has been updated');
    }

    /**
     *Sub-administrators modify their own passwords
     */
    public function UpdSubadminUserPass()
    {
        if ($this->payload['password'] != $this->payload['confirm']) {
            return message(200, 0, 'Inconsistent input');
        }

        if (trim($this->payload['password']) == '') {
            return message(200, 0, 'Password is invalid');
        }

        $this->database->update('subadmin', [
            'subadmin_password' => md5($this->payload['password'])
        ], [
            'subadmin_name' => $this->userName
        ]);

        //mail
        $this->Mail->SendMail('Personal/UpdSubadminUserPass', 'Sub-administrator change password notification', 'account:' . $this->userName . ' '  . 'time:' . date('Y-m-d H:i:s'));

        return message(200, 1, 'password has been updated');
    }
}
