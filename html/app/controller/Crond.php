<?php
/*
 *@Author: bibo318
 *
 *@LastEditors: bibo318
 *
 *@Description: github: /bibo318
 */

namespace app\controller;

use app\service\Crond as ServiceCrond;

class Crond extends Base
{
    /**
     *Service layer object
     *
     *@var object
     */
    private $ServiceCrond;

    function __construct($parm)
    {
        parent::__construct($parm);

        $this->ServiceCrond = new ServiceCrond($parm);
    }

    /**
     *Get the drop-down list of special structure
     *
     *@return void
     */
    public function GetRepList()
    {
        $result = $this->ServiceCrond->GetRepList();
        json2($result);
    }

    /**
     *Get task plan list
     *
     *@return array
     */
    public function GetCrontabList()
    {
        $result = $this->ServiceCrond->GetCrontabList();
        json2($result);
    }

    /**
     *Set task schedule
     *
     *@return array
     */
    public function CreateCrontab()
    {
        $result = $this->ServiceCrond->CreateCrontab();
        json2($result);
    }

    /**
     *Update mission plan
     *
     *@return array
     */
    public function UpdCrontab()
    {
        $result = $this->ServiceCrond->UpdCrontab();
        json2($result);
    }

    /**
     *Modify task planning status
     *
     *@return array
     */
    public function UpdCrontabStatus()
    {
        $result = $this->ServiceCrond->UpdCrontabStatus();
        json2($result);
    }

    /**
     *Delete task plan
     *
     *@return array
     */
    public function DelCrontab()
    {
        $result = $this->ServiceCrond->DelCrontab();
        json2($result);
    }

    /**
     *Get log information
     *
     *@return array
     */
    public function GetCrontabLog()
    {
        $result = $this->ServiceCrond->GetCrontabLog();
        json2($result);
    }

    /**
     *Now execute task plan
     *
     *@return array
     */
    public function TriggerCrontab()
    {
        $result = $this->ServiceCrond->TriggerCrontab();
        json2($result);
    }

    /**
     *Check if crontab at is installed and started
     *
     *@return array
     */
    public function GetCronStatus()
    {
        $result = $this->ServiceCrond->GetCronStatus();
        json2($result);
    }
}
