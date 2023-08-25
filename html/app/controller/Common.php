<?php
/*
 *@Author: bibo318
 *
 *@LastEditors: bibo318
 *
 *@Description: github: /bibo318
 */

namespace app\controller;

use app\service\Common as ServiceCommon;

class Common extends Base
{
    /**
     *Service layer object
     *
     *@var object
     */
    private $ServiceCommon;

    function __construct($parm)
    {
        parent::__construct($parm);

        $this->ServiceCommon = new ServiceCommon($parm);
    }

    /**
     *Log in
     */
    public function Login()
    {
        $result = $this->ServiceCommon->Login();
        json2($result);
    }

    /**
     *log out
     *
     *The logout operation is to add the user's token that has not expired to the so-called blacklist
     *Each logout triggers an active scan of the blacklist and deletes expired tokens in the list
     *Purpose: To realize that the token that has not expired after the user logs out cannot continue to be used
     */
    public function Logout()
    {
        $result = $this->ServiceCommon->Logout();
        json2($result);
    }

    /**
     *get verification code
     */
    public function GetVerifyCode()
    {
        $result = $this->ServiceCommon->GetVerifyCode();
        json2($result);
    }
}
