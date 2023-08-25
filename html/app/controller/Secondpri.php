<?php
/*
 *@Author: bibo318
 *
 *@LastEditors: bibo318
 *
 *@Description: github: /bibo318
 */

namespace app\controller;

use app\service\Secondpri as ServiceSecondpri;

class Secondpri extends Base
{
    /**
     *Service layer object
     *
     *@var object
     */
    private $ServiceSecondpri;

    function __construct($parm)
    {
        parent::__construct($parm);

        $this->ServiceSecondpri = new ServiceSecondpri($parm);
    }

    /**
     *Set the secondary authorization status
     *
     *@return void
     */
    public function UpdSecondpri()
    {
        $result = $this->ServiceSecondpri->UpdSecondpri();
        json2($result);
    }

    /**
     *Obtain secondary authorization manageable objects
     *
     *@return void
     */
    public function GetSecondpriObjectList()
    {
        $result = $this->ServiceSecondpri->GetSecondpriObjectList();
        json2($result);
    }

    /**
     *Add secondary authorization manageable objects
     *
     *@return void
     */
    public function CreateSecondpriObject()
    {
        $result = $this->ServiceSecondpri->CreateSecondpriObject();
        json2($result);
    }

    /**
     *Delete secondary authorization manageable objects
     *
     *@return void
     */
    public function DelSecondpriObject()
    {
        $result = $this->ServiceSecondpri->DelSecondpriObject();
        json2($result);
    }
}
