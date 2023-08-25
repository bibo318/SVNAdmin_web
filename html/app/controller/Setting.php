<?php
/*
 *@Author: bibo318
 *
 *@LastEditors: bibo318
 *
 *@Description: github: /bibo318
 */

namespace app\controller;

use app\service\Setting as ServiceSetting;
use app\service\Mail as ServiceMail;
use app\service\Ldap as ServiceLdap;
use app\service\Svn as ServiceSvn;
use app\service\Apache as ServiceApache;

class Setting extends Base
{
    /**
     *Service layer object
     *
     *@var object
     */
    private $ServiceSetting;
    private $ServiceMail;
    private $ServiceLdap;
    private $ServiceSvn;
    private $ServiceApache;

    function __construct($parm)
    {
        parent::__construct($parm);

        $this->ServiceSetting = new ServiceSetting($parm);
        $this->ServiceMail = new ServiceMail($parm);
        $this->ServiceLdap = new ServiceLdap($parm);
        $this->ServiceSvn = new ServiceSvn($parm);
        $this->ServiceApache = new ServiceApache($parm);
    }

    /**
     *Get host configuration
     *
     *@return array
     */
    public function GetDcokerHostInfo()
    {
        $result = $this->ServiceSetting->GetDcokerHostInfo();
        json2($result);
    }

    /**
     *Modify the host configuration
     */
    public function UpdDockerHostInfo()
    {
        $result = $this->ServiceSetting->UpdDockerHostInfo();
        json2($result);
    }

    /**
     *Get svnserve details
     */
    public function GetSvnInfo()
    {
        $result = $this->ServiceSvn->GetSvnInfo();
        json2($result);
    }

    /**
     *Save svnserve related configuration
     */
    public function UpdSvnUsersource()
    {
        $result = $this->ServiceSvn->UpdSvnUsersource();
        json2($result);
    }

    /**
     *Start SVN
     */
    public function UpdSvnserveStatusStart()
    {
        $result = $this->ServiceSvn->UpdSvnserveStatusStart();
        json2($result);
    }

    /**
     *Stop SVN
     */
    public function UpdSvnserveStatusStop()
    {
        $result = $this->ServiceSvn->UpdSvnserveStatusStop();
        json2($result);
    }

    /**
     *Modify svnserve listening port
     */
    public function UpdSvnservePort()
    {
        $result = $this->ServiceSetting->UpdSvnservePort();
        json2($result);
    }

    /**
     *Modify the listening host of svnserve
     */
    public function UpdSvnserveHost()
    {
        $result = $this->ServiceSetting->UpdSvnserveHost();
        json2($result);
    }

    /**
     *Get a list of configuration files
     */
    public function GetDirInfo()
    {
        $result = $this->ServiceSetting->GetDirInfo();
        json2($result);
    }

    /**
     *Detect new version
     */
    public function CheckUpdate()
    {
        $result = $this->ServiceSetting->CheckUpdate();
        json2($result);
    }

    /**
     *Get mail configuration information
     */
    public function GetMailInfo()
    {
        $result = $this->ServiceMail->GetMailInfo();
        json2($result);
    }

    /**
     *Send test email
     */
    public function SendMailTest()
    {
        $result = $this->ServiceMail->SendMailTest();
        json2($result);
    }

    /**
     *Modify mail configuration information
     */
    public function UpdMailInfo()
    {
        $this->ServiceMail->UpdMailInfo();
        json2();
    }

    /**
     *Get message push information configuration
     */
    public function GetMailPushInfo()
    {
        $result = $this->ServiceMail->GetPushInfo();
        json2($result);
    }

    /**
     *Get security configuration options
     *
     *@return array
     */
    public function GetSafeInfo()
    {
        $result = $this->ServiceSetting->GetSafeInfo();
        json2($result);
    }

    /**
     *Set security configuration options
     *
     *@return array
     */
    public function UpdSafeInfo()
    {
        $result = $this->ServiceSetting->UpdSafeInfo();
        json2($result);
    }

    /**
     *Modify push options
     */
    function UpdPushInfo()
    {
        $result = $this->ServiceMail->UpdPushInfo();
        json2($result);
    }

    /**
     *Get login verification code option
     *
     *@return array
     */
    public function GetVerifyOption()
    {
        $result = $this->ServiceSetting->GetVerifyOption();
        json2($result);
    }

    /**
     *Test connection to ldap server
     *
     *@return array
     */
    public function LdapTest()
    {
        $result = $this->ServiceLdap->LdapTest();
        json2($result);
    }

    /**
     *Start the saslauthd service
     *
     *@return void
     */
    public function UpdSaslStatusStart()
    {
        $result = $this->ServiceSvn->UpdSaslStatusStart();
        json2($result);
    }

    /**
     *Close the saslauthd service
     *
     *@return void
     */
    public function UpdSaslStatusStop()
    {
        $result = $this->ServiceSvn->UpdSaslStatusStop();
        json2($result);
    }

    /**
     *Get apache server information
     *
     *@return void
     */
    public function GetApacheInfo()
    {
        $result = $this->ServiceApache->GetApacheInfo();
        json2($result);
    }

    /**
     *Enable http protocol checkout
     *
     *@return void
     */
    public function UpdSubversionEnable()
    {
        $result = $this->ServiceApache->UpdSubversionEnable();
        json2($result);
    }

    /**
     *Enable svn protocol checkout
     *
     *@return void
     */
    public function UpdSvnEnable()
    {
        $result = $this->ServiceSvn->UpdSvnEnable();
        json2($result);
    }

    /**
     *Modify the http protocol access prefix
     *
     *@return void
     */
    public function UpdHttpPrefix()
    {
        $result = $this->ServiceApache->UpdHttpPrefix();
        json2($result);
    }

    /**
     *Modify http protocol display port
     *
     *@return void
     */
    public function UpdHttpPort()
    {
        $result = $this->ServiceApache->UpdHttpPort();
        json2($result);
    }

    /**
     *Save apache related configuration
     */
    public function UpdHttpUsersource()
    {
        $result = $this->ServiceApache->UpdHttpUsersource();
        json2($result);
    }
}
