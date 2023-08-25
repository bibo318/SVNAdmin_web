<?php
/*
 *@Author: bibo318
 *
 *@LastEditors: bibo318
 *
 *@Description: github: /bibo318
 */

namespace app\service;

use app\service\Svn as ServiceSvn;
use Config;

class Setting extends Base
{
    /**
     *Service layer object
     *
     *@var object
     */
    private $ServiceSvn;

    function __construct($parm = [])
    {
        parent::__construct($parm);
    }

    /**
     *Get host configuration
     *
     *@return array
     */
    public function GetDcokerHostInfo()
    {
        return message(200, 1, '成功', [
            'docker_host' => $this->dockerHost,
            'docker_svn_port' => $this->dockerSvnPort,
            'docker_http_port' => $this->dockerHttpPort,
        ]);
    }

    /**
     *Modify the host configuration
     *
     *@return void
     */
    public function UpdDockerHostInfo()
    {
        //check the form
        $checkResult = funCheckForm($this->payload, [
            'dockerHost' => ['type' => 'array', 'notNull' => true]
        ]);
        if ($checkResult['status'] == 0) {
            return message($checkResult['code'], $checkResult['status'], $checkResult['message'] . ': ' . $checkResult['data']['column']);
        }

        $checkResult = funCheckForm($this->payload['dockerHost'], [
            'docker_host' => ['type' => 'string', 'notNull' => true],
            'docker_svn_port' => ['type' => 'integer', 'notNull' => true],
            'docker_http_port' => ['type' => 'integer', 'notNull' => true]
        ]);
        if ($checkResult['status'] == 0) {
            return message($checkResult['code'], $checkResult['status'], $checkResult['message'] . ': ' . $checkResult['data']['column']);
        }

        if (!preg_match('/^(?!(http|https):\/\/).*$/m', $this->payload['dockerHost']['docker_host'], $result)) {
            return message(200, 0, 'The host address does not need to carry the protocol prefix');
        }

        $this->database->update('options', [
            'option_value' => serialize($this->payload['dockerHost'])
        ], [
            'option_name' => '24_docker_host',
        ]);

        return message();
    }

    /**
     *Modify svnserve listening port
     */
    public function UpdSvnservePort()
    {
        //check the form
        $checkResult = funCheckForm($this->payload, [
            'listen_port' => ['type' => 'integer', 'notNull' => true]
        ]);
        if ($checkResult['status'] == 0) {
            return message($checkResult['code'], $checkResult['status'], $checkResult['message'] . ': ' . $checkResult['data']['column']);
        }

        if ($this->payload['listen_port'] == $this->localSvnPort) {
            return message(200, 0, 'No replacement required, same ports');
        }

        //stop
        (new ServiceSvn())->UpdSvnserveStatusStop();

        //Rebuild the configuration file content
        $config = sprintf(
            "OPTIONS=\"-r '%s' --config-file '%s' --log-file '%s' --listen-port %s --listen-host %s\"",
            $this->configSvn['rep_base_path'],
            $this->configSvn['svn_conf_file'],
            $this->configSvn['svnserve_log_file'],
            $this->payload['listen_port'],
            $this->localSvnHost
        );

        //write configuration file
        funFilePutContents($this->configSvn['svnserve_env_file'], $config);

        parent::RereadSvnserve();

        sleep(1);

        //start up
        $resultStart = (new ServiceSvn())->UpdSvnserveStatusStart();
        if ($resultStart['status'] != 1) {
            return $resultStart;
        }

        return message();
    }

    /**
     *Modify the listening host of svnserve
     */
    public function UpdSvnserveHost()
    {
        //check the form
        $checkResult = funCheckForm($this->payload, [
            'listen_host' => ['type' => 'string', 'notNull' => true]
        ]);
        if ($checkResult['status'] == 0) {
            return message($checkResult['code'], $checkResult['status'], $checkResult['message'] . ': ' . $checkResult['data']['column']);
        }

        if (!preg_match('/^(?!(http|https):\/\/).*$/m', $this->payload['listen_host'], $result)) {
            return message(200, 0, 'The host address does not need to carry the protocol prefix');
        }

        if ($this->payload['listen_host'] == $this->localSvnHost) {
            return message(200, 0, 'No need to replace, same address');
        }

        //stop
        (new ServiceSvn())->UpdSvnserveStatusStop();

        //Rebuild the configuration file content
        $config = sprintf(
            "OPTIONS=\"-r '%s' --config-file '%s' --log-file '%s' --listen-port %s --listen-host %s\"",
            $this->configSvn['rep_base_path'],
            $this->configSvn['svn_conf_file'],
            $this->configSvn['svnserve_log_file'],
            $this->localSvnPort,
            $this->payload['listen_host']
        );

        //write configuration file
        funFilePutContents($this->configSvn['svnserve_env_file'], $config);

        parent::RereadSvnserve();

        sleep(1);

        //start up
        $resultStart = (new ServiceSvn())->UpdSvnserveStatusStart();
        if ($resultStart['status'] != 1) {
            return $resultStart;
        }

        return message();
    }

    /**
     *Get a list of configuration files
     */
    public function GetDirInfo()
    {
        return message(200, 1, 'success', [
            [
                'key' => 'Main directory',
                'value' => $this->configSvn['home_path']
            ],
            [
                'key' => 'Warehouse parent directory',
                'value' => $this->configSvn['rep_base_path']
            ],
            [
                'key' => 'Warehouse configuration file',
                'value' => $this->configSvn['svn_conf_file']
            ],
            [
                'key' => 'Warehouse permissions file',
                'value' => $this->configSvn['svn_authz_file']
            ],
            [
                'key' => 'user account file',
                'value' => $this->configSvn['svn_passwd_file']
            ],
            [
                'key' => 'backup directory',
                'value' => $this->configSvn['backup_base_path']
            ],
            [
                'key' => 'svnserve environment variable file',
                'value' => $this->configSvn['svnserve_env_file']
            ],
        ]);
    }

    /**
     *Detect new version
     */
    public function CheckUpdate()
    {
        $code = 200;
        $status = 0;
        $message = 'update server failure';

        $configVersion = Config::get('version');

        $configUpdate = Config::get('update');

        if (!function_exists('curl_init')) {
            return message(200, 0, 'Please install or enable the curl extension of php first');
        }

        foreach ($configUpdate['update_server'] as $key1 => $value1) {

            $result = funCurlRequest(sprintf($value1['url'], $configVersion['version']));

            if (empty($result)) {
                continue;
            }

            //json => array
            $result = json_decode($result, true);

            if (!isset($result['code'])) {
                continue;
            }

            if ($result['code'] != 200) {
                $code = $result['code'];
                $status = $result['status'];
                $message = $result['message'];
                continue;
            }

            return message($result['code'], $result['status'], $result['message'], $result['data']);
        }

        return message($code, $status, $message);
    }

    /**
     *Get security configuration options
     *
     *@return array
     */
    public function GetSafeInfo()
    {
        $safe_config = $this->database->get('options', [
            'option_value'
        ], [
            'option_name' => 'safe_config'
        ]);

        $safe_config_null = [
            [
                'name' => 'login_verify_code',
                'note' => 'login verification code',
                'enable' => true,
            ]
        ];

        if ($safe_config == null) {
            $this->database->insert('options', [
                'option_name' => 'safe_config',
                'option_value' => serialize($safe_config_null),
                'option_description' => ''
            ]);

            return message(200, 1, 'success', $safe_config_null);
        }

        if ($safe_config['option_value'] == '') {
            $this->database->update('options', [
                'option_value' => serialize($safe_config_null),
            ], [
                'option_name' => 'safe_config',
            ]);

            return message(200, 1, 'success', $safe_config_null);
        }

        return message(200, 1, 'success', unserialize($safe_config['option_value']));
    }

    /**
     *Set security configuration options
     *
     *@return array
     */
    public function UpdSafeInfo()
    {
        $this->database->update('options', [
            'option_value' => serialize($this->payload['listSafe'])
        ], [
            'option_name' => 'safe_config'
        ]);

        return message();
    }

    /**
     *Get login verification code option
     *
     *@return array
     */
    public function GetVerifyOption()
    {
        $result = $this->GetSafeInfo();

        if ($result['status'] != 1) {
            return message(200, 0, 'Error getting configuration information');
        }

        $safeConfig = $result['data'];
        $index = array_search('login_verify_code', array_column($safeConfig, 'name'));
        if ($index === false) {
            return message(200, 0, 'Error getting configuration information');
        }

        return message(200, 1, 'success', $safeConfig[$index]);
    }
}
