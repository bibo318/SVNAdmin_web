<?php
/*
 *@Author: bibo318
 *
 *@LastEditors: bibo318
 *
 *@Description: github: /bibo318
 */

namespace app\controller;

use app\service\Svnaliase as ServiceSvnaliase;

class Svnaliase extends Base
{
    /**
     *Service layer object
     *
     *@var object
     */
    private $ServiceSvnaliase;

    function __construct($parm)
    {
        parent::__construct($parm);

        $this->ServiceSvnaliase = new ServiceSvnaliase($parm);
    }

    /**
     *Get all SVN aliases
     */
    public function GetAliaseList()
    {
        $result = $this->ServiceSvnaliase->GetAliaseList();
        json2($result);
    }
}
