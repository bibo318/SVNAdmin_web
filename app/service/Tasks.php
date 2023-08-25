<?php
/*
 *@Author: bibo318
 *
 *@LastEditors: bibo318
 *
 *@Description: github: /bibo318
 */

namespace app\service;

class Tasks extends Base
{
    function __construct($parm = [])
    {
        parent::__construct($parm);
    }

    /**
     *Get background task real-time log
     *
     *@return void
     */
    public function GetTaskRun()
    {
        //number of queues
        $total = $this->database->count('tasks', [
            'task_id'
        ], [
            'OR' => [
                'task_status' => [
                    1,
                    2
                ]
            ],
        ]);

        //Get the list of currently executing tasks
        $result = $this->database->get('tasks', [
            'task_id [Int]',
            'task_name',
            //'task_status',
            //'task_cmd',
            //'task_unique',
            'task_log_file',
            //'task_optional',
            //'task_create_time',
            //'task_update_time'
        ], [
            'task_status' => 2
        ]);
        if (empty($result)) {
            return message(200, 1, 'success', [
                'task_name' => '',
                'task_running' => false,
                'task_log' => '',
                'task_queue_count' => $total
            ]);
        }

        //Get the log path of the task
        $file = $result['task_log_file'];
        if (!file_exists($file)) {
            return message(200, 0, sprintf('Log file [%s] does not exist or is unreadable', $file));
        }

        //read the log and return
        $filesize = filesize($file) / 1024 / 1024;
        return message(200, 1, 'success', [
            'task_name' => $result['task_name'],
            'task_running' => true,
            'task_log' => $filesize > 10 ? sprintf('The log file [%s] exceeds 10M and needs to be checked manually', $file) : file_get_contents($file),
            'task_queue_count' => $total
        ]);
    }

    /**
     *Get background task queue
     *
     *@return void
     */
    public function GetTaskQueue()
    {
        $list = $this->database->select('tasks', [
            'task_id [Int]',
            'task_name',
            'task_status [Int]',
            'task_cmd',
            'task_unique',
            'task_log_file',
            'task_optional',
            'task_create_time',
            'task_update_time'
        ], [
            'OR' => [
                'task_status' => [
                    1,
                    2
                ]
            ],
            'ORDER' => [
                'task_id'  => 'ASC'
            ]
        ]);

        return message(200, 1, 'success', [
            'data' => $list,
        ]);
    }

    /**
     *Get background task execution history
     *
     *@return void
     */
    public function GetTaskHistory()
    {
        //check the form
        $checkResult = funCheckForm($this->payload, [
            'pageSize' => ['type' => 'integer', 'notNull' => true],
            'currentPage' => ['type' => 'integer', 'notNull' => true],
        ]);
        if ($checkResult['status'] == 0) {
            return message($checkResult['code'], $checkResult['status'], $checkResult['message'] . ': ' . $checkResult['data']['column']);
        }

        $pageSize = $this->payload['pageSize'];
        $currentPage = $this->payload['currentPage'];

        //pagination
        $begin = $pageSize * ($currentPage - 1);

        $list = $this->database->select('tasks', [
            'task_id [Int]',
            'task_name',
            'task_status',
            'task_cmd',
            'task_unique',
            'task_log_file',
            'task_optional',
            'task_create_time',
            'task_update_time'
        ], [
            'OR' => [
                'task_status' => [
                    3,
                    4,
                    5
                ]
            ],
            'LIMIT' => [$begin, $pageSize],
            'ORDER' => [
                'task_id'  => 'DESC'
            ]
        ]);

        $total = $this->database->count('tasks', [
            'task_id'
        ], [
            'OR' => [
                'task_status' => [
                    3,
                    4,
                    5
                ]
            ],
        ]);

        return message(200, 1, 'success', [
            'data' => $list,
            'total' => $total
        ]);
    }

    /**
     *Get historical task logs
     *
     *@return void
     */
    public function GetTaskHistoryLog()
    {
        //check the form
        $checkResult = funCheckForm($this->payload, [
            'task_id' => ['type' => 'integer', 'notNull' => true],
        ]);
        if ($checkResult['status'] == 0) {
            return message($checkResult['code'], $checkResult['status'], $checkResult['message'] . ': ' . $checkResult['data']['column']);
        }

        $result = $this->database->get('tasks', [
            //'task_id [Int]',
            'task_name',
            'task_status',
            //'task_cmd',
            //'task_unique',
            'task_log_file',
            //'task_optional',
            //'task_create_time',
            //'task_update_time'
        ], [
            'task_id' => $this->payload['task_id']
        ]);
        if (empty($result)) {
            return message(200, 1, 'task does not exist');
        }

        //Get the log path of the task
        $file = $result['task_log_file'];
        if (!file_exists($file)) {
            return message(200, 0, sprintf('Log file [%s] does not exist or is unreadable', $file));
        }

        //read the log and return
        return message(200, 1, 'success', [
            'task_name' => $result['task_name'],
            'task_log' => file_get_contents($file),
        ]);
    }

    /**
     *Delete historical execution tasks
     *
     *@return void
     */
    public function DelTaskHistory()
    {
        //check the form
        $checkResult = funCheckForm($this->payload, [
            'task_id' => ['type' => 'integer', 'notNull' => true],
        ]);
        if ($checkResult['status'] == 0) {
            return message($checkResult['code'], $checkResult['status'], $checkResult['message'] . ': ' . $checkResult['data']['column']);
        }

        $result = $this->database->get('tasks', [
            'task_lof_file'
        ], [
            'task_id' => $this->payload['task_id']
        ]);

        if (!empty($result)) {
            @unlink($result['task_log_file']);
        }

        $this->database->delete('tasks', [
            'task_id' => $this->payload['task_id']
        ]);

        return message();
    }

    /**
     *Stop background tasks
     *
     *@return void
     */
    public function UpdTaskStop()
    {
        //check the form
        $checkResult = funCheckForm($this->payload, [
            'task_id' => ['type' => 'integer', 'notNull' => true]
        ]);
        if ($checkResult['status'] == 0) {
            return message($checkResult['code'], $checkResult['status'], $checkResult['message'] . ': ' . $checkResult['data']['column']);
        }

        $task_id = $this->payload['task_id'];

        $result = $this->database->get('tasks', [
            'task_id [Int]',
            'task_name',
            'task_status',
            'task_cmd',
            'task_unique',
            'task_log_file',
            'task_optional',
            'task_create_time',
            'task_update_time'
        ], [
            'task_id' => $task_id
        ]);
        if (empty($result)) {
            return message(200, 0, 'task does not exist');
        }

        if ($result['task_status'] == 2) {
            //If it is an executing background task, stop it by pid

            $result = funShellExec(sprintf("ps aux | grep -v grep | grep %s | awk 'NR==1' | awk '{print $2}'", $result['task_unique']));
            if ($result['code'] != 0) {
                return message(200, 0, 'get process failed: ' . $result['error']);
            }

            $pid = trim($result['result']);

            if (empty($pid)) {
                $this->database->update('tasks', [
                    'task_status' => 4
                ], [
                    'task_id' => $task_id
                ]);

                return message();
            }

            clearstatcache();
            if (!is_dir("/proc/$pid")) {
                $this->database->update('tasks', [
                    'task_status' => 4
                ], [
                    'task_id' => $task_id
                ]);

                return message();
            }

            $info = funShellExec(sprintf("kill -15 %s && kill -9 %s", trim($result['result']), trim($result['result'])), true);
            if ($info['code'] != 0) {
                return message(200, 0, $info['error']);
            }

            $this->database->update('tasks', [
                'task_status' => 4
            ], [
                'task_id' => $task_id
            ]);

            return message();
        } elseif ($result['task_status'] == 1) {
            //If it is a pending background task, mark it as deleted directly from the database
            $result = $this->database->update('tasks', [
                'task_status' => 4
            ], [
                'task_id' => $task_id
            ]);

            return message();
        } elseif ($result['task_status'] == 3) {
            return message();
        } elseif ($result['task_status'] == 4) {
            return message();
        } elseif ($result['task_status'] == 5) {
            return message();
        }

        return message(200, 0, 'current operation is not supported');
    }
}
