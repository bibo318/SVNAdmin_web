<?php
/*
 *@Author: bibo318
 *
 *@LastEditors: bibo318
 *
 *@Description: github: /bibo318
 */

namespace app\controller;

use app\service\Subadmin as ServiceSubadmin;

class Subadmin extends Base
{
    /**
     *Service layer object
     *
     *@var object
     */
    private $ServiceSubadmin;

    function __construct($parm)
    {
        parent::__construct($parm);

        $this->ServiceSubadmin = new ServiceSubadmin($parm);
    }

    /**
     *Get the list of sub-administrators
     *
     *@return void
     */
    public function GetSubadminList()
    {
        $result = $this->ServiceSubadmin->GetSubadminList();
        json2($result);
    }

    /**
     *Create sub-administrators
     *
     *@return void
     */
    public function CreateSubadmin()
    {
        $result = $this->ServiceSubadmin->CreateSubadmin();
        json2($result);
    }

    /**
     *Delete sub-admin
     *
     *@return void
     */
    public function DelSubadmin()
    {
        $result = $this->ServiceSubadmin->DelSubadmin();
        json2($result);
    }

    /**
     *Reset sub-administrator password
     *
     *@return void
     */
    public function UpdSubadminPass()
    {
        $result = $this->ServiceSubadmin->UpdSubadminPass();
        json2($result);
    }

    /**
     *Modify the enabled status of the sub-administrator
     *
     *@return void
     */
    public function UpdSubadminStatus()
    {
        $result = $this->ServiceSubadmin->UpdSubadminStatus();
        json2($result);
    }

    /**
     *Modify sub-administrator remarks
     *
     *@return void
     */
    public function UpdSubadminNote()
    {
        $result = $this->ServiceSubadmin->UpdSubadminNote();
        json2($result);
    }

    /**
     *Get the permission tree of a sub-administrator
     */
    public function GetSubadminTree()
    {
        $result = $this->ServiceSubadmin->GetSubadminTree();
        json2($result);
    }

    /**
     *Modify the permission tree of a sub-administrator
     */
    public function UpdSubadminTree()
    {
        $result = $this->ServiceSubadmin->UpdSubadminTree();
        json2($result);
    }
}
