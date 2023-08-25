<?php
/*
 *@Tác giả: bibo318
 *
 *@LastEditors: bibo318
 *
 *@Mô tả: github: /bibo318
 */

class Check
{
    private $configReg;

    function __construct($configReg)
    {
        $this->configReg = $configReg;
    }

    /**
     *Kiểm tra tên thư mục SVN
     */
    public function CheckRepName($repName, $message = 'The SVN warehouse name can only contain letters, numbers, dashes, underscores, and dots, and cannot start or end with dots')
    {
        if (preg_match($this->configReg['REG_SVN_REP_NAME'], $repName) != 1) {
            return ['code' => 200, 'status' => 0, 'message' => $message, 'data' => []];
        }
        return ['code' => 200, 'status' => 1, 'message' => '', 'data' => []];
    }

    /**
     *Kiểm tra tên người dùng SVN
     */
    public function CheckRepUser($repUserName)
    {
        if (preg_match($this->configReg['REG_SVN_USER_NAME'], $repUserName) != 1) {
            return ['code' => 200, 'status' => 0, 'message' => 'The SVN username can only contain letters, numbers, dashes, underscores, and dots', 'data' => []];
        }
        return ['code' => 200, 'status' => 1, 'message' => '', 'data' => []];
    }

    /**
     *Kiểm tra tên nhóm người dùng SVN
     */
    public function CheckRepGroup($repGroupName)
    {
        if (preg_match($this->configReg['REG_SVN_GROUP_NAME'], $repGroupName) != 1) {
            return ['code' => 200, 'status' => 0, 'message' => 'SVN group names can only contain letters, numbers, dashes, underscores, and dots', 'data' => []];
        }
        return ['code' => 200, 'status' => 1, 'message' => '', 'data' => []];
    }

    /**
     *Kiểm tra hộp thư
     */
    public function CheckMail($mail)
    {
        if (preg_match_all($this->configReg['REG_MAIL'], $mail) == 1) {
            return ['code' => 200, 'status' => 0, 'message' => 'email error', 'data' => []];
        }
        return ['code' => 200, 'status' => 1, 'message' => '', 'data' => []];
    }
}
