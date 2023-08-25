<?php
/*
 * @Author: bibo318
 * 
 * @LastEditors: bibo318
 * 
 * @Description: github: /bibo318
 */

namespace app\service;

use app\service\Svn as ServiceSvn;
use app\service\Logs as ServiceLogs;
use Witersen\Upload;

class Svnrep extends Base
{
    /**
     * 服务层对象
     *
     * @var object
     */
    private $ServiceSvn;
    private $ServiceLogs;

    function __construct($parm = [])
    {
        parent::__construct($parm);

        $this->ServiceSvn = new ServiceSvn($parm);
        $this->ServiceLogs = new ServiceLogs($parm);
    }

    /**
     * 检测 authz 是否有效
     *
     * @return array
     */
    public function CheckAuthz()
    {
        if (!array_key_exists('svnauthz-validate', $this->configBin)) {
            return message(200, 0, 'Đường dẫn đến svnauthz-validate cần được cấu hình trong file config/bin.php');
        }

        if (empty($this->configBin['svnauthz-validate'])) {
            return message(200, 0, 'Đường dẫn xác thực svnauthz không được định cấu hình trong tệp config/bin.php');
        }

        $result = funShellExec(sprintf("'%s' '%s'", '/usr/bin/svn-tools/svnauthz-validate', $this->configSvn['svn_authz_file'], $this->configBin['svnauthz-validate']));
        if ($result['code'] != 0) {
            return message(200, 2, 'Đã phát hiện sự bất thường', $result['error']);
        } else {
            return message(200, 1, 'Cấu hình file authz đúng');
        }
    }

    /**
     * 获取检出地址前缀
     */
    public function GetCheckout()
    {
        if ($this->enableCheckout == 'svn') {
            $checkoutHost = $this->dockerSvnPort == 3690 ? $this->dockerHost : $this->dockerHost . ':' . $this->dockerSvnPort;

            return message(200, 1, '成功', [
                'protocal' => 'svn://',
                'prefix' => $checkoutHost
            ]);
        } else {
            $checkoutHost = $this->dockerHost;

            if ($this->dockerHttpPort != 80 && $this->dockerHttpPort != 443) {
                $checkoutHost .= ':' . $this->dockerHttpPort;
            }

            if ($this->httpPrefix != '/') {
                $checkoutHost .= $this->httpPrefix;
            }

            $protocal = $this->dockerHttpPort == 443 ? 'https://' : 'http://';

            return message(200, 1, '成功', [
                'protocal' => $protocal,
                'prefix' => $checkoutHost
            ]);
        }
    }

    /**
     * 新建仓库
     */
    public function CreateRep()
    {
        //检查表单
        $checkResult = funCheckForm($this->payload, [
            'rep_name' => ['type' => 'string', 'notNull' => true],
            'rep_note' => ['type' => 'string', 'notNull' => false],
            'rep_type' => ['type' => 'string', 'notNull' => true],
        ]);
        if ($checkResult['status'] == 0) {
            return message($checkResult['code'], $checkResult['status'], $checkResult['message'] . ': ' . $checkResult['data']['column']);
        }

        $repBasePath = $this->configSvn['rep_base_path'];
        $repName = $this->payload['rep_name'];
        $repPath = $repBasePath . $repName;

        //检查仓库名是否合法
        $checkResult = $this->checkService->CheckRepName($repName);
        if ($checkResult['status'] != 1) {
            return message($checkResult['code'], $checkResult['status'], $checkResult['message'], $checkResult['data']);
        }

        //检查仓库是否存在
        clearstatcache();
        if (is_dir($repPath)) {
            return message(200, 0, 'Kho đã tồn tại');
        }

        //创建空仓库
        $cmd = sprintf("'%s' create " . $repPath, $this->configBin['svnadmin']);
        funShellExec($cmd);

        //关闭selinux
        funShellExec('setenforce 0');

        if ($this->payload['rep_type'] == '2') {
            //以指定的目录结构初始化仓库
            $this->InitRepStruct($this->configSvn['templete_init_01_path'], $repPath);
        }

        //检查是否创建成功
        if (!is_dir($repPath)) {
            return message(200, 0, 'Không tạo được thư mục lưu trữ');
        }

        //向authz写入仓库信息
        $result = $this->SVNAdmin->WriteRepPathToAuthz($this->authzContent, $repName, '/');
        if (is_numeric($result)) {
            if ($result == 851) {
                $result = $this->authzContent;
            } else {
                return message(200, 0, "Lỗi đồng bộ hóa với hồ sơ$result");
            }
        }
        funFilePutContents($this->configSvn['svn_authz_file'], $result);

        //写入数据库
        $this->database->delete('svn_reps', [
            'rep_name' =>  $repName,
        ]);
        $this->database->insert('svn_reps', [
            'rep_name' => $repName,
            'rep_size' => funGetDirSizeDu($repPath),
            'rep_note' => $this->payload['rep_note'],
            'rep_rev' => $this->GetRepRev($repName),
            'rep_uuid' => $this->GetRepUUID($repName)
        ]);

        //日志
        $this->ServiceLogs->InsertLog(
            'Tạo thư mục',
            sprintf("tên thư mục:%s", $repName),
            $this->userName
        );

        return message();
    }

    /**
     * SVN仓库 => 数据库
     */
    public function SyncRep2Db()
    {
        /**
         * 删除数据表重复插入的项
         */
        $dbRepList = $this->database->select('svn_reps', [
            'rep_id',
            'rep_name',
        ], [
            'GROUP' => [
                'rep_name'
            ]
        ]);
        $dbRepListAll = $this->database->select('svn_reps', [
            'rep_id',
            'rep_name',
        ]);

        $duplicates = array_diff(array_column($dbRepListAll, 'rep_id'), array_column($dbRepList, 'rep_id'));
        foreach ($duplicates as $value) {
            $this->database->delete('svn_reps', [
                'rep_id' => $value,
            ]);
        }

        /**
         * 数据对比增删改
         */
        $old = array_column($dbRepList, 'rep_name');
        $new = $this->GetSimpleRepList();

        //删除
        $delete = array_diff($old, $new);
        foreach ($delete as $value) {
            $this->database->delete('svn_reps', [
                'rep_name' => $value,
            ]);
        }

        //新增
        $create = array_diff($new, $old);
        foreach ($create as $value) {
            $this->database->insert('svn_reps', [
                'rep_name' => $value,
                'rep_size' => funGetDirSizeDu($this->configSvn['rep_base_path'] .  $value),
                'rep_note' => '',
                'rep_rev' => $this->GetRepRev($value),
                'rep_uuid' => $this->GetRepUUID($value)
            ]);
        }

        //更新
        $update = array_intersect($old, $new);
        foreach ($update as $value) {
            $this->database->update('svn_reps', [
                'rep_size' => funGetDirSizeDu($this->configSvn['rep_base_path'] .  $value),
                'rep_rev' => $this->GetRepRev($value),
                'rep_uuid' => $this->GetRepUUID($value)
            ], [
                'rep_name' => $value
            ]);
        }

        return message();
    }

    /**
     * SVN仓库 => authz文件
     */
    public function SyncRep2Authz()
    {
        $svnRepList = $this->GetSimpleRepList();

        $svnRepAuthzList = $this->SVNAdmin->GetRepListFromAuthz($this->authzContent);

        $authzContet = $this->authzContent;

        foreach ($svnRepList as $key => $value) {
            if (!in_array($value, $svnRepAuthzList)) {
                $result = $this->SVNAdmin->WriteRepPathToAuthz($authzContet, $value, '/');
                if (is_numeric($result)) {
                    if ($result == 851) {
                    } else {
                        json1(200, 0, "Lỗi đồng bộ hóa với hồ sơ$authzContet");
                    }
                } else {
                    $authzContet = $result;
                }
            }
        }

        foreach ($svnRepAuthzList as $key => $value) {
            if (!in_array($value, $svnRepList)) {
                $result = $this->SVNAdmin->DelRepFromAuthz($authzContet, $value);
                if (is_numeric($result)) {
                    if ($result == 751) {
                    } else {
                        json1(200, 0, "Lỗi đồng bộ hóa với hồ sơ$authzContet");
                    }
                } else {
                    $authzContet = $result;
                }
            }
        }

        if ($authzContet != $this->authzContent) {
            funFilePutContents($this->configSvn['svn_authz_file'], $authzContet);
        }
    }

    /**
     * 对用户有权限的仓库路径列表进行一一验证
     * 
     * 确保该仓库的路径存在于仓库的最新版本库中
     * 
     * 此方式可以清理掉因为目录/文件名进行修改/删除后造成的authz文件冗余 
     * 但是此方式只能清理对此用户进行的有权限的授权 而不能清理无权限的情况
     * 以后有时间会考虑对所有的路径进行扫描和清理[todo]
     */
    private function SyncRepPathCheck()
    {
        $authzContent = $this->authzContent;

        //获取用户有权限的仓库路径列表
        $userRepList = $this->SVNAdmin->GetUserAllPri($this->authzContent, $this->userName);
        if (is_numeric($userRepList)) {
            if ($userRepList == 612) {
                json1(200, 0, 'định dạng tập tin sai([groups] identifier does not exist)');
            } elseif ($userRepList == 700) {
                json1(200, 0, 'đối tượng không tồn tại');
            } elseif ($userRepList == 901) {
                json1(200, 0, 'Loại đối tượng ủy quyền không được hỗ trợ');
            } else {
                json1(200, 0, "mã lỗi$userRepList");
            }
        }

        foreach ($userRepList as $key => $value) {
            $cmd = sprintf("'%s' tree  '%s' --full-paths --non-recursive '%s'", $this->configBin['svnlook'], $this->configSvn['rep_base_path'] .  $value['repName'], $value['priPath']);
            $result = funShellExec($cmd);

            if (strstr($result['error'], 'svnlook: E160013:')) {
                //路径在thư mục không tồn tại
                //从配置文件删除指定仓库的指定路径
                $tempResult = $this->SVNAdmin->DelRepPathFromAuthz($authzContent, $value['repName'], $value['priPath']);
                if (!is_numeric($tempResult)) {
                    $authzContent = $tempResult;
                }
            }
        }

        //写入配置文件
        if ($authzContent != $this->authzContent) {
            funFilePutContents($this->configSvn['svn_authz_file'], $authzContent);
        }
    }

    /**
     * 用户有权限的仓库路径列表 => 数据库
     */
    private function SyncUserRepAndDb()
    {
        //获取用户有权限的仓库路径列表
        $userRepList = $this->SVNAdmin->GetUserAllPri($this->authzContent, $this->userName);
        if (is_numeric($userRepList)) {
            if ($userRepList == 612) {
                json1(200, 0, 'định dạng tập tin sai(does not exist[groups]Logo)');
            } elseif ($userRepList == 700) {
                json1(200, 0, 'đối tượng không tồn tại');
            } elseif ($userRepList == 901) {
                json1(200, 0, 'Loại đối tượng ủy quyền không được hỗ trợ');
            } else {
                json1(200, 0, "mã lỗi$userRepList");
            }
        }
        $new = [];
        $newRepList = [];
        foreach ($userRepList as $value) {
            $unique = $value['repName'] . $value['priPath'] . $value['repPri'];
            $new[] = $unique;
            $newRepList[$unique] = $value;
        }

        $userRepList = $this->database->select('svn_user_pri_paths', [
            'svnn_user_pri_path_id [Int]',
            'rep_name',
            'pri_path',
            'rep_pri',
        ], [
            'svn_user_name' => $this->userName
        ]);
        $old = [];
        $oldRepList = [];
        foreach ($userRepList as $value) {
            $unique = $value['rep_name'] . $value['pri_path'] . $value['rep_pri'];
            $old[] = $unique;
            $oldRepList[$unique] = $value;
        }
        unset($userRepList);

        $delete = array_diff($old, $new);
        foreach ($delete as $value) {
            $this->database->delete('svn_user_pri_paths', [
                'svnn_user_pri_path_id' => $oldRepList[$value]['svnn_user_pri_path_id'],
            ]);
        }

        $create = array_diff($new, $old);
        foreach ($create as $value) {
            $this->database->insert('svn_user_pri_paths', [
                'rep_name' => $newRepList[$value]['repName'],
                'pri_path' => $newRepList[$value]['priPath'],
                'rep_pri' => $newRepList[$value]['repPri'],
                'svn_user_name' => $this->userName,
                'unique' => '', //兼容2.3.3及之前版本 从2.3.3.1版本开始无实际意义
                'second_pri' => 0
            ]);
        }
    }

    /**
     * 获取仓库列表
     */
    public function GetRepList()
    {
        $sync = $this->payload['sync'];
        $page = $this->payload['page'];

        if ($sync) {
            /**
             * 物理仓库 => authz文件
             * 
             * 1、将物理仓库已经删除但是authz文件中依然存在的从authz文件删除
             * 2、将在物理仓库存在但是authz文件中不存在的向authz文件写入
             */
            $this->SyncRep2Authz();

            /**
             * 物理仓库 => svn_reps数据表
             * 
             * 1、将物理仓库存在而没有写入数据库的记录写入数据库
             * 2、将物理仓库已经删除但是数据库依然存在的从数据库删除
             */
            $syncResult = $this->SyncRep2Db();
            if ($syncResult['status'] != 1) {
                return message($syncResult['code'], $syncResult['status'], $syncResult['message'], $syncResult['data']);
            }
        }

        if ($page) {
            $pageSize = $this->payload['pageSize'];
            $currentPage = $this->payload['currentPage'];
        }
        $searchKeyword = trim($this->payload['searchKeyword']);

        //分页
        if ($page) {
            $begin = $pageSize * ($currentPage - 1);
        }

        /**
         * 特殊字符转义处理 todo
         */
        // $configDatabase = Config::get('database');
        // if ($configDatabase['database_type'] == 'mysql') {
        //     $searchKeyword = str_replace([
        //         '_',
        //     ], [
        //         '[_]',
        //     ], $searchKeyword);
        // } elseif ($configDatabase['database_type'] == 'sqlite') {
        //     $searchKeyword = str_replace([
        //         '/', "'", '[', ']', '%', '&', '_', '(', ')'
        //     ], [
        //         '//', "''", '/[', '/]', '/%', '/&', '/_', '/(', '/)'
        //     ], $searchKeyword);
        // }

        if ($page) {
            $list = $this->database->select('svn_reps', [
                'rep_id',
                'rep_name',
                'rep_size',
                'rep_note',
                'rep_rev',
                'rep_uuid'
            ], [
                'AND' => [
                    'OR' => [
                        'rep_name[~]' => $searchKeyword,
                        'rep_note[~]' => $searchKeyword,
                    ],
                ],
                'LIMIT' => [$begin, $pageSize],
                'ORDER' => [
                    $this->payload['sortName']  => strtoupper($this->payload['sortType'])
                ]
            ]);
        } else {
            $list = $this->database->select('svn_reps', [
                'rep_id',
                'rep_name',
                'rep_size',
                'rep_note',
                'rep_rev',
                'rep_uuid'
            ], [
                'AND' => [
                    'OR' => [
                        'rep_name[~]' => $searchKeyword,
                        'rep_note[~]' => $searchKeyword,
                    ],
                ],
                'ORDER' => [
                    $this->payload['sortName']  => strtoupper($this->payload['sortType'])
                ]
            ]);
        }

        $total = $this->database->count('svn_reps', [
            'rep_id'
        ], [
            'AND' => [
                'OR' => [
                    'rep_name[~]' => $searchKeyword,
                    'rep_note[~]' => $searchKeyword,
                ],
            ],
        ]);

        foreach ($list as $key => $value) {
            $list[$key]['rep_size'] = funFormatSize($value['rep_size']);
        }

        return message(200, 1, '成功', [
            'data' => $list,
            'total' => $total
        ]);
    }

    /**
     * SVN用户获取有权限的仓库路径列表
     */
    public function GetSvnUserRepList()
    {
        if ($this->enableCheckout == 'http') {
            $checkoutHost = $this->localHttpProtocol . '://' . ($this->dockerHttpPort == 80 ? $this->dockerHost : $this->dockerHost . ':' . $this->dockerHttpPort) . ($this->httpPrefix == '/' ? '' : $this->httpPrefix);
        }

        $sync = $this->payload['sync'];
        $page = $this->payload['page'];

        if ($sync) {
            /**
             * 物理仓库 => authz文件
             * 
             * 1、将物理仓库已经删除但是authz文件中依然存在的从authz文件删除
             * 2、将在物理仓库存在但是authz文件中不存在的向authz文件写入
             */
            $this->SyncRep2Authz();

            /**
             * 及时更新
             */
            parent::RereadAuthz();

            /**
             * 对用户有权限的仓库路径列表进行一一验证
             * 
             * 确保该仓库的路径存在于仓库的最新版本库中
             * 
             * 暂时去除 这样做可能会对已经配置的路径造成误删除 因为文件或者文件夹可能为误删除 进行此同步后就会造成整个路径误删除
             */
            // $this->SyncRepPathCheck();

            /**
             * 及时更新
             */
            // parent::RereadAuthz();

            /**
             * 用户有权限的仓库路径列表 => svn_user_pri_paths数据表
             * 
             * 1、列表中存在的但是数据表不存在则向数据表插入
             * 2、列表中不存在的但是数据表存在从数据表删除
             */
            $this->SyncUserRepAndDb();
        }

        if ($page) {
            $pageSize = $this->payload['pageSize'];
            $currentPage = $this->payload['currentPage'];
        }

        $searchKeyword = trim($this->payload['searchKeyword']);

        if ($page) {
            //分页
            $begin = $pageSize * ($currentPage - 1);
        }

        if ($page) {
            $list = $this->database->select('svn_user_pri_paths', [
                'svnn_user_pri_path_id [Int]',
                'rep_name',
                'pri_path',
                'rep_pri',
                'second_pri [Int]'
            ], [
                'AND' => [
                    'OR' => [
                        'rep_name[~]' => $searchKeyword,
                    ],
                ],
                'LIMIT' => [$begin, $pageSize],
                'ORDER' => [
                    'rep_name'  => strtoupper($this->payload['sortType'])
                ],
                'svn_user_name' => $this->userName
            ]);
        } else {
            $list = $this->database->select('svn_user_pri_paths', [
                'svnn_user_pri_path_id [Int]',
                'rep_name',
                'pri_path',
                'rep_pri',
                'second_pri [Int]'
            ], [
                'AND' => [
                    'OR' => [
                        'rep_name[~]' => $searchKeyword,
                    ],
                ],
                'ORDER' => [
                    'rep_name'  => strtoupper($this->payload['sortType'])
                ],
                'svn_user_name' => $this->userName
            ]);
        }

        if ($this->enableCheckout == 'http') {
            foreach ($list as $key => $value) {
                $list[$key]['second_pri'] = $value['second_pri'] == 1 ? true : false;
                $list[$key]['raw_url'] = rtrim($checkoutHost, '/') . '/' . $value['rep_name'] . $value['pri_path'];
            }
        } else {
            foreach ($list as $key => $value) {
                $list[$key]['second_pri'] = $value['second_pri'] == 1 ? true : false;
            }
        }

        $total = $this->database->count('svn_user_pri_paths', [
            'svnn_user_pri_path_id [Int]'
        ], [
            'AND' => [
                'OR' => [
                    'rep_name[~]' => $searchKeyword,
                ],
            ],
            'svn_user_name' => $this->userName
        ]);

        return message(200, 1, 'thành công', [
            'data' => $list,
            'total' => $total,
            'enableCheckout' => $this->enableCheckout
        ]);
    }

    /**
     * 修改仓库的备注信息
     */
    public function UpdRepNote()
    {
        $this->database->update('svn_reps', [
            'rep_note' => $this->payload['rep_note']
        ], [
            'rep_name' => $this->payload['rep_name']
        ]);

        return message(200, 1, 'Đã lưu');
    }

    /**
     * SVN用户根据目录名称获取该目录下的文件和文件夹列表
     */
    public function GetUserRepCon()
    {
        //检查表单
        $checkResult = funCheckForm($this->payload, [
            'path' => ['type' => 'string', 'notNull' => true],
            'rep_name' => ['type' => 'string', 'notNull' => false],
        ]);
        if ($checkResult['status'] == 0) {
            return message($checkResult['code'], $checkResult['status'], $checkResult['message'] . ': ' . $checkResult['data']['column']);
        }

        //检查仓库是否存在
        clearstatcache();
        if (!is_dir($this->configSvn['rep_base_path'] . $this->payload['rep_name'])) {
            return message(200, 0, 'thư mục không tồn tại');
        }

        $repPath = $this->payload['path'];
        $repName = $this->payload['rep_name'];

        $result = $this->GetSvnList($repPath, $repName);
        if ($result['status'] != 1) {
            return message($result['code'], $result['status'], $result['message']);
        }
        $result = trim($result['data']);

        $resultArray = empty($result) ? [] : explode("\n", $result);

        $isFile = $this->IsDirOrFile($repName, $repPath);
        if ($isFile['status'] != 1) {
            return message($isFile['code'], $isFile['status'], $isFile['message']);
        }
        $isFile = $isFile['data'];

        /**
         * 获取版本号等文件详细信息
         * 
         * 此处也要针对单文件授权进行单独处理
         */
        $data = [];
        foreach ($resultArray as $key => $value) {
            //补全路径
            $value = $isFile ? $repPath : rtrim($repPath, '/') . '/' . $value;

            //获取文件或者文件夹最年轻的版本号
            $lastRev  = $this->GetRepFileRev($repName, $value);

            //获取文件或者文件夹最年轻的版本的作者
            $lastRevAuthor = $this->GetRepFileAuthor($repName, $lastRev);

            //同上 日期
            $lastRevDate = $this->GetRepFileDate($repName, $lastRev);

            //同上 日志
            $lastRevLog = $this->GetRepFileLog($repName, $lastRev);

            $pathArray = explode('/', $value);
            $pathArray = array_values(array_filter($pathArray, 'funArrayValueFilter'));
            if (substr($value, -1) == '/') {
                array_push($data, [
                    'resourceType' => 2,
                    'resourceName' => end($pathArray),
                    'fileSize' => '',
                    'revAuthor' => $lastRevAuthor,
                    'revNum' => 'r' . $lastRev,
                    'revTime' => $lastRevDate,
                    'revLog' => $lastRevLog,
                    'fullPath' => $value
                ]);
            } else {
                array_push($data, [
                    'resourceType' => 1,
                    'resourceName' => end($pathArray),
                    'fileSize' => $this->GetRepRevFileSize($repName, $value),
                    'revAuthor' => $lastRevAuthor,
                    'revNum' => 'r' . $lastRev,
                    'revTime' => $lastRevDate,
                    'revLog' => $lastRevLog,
                    'fullPath' => $value
                ]);
            }
        }

        //按照文件夹在前、文件在后的顺序进行字典排序
        array_multisort(array_column($data, 'resourceType'), SORT_DESC, $data);

        /**
         * 处理面包屑
         */
        if ($repPath == '/') {
            $breadPathArray = ['/'];
            $breadNameArray = [$repName];
        } else {
            $pathArray = explode('/', $repPath);
            //将全路径处理为带有/的数组
            $tempArray = [];
            array_push($tempArray, '/');
            for ($i = 0; $i < count($pathArray); $i++) {
                if ($pathArray[$i] != '') {
                    array_push($tempArray, $pathArray[$i]);
                    array_push($tempArray, '/');
                }
            }
            //处理为递增的路径数组
            $breadPathArray = ['/'];
            $breadNameArray = [$repName];
            $tempPath = '/';
            for ($i = 1; $i < count($tempArray); $i += 2) {
                $tempPath .= $tempArray[$i] . $tempArray[$i + 1];
                array_push($breadPathArray, $tempPath);
                array_push($breadNameArray, $tempArray[$i]);
            }
        }

        //针对单文件授权情况进行处理
        if ($isFile) {
            unset($breadPathArray[count($breadPathArray) - 1]);
            // unset($breadNameArray[count($breadNameArray) - 1]);
        }

        return message(200, 1, '', [
            'data' => $data,
            'bread' => [
                'path' => $breadPathArray,
                'name' => $breadNameArray,
            ]
        ]);
    }

    /**
     * 管理人员根据目录名称获取该目录下的文件和文件夹列表
     */
    public function GetRepCon()
    {
        //检查仓库是否存在
        clearstatcache();
        if (!is_dir($this->configSvn['rep_base_path'] . $this->payload['rep_name'])) {
            return message(200, 0, 'thư mục không tồn tại');
        }

        /**
         * 有权限的开始路径
         * 
         * 管理员为 /
         * SVN用户为管理员设定的路径值
         */
        $path = $this->payload['path'];

        //获取全路径的一层目录树
        $cmd = sprintf("'%s' tree  '%s' --full-paths --non-recursive '%s'", $this->configBin['svnlook'], $this->configSvn['rep_base_path'] .  $this->payload['rep_name'], $path);
        $result = funShellExec($cmd);
        if ($result['code'] != 0) {
            return message(200, 0, $result['error']);
        }

        $resultArray = empty(trim($result['result'])) ? [] : explode("\n", trim($result['result']));

        array_shift($resultArray);

        $data = [];
        foreach ($resultArray as $key => $value) {
            //获取文件或者文件夹最年轻的版本号
            $lastRev  = $this->GetRepFileRev($this->payload['rep_name'], $value);

            //获取文件或者文件夹最年轻的版本的作者
            $lastRevAuthor = $this->GetRepFileAuthor($this->payload['rep_name'], $lastRev);

            //同上 日期
            $lastRevDate = $this->GetRepFileDate($this->payload['rep_name'], $lastRev);

            //同上 日志
            $lastRevLog = $this->GetRepFileLog($this->payload['rep_name'], $lastRev);

            $pathArray = explode('/', $value);
            $pathArray = array_values(array_filter($pathArray, 'funArrayValueFilter'));
            if (substr($value, -1) == '/') {
                array_push($data, [
                    'resourceType' => 2,
                    'resourceName' => end($pathArray),
                    'fileSize' => '',
                    'revAuthor' => $lastRevAuthor,
                    'revNum' => 'r' . $lastRev,
                    'revTime' => $lastRevDate,
                    'revLog' => $lastRevLog,
                    'fullPath' => $value
                ]);
            } else {
                array_push($data, [
                    'resourceType' => 1,
                    'resourceName' => end($pathArray),
                    'fileSize' => $this->GetRepRevFileSize($this->payload['rep_name'], $value),
                    'revAuthor' => $lastRevAuthor,
                    'revNum' => 'r' . $lastRev,
                    'revTime' => $lastRevDate,
                    'revLog' => $lastRevLog,
                    'fullPath' => $value
                ]);
            }
        }

        //按照文件夹在前、文件在后的顺序进行字典排序
        array_multisort(array_column($data, 'resourceType'), SORT_DESC, $data);

        //处理面包屑
        if ($path == '/') {
            $breadPathArray = ['/'];
            $breadNameArray = [$this->payload['rep_name']];
        } else {
            $pathArray = explode('/', $path);
            //将全路径处理为带有/的数组
            $tempArray = [];
            array_push($tempArray, '/');
            for ($i = 0; $i < count($pathArray); $i++) {
                if ($pathArray[$i] != '') {
                    array_push($tempArray, $pathArray[$i]);
                    array_push($tempArray, '/');
                }
            }
            //处理为递增的路径数组
            $breadPathArray = ['/'];
            $breadNameArray = [$this->payload['rep_name']];
            $tempPath = '/';
            for ($i = 1; $i < count($tempArray); $i += 2) {
                $tempPath .= $tempArray[$i] . $tempArray[$i + 1];
                array_push($breadPathArray, $tempPath);
                array_push($breadNameArray, $tempArray[$i]);
            }
        }

        return message(200, 1, '', [
            'data' => $data,
            'bread' => [
                'path' => $breadPathArray,
                'name' => $breadNameArray
            ]
        ]);
    }

    /**
     * 根据目录名称获取该目录下的目录树
     * 
     * 管理员配置目录授权用
     * 
     */
    public function GetRepTree()
    {
        //检查表单
        $checkResult = funCheckForm($this->payload, [
            'path' => ['type' => 'string', 'notNull' => true],
            'rep_name' => ['type' => 'string', 'notNull' => false],
        ]);
        if ($checkResult['status'] == 0) {
            return message($checkResult['code'], $checkResult['status'], $checkResult['message'] . ': ' . $checkResult['data']['column']);
        }

        $path = $this->payload['path'];
        $repName = $this->payload['rep_name'];

        //检查仓库是否存在
        clearstatcache();
        if (!is_dir($this->configSvn['rep_base_path'] . $repName)) {
            return message(200, 0, 'thư mục không tồn tại');
        }

        //获取全路径的一层目录树
        $cmdSvnlookTree = sprintf("'%s' tree  '%s' --full-paths --non-recursive '%s'", $this->configBin['svnlook'], $this->configSvn['rep_base_path']  . $repName, $path);
        $result = funShellExec($cmdSvnlookTree);
        if ($result['code'] != 0) {
            return message(200, 0, $result['error']);
        }

        $resultArray = empty(trim($result['result'])) ? [] : explode("\n", trim($result['result']));

        array_shift($resultArray);

        $data = [];
        foreach ($resultArray as $value) {
            $pathArray = explode('/', $value);
            $pathArray = array_values(array_filter($pathArray, 'funArrayValueFilter'));
            if (substr($value, -1) == '/') {
                array_push($data, [
                    'expand' => false,
                    'contextmenu' => true,
                    'loading' => false,
                    'resourceType' => 2,
                    'title' => end($pathArray) . '/',
                    'fullPath' => $value,
                    'children' => []
                ]);
            } else {
                array_push($data, [
                    'resourceType' => 1,
                    'title' => end($pathArray),
                    'fullPath' => $value,
                ]);
            }
        }

        //按照文件夹在前、文件在后的顺序进行字典排序
        array_multisort(array_column($data, 'resourceType'), SORT_DESC, $data);

        if ($path == '/') {
            return message(200, 1, '', [
                [
                    'expand' => true,
                    'contextmenu' => true,
                    'loading' => false,
                    'resourceType' => 2,
                    'title' => $repName . '/',
                    'fullPath' => '/',
                    'children' => $data
                ]
            ]);
        } else {
            return message(200, 1, '', $data);
        }
    }

    /**
     * 根据目录名称获取该目录下的目录树
     *
     * SVN用户配置目录授权用
     */
    public function GetRepTree2()
    {
        //检查表单
        $checkResult = funCheckForm($this->payload, [
            'path' => ['type' => 'string', 'notNull' => true],
            'rep_name' => ['type' => 'string', 'notNull' => false],
            'first' => ['type' => 'boolean'],
        ]);
        if ($checkResult['status'] == 0) {
            return message($checkResult['code'], $checkResult['status'], $checkResult['message'] . ': ' . $checkResult['data']['column']);
        }

        $repPath = $this->payload['path'];
        $repName = $this->payload['rep_name'];
        $first = $this->payload['first'];

        //检查仓库是否存在
        clearstatcache();
        if (!is_dir($this->configSvn['rep_base_path'] . $repName)) {
            return message(200, 0, 'thư mục không tồn tại');
        }

        if (!$first && substr($repPath, -1) != '/') {
            return message(200, 0, 'Request Error -File has no subtree');
        }

        $result = $this->GetSvnList($repPath, $repName);
        if ($result['status'] != 1) {
            return message($result['code'], $result['status'], $result['message']);
        }

        $resultArray = empty(trim($result['data'])) ? [] : explode("\n", trim($result['data']));

        $isFile = $this->IsDirOrFile($repName, $repPath);
        if ($isFile['status'] != 1) {
            return message($isFile['code'], $isFile['status'], $isFile['message']);
        }
        $isFile = $isFile['data'];

        if ($repPath != '/' && !$isFile) {
            $repPath = $repPath . '/';
        }

        $data = [];
        foreach ($resultArray as $value) {
            $pathArray = explode('/', $value);
            $pathArray = array_values(array_filter($pathArray, 'funArrayValueFilter'));
            if (substr($value, -1) == '/') {
                array_push($data, [
                    'expand' => $first && $repPath == '/' ? false : true,
                    'loading' => false,
                    'resourceType' => 2,
                    'title' => end($pathArray) . '/',
                    'fullPath' => rtrim($repPath, '/') . '/' . $value,
                    'children' => []
                ]);
            } else {
                array_push($data, [
                    'resourceType' => 1,
                    'title' => end($pathArray),
                    'fullPath' => rtrim($repPath, '/') . '/' . $value,
                ]);
            }
        }

        //按照文件夹在前、文件在后的顺序进行字典排序
        array_multisort(array_column($data, 'resourceType'), SORT_DESC, $data);

        //首次请求
        if ($first) {
            if ($repPath == '/') {
                $result = [
                    [
                        'expand' => true,
                        'loading' => false,
                        'resourceType' => 2,
                        'title' => $repName . '/',
                        'fullPath' => '/',
                        'children' => $data
                    ]
                ];
            } elseif (substr($repPath, -1) == '/') {
                $pathArray = explode('/', $repPath);
                $pathArray = array_values(array_filter($pathArray, 'funArrayValueFilter'));
                $result = [
                    [
                        'expand' => true,
                        'loading' => false,
                        'resourceType' => 2,
                        'title' => $repName . '/',
                        'fullPath' => '/',
                        'children' => [$this->GetRepTreeChildren($pathArray, $data, [])]
                    ]
                ];
            } else {
                $pathArray = explode('/', $repPath);
                $pathArray = array_values(array_filter($pathArray, 'funArrayValueFilter'));
                $last = end($pathArray);
                array_pop($pathArray);
                $result = [
                    [
                        'expand' => true,
                        'loading' => false,
                        'resourceType' => 2,
                        'title' => $repName . '/',
                        'fullPath' => '/',
                        'children' => [$this->GetRepTreeChildren($pathArray, $last, [])]
                    ]
                ];
            }
            return message(200, 1, 'thành công', $result);
        } else {
            return message(200, 1, 'thành công', $data);
        }
    }

    /**
     * 递归方式拼接单路径目录树
     */
    private function GetRepTreeChildren($pathArray, $last, $pathHistoryArray = [])
    {
        if (empty($pathArray)) {
            if (is_array($last)) {
                return empty($last) ? [] : $last;
            } else {
                return [
                    'resourceType' => 1,
                    'title' => $last,
                    'fullPath' => '/' . implode('/', $pathHistoryArray) . '/' . $last,
                ];
            }
        }

        $current = $pathArray[0];
        array_push($pathHistoryArray, $pathArray[0]);
        array_shift($pathArray);


        $recursion = $this->GetRepTreeChildren($pathArray, $last, $pathHistoryArray);
        if (empty($recursion)) {
            $children = [];
        } elseif (array_key_exists('expand', $recursion)) {
            $children = [$recursion];
        } elseif (array_key_exists('resourceType', $recursion)) {
            $children = [$recursion];
        } else {
            $children = $recursion;
        }

        $data = [
            'expand' => true,
            'loading' => false,
            'resourceType' => 2,
            'title' => $current . '/',
            'fullPath' => '/' . implode('/', $pathHistoryArray) . '/',
            'children' => $children
        ];

        return $data;
    }

    /**
     * 在线创建目录
     */
    public function CreateRepFolder()
    {
        //检查表单
        $checkResult = funCheckForm($this->payload, [
            'rep_name' => ['type' => 'string', 'notNull' => true],
            'folder_name' => ['type' => 'string', 'notNull' => true],
            'path' => ['type' => 'string', 'notNull' => true]
        ]);
        if ($checkResult['status'] == 0) {
            return message($checkResult['code'], $checkResult['status'], $checkResult['message'] . ': ' . $checkResult['data']['column']);
        }

        $repPath = $this->configSvn['rep_base_path'] . $this->payload['rep_name'] . '/' . $this->payload['path'];

        $prefix = uniqid('create_');

        $templetePath = $this->configSvn['home_path'] . 'temp/' . $prefix;

        $folderPath = $this->configSvn['home_path'] . 'temp/' . $prefix . '/' . $this->payload['folder_name'];

        mkdir($folderPath, 0755, true);

        clearstatcache();
        if (!is_dir($folderPath)) {
            return message(200, 0, sprintf('Không thể tạo đường dẫn[%s]', $folderPath));
        }

        $user = 'SVNAdmin';
        $pass = 'SVNAdmin';
        $message = 'Create folder';

        $cmd = sprintf("'%s' import '%s' 'file:///%s' --quiet --username '%s' --password '%s' --message '%s'", $this->configBin['svn'], $templetePath, $repPath, $user, $pass, $message);
        $result = funShellExec($cmd);
        if ($result['code'] != 0) {
            return message(200, 0, $result['error']);
        }

        @unlink($templetePath);

        return message(200, 1, 'Vui lòng làm mới trang để xem hiệu ứng');
    }

    /**
     * 判断从authz获取的权限路径是文件还是目录
     */
    private function IsDirOrFile($repName, $repPath)
    {
        $cmd = sprintf("'%s' tree  '%s' --full-paths --non-recursive '%s'", $this->configBin['svnlook'], $this->configSvn['rep_base_path'] . $repName, $repPath);
        $result = funShellExec($cmd);
        if ($result['code'] != 0) {
            return message(200, 0, $result['error']);
        }
        $result = trim($result['result']);

        $resultArray = empty($result) ? [] : explode("\n", $result);

        $resultArray = array_values(array_filter($resultArray, 'funArrayValueFilter'));

        /**
         * 1、结果只有一条
         * 2、结尾无/
         */
        $isFile = false;
        if (count($resultArray) == 1) {
            if (substr($result, -1) != '/') {
                $isFile = true;
            }
        }

        return message(200, 1, '成功', $isFile);
    }

    /**
     * 获取某个仓库路径的所有权限列表
     */
    public function GetRepPathAllPri()
    {
        //检查表单
        $checkResult = funCheckForm($this->payload, [
            'path' => ['type' => 'string', 'notNull' => true],
            'rep_name' => ['type' => 'string', 'notNull' => true],
            'svnn_user_pri_path_id' => ['type' => 'integer', 'required' => $this->userRoleId == 2]
        ]);
        if ($checkResult['status'] == 0) {
            return message($checkResult['code'], $checkResult['status'], $checkResult['message'] . ': ' . $checkResult['data']['column']);
        }

        //检查仓库是否存在
        clearstatcache();
        if (!is_dir($this->configSvn['rep_base_path'] . $this->payload['rep_name'])) {
            return message(200, 0, 'thư mục không tồn tại');
        }

        if ($this->userRoleId == 2) {
            $pri = $this->database->get('svn_user_pri_paths', [
                'pri_path',
                'rep_name'
            ], [
                'svnn_user_pri_path_id' => $this->payload['svnn_user_pri_path_id']
            ]);

            //校验1 路径长度校验
            if (strlen($this->payload['path']) < strlen($pri['pri_path'])) {
                return message(200, 0, 'Không có quyền quản lý đường dẫn');
            }
            if (substr($this->payload['path'], 0, strlen($pri['pri_path'])) != $pri['pri_path']) {
                return message(200, 0, 'Không có quyền quản lý đường dẫn');
            }

            //校验2 路径有权校验
            $pri = $this->GetSvnList($this->payload['path'], $pri['rep_name']);
            if ($pri['status'] != 1) {
                return message($pri['code'], $pri['status'], $pri['message']);
            }
        }

        $result = $this->SVNAdmin->GetRepPathPri($this->authzContent, $this->payload['rep_name'], $this->payload['path']);
        if (is_numeric($result)) {
            if ($result == 751) {
                //没有该路径的记录
                if ($this->payload['path'] == '/') {
                    //不正常 没有写入仓库记录
                    return message(200, 0, 'Kho lưu trữ không được ghi vào tệp cấu hình! Vui lòng làm mới danh sách thư mục lưu trữ để đồng bộ hóa');
                } else {
                    //正常 无记录
                    return message();
                }
            } elseif ($result == 752) {
                return message(200, 0, 'Đường dẫn thư mục lưu trữ cần bắt đầu bằng /');
            } else {
                return message(200, 0, "mã lỗi$result");
            }
        } else {
            //针对SVN用户可管理对象进行过滤
            if ($this->userRoleId == 2) {
                $filters = $this->database->select('svn_second_pri', [
                    '[>]svn_user_pri_paths' => ['svnn_user_pri_path_id' => 'svnn_user_pri_path_id']
                ], [
                    'svn_second_pri.svn_object_type(objectType)',
                    'svn_second_pri.svn_object_name(objectName)',
                ], [
                    'svn_user_pri_paths.svn_user_name' => $this->userName,
                    'svn_user_pri_paths.svnn_user_pri_path_id' => $this->payload['svnn_user_pri_path_id']
                ]);
                foreach ($result as $key => $value) {
                    if ($value['objectType'] == 'user' && $value['objectName'] == $this->userName) {
                        continue;
                    }
                    if (!in_array([
                        'objectType' => $value['objectType'],
                        'objectName' => $value['objectName']
                    ], $filters)) {
                        unset($result[$key]);
                    }
                }
                $result = array_values($result);
            }
            return message(200, 1, 'thành công', $result);
        }
    }

    /**
     * 为某仓库路径下增加权限
     *
     * @return array
     */
    public function CreateRepPathPri()
    {
        //检查表单
        $checkResult = funCheckForm($this->payload, [
            'path' => ['type' => 'string', 'notNull' => true],
            'rep_name' => ['type' => 'string', 'notNull' => true],
            'objectType' => ['type' => 'string', 'notNull' => true],
            'objectPri' => ['type' => 'string', 'notNull' => true],
            'objectName' => ['type' => 'string', 'notNull' => true],
            'svnn_user_pri_path_id' => ['type' => 'integer', 'required' => $this->userRoleId == 2]
        ]);
        if ($checkResult['status'] == 0) {
            return message($checkResult['code'], $checkResult['status'], $checkResult['message'] . ': ' . $checkResult['data']['column']);
        }

        $repName = $this->payload['rep_name'];
        $path = $this->payload['path'];
        $objectType = $this->payload['objectType'];
        $objectPri = $this->payload['objectPri'];
        $objectName = $this->payload['objectName'];

        //检查仓库是否存在
        clearstatcache();
        if (!is_dir($this->configSvn['rep_base_path'] . $repName)) {
            return message(200, 0, 'thư mục không tồn tại');
        }

        if ($this->userRoleId == 2) {
            $pri = $this->database->get('svn_user_pri_paths', [
                'pri_path',
                'rep_name'
            ], [
                'svnn_user_pri_path_id' => $this->payload['svnn_user_pri_path_id']
            ]);

            //校验1 路径长度校验
            if (strlen($this->payload['path']) < strlen($pri['pri_path'])) {
                return message(200, 0, 'Không có quyền quản lý đường dẫn');
            }
            if (substr($this->payload['path'], 0, strlen($pri['pri_path'])) != $pri['pri_path']) {
                return message(200, 0, 'Không có quyền quản lý đường dẫn');
            }

            //校验2 路径有权校验
            $pri = $this->GetSvnList($this->payload['path'], $pri['rep_name']);
            if ($pri['status'] != 1) {
                return message($pri['code'], $pri['status'], $pri['message']);
            }
        }

        //针对SVN用户可管理对象进行过滤
        if ($this->userRoleId == 2) {
            if ($objectType == 'user' && $objectName == $this->userName) {
                return message(200, 0, 'bản thân nó không thể hoạt động được');
            }

            $filters = $this->database->select('svn_second_pri', [
                '[>]svn_user_pri_paths' => ['svnn_user_pri_path_id' => 'svnn_user_pri_path_id']
            ], [
                'svn_second_pri.svn_object_type(objectType)',
                'svn_second_pri.svn_object_name(objectName)',
            ], [
                'svn_user_pri_paths.svn_user_name' => $this->userName,
                'svn_user_pri_paths.svnn_user_pri_path_id' => $this->payload['svnn_user_pri_path_id']
            ]);
            if (!in_array([
                'objectType' => $objectType,
                'objectName' => $objectName
            ], $filters)) {
                return message(200, 0, 'Đối tượng hoạt động mà không được phép');
            }
        }

        /**
         * 处理权限
         */
        $objectPri = $objectPri == 'no' ? '' : $objectPri;

        $result = $this->SVNAdmin->AddRepPathPri($this->authzContent, $repName, $path, $objectType, $objectName, $objectPri, false);

        if (is_numeric($result)) {
            if ($result == 751) {
                //没有该仓库路径记录 则进行插入
                $result = $this->SVNAdmin->WriteRepPathToAuthz($this->authzContent, $repName, $path);
                if (is_numeric($result)) {
                    if ($result == 851) {
                        $result = $this->authzContent;
                    } else {
                        return message(200, 0, "mã lỗi$result");
                    }
                } elseif ($result == 752) {
                    return message(200, 0, 'Đường dẫn thư mục lưu trữ cần bắt đầu bằng /');
                } else {
                    //重新写入权限
                    $result = $this->SVNAdmin->AddRepPathPri($result, $repName, $path, $objectType, $objectName, $objectPri, false);
                    if (is_numeric($result)) {
                        return message(200, 0, "mã lỗi$result");
                    }
                }
            } elseif ($result == 801) {
                return message(200, 0, 'Đối tượng đã có bản ghi ủy quyền');
            } elseif ($result == 901) {
                return message(200, 0, 'Loại đối tượng ủy quyền không được hỗ trợ');
            } else {
                return message(200, 0, "mã lỗi$result");
            }
        }

        //写入
        funFilePutContents($this->configSvn['svn_authz_file'], $result);

        //返回
        return message();
    }

    /**
     * 修改某个仓库路径下的权限
     */
    public function UpdRepPathPri()
    {
        //检查表单
        $checkResult = funCheckForm($this->payload, [
            'path' => ['type' => 'string', 'notNull' => true],
            'rep_name' => ['type' => 'string', 'notNull' => true],
            'objectType' => ['type' => 'string', 'notNull' => true],
            'objectPri' => ['type' => 'string', 'notNull' => true],
            'objectName' => ['type' => 'string', 'notNull' => true],
            'invert' => ['type' => 'boolean'],
            'svnn_user_pri_path_id' => ['type' => 'integer', 'required' => $this->userRoleId == 2]
        ]);
        if ($checkResult['status'] == 0) {
            return message($checkResult['code'], $checkResult['status'], $checkResult['message'] . ': ' . $checkResult['data']['column']);
        }

        $repName = $this->payload['rep_name'];
        $path = $this->payload['path'];
        $objectType = $this->payload['objectType'];
        $invert = $this->payload['invert'];
        $objectName = $this->payload['objectName'];
        $objectPri = $this->payload['objectPri'];

        //检查仓库是否存在
        clearstatcache();
        if (!is_dir($this->configSvn['rep_base_path'] . $repName)) {
            return message(200, 0, 'thư mục không tồn tại');
        }

        if ($this->userRoleId == 2) {
            $pri = $this->database->get('svn_user_pri_paths', [
                'pri_path',
                'rep_name'
            ], [
                'svnn_user_pri_path_id' => $this->payload['svnn_user_pri_path_id']
            ]);

            //校验1 路径长度校验
            if (strlen($this->payload['path']) < strlen($pri['pri_path'])) {
                return message(200, 0, 'Không có quyền quản lý đường dẫn');
            }
            if (substr($this->payload['path'], 0, strlen($pri['pri_path'])) != $pri['pri_path']) {
                return message(200, 0, 'Không có quyền quản lý đường dẫn');
            }

            //校验2 路径有权校验
            $pri = $this->GetSvnList($this->payload['path'], $pri['rep_name']);
            if ($pri['status'] != 1) {
                return message($pri['code'], $pri['status'], $pri['message']);
            }
        }

        //针对SVN用户可管理对象进行过滤
        if ($this->userRoleId == 2) {
            if ($objectType == 'user' && $objectName == $this->userName) {
                return message(200, 0, 'bản thân nó không thể hoạt động được');
            }

            $filters = $this->database->select('svn_second_pri', [
                '[>]svn_user_pri_paths' => ['svnn_user_pri_path_id' => 'svnn_user_pri_path_id']
            ], [
                'svn_second_pri.svn_object_type(objectType)',
                'svn_second_pri.svn_object_name(objectName)',
            ], [
                'svn_user_pri_paths.svn_user_name' => $this->userName,
                'svn_user_pri_paths.svnn_user_pri_path_id' => $this->payload['svnn_user_pri_path_id']
            ]);
            if (!in_array([
                'objectType' => $objectType,
                'objectName' => $objectName
            ], $filters)) {
                return message(200, 0, 'Đối tượng hoạt động mà không được phép');
            }
        }

        /**
         * 处理权限
         */
        $objectPri = $objectPri == 'no' ? '' : $objectPri;

        $result = $this->SVNAdmin->EditRepPathPri($this->authzContent, $repName, $path, $objectType, $objectName, $objectPri, $invert == 1 ? true : false);

        if (is_numeric($result)) {
            if ($result == 751) {
                return message(200, 0, 'Đường dẫn thư mục không tồn tại');
            } elseif ($result == 752) {
                return message(200, 0, 'Đường dẫn thư mục cần bắt đầu bằng/开始');
            } elseif ($result == 901) {
                return message(200, 0, 'Loại đối tượng ủy quyền không được hỗ trợ');
            } elseif ($result == 701) {
                return message(200, 0, 'Không có bản ghi cấp phép cho đối tượng này theo đường dẫn thư mục');
            } else {
                return message(200, 0, "mã lỗi$result");
            }
        }

        //写入
        funFilePutContents($this->configSvn['svn_authz_file'], $result);

        //返回
        return message();
    }

    /**
     * 删除某个仓库下的权限
     */
    public function DelRepPathPri()
    {
        //检查表单
        $checkResult = funCheckForm($this->payload, [
            'path' => ['type' => 'string', 'notNull' => true],
            'rep_name' => ['type' => 'string', 'notNull' => true],
            'objectType' => ['type' => 'string', 'notNull' => true],
            'objectName' => ['type' => 'string', 'notNull' => true],
            'svnn_user_pri_path_id' => ['type' => 'integer', 'required' => $this->userRoleId == 2]
        ]);
        if ($checkResult['status'] == 0) {
            return message($checkResult['code'], $checkResult['status'], $checkResult['message'] . ': ' . $checkResult['data']['column']);
        }

        $repName = $this->payload['rep_name'];
        $path = $this->payload['path'];
        $objectType = $this->payload['objectType'];
        $objectName = $this->payload['objectName'];

        //检查仓库是否存在
        clearstatcache();
        if (!is_dir($this->configSvn['rep_base_path'] . $repName)) {
            return message(200, 0, 'thư mục không tồn tại');
        }

        if ($this->userRoleId == 2) {
            $pri = $this->database->get('svn_user_pri_paths', [
                'pri_path',
                'rep_name'
            ], [
                'svnn_user_pri_path_id' => $this->payload['svnn_user_pri_path_id']
            ]);

            //校验1 路径长度校验
            if (strlen($this->payload['path']) < strlen($pri['pri_path'])) {
                return message(200, 0, 'Không có quyền quản lý đường dẫn');
            }
            if (substr($this->payload['path'], 0, strlen($pri['pri_path'])) != $pri['pri_path']) {
                return message(200, 0, 'Không có quyền quản lý đường dẫn');
            }

            //校验2 路径有权校验
            $pri = $this->GetSvnList($this->payload['path'], $pri['rep_name']);
            if ($pri['status'] != 1) {
                return message($pri['code'], $pri['status'], $pri['message']);
            }
        }

        //针对SVN用户可管理对象进行过滤
        if ($this->userRoleId == 2) {
            if ($objectType == 'user' && $objectName == $this->userName) {
                return message(200, 0, 'bản thân nó không thể hoạt động được');
            }

            $filters = $this->database->select('svn_second_pri', [
                '[>]svn_user_pri_paths' => ['svnn_user_pri_path_id' => 'svnn_user_pri_path_id']
            ], [
                'svn_second_pri.svn_object_type(objectType)',
                'svn_second_pri.svn_object_name(objectName)',
            ], [
                'svn_user_pri_paths.svn_user_name' => $this->userName,
                'svn_user_pri_paths.svnn_user_pri_path_id' => $this->payload['svnn_user_pri_path_id']
            ]);
            if (!in_array([
                'objectType' => $objectType,
                'objectName' => $objectName
            ], $filters)) {
                return message(200, 0, 'Đối tượng hoạt động mà không được phép');
            }
        }

        $result = $this->SVNAdmin->DelRepPathPri($this->authzContent, $repName, $path, $objectType, $objectName);

        if (is_numeric($result)) {
            if ($result == 751) {
                return message(200, 0, 'Không có bản ghi nào cho đường dẫn thư mục này');
            } elseif ($result == 752) {
                return message(200, 0, 'Đường dẫn thư mục lưu trữ cần bắt đầu bằng /');
            } elseif ($result == 901) {
                return message(200, 0, 'Loại đối tượng ủy quyền không được hỗ trợ');
            } elseif ($result == 701) {
                return message(200, 0, '已删除');
            } else {
                return message(200, 0, "mã lỗi$result");
            }
        }

        //写入
        funFilePutContents($this->configSvn['svn_authz_file'], $result);

        //返回
        return message();
    }

    /**
     * 修改仓库名称
     */
    public function UpdRepName()
    {
        //检查新仓库名是否合法
        $checkResult = $this->checkService->CheckRepName($this->payload['new_rep_name']);
        if ($checkResult['status'] != 1) {
            return message($checkResult['code'], $checkResult['status'], $checkResult['message'], $checkResult['data']);
        }

        //检查原仓库是否不存在
        clearstatcache();
        if (!is_dir($this->configSvn['rep_base_path'] . $this->payload['old_rep_name'])) {
            return message(200, 0, 'thư mục để sửa đổi không tồn tại');
        }

        //检查新仓库名是否存在
        if (is_dir($this->configSvn['rep_base_path'] . $this->payload['new_rep_name'])) {
            return message(200, 0, 'Một thư mục có cùng tên đã tồn tại');
        }

        //从仓库目录修改仓库名称
        funShellExec('mv ' . $this->configSvn['rep_base_path'] .  $this->payload['old_rep_name'] . ' ' . $this->configSvn['rep_base_path'] . $this->payload['new_rep_name']);

        //检查修改过的仓库名称是否存在
        clearstatcache();
        if (!is_dir($this->configSvn['rep_base_path'] . $this->payload['new_rep_name'])) {
            return message(200, 0, 'Không thể sửa đổi tên thư mục');
        }

        //从数据库修改仓库名称
        $this->database->update('svn_reps', [
            'rep_name' => $this->payload['new_rep_name']
        ], [
            'rep_name' => $this->payload['old_rep_name']
        ]);

        //从配置文件修改仓库名称
        $result = $this->SVNAdmin->UpdRepFromAuthz($this->authzContent, $this->payload['old_rep_name'], $this->payload['new_rep_name']);
        if (is_numeric($result)) {
            if ($result == 751) {
                return message(200, 0, 'thư mục không tồn tại');
            } else {
                return message(200, 0, "mã lỗi$result");
            }
        }

        funFilePutContents($this->configSvn['svn_authz_file'], $result);

        //日志
        $this->ServiceLogs->InsertLog(
            '修改仓库名称',
            sprintf("Tên thư mục ban đầu: %s Tên thư mục mới:%s", $this->payload['old_rep_name'], $this->payload['new_rep_name']),
            $this->userName
        );

        return message();
    }

    /**
     * 删除仓库
     */
    public function DelRep()
    {
        //从配置文件删除指定仓库的所有路径
        $authzContet = $this->SVNAdmin->DelRepFromAuthz($this->authzContent, $this->payload['rep_name']);
        if (is_numeric($authzContet)) {
            if ($authzContet == 751) {
            } else {
                return message(200, 0, "mã lỗi$authzContet");
            }
        } else {
            funFilePutContents($this->configSvn['svn_authz_file'], $authzContet);
        }

        //从数据库中删除
        $this->database->delete('svn_reps', [
            'rep_name' => $this->payload['rep_name']
        ]);

        //从仓库目录删除仓库文件夹
        funShellExec('cd ' . $this->configSvn['rep_base_path'] . ' && rm -rf ./' . $this->payload['rep_name']);
        clearstatcache();
        if (is_dir($this->configSvn['rep_base_path'] .  $this->payload['rep_name'])) {
            return message(200, 0, 'không thể xóa');
        }

        //日志
        $this->ServiceLogs->InsertLog(
            '删除仓库',
            sprintf("仓库名:%s", $this->payload['rep_name']),
            $this->userName
        );

        //返回
        return message();
    }

    /**
     * 获取仓库的属性内容（key-value的形式）
     */
    public function GetRepDetail()
    {
        //检查仓库是否存在
        clearstatcache();
        if (!is_dir($this->configSvn['rep_base_path'] . $this->payload['rep_name'])) {
            return message(200, 0, 'thư mục không tồn tại');
        }

        $result = $this->GetRepDetail110($this->payload['rep_name']);
        if ($result['code'] == 0) {
            $result = $result['result'];
            //Subversion 1.10 及以上版本
            $resultArray = explode("\n", $result);
            $newArray = [];
            foreach ($resultArray as $key => $value) {
                if (trim($value) != '') {
                    $tempArray = explode(':', $value);
                    if (count($tempArray) == 2) {
                        array_push($newArray, [
                            'repKey' => $tempArray[0],
                            'repValue' => $tempArray[1],
                        ]);
                    }
                }
            }
        } else {
            //Subversion 1.8 -1.9
            $newArray = [
                [
                    'repKey' => 'Path',
                    'repValue' => $this->configSvn['rep_base_path'] .  $this->payload['rep_name'],
                ],
                [
                    'repKey' => 'UUID',
                    'repValue' => $this->database->get('svn_reps', 'rep_uuid', [
                        'rep_name' => $this->payload['rep_name']
                    ])
                ],
            ];
        }

        return message(200, 1, '成功', $newArray);
    }

    /**
     * 重设仓库的UUID
     */
    public function SetUUID()
    {
        if ($this->payload['uuid'] == '') {
            $cmd = sprintf("'%s' setuuid '%s'", $this->configBin['svnadmin'], $this->configSvn['rep_base_path'] .  $this->payload['rep_name']);
        } else {
            $cmd = sprintf("'%s' setuuid '%s' '%s'", $this->configBin['svnadmin'], $this->configSvn['rep_base_path'] .  $this->payload['rep_name'], $this->payload['uuid']);
        }

        $result = funShellExec($cmd);

        if ($result['code'] == 0) {
            $this->database->update('svn_reps', [
                'rep_uuid' => $this->GetRepUUID($this->payload['rep_name'])
            ], [
                'rep_name' => $this->payload['rep_name']
            ]);

            return message();
        } else {
            return message(200, 0, $result['error']);
        }
    }

    /**
     * 获取备份文件夹下的文件列表
     */
    public function GetBackupList()
    {
        $result = funGetDirFileList($this->configSvn['backup_base_path']);

        foreach ($result as $key => $value) {
            $result[$key]['fileToken'] = hash_hmac('md5', $value['fileName'], $this->configSign['signature']);
            $result[$key]['fileUrl'] = sprintf('api.php?c=Svnrep&a=DownloadRepBackup&t=web&fileName=%s&token=%s', $value['fileName'], $result[$key]['fileToken']);
        }

        return message(200, 1, 'thành công', $result);
    }

    /**
     * 立即备份当前仓库
     */
    public function SvnadminDump()
    {
        //检查仓库是否存在
        clearstatcache();
        if (!is_dir($this->configSvn['rep_base_path'] . $this->payload['rep_name'])) {
            return message(200, 0, 'thư mục không tồn tại');
        }

        $task_unique = uniqid('task_svnadmin_dump_');

        $task_log_file = $this->configSvn['log_base_path'] . $task_unique . '.log';

        $backupName = $this->payload['rep_name'] . '_' . date('YmdHis') . '_' . $task_unique . '.dump';

        $task_cmd = sprintf("'%s' dump '%s' --quiet  > '%s'", $this->configBin['svnadmin'], $this->configSvn['rep_base_path'] .  $this->payload['rep_name'], $this->configSvn['backup_base_path'] .  $backupName);

        $this->database->insert('tasks', [
            'task_name' => sprintf('Đã tạo tệp sao lưu thư mục [%s]', $this->payload['rep_name']),
            'task_status' => 1,
            'task_cmd' => $task_cmd,
            'task_type' => 'svnadmin:dump',
            'task_unique' => $task_unique,
            'task_log_file' => $task_log_file,
            'task_optional' => '',
            'task_create_time' => date('Y-m-d H:i:s'),
            'task_update_time' => ''
        ]);

        return message(200, 1, 'Added background task execution', [
            'task_unique' => $task_unique
        ]);
    }

    /**
     * 删除备份文件
     */
    public function DelRepBackup()
    {
        $cmd = sprintf("cd '%s' && rm -f './%s'", $this->configSvn['backup_base_path'], $this->payload['fileName']);
        funShellExec($cmd);

        return message();
    }

    /**
     * 下载前请求文件名和文件token
     */
    public function GetDownloadInfo()
    {
    }

    /**
     * 下载备份文件
     */
    public function DownloadRepBackup()
    {
        if (empty($_GET['fileName'])) {
            json1(200, 0, 'Tên tập tin bị thiếu');
        }
        $fileName = $_GET['fileName'];
        $filePath = $this->configSvn['backup_base_path'] .  $fileName;

        if (empty($_GET['token'])) {
            json1(200, 0, 'Thiếu mã thông báo tệp');
        }
        $token = $_GET['token'];

        if ($token !== hash_hmac('md5', $fileName, $this->configSign['signature'])) {
            json1(200, 0, 'Mã thông báo tệp không hợp lệ');
        }

        if (!file_exists($this->configSvn['backup_base_path'] .  $fileName)) {
            json1(200, 0, 'tập tin không tồn tại');
        }

        set_time_limit(0);

        $fp = @fopen($filePath, 'rb');
        if ($fp) {
            $file_size = filesize($filePath);

            header('content-type:application/octet-stream');
            header('Content-Disposition: attachment; filename=' . $fileName);

            header('HTTP/1.1 200 OK');
            header('Accept-Ranges:bytes');
            header('content-length:' . $file_size);

            ob_end_clean();
            ob_start();

            while (!feof($fp)) {
                echo fread($fp, 4096);
                ob_flush();
                flush();
            }

            ob_end_flush();

            fclose($fp);
        }
    }

    /**
     * 获取php文件上传相关参数
     */
    public function GetUploadInfo()
    {
        $upload = ini_get('file_uploads');
        if ($upload == 0 || $upload == false || strtolower($upload) == 'off') {
            $upload = false;
        } else {
            $upload = true;
        }

        return message(200, 1, 'thành công', [
            //文件上传功能开启状态
            'upload' => $upload,
            //分片上传大小
            'chunkSize' => 1,
            //分片合并后删除分片
            'deleteOnMerge' => 1,
        ]);
    }

    /**
     * 上传文件到备份文件夹
     */
    public function UploadBackup()
    {
        $file_uploads = ini_get('file_uploads');
        if ($file_uploads == 0 || $file_uploads == false || strtolower($file_uploads) == 'off') {
            return message(200, 0, 'Tính năng tải tập tin lên bị tắt');
        }

        //检查表单
        $checkResult = funCheckForm($_POST, [
            'filename' => ['type' => 'string', 'notNull' => true],
            'md5' => ['type' => 'string', 'notNull' => true],
            'numBlobTotal' => ['type' => 'integer|string', 'notNull' => true],
            'numBlobCurrent' => ['type' => 'integer|string', 'notNull' => true],
            'deleteOnMerge' => ['type' => 'boolean|string', 'notNull' => true],
        ]);
        if ($checkResult['status'] == 0) {
            return message($checkResult['code'], $checkResult['status'], $checkResult['message'] . ': ' . $checkResult['data']['column']);
        }

        if (array_key_exists('file', $_FILES)) {
            $file = $_FILES['file'];
            if ($file["error"] > 0) {
                return message(200, 0, $file["error"]);
            }

            $nameDirTempSave = $this->configSvn['home_path'] . '/temp/';
            if (!is_dir($nameDirTempSave)) {
                mkdir($nameDirTempSave);
                if (!is_dir($nameDirTempSave)) {
                    return message(200, 0, sprintf('Thư mục [%s] không tồn tại và không thể tạo được', $nameDirTempSave));
                }
            }
            if (!is_writable($nameDirTempSave)) {
                return message(200, 0, sprintf('Không thể ghi thư mục [%s]', $nameDirTempSave));
            }

            //限制拓展名 todo

            $nameDirSave = $this->configSvn['backup_base_path'];
            $nameFileSave = $_POST['filename'];
            $nameFileMd5 = $_POST['md5'];
            $nameFileCurrent = $_FILES['file']['tmp_name'];
            $numBlobCurrent = (int)$_POST['numBlobCurrent'];
            $numBlobTotal = (int)$_POST['numBlobTotal'];
            $deleteOnMerge = (int)$_POST['deleteOnMerge'] == 1;

            $endfix = strtolower(substr(strrchr($nameFileSave, '.'), 1));
            if (!in_array(strtolower($endfix), [
                'dump'
            ])) {
                return json1(200, 0, 'Để an toàn, vui lòng thay đổi hậu tố tệp sao lưu thành dump thử lại');
            }

            if (preg_match('/^[a-zA-Z0-9]+$/', $nameFileMd5, $matches) === false) {
                return message(200, 0, 'Giá trị md5 cần phải bao gồm các số và chữ hoa và chữ thường');
            }

            $upload = new Upload($nameDirTempSave, $nameDirSave, $nameFileSave, $nameFileMd5, $nameFileCurrent, $numBlobCurrent, $numBlobTotal, $deleteOnMerge);
            $upload->fileUpload();
            $result = $upload->message();

            return message(200, $result['status'], $result['message'], $result['data']);
        } else {
            return message(200, 0, 'Tham số không đầy đủ');
        }
    }

    /**
     * 从本地备份文件导入仓库
     */
    public function SvnadminLoad()
    {
        //检查备份文件是否存在
        if (!file_exists($this->configSvn['backup_base_path'] .  $this->payload['fileName'])) {
            return message(200, 0, 'tập tin sao lưu không tồn tại');
        }

        //检查操作的仓库是否存在
        clearstatcache();
        if (!is_dir($this->configSvn['rep_base_path'] . $this->payload['rep_name'])) {
            return message(200, 0, 'thư mục không tồn tại');
        }

        $task_unique = uniqid('task_svnadmin_load_');

        $task_log_file = $this->configSvn['log_base_path'] . $task_unique . '.log';

        $task_cmd = sprintf("'%s' load --quiet '%s' < '%s'", $this->configBin['svnadmin'], $this->configSvn['rep_base_path'] .  $this->payload['rep_name'], $this->configSvn['backup_base_path'] .  $this->payload['fileName'], $task_log_file);

        $this->database->insert('tasks', [
            'task_name' => sprintf('Kho [%s] nhập tệp sao lưu [%s]', $this->payload['rep_name'], $this->payload['fileName']),
            'task_status' => 1,
            'task_cmd' => $task_cmd,
            'task_type' => 'svnadmin:load',
            'task_unique' => $task_unique,
            'task_log_file' => $task_log_file,
            'task_optional' => '',
            'task_create_time' => date('Y-m-d H:i:s'),
            'task_update_time' => ''
        ]);

        //更新仓库的版本 todo

        return message(200, 1, 'Đã thêm thực thi tác vụ nền', [
            'task_unique' => $task_unique
        ]);
    }

    /**
     * 获取仓库的钩子和对应的内容列表
     */
    public function GetRepHooks()
    {
        //检查仓库是否存在
        clearstatcache();
        if (!is_dir($this->configSvn['rep_base_path'] . $this->payload['rep_name'])) {
            return message(200, 0, 'thư mục không tồn tại');
        }

        $repHooks =  [
            'start_commit' =>  [
                'fileName' => 'start-commit',
                'hasFile' => false,
                'con' => '',
                'tmpl' => ''
            ],
            'pre_commit' =>  [
                'fileName' => 'pre-commit',
                'hasFile' => false,
                'con' => '',
                'tmpl' => ''
            ],
            'post_commit' =>  [
                'fileName' => 'post-commit',
                'hasFile' => false,
                'con' => '',
                'tmpl' => ''
            ],
            'pre_lock' =>  [
                'fileName' => 'pre-lock',
                'hasFile' => false,
                'con' => '',
                'tmpl' => ''
            ],
            'post_lock' =>  [
                'fileName' => 'post-lock',
                'hasFile' => false,
                'con' => '',
                'tmpl' => ''
            ],
            'pre_unlock' =>  [
                'fileName' => 'pre-unlock',
                'hasFile' => false,
                'con' => '',
                'tmpl' => ''
            ],
            'post_unlock' =>  [
                'fileName' => 'post-unlock',
                'hasFile' => false,
                'con' => '',
                'tmpl' => ''
            ],
            'pre_revprop_change' =>  [
                'fileName' => 'pre-revprop-change',
                'hasFile' => false,
                'con' => '',
                'tmpl' => ''
            ],
            'post_revprop_change' =>  [
                'fileName' => 'post-revprop-change',
                'hasFile' => false,
                'con' => '',
                'tmpl' => ''
            ],
        ];

        $hooksPath = $this->configSvn['rep_base_path'] . $this->payload['rep_name'] . '/hooks/';

        clearstatcache();
        if (!is_dir($hooksPath)) {
            return message(200, 0, 'Kho không tồn tại thư mục hook');
        }

        foreach ($repHooks as $key => $value) {
            $hookFile = $hooksPath . $value['fileName'];
            $hookTmpleFile = $hookFile . '.tmpl';

            if (file_exists($hookFile)) {
                $repHooks[$key]['hasFile'] = true;

                if (!is_readable($hookFile)) {
                    return message(200, 0, 'tài liệu' . $hookFile . 'không thể đọc được');
                }
                $repHooks[$key]['con'] = file_get_contents($hookFile);
            }
            if (file_exists($hookTmpleFile)) {

                if (!is_readable($hookTmpleFile)) {
                    return message(200, 0, 'tài liệu' . $hookTmpleFile . 'không thể đọc được');
                }
                $repHooks[$key]['tmpl'] = file_get_contents($hookTmpleFile);
            }
        }

        return message(200, 1, 'thành công', $repHooks);
    }

    /**
     * 移除仓库钩子
     */
    public function DelRepHook()
    {
        clearstatcache();

        $hooksPath = $this->configSvn['rep_base_path'] . $this->payload['rep_name'] . '/hooks/';

        if (is_dir($hooksPath)) {
            if (!file_exists($hooksPath . $this->payload['fileName'])) {
                return message(200, 0, 'Hook thư mục lưu trữ đã bị xóa');
            }
        } else {
            return message(200, 0, 'thư mục không tồn tại');
        }

        funShellExec(sprintf("cd '%s' && rm -f ./'%s'", $hooksPath, $this->payload['fileName']));

        return message(200, 1, 'Đã xóa thành công');
    }

    /**
     * 修改仓库的某个钩子内容
     */
    public function UpdRepHook()
    {
        $hooksPath = $this->configSvn['rep_base_path'] . $this->payload['rep_name'] . '/hooks/';

        if (!is_writable($hooksPath)) {
            return message(200, 0, sprintf('tài liệu[%s]不可写', $hooksPath));
        }

        funFilePutContents($hooksPath . $this->payload['fileName'], $this->payload['content']);
        $result = funShellExec(sprintf("chmod +x '%s'", $hooksPath . $this->payload['fileName']));
        if ($result['code'] != 0) {
            return message(200, 0, $result['error']);
        }

        return message();
    }

    /**
     * 获取常用钩子列表
     */
    public function GetRecommendHooks()
    {
        clearstatcache();

        $list = [];

        $recommend_hook_path = $this->configSvn['recommend_hook_path'];
        clearstatcache();
        if (!is_dir($recommend_hook_path)) {
            return message(200, 0, 'Không có thư mục hook tùy chỉnh nào được tạo');
        }

        if (!is_readable($recommend_hook_path)) {
            return message(200, 0, 'Mục lục' . $recommend_hook_path . 'không thể đọc được');
        }
        $dirs = scandir($recommend_hook_path);

        foreach ($dirs as $dir) {
            clearstatcache();

            if ($dir == '.' || $dir == '..') {
                continue;
            }

            if (!is_dir($recommend_hook_path . $dir)) {
                continue;
            }

            if (!is_readable($recommend_hook_path . $dir)) {
                return message(200, 0, 'Mục lục' . $recommend_hook_path . $dir . 'không thể đọc được');
            }

            $dirFiles = scandir($recommend_hook_path . $dir);

            if (!in_array('hookDescription', $dirFiles) || !in_array('hookName', $dirFiles)) {
                continue;
            }

            if (!is_readable($recommend_hook_path . $dir . '/hookName')) {
                return message(200, 0, 'tài liệu' . $recommend_hook_path . $dir . '/hookName' . 'không thể đọc được');
            }
            $hookName = trim(file_get_contents($recommend_hook_path . $dir . '/hookName'));

            if (!file_exists($recommend_hook_path . $dir . '/' . trim($hookName))) {
                continue;
            }

            if (!is_readable($recommend_hook_path . $dir . '/' . $hookName)) {
                return message(200, 0, 'tài liệu' . $recommend_hook_path . $dir . '/' . $hookName . 'không thể đọc được');
            }
            $hookContent = trim(file_get_contents($recommend_hook_path . $dir . '/' . $hookName));

            if (!is_readable($recommend_hook_path . $dir . '/hookDescription')) {
                return message(200, 0, 'tài liệu' . $recommend_hook_path . $dir . '/hookDescription' . 'không thể đọc được');
            }
            $hookDescription = trim(file_get_contents($recommend_hook_path . $dir . '/hookDescription'));

            array_push($list, [
                'hookName' => $hookName,
                'hookContent' => $hookContent,
                'hookDescription' => $hookDescription
            ]);
        }

        return message(200, 1, '成功', $list);
    }

    /**
     * 获取简单SVN仓库列表
     */
    private function GetSimpleRepList()
    {
        $repArray = [];
        $file_arr = scandir($this->configSvn['rep_base_path']);
        foreach ($file_arr as $file_item) {
            clearstatcache();
            if ($file_item != '.' && $file_item != '..') {
                if (is_dir($this->configSvn['rep_base_path'] .  $file_item)) {
                    $file_arr2 = scandir($this->configSvn['rep_base_path'] .  $file_item);
                    foreach ($file_arr2 as $file_item2) {
                        if (($file_item2 == 'conf' || $file_item2 == 'db' || $file_item2 == 'hooks' || $file_item2 == 'locks')) {
                            array_push($repArray, $file_item);
                            break;
                        }
                    }
                }
            }
        }
        return $repArray;
    }

    /**
     * 初始化仓库结构为 trunk branches tags
     */
    private function InitRepStruct($templetePath, $repPath, $initUser = 'SVNAdmin', $initPass = 'SVNAdmin', $message = 'Initial structure')
    {
        $cmd = sprintf("'%s' import '%s' 'file:///%s' --quiet --username '%s' --password '%s' --message '%s'", $this->configBin['svn'], $templetePath, $repPath, $initUser, $initPass, $message);
        funShellExec($cmd);
    }

    /**
     * 获取仓库的修订版本数量
     * svnadmin info
     * 
     * Subversion 1.9 及以前没有 svnadmin info 子指令 
     * 因此使用 svnlook youngest 来代替
     */
    private function GetRepRev($repName)
    {
        // $cmd = sprintf("'%s' info '%s' | grep 'Revisions' | awk '{print $2}'", $this->configBin['svnadmin'], $this->configSvn['rep_base_path'] .  $repName);

        $cmd = sprintf("'%s' youngest '%s'", $this->configBin['svnlook'], $this->configSvn['rep_base_path'] .  $repName);

        $result = funShellExec($cmd);

        return (int)trim($result['result']);
    }

    /**
     * 获取仓库的UUID
     */
    private function GetRepUUID($repName)
    {
        $cmd = sprintf("'%s' uuid '%s'", $this->configBin['svnlook'], $this->configSvn['rep_base_path'] .  $repName);

        $result = funShellExec($cmd);

        return trim($result['result']);
    }

    /**
     * 获取仓库的属性内容（key-value的形式）
     * svnadmin info
     * 
     * Subversion 1.9 及以前没有 svnadmin info 子指令 
     */
    private function GetRepDetail110($repName)
    {
        $cmd = sprintf("'%s' info '%s'", $this->configBin['svnadmin'], $this->configSvn['rep_base_path'] .  $repName);
        $result = funShellExec($cmd);
        return $result;
    }

    /**
     * 获取仓库下某个文件的体积
     * 
     * 目前为默认最新版本
     * 
     * 根据体积大小自动调整单位
     * 
     * svnlook file
     */
    private function GetRepRevFileSize($repName, $filePath)
    {
        $cmd = sprintf("'%s' filesize '%s' '%s'", $this->configBin['svnlook'], $this->configSvn['rep_base_path'] . $repName, $filePath);
        $result = funShellExec($cmd);
        $size = (int)$result['result'];
        return funFormatSize($size);
    }

    /**
     * 获取仓库下指定文件或者文件夹的最高修订版本
     * 
     * svnlook history
     * 
     * 是否有必要做错误捕获 todo
     */
    private function GetRepFileRev($repName, $filePath)
    {
        $cmd = sprintf("'%s' history --limit 1 '%s' '%s'", $this->configBin['svnlook'], $this->configSvn['rep_base_path'] .  $repName, $filePath);
        $result = funShellExec($cmd);
        $result = $result['result'];
        $resultArray = explode("\n", $result);
        $content = preg_replace("/\s{2,}/", ' ', $resultArray[2]);
        $contentArray = explode(' ', $content);
        return trim($contentArray[1]);
    }

    /**
     * 获取仓库下指定文件或者文件夹的作者
     * 
     * svnlook author
     * 
     * 是否有必要做错误捕获 todo
     */
    private function GetRepFileAuthor($repName, $rev)
    {
        $cmd = sprintf("'%s' author -r %s '%s'", $this->configBin['svnlook'], $rev, $this->configSvn['rep_base_path'] .  $repName);
        $result = funShellExec($cmd);
        return $result['result'];
    }

    /**
     * 获取仓库下指定文件或者文件夹的提交日期
     * 
     * svnlook date
     * 
     * 是否有必要做错误捕获 todo
     */
    private function GetRepFileDate($repName, $rev)
    {
        $cmd = sprintf("'%s' date -r %s '%s'", $this->configBin['svnlook'], $rev, $this->configSvn['rep_base_path'] .  $repName);
        $result = funShellExec($cmd);
        return $result['result'];
    }

    /**
     * 获取仓库下指定文件或者文件夹的提交日志
     * 
     * svnlook log
     * 
     * 是否有必要做错误捕获 todo
     */
    private function GetRepFileLog($repName, $rev)
    {
        $cmd = sprintf("'%s' log -r %s '%s'", $this->configBin['svnlook'], $rev, $this->configSvn['rep_base_path'] .  $repName);
        $result = funShellExec($cmd);
        return $result['result'];
    }

    /**
     * 以SVN用户身份获取 svn list 的结果
     */
    private function GetSvnList($repPath, $repName)
    {
        if ($this->enableCheckout == 'svn') {
            $checkoutHost = 'svn://' . ($this->localSvnPort == 3690 ? $this->localSvnHost : $this->localSvnHost . ':' . $this->localSvnPort);

            $svnUserPass = $this->database->get('svn_users', 'svn_user_pass', [
                'svn_user_name' => $this->userName
            ]);
        } else {
            $checkoutHost = $this->localHttpProtocol . '://' . ($this->localHttpPort == 80 ? $this->localHttpHost : $this->localHttpHost . ':' . $this->localHttpPort) . ($this->httpPrefix == '/' ? '' : $this->httpPrefix);

            $svnUserPass = $this->database->get('svn_users', 'svn_user_pass', [
                'svn_user_name' => $this->userName
            ]);
        }

        if (empty($svnUserPass)) {
            return message(200, 0, 'Mật khẩu người dùng trống -không được đồng bộ hóa với hệ thống này');
        }

        $checkResult = $this->CheckSvnUserPathAutzh($checkoutHost, $repName, $repPath, $this->userName, $svnUserPass);
        if ($checkResult['status'] != 1) {
            return message($checkResult['code'], $checkResult['status'], $checkResult['message'], $checkResult['data']);
        }
        $result = $checkResult['data'];

        return message(200, 1, 'thành công', $result);
    }

    /**
     * svn list执行
     */
    private function CheckSvnUserPathAutzh($checkoutHost, $repName, $repPath, $svnUserName, $svnUserPass)
    {
        $cmd = sprintf("'%s' list '%s' --username '%s' --password '%s' --no-auth-cache --non-interactive --trust-server-cert", $this->configBin['svn'], $checkoutHost . '/' . $repName . $repPath, $svnUserName, $svnUserPass);
        $result = funShellExec($cmd);

        if ($result['code'] != 0) {
            //: Authentication error from server: Password incorrect
            if (strstr($result['error'], 'svn: E170001') && strstr($result['error'], 'Password incorrect')) {
                return ['code' => 200, 'status' => 0, 'message' => 'sai mật khẩu', 'data' => []];
            }
            //: Authorization failed
            if (strstr($result['error'], 'svn: E170001') && strstr($result['error'], 'Authorization failed')) {
                return ['code' => 200, 'status' => 0, 'message' => 'không truy cập', 'data' => []];
            }
            //svn: E170001类型的其它错误
            if (strstr($result['error'], 'svn: E170001')) {
                return ['code' => 200, 'status' => 0, 'message' => 'không có quyền truy cập -svn: E170001', 'data' => []];
            }
            //: Invalid authz configuration
            if (strstr($result['error'], 'svn: E220003')) {
                return ['code' => 200, 'status' => 0, 'message' => 'Cấu hình file authz sai, bạn dùng công cụ svnauthz-validate để kiểm tra nhé', 'data' => []];
            }
            //: Unable to connect to a repository at URL
            if (strstr($result['error'], 'svn: E170013')) {
                return ['code' => 200, 'status' => 0, 'message' => 'Không thể kết nối với thư mục lưu trữ hoặc không có quyền', 'data' => []];
            }
            //: Could not list all targets because some targets don't exist
            if (strstr($result['error'], 'svn: warning: W160013') || strstr($result['error'], "svn: E200009")) {
                return ['code' => 200, 'status' => 0, 'message' => 'Đường dẫn ủy quyền nằm trong thư mục không tồn tại Vui lòng làm mới để đồng bộ hóa', 'data' => []];
            }
            return ['code' => 200, 'status' => 0, 'message' => 'lỗi xác thực' . $result['error'], 'data' => []];
        }

        return ['code' => 200, 'status' => 1, 'message' => 'thành công', 'data' => $result['result']];
    }
}
