<?php
/*
 *@Author: bibo318
 *
 *@LastEditors: bibo318
 *
 *@Description: github: /bibo318
 */

namespace app\service;

class Crond extends Base
{
    function __construct($parm = [])
    {
        parent::__construct($parm);
    }

    /**
     *Get the drop-down list of special structure
     *
     *@return void
     */
    public function GetRepList()
    {
        $list = $this->database->select('svn_reps', [
            'rep_name(rep_key)',
            'rep_name',
        ]);

        $list = array_merge([[
            'rep_key' => '-1',
            'rep_name' => 'tất cả các thư mục'
        ]], $list);

        return message(200, 1, 'thành công', $list);
    }

    /**
     *Get task plan list
     *
     *@return array
     */
    public function GetCrontabList()
    {
        $pageSize = $this->payload['pageSize'];
        $currentPage = $this->payload['currentPage'];
        $searchKeyword = trim($this->payload['searchKeyword']);

        //pagination
        $begin = $pageSize * ($currentPage - 1);

        $list = $this->database->select('crond', [
            'crond_id',
            'sign',
            'task_type [Int]',
            'task_name',
            'cycle_type',
            'cycle_desc',
            'status',
            'save_count [Int]',
            'rep_name',
            'week [Int]',
            'day [Int]',
            'hour [Int]',
            'minute [Int]',
            'notice',
            'shell',
            'last_exec_time',
            'create_time',
        ], [
            'AND' => [
                'OR' => [
                    'task_name[~]' => $searchKeyword,
                    'cycle_desc[~]' => $searchKeyword,
                ],
            ],
            'LIMIT' => [$begin, $pageSize],
            'ORDER' => [
                $this->payload['sortName']  => strtoupper($this->payload['sortType'])
            ]
        ]);

        $total = $this->database->count('crond', [
            'crond_id'
        ], [
            'AND' => [
                'OR' => [
                    'task_name[~]' => $searchKeyword,
                    'cycle_desc[~]' => $searchKeyword,
                ],
            ],
        ]);

        foreach ($list as $key => $value) {
            //5 6 7 8 9 type does not need count field
            if (in_array($value['task_type'], [5, 6, 7, 8, 9])) {
                $list[$key]['save_count'] = '-';
            }

            //number to boolean
            $list[$key]['status'] = $value['status'] == 1 ? true : false;

            //numbers to array
            if ($value['notice'] == 0) {
                $list[$key]['notice'] = [];
            } elseif ($value['notice'] == 1) {
                $list[$key]['notice'] = ['success'];
            } elseif ($value['notice'] == 2) {
                $list[$key]['notice'] = ['fail'];
            } elseif ($value['notice'] == 3) {
                $list[$key]['notice'] = ['success', 'fail'];
            } else {
                $list[$key]['notice'] = [];
            }

            //Docs-BLT
            $list[$key]['rep_key'] = json_decode($value['rep_name'])[0];
            unset($list[$key]['rep_name']);
        }

        return message(200, 1, '成功', [
            'data' => $list,
            'total' => $total
        ]);
    }

    /**
     *Set task schedule
     *
     *@return array
     */
    public function CreateCrontab()
    {
        if (!isset($this->payload['cycle'])) {
            return message(200, 0, '参数[cycle]不存在');
        }
        $cycle = $this->payload['cycle'];

        //sign processing
        $sign = md5(time());

        //notice processing
        if (in_array('success', (array)$cycle['notice']) && in_array('fail', (array)$cycle['notice'])) {
            $cycle['notice'] = 3;
        } elseif (in_array('fail', (array)$cycle['notice'])) {
            $cycle['notice'] = 2;
        } elseif (in_array('success', (array)$cycle['notice'])) {
            $cycle['notice'] = 1;
        } else {
            $cycle['notice'] = 0;
        }

        //cycle_desc and code processing
        $code = '';
        $cycle['cycle_desc'] = '';
        switch ($cycle['cycle_type']) {
            case 'minute': //every minute
                $code = '* * * * *';
                $cycle['cycle_desc'] = "Execute every minute";
                break;
            case 'minute_n': //every N minutes
                $code = sprintf("*/%s * * * *", $cycle['minute']);
                $cycle['cycle_desc'] = sprintf("Execute every %s minutes", $cycle['minute']);
                break;
            case 'hour': //per hour
                $code = sprintf("%s * * * *", $cycle['minute']);
                $cycle['cycle_desc'] = sprintf("Execute every hour -%s minutes", $cycle['minute']);
                break;
            case 'hour_n': //every N hours
                $code = sprintf("%s */%s * * *", $cycle['minute'], $cycle['hour']);
                $cycle['cycle_desc'] = sprintf("Execute every %s hours -%s minutes", $cycle['hour'], $cycle['minute']);
                break;
            case 'day': //every day
                $code = sprintf("%s %s * * *", $cycle['minute'], $cycle['hour']);
                $cycle['cycle_desc'] = sprintf("Execute once a day at -%s point %s minutes", $cycle['hour'], $cycle['minute']);
                break;
            case 'day_n': //every N days
                $code = sprintf("%s %s */%s * *", $cycle['minute'], $cycle['hour'], $cycle['day']);
                $cycle['cycle_desc'] = sprintf("Execute every %s days -%s points %s minutes", $cycle['day'], $cycle['hour'], $cycle['minute']);
                break;
            case 'week': //weekly
                $code = sprintf("%s %s * * %s", $cycle['minute'], $cycle['hour'], $cycle['week']);
                $cycle['cycle_desc'] = sprintf("Execute once a week at %s-%s point %s minutes", $cycle['week'], $cycle['hour'], $cycle['minute']);
                break;
            case 'month': //per month
                $code = sprintf("%s %s %s * *", $cycle['minute'], $cycle['hour'], $cycle['day']);
                $cycle['cycle_desc'] = sprintf("Executed every month at %s day -%s point %s minutes", $cycle['day'], $cycle['hour'], $cycle['minute']);
                break;
            default:
                break;
        }

        //write to /home/svnadmin/crond/xxx
        $nameCrond = $this->configSvn['crond_base_path'] . $sign;
        $nameCrondLog = $nameCrond . '.log';

        $conCrond = sprintf(
            "#!/bin/bash\nPATH=/bin:/sbin:/usr/bin:/usr/sbin:/usr/local/bin:/usr/local/sbin:~/bin\nexport PATH\nstartDate=`date +%s`\necho ----------starTime:[\$startDate]--------------------------------------------\nphp %s %s %s\nendDate=`date +%s`\necho ----------endTime:[\$endDate]--------------------------------------------",
            "\"%Y-%m-%d %H:%M:%S\"",
            BASE_PATH . '/server/command.php',
            $cycle['task_type'],
            $sign,
            "\"%Y-%m-%d %H:%M:%S\""
        );

        file_put_contents($nameCrond, $conCrond);
        funShellExec(sprintf("chmod 755 '%s'", $nameCrond), true);

        //crontab -l Get the original task plan list
        $result = funShellExec('crontab -l', true);
        $crontabs = trim($result['result']);

        //crontab file writes a new task plan list
        $tempFile = tempnam($this->configSvn['crond_base_path'], 'svnadmin_crond_');
        file_put_contents($tempFile, (empty($crontabs) ? '' : $crontabs . "\n") . sprintf("%s %s >> %s 2>&1\n", $code, $nameCrond, $nameCrondLog));
        $result = funShellExec(sprintf("crontab %s", $tempFile), true);
        @unlink($tempFile);
        if ($result['code'] != 0) {
            @unlink($nameCrond);
            return message(200, 0, $result['error']);
        }

        $this->database->insert('crond', [
            'sign' => $sign,
            'task_type' => $cycle['task_type'],
            'task_name' => $cycle['task_name'], //There is a chance to be empty, not empty
            'cycle_type' => $cycle['cycle_type'],
            'cycle_desc' => $cycle['cycle_desc'], //You need to generate a semantic description according to the cycle
            'status' => 1, //Enabled state is enabled by default
            'save_count' => $cycle['save_count'],
            'rep_name' => json_encode([$cycle['rep_key']]),
            'week' => $cycle['week'],
            'day' => $cycle['day'],
            'hour' => $cycle['hour'],
            'minute' => $cycle['minute'],
            'notice' => $cycle['notice'],
            'code' => $code,
            'shell' => $cycle['shell'],
            'last_exec_time' => '-',
            'create_time' => date('Y-m-d H:i:s'),
        ]);

        return message(200, 1, 'success');
    }

    /**
     *Update mission plan
     *
     *@return array
     */
    public function UpdCrontab()
    {
        if (!isset($this->payload['cycle'])) {
            return message(200, 0, 'Parameter [cycle] does not exist');
        }
        $cycle = $this->payload['cycle'];

        //sign processing
        $sign = $cycle['sign'];

        //notice processing
        if (in_array('success', (array)$cycle['notice']) && in_array('fail', (array)$cycle['notice'])) {
            $cycle['notice'] = 3;
        } elseif (in_array('fail', (array)$cycle['notice'])) {
            $cycle['notice'] = 2;
        } elseif (in_array('success', (array)$cycle['notice'])) {
            $cycle['notice'] = 1;
        } else {
            $cycle['notice'] = 0;
        }

        //cycle_desc and code processing
        $code = '';
        $cycle['cycle_desc'] = '';
        switch ($cycle['cycle_type']) {
            case 'minute': //every minute
                $code = '* * * * *';
                $cycle['cycle_desc'] = "Execute every minute";
                break;
            case 'minute_n': //every N minutes
                $code = sprintf("*/%s * * * *", $cycle['minute']);
                $cycle['cycle_desc'] = sprintf("Execute every %s minutes", $cycle['minute']);
                break;
            case 'hour': //per hour
                $code = sprintf("%s * * * *", $cycle['minute']);
                $cycle['cycle_desc'] = sprintf("Execute every hour -%s minutes", $cycle['minute']);
                break;
            case 'hour_n': //every N hours
                $code = sprintf("%s */%s * * *", $cycle['minute'], $cycle['hour']);
                $cycle['cycle_desc'] = sprintf("Execute every %s hours -%s minutes", $cycle['hour'], $cycle['minute']);
                break;
            case 'day': //every day
                $code = sprintf("%s %s * * *", $cycle['minute'], $cycle['hour']);
                $cycle['cycle_desc'] = sprintf("Execute once a day at -%s point %s minutes", $cycle['hour'], $cycle['minute']);
                break;
            case 'day_n': //every N days
                $code = sprintf("%s %s */%s * *", $cycle['minute'], $cycle['hour'], $cycle['day']);
                $cycle['cycle_desc'] = sprintf("Execute every %s days -%s points %s minutes", $cycle['day'], $cycle['hour'], $cycle['minute']);
                break;
            case 'week': //weekly
                $code = sprintf("%s %s * * %s", $cycle['minute'], $cycle['hour'], $cycle['week']);
                $cycle['cycle_desc'] = sprintf("Execute once a week at %s-%s point %s minutes", $cycle['week'], $cycle['hour'], $cycle['minute']);
                break;
            case 'month': //per month
                $code = sprintf("%s %s %s * *", $cycle['minute'], $cycle['hour'], $cycle['day']);
                $cycle['cycle_desc'] = sprintf("Executed every month at %s day -%s point %s minutes", $cycle['day'], $cycle['hour'], $cycle['minute']);
                break;
            default:
                break;
        }

        //write to /home/svnadmin/crond/xxx
        $nameCrond = $this->configSvn['crond_base_path'] . $sign;
        $nameCrondLog = $nameCrond . '.log';

        $conCrond = sprintf(
            "#!/bin/bash\nPATH=/bin:/sbin:/usr/bin:/usr/sbin:/usr/local/bin:/usr/local/sbin:~/bin\nexport PATH\nstartDate=`date +%s`\necho ----------starTime:[\$startDate]--------------------------------------------\nphp %s %s %s\nendDate=`date +%s`\necho ----------endTime:[\$endDate]--------------------------------------------",
            "\"%Y-%m-%d %H:%M:%S\"",
            BASE_PATH . '/server/command.php',
            $cycle['task_type'],
            $sign,
            "\"%Y-%m-%d %H:%M:%S\""
        );

        file_put_contents($nameCrond, $conCrond);
        funShellExec(sprintf("chmod 755 '%s'", $nameCrond), true);

        //crontab -l Get the original task plan list
        $result = funShellExec('crontab -l', true);
        $crontabs = trim($result['result']);

        //Query the ID and delete the row where the ID is located
        $contabArray = explode("\n", $crontabs);
        foreach ($contabArray as $key => $value) {
            if (strstr($value, $sign . '.log')) {
                unset($contabArray[$key]);
                break;
            }
        }

        if ($contabArray == explode("\n", $crontabs)) {
            //no change, deleted is the suspended record
        } else {
            $crontabs = trim(implode("\n", $contabArray));
            //crontab file writes a new task plan list
            $tempFile = tempnam($this->configSvn['crond_base_path'], 'svnadmin_crond_');
            file_put_contents($tempFile, (empty($crontabs) ? '' : $crontabs . "\n") . sprintf("%s %s >> %s 2>&1\n", $code, $nameCrond, $nameCrondLog));
            $result = funShellExec(sprintf("crontab %s", $tempFile), true);
            @unlink($tempFile);
            if ($result['code'] != 0) {
                @unlink($nameCrond);
                return message(200, 0, $result['error']);
            }
        }

        $this->database->update('crond', [
            'task_type' => $cycle['task_type'],
            'task_name' => $cycle['task_name'], //There is a chance to be empty, not empty
            'cycle_type' => $cycle['cycle_type'],
            'cycle_desc' => $cycle['cycle_desc'], //You need to generate a semantic description according to the cycle
            'save_count' => $cycle['save_count'],
            'rep_name' => json_encode([$cycle['rep_key']]),
            'week' => $cycle['week'],
            'day' => $cycle['day'],
            'hour' => $cycle['hour'],
            'minute' => $cycle['minute'],
            'notice' => $cycle['notice'],
            'code' => $code,
            'shell' => $cycle['shell'],
        ], [
            'sign' => $sign
        ]);

        return message(200, 1, 'success');
    }

    /**
     *Modify task planning status
     *
     *@return void
     */
    public function UpdCrontabStatus()
    {
        if (!isset($this->payload['crond_id'])) {
            return message(200, 0, 'incomplete parameters');
        }

        $result = $this->database->get('crond', '*', [
            'crond_id' => $this->payload['crond_id']
        ]);
        if (empty($result)) {
            return message(200, 0, 'task plan does not exist');
        }

        $sign = $result['sign'];
        $code = $result['code'];

        //crontab -l Get the original task plan list
        $result = funShellExec('crontab -l', true);
        $crontabs = trim($result['result']);

        if ($this->payload['status']) {
            $nameCrond = $this->configSvn['crond_base_path'] . $sign;
            $nameCrondLog = $nameCrond . '.log';
            $crontabs = (empty($crontabs) ? '' : $crontabs . "\n") . sprintf("%s %s >> %s 2>&1", $code, $nameCrond, $nameCrondLog);
        } else {
            //Query the ID and delete the row where the ID is located
            $contabArray = explode("\n", $crontabs);
            foreach ($contabArray as $key => $value) {
                if (strstr($value, ' ' . $sign . '.log') || strstr($value, $sign . '.log')) {
                    unset($contabArray[$key]);
                }
            }
            $crontabs = trim(implode("\n", $contabArray));
        }

        if (empty($crontabs)) {
            funShellExec('crontab -r', true);
        } else {
            $tempFile = tempnam($this->configSvn['crond_base_path'], 'svnadmin_crond_');
            file_put_contents($tempFile, $crontabs . "\n");
            $result = funShellExec(sprintf("crontab %s", $tempFile), true);
            @unlink($tempFile);
            if ($result['code'] != 0) {
                return message(200, 0, $result['error']);
            }
        }

        //modify from database
        $this->database->update('crond', [
            'status' => $this->payload['status'] ? 1 : 0
        ], [
            'crond_id' => $this->payload['crond_id']
        ]);

        return message();
    }

    /**
     *Delete task plan
     *
     *@return array
     */
    public function DelCrontab()
    {
        if (!isset($this->payload['crond_id'])) {
            return message(200, 0, 'incomplete parameters');
        }

        $result = $this->database->get('crond', '*', [
            'crond_id' => $this->payload['crond_id']
        ]);
        if (empty($result)) {
            return message(200, 0, 'task plan does not exist');
        }
        $sign = $result['sign'];

        //crontab -l Get the original task plan list
        $result = funShellExec('crontab -l', true);
        $crontabs = trim($result['result']);

        //Query the ID and delete the row where the ID is located
        $contabArray = explode("\n", $crontabs);
        foreach ($contabArray as $key => $value) {
            if (strstr($value, $sign . '.log')) {
                unset($contabArray[$key]);
                break;
            }
        }
        if ($contabArray == explode("\n", $crontabs)) {
            //no change, deleted is the suspended record
        } else {
            $crontabs = trim(implode("\n", $contabArray));
            //crontab file writes a new task plan list
            if (empty($crontabs)) {
                funShellExec('crontab -r', true);
            } else {
                $tempFile = tempnam($this->configSvn['crond_base_path'], 'svnadmin_crond_');
                file_put_contents($tempFile, $crontabs . "\n");
                $result = funShellExec(sprintf("crontab %s", $tempFile), true);
                @unlink($tempFile);
                if ($result['code'] != 0) {
                    return message(200, 0, $result['error']);
                }
            }
        }

        //delete from file
        @unlink($this->configSvn['crond_base_path'] . $sign);

        //delete log
        @unlink($this->configSvn['crond_base_path'] . $sign . '.log');

        //delete from database
        $this->database->delete('crond', [
            'crond_id' => $this->payload['crond_id']
        ]);

        return message();
    }

    /**
     *Now execute task plan
     *
     *@return void
     */
    public function TriggerCrontab()
    {
        if (!isset($this->payload['crond_id'])) {
            return message(200, 0, 'incomplete parameters');
        }

        $result = $this->database->get('crond', '*', [
            'crond_id' => $this->payload['crond_id']
        ]);
        if (empty($result)) {
            return message(200, 0, 'task plan does not exist');
        }
        $sign = $result['sign'];

        $nameCrond = $this->configSvn['crond_base_path'] . $sign;
        $nameCrondLog = $nameCrond . '.log';

        $tempFile = tempnam($this->configSvn['crond_base_path'], 'svnadmin_crond_');

        file_put_contents($tempFile, sprintf("%s >> %s 2>&1\n", $nameCrond, $nameCrondLog));
        shell_exec(sprintf("chmod 755 '%s'", $tempFile));

        $result = funShellExec(sprintf("at -f '%s' now", $tempFile), true);

        @unlink($tempFile);

        if ($result['code'] != 0) {
            return message(200, 0, $result['error']);
        }

        return message();
    }

    /**
     *Get log information
     *
     *@return void
     */
    public function GetCrontabLog()
    {
        if (!isset($this->payload['crond_id'])) {
            return message(200, 0, 'incomplete parameters');
        }

        $result = $this->database->get('crond', '*', [
            'crond_id' => $this->payload['crond_id']
        ]);
        if (empty($result)) {
            return message(200, 0, 'task plan does not exist');
        }
        $sign = $result['sign'];

        clearstatcache();
        if (file_exists($this->configSvn['crond_base_path'] . $sign . '.log')) {
            return message(200, 1, 'success', [
                'log_path' => $this->configSvn['crond_base_path'] . $sign . '.log',
                'log_con' => file_get_contents($this->configSvn['crond_base_path'] . $sign . '.log')
            ]);
        } else {
            return message(200, 1, 'success', [
                'log_path' => 'not generated',
                'log_con' => ''
            ]);
        }
    }

    /**
     *Check if crontab at is installed and started
     *
     *It is not accurate to use this method to detect whether the daemon process is alive. It would be better to use pid to judge
     *
     *@return void
     */
    public function GetCronStatus()
    {
        $resultCrond = funShellExec('ps aux | grep -v grep | grep crond');
        if (empty($resultCrond['result'])) {
            $resultCron = funShellExec('ps aux | grep -v grep | grep cron');
            if (empty($resultCron)) {
                return message(200, 0, 'cron or crond service not started');
            }
        }

        $resultCron = funShellExec('ps aux | grep -v grep | grep atd');
        if (empty($resultCron)) {
            return message(200, 0, 'atd service not started');
        }

        return message();
    }
}
