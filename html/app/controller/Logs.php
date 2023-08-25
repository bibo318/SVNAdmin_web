<?php
/*
 *@Author: bibo318
 *
 *@LastEditors: bibo318
 *
 *@Description: github: /bibo318
 */

namespace app\controller;

use app\service\Logs as ServiceLogs;

class Logs extends Base
{
    /**
     *Service layer object
     *
     *@var object
     */
    private $ServiceLogs;

    function __construct($parm)
    {
        parent::__construct($parm);

        $this->ServiceLogs = new ServiceLogs($parm);
    }

    /**
     *Get log list
     */
    public function GetLogList()
    {
        $result = $this->ServiceLogs->GetLogList();
        json2($result);
    }

    /**
     *clear log
     */
    public function DelLogs()
    {
        $result = $this->ServiceLogs->DelLogs();
        json2($result);
    }
}
