<?php
/*
 *@Author: bibo318
 *
 *@LastEditors: bibo318
 *
 *@Description: github: /bibo318
 */

namespace app\controller;

use app\service\Statistics as ServiceStatistics;

class Statistics extends Base
{
    /**
     *Service layer object
     *
     *@var object
     */
    private $ServiceStatistics;

    function __construct($parm)
    {
        parent::__construct($parm);

        $this->ServiceStatistics = new ServiceStatistics($parm);
    }

    /**
     *get status
     *
     *load status
     *CPU usage
     *memory usage
     */
    public function GetLoadInfo()
    {
        $result = $this->ServiceStatistics->GetLoadInfo();
        json2($result);
    }

    /**
     *get hard drive
     *
     *Get the number of hard disks and detailed information of each hard disk
     */
    public function GetDiskInfo()
    {
        $result = $this->ServiceStatistics->GetDiskInfo();
        json2($result);
    }

    /**
     *get statistics
     *
     *OS type
     *Warehouse occupied volume
     *Number of SVN warehouses
     *Number of SVN users
     *Number of SVN groups
     *Number of scheduled tasks
     *Number of running logs
     */
    public function GetStatisticsInfo()
    {
        $result = $this->ServiceStatistics->GetStatisticsInfo();
        json2($result);
    }
}
