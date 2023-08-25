<?php
/*
 *@Tác giả: bibo318
 *
 *@LastEditors: bibo318
 *
 *@Mô tả: github: /bibo318
 */

namespace app\service;

//yêu cầu cấu hình
auto_require(BASE_PATH . '/config/');

//yêu cầu hàm
auto_require(BASE_PATH . '/app/function/');

//yêu cầu sử dụng
auto_require(BASE_PATH . '/app/util/');

//yêu cầu dịch vụ
auto_require(BASE_PATH . '/app/service/');

//yêu cầu mở rộng
auto_require(BASE_PATH . '/extension/Medoo-1.7.10/src/Medoo.php');

auto_require(BASE_PATH . '/extension/PHPMailer-6.6.0/src/Exception.php');
auto_require(BASE_PATH . '/extension/PHPMailer-6.6.0/src/PHPMailer.php');
auto_require(BASE_PATH . '/extension/PHPMailer-6.6.0/src/SMTP.php');
auto_require(BASE_PATH . '/extension/PHPMailer-6.6.0/language/phpmailer.lang-zh_cn.php');

auto_require(BASE_PATH . '/extension/Verifycode/Verifycode.php');

auto_require(BASE_PATH . '/extension/Witersen/SVNAdmin.php');

auto_require(BASE_PATH . '/extension/Witersen/File/Upload.php');

function auto_require($path, $recursively = false)
{
    if (is_file($path)) {
        if (substr($path, -4) == '.php') {
            require_once $path;
        }
    } else {
        $files = scandir($path);
        foreach ($files as $file) {
            if ($file != '.' && $file != '..') {
                if (is_dir($path . '/' . $file)) {
                    $recursively ? auto_require($path . '/' . $file, true) : '';
                } else {
                    if (substr($file, -4) == '.php') {
                        require_once $path . '/' . $file;
                    }
                }
            }
        }
    }
}

use Check;

use Config;
use Medoo\Medoo;
use Witersen\SVNAdmin;
use Witersen\Upload;

class Base
{
    public $token;

    //Thông tin người dùng thu được theo token
    public $userName;
    public $userRoleId;

    //file cấu hình svn
    public $authzContent;
    public $passwdContent;
    public $httpPasswdContent;
    public $svnserveContent;

    //medoo
    public $database;

    //thông tin cấu hình
    public $configBin;
    public $configSvn;
    public $configReg;
    public $configSign;

    //khối hàng
    public $payload;

    //SVNAdmin
    public $SVNAdmin;
    public $SVNAdminGroup;
    public $SVNAdminInfo;
    public $SVNAdminRep;
    public $SVNAdminUser;

    //nghiên cứu
    public $checkService;

    //chủ nhà
    public $dockerHost = '127.0.0.1';
    public $dockerSvnPort = 3690;
    public $dockerHttpPort = 80;

    //địa phương
    public $localSvnHost = '127.0.0.1';
    public $localSvnPort = 3690;
    public $localHttpHost = '127.0.0.1';
    public $localHttpPort = 80;
    public $localHttpProtocol = 'http';

    //Kích hoạt giao thức
    public $enableCheckout = '';

    //nguồn dữ liệu
    public $svnDataSource;
    public $httpDataSource;

    //http
    public $httpPrefix = '';

    /**
     *Cây quyền quản trị viên phụ
     *
     *mảng @var
     */
    public $subadminTree = [
        [
            'title' => 'background task',
            'expand' => false,
            'checked' => true,
            'disabled' => true,
            'necessary_functions' => [],
            'children' => [
                [
                    'title' => 'current task',
                    'expand' => false,
                    'checked' => true,
                    'disabled' => true,
                    'necessary_functions' => [],
                    'children' => [
                        [
                            'title' => 'Obtain real-time logs of background tasks',
                            'expand' => false,
                            'checked' => true,
                            'disabled' => true,
                            'necessary_functions' => [
                                'Tasks/GetTaskRun',
                            ],
                            'children' => []
                        ],
                    ]
                ],
                [
                    'title' => 'Queue tasks',
                    'expand' => false,
                    'checked' => true,
                    'disabled' => true,
                    'necessary_functions' => [],
                    'children' => [
                        [
                            'title' => 'Get background task queue',
                            'expand' => false,
                            'checked' => true,
                            'disabled' => true,
                            'necessary_functions' => [
                                'Tasks/GetTaskQueue',
                            ],
                            'children' => [
                                [
                                    'title' => 'stop background task',
                                    'expand' => false,
                                    'checked' => true,
                                    'disabled' => true,
                                    'necessary_functions' => [
                                        'Tasks/UpdTaskStop',
                                    ],
                                    'children' => []
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    'title' => 'Nhiệm vụ trong lịch sử',
                    'expand' => false,
                    'checked' => true,
                    'disabled' => true,
                    'necessary_functions' => [],
                    'children' => [
                        [
                            'title' => 'Get background task execution history',
                            'expand' => false,
                            'checked' => true,
                            'disabled' => true,
                            'necessary_functions' => [
                                'Tasks/GetTaskHistory',
                            ],
                            'children' => [
                                [
                                    'title' => 'Get historical task logs',
                                    'expand' => false,
                                    'checked' => true,
                                    'disabled' => true,
                                    'necessary_functions' => [
                                        'Tasks/GetTaskHistoryLog',
                                    ],
                                    'children' => []
                                ],
                                [
                                    'title' => 'Delete historical execution tasks',
                                    'expand' => false,
                                    'checked' => true,
                                    'disabled' => true,
                                    'necessary_functions' => [
                                        'Tasks/DelTaskHistory',
                                    ],
                                    'children' => []
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ],
        [
            'title' => 'Thống kê thông tin',
            'expand' => false,
            'checked' => false,
            'disabled' => false,
            'router_name' => 'index',
            'necessary_functions' => [
                'Statistics/GetLoadInfo',
                'Statistics/GetDiskInfo',
                'Statistics/GetStatisticsInfo',
            ],
            'children' => []
        ],
        [
            'title' => 'Thư mục lưu trữ',
            'expand' => false,
            'checked' => false,
            'disabled' => false,
            'router_name' => 'repositoryInfo',
            'necessary_functions' => [
                'Svnrep/GetRepList',
                'Svnrep/GetSvnserveStatus',
            ],
            'children' => [
                [
                    'title' => 'new warehouse',
                    'expand' => false,
                    'checked' => false,
                    'disabled' => false,
                    'necessary_functions' => [
                        'Svnrep/CreateRep',
                    ],
                    'children' => []
                ],
                [
                    'title' => 'authz detection',
                    'expand' => false,
                    'checked' => false,
                    'disabled' => false,
                    'necessary_functions' => [
                        'Svnrep/CheckAuthz',
                    ],
                    'children' => []
                ],
                [
                    'title' => 'Modification of Remarks',
                    'expand' => false,
                    'checked' => false,
                    'disabled' => false,
                    'necessary_functions' => [
                        'Svnrep/UpdRepNote',
                    ],
                    'children' => []
                ],
                [
                    'title' => 'Warehouse content browsing',
                    'expand' => false,
                    'checked' => false,
                    'disabled' => false,
                    'necessary_functions' => [
                        'Svnrep/GetCheckout',
                        'Svnrep/GetRepCon',
                    ],
                    'children' => []
                ],
                [
                    'title' => 'Warehouse backup management',
                    'expand' => false,
                    'checked' => false,
                    'disabled' => false,
                    'necessary_functions' => [],
                    'children' => [
                        [
                            'title' => 'Get a list of backup files',
                            'expand' => false,
                            'checked' => false,
                            'disabled' => true,
                            'necessary_functions' => [
                                'Svnrep/GetBackupList',
                            ],
                            'children' => []
                        ],
                        //[
                        //'title' => 'Tạo file sao lưu thư mục (svnadmin dump)',
                        //'mở rộng' => sai,
                        //'đã kiểm tra' => sai,
                        //'bị vô hiệu hóa' => đúng,
                        //'hàm_cần thiết' => [
                        //'Svnrep/SvnadminDump',
                        //],
                        //'trẻ em' => []
                        //],
                        [
                            'title' => 'Delete repository backup files',
                            'expand' => false,
                            'checked' => false,
                            'disabled' => true,
                            'necessary_functions' => [
                                'Svnrep/DelRepBackup',
                            ],
                            'children' => []
                        ],
                    ]
                ],
                [
                    'title' => 'Warehouse permission configuration',
                    'expand' => false,
                    'checked' => false,
                    'disabled' => false,
                    'necessary_functions' => [
                        'Svnrep/GetRepTree',
                        'Svnrep/GetRepPathAllPri',
                        'Svnrep/DelRepBackup',
                    ],
                    'children' => [
                        [
                            'title' => 'Warehouse authorization component',
                            'expand' => false,
                            'checked' => false,
                            'disabled' => true,
                            'necessary_functions' => [],
                            'children' => [
                                [
                                    'title' => 'Repository tree browsing(left side)',
                                    'expand' => false,
                                    'checked' => false,
                                    'disabled' => true,
                                    'necessary_functions' => [],
                                    'children' => [
                                        [
                                            'title' => 'Get the warehouse directory tree',
                                            'expand' => false,
                                            'checked' => false,
                                            'disabled' => true,
                                            'necessary_functions' => [
                                                'Svnrep/GetRepTree',
                                            ],
                                            'children' => []
                                        ],
                                        [
                                            'title' => 'Create folders online',
                                            'expand' => false,
                                            'checked' => false,
                                            'disabled' => true,
                                            'necessary_functions' => [
                                                'Svnrep/CreateRepFolder',
                                            ],
                                            'children' => []
                                        ]
                                    ]
                                ],
                                [
                                    'title' => 'Warehouse path authorization (right side)',
                                    'expand' => false,
                                    'checked' => false,
                                    'disabled' => true,
                                    'necessary_functions' => [],
                                    'children' => [
                                        [
                                            'title' => 'Increase the permissions under a warehouse path',
                                            'expand' => false,
                                            'checked' => false,
                                            'disabled' => true,
                                            'necessary_functions' => [
                                                'Svnrep/CreateRepPathPri',
                                            ],
                                            'children' => []
                                        ],
                                        [
                                            'title' => '获取某个仓库路径下的权限',
                                            'expand' => false,
                                            'checked' => false,
                                            'disabled' => true,
                                            'necessary_functions' => [
                                                'Svnrep/GetRepPathAllPri',
                                            ],
                                            'children' => []
                                        ],
                                        [
                                            'title' => 'Modify permissions under a warehouse path',
                                            'expand' => false,
                                            'checked' => false,
                                            'disabled' => true,
                                            'necessary_functions' => [
                                                'Svnrep/UpdRepPathPri',
                                            ],
                                            'children' => []
                                        ],
                                        [
                                            'title' => 'Delete permissions under a warehouse path',
                                            'expand' => false,
                                            'checked' => false,
                                            'disabled' => true,
                                            'necessary_functions' => [
                                                'Svnrep/DelRepPathPri',
                                            ],
                                            'children' => []
                                        ],
                                    ]
                                ],
                            ]
                        ],
                        [
                            'title' => 'object list component',
                            'expand' => false,
                            'checked' => false,
                            'disabled' => true,
                            'necessary_functions' => [],
                            'children' => [
                                [
                                    'title' => 'Get SVN user list',
                                    'expand' => false,
                                    'checked' => false,
                                    'disabled' => true,
                                    'necessary_functions' => [
                                        'Svnuser/GetUserList',
                                    ],
                                    'children' => []
                                ],
                                [
                                    'title' => 'Get SVN group list',
                                    'expand' => false,
                                    'checked' => false,
                                    'disabled' => true,
                                    'necessary_functions' => [
                                        'Svngroup/GetGroupList',
                                    ],
                                    'children' => [
                                        [
                                            'title' => 'Get SVN group members',
                                            'expand' => false,
                                            'checked' => false,
                                            'disabled' => true,
                                            'necessary_functions' => [
                                                'Svngroup/GetGroupMember',
                                            ],
                                            'children' => []
                                        ],
                                    ]
                                ],
                                [
                                    'title' => 'Get list of SVN aliases',
                                    'expand' => false,
                                    'checked' => false,
                                    'disabled' => true,
                                    'necessary_functions' => [
                                        'Svnaliase/GetAliaseList',
                                    ],
                                    'children' => []
                                ],
                            ]
                        ]
                    ]
                ],
                [
                    'title' => 'Warehouse hooksEdit',
                    'expand' => false,
                    'checked' => false,
                    'disabled' => false,
                    'necessary_functions' => [],
                    'children' => [
                        [
                            'title' => 'Get a list of repository hooks',
                            'expand' => false,
                            'checked' => false,
                            'disabled' => true,
                            'necessary_functions' => [
                                'Svnrep/GetRepHooks'
                            ],
                            'children' => []
                        ],
                        [
                            'title' => 'Get a list of commonly used hooks',
                            'expand' => false,
                            'checked' => false,
                            'disabled' => true,
                            'necessary_functions' => [
                                'Svnrep/GetRecommendHooks'
                            ],
                            'children' => []
                        ],
                        [
                            'title' => 'Modify the warehouse hook content',
                            'expand' => false,
                            'checked' => false,
                            'disabled' => true,
                            'necessary_functions' => [
                                'Svnrep/UpdRepHook'
                            ],
                            'children' => []
                        ],
                        [
                            'title' => 'Clear warehouse hook content',
                            'expand' => false,
                            'checked' => false,
                            'disabled' => true,
                            'necessary_functions' => [
                                'Svnrep/DelRepHook'
                            ],
                            'children' => []
                        ],
                    ]
                ],
                [
                    'title' => 'other',
                    'expand' => false,
                    'checked' => false,
                    'disabled' => false,
                    'necessary_functions' => [],
                    'children' => [
                        [
                            'title' => 'advanced',
                            'expand' => false,
                            'checked' => false,
                            'disabled' => false,
                            'necessary_functions' => [],
                            'children' => [
                                [
                                    'title' => 'warehouse properties',
                                    'expand' => false,
                                    'checked' => false,
                                    'disabled' => true,
                                    'necessary_functions' => [
                                        'Svnrep/GetRepDetail'
                                    ],
                                    'children' => []
                                ],
                                [
                                    'title' => 'Bản backup ',
                                    'expand' => false,
                                    'checked' => false,
                                    'disabled' => true,
                                    'necessary_functions' => [
                                        'Svnrep/GetRepHooks'
                                    ],
                                    'children' => [
                                        [
                                            'title' => 'Backup the repository now',
                                            'expand' => false,
                                            'checked' => false,
                                            'disabled' => true,
                                            'necessary_functions' => [
                                                'Svnrep/SvnadminDump'
                                            ],
                                            'children' => []
                                        ],
                                        [
                                            'title' => 'Get php file upload related parameters',
                                            'expand' => false,
                                            'checked' => false,
                                            'disabled' => true,
                                            'necessary_functions' => [
                                                'Svnrep/GetUploadInfo'
                                            ],
                                            'children' => []
                                        ],
                                        [
                                            'title' => 'Get a list of backup files',
                                            'expand' => false,
                                            'checked' => false,
                                            'disabled' => true,
                                            'necessary_functions' => [
                                                'Svnrep/GetBackupList'
                                            ],
                                            'children' => []
                                        ],
                                        [
                                            'title' => 'Upload files (upload backup files to the server)',
                                            'expand' => false,
                                            'checked' => false,
                                            'disabled' => true,
                                            'necessary_functions' => [
                                                'Svnrep/UploadBackup'
                                            ],
                                            'children' => []
                                        ],
                                        [
                                            'title' => 'Import Bản backup (svnadmin load)',
                                            'expand' => false,
                                            'checked' => false,
                                            'disabled' => true,
                                            'necessary_functions' => [
                                                'Svnrep/SvnadminLoad'
                                            ],
                                            'children' => []
                                        ],
                                    ]
                                ],
                            ]
                        ],
                        [
                            'title' => 'Modify (modify the warehouse name)',
                            'expand' => false,
                            'checked' => false,
                            'disabled' => false,
                            'necessary_functions' => [
                                'Svnrep/UpdRepName'
                            ],
                            'children' => []
                        ],
                        [
                            'title' => 'delete (remove repository)',
                            'expand' => false,
                            'checked' => false,
                            'disabled' => false,
                            'necessary_functions' => [
                                'Svnrep/DelRep'
                            ],
                            'children' => []
                        ],
                    ]
                ],
            ]
        ],
        [
            'title' => 'SVN user',
            'expand' => false,
            'checked' => false,
            'disabled' => false,
            'router_name' => 'repositoryUser',
            'necessary_functions' => [
                'Svnuser/GetUserList',
            ],
            'children' => [
                [
                    'title' => 'Create a new SVN user',
                    'expand' => false,
                    'checked' => false,
                    'disabled' => false,
                    'necessary_functions' => [
                        'Svnuser/CreateUser',
                    ],
                    'children' => []
                ],
                [
                    'title' => 'user migration',
                    'expand' => false,
                    'checked' => false,
                    'disabled' => false,
                    'necessary_functions' => [],
                    'children' => [
                        [
                            'title' => 'user identification',
                            'expand' => false,
                            'checked' => false,
                            'disabled' => true,
                            'necessary_functions' => [
                                'Svnuser/UserScan',
                            ],
                            'children' => []
                        ],
                        [
                            'title' => 'Confirm import',
                            'expand' => false,
                            'checked' => false,
                            'disabled' => true,
                            'necessary_functions' => [
                                'Svnuser/UserImport',
                            ],
                            'children' => []
                        ],
                    ]
                ],
                [
                    'title' => 'Deprecate or disable SVN users',
                    'expand' => false,
                    'checked' => false,
                    'disabled' => false,
                    'necessary_functions' => [
                        'Svnuser/UpdUserStatus',
                    ],
                    'children' => []
                ],
                [
                    'title' => 'Modifying SVN User Remarks',
                    'expand' => false,
                    'checked' => false,
                    'disabled' => false,
                    'necessary_functions' => [
                        'Svnuser/UpdUserNote',
                    ],
                    'children' => []
                ],
                [
                    'title' => 'right path',
                    'expand' => false,
                    'checked' => false,
                    'disabled' => false,
                    'necessary_functions' => [],
                    'children' => [
                        [
                            'title' => 'Check',
                            'expand' => false,
                            'checked' => false,
                            'disabled' => false,
                            'necessary_functions' => [
                                'Svnrep/GetSvnUserRepList2',
                            ],
                            'children' => []
                        ],
                        [
                            'title' => 'secondary authorization',
                            'expand' => false,
                            'checked' => false,
                            'disabled' => false,
                            'necessary_functions' => [],
                            'children' => [
                                [
                                    'title' => 'Secondary Authorization Status',
                                    'expand' => false,
                                    'checked' => false,
                                    'disabled' => true,
                                    'necessary_functions' => [
                                        'Secondpri/UpdSecondpri',
                                    ],
                                    'children' => []
                                ],
                                [
                                    'title' => 'secondary authorization object',
                                    'expand' => false,
                                    'checked' => false,
                                    'disabled' => true,
                                    'necessary_functions' => [
                                        'Secondpri/GetSecondpriObjectList',
                                    ],
                                    'children' => [
                                        [
                                            'title' => 'add members',
                                            'expand' => false,
                                            'checked' => false,
                                            'disabled' => true,
                                            'necessary_functions' => [
                                                'Secondpri/CreateSecondpriObject',
                                            ],
                                            'children' => []
                                        ],
                                        [
                                            'title' => 'remove member',
                                            'expand' => false,
                                            'checked' => false,
                                            'disabled' => true,
                                            'necessary_functions' => [
                                                'Secondpri/DelSecondpriObject',
                                            ],
                                            'children' => []
                                        ],
                                        [
                                            'title' => 'object list component',
                                            'expand' => false,
                                            'checked' => false,
                                            'disabled' => true,
                                            'necessary_functions' => [],
                                            'children' => [
                                                [
                                                    'title' => 'Get SVN user list',
                                                    'expand' => false,
                                                    'checked' => false,
                                                    'disabled' => true,
                                                    'necessary_functions' => [
                                                        'Svnuser/GetUserList',
                                                    ],
                                                    'children' => []
                                                ],
                                                [
                                                    'title' => 'Get SVN group list',
                                                    'expand' => false,
                                                    'checked' => false,
                                                    'disabled' => true,
                                                    'necessary_functions' => [
                                                        'Svngroup/GetGroupList',
                                                    ],
                                                    'children' => [
                                                        [
                                                            'title' => 'Get SVN group members',
                                                            'expand' => false,
                                                            'checked' => false,
                                                            'disabled' => true,
                                                            'necessary_functions' => [
                                                                'Svngroup/GetGroupMember',
                                                            ],
                                                            'children' => []
                                                        ],
                                                    ]
                                                ],
                                                [
                                                    'title' => 'Get list of SVN aliases',
                                                    'expand' => false,
                                                    'checked' => false,
                                                    'disabled' => true,
                                                    'necessary_functions' => [
                                                        'Svnaliase/GetAliaseList',
                                                    ],
                                                    'children' => []
                                                ],
                                            ]
                                        ]
                                    ]
                                ],
                            ]
                        ],
                    ]
                ],
                [
                    'title' => 'Modify the SVN user password',
                    'expand' => false,
                    'checked' => false,
                    'disabled' => false,
                    'necessary_functions' => [
                        'Svnuser/UpdUserPass',
                    ],
                    'children' => []
                ],
                [
                    'title' => 'Delete SVN user',
                    'expand' => false,
                    'checked' => false,
                    'disabled' => false,
                    'necessary_functions' => [
                        'Svnuser/DelUser',
                    ],
                    'children' => []
                ],
            ]
        ],
        [
            'title' => 'Nhóm SVN',
            'expand' => false,
            'checked' => false,
            'disabled' => false,
            'router_name' => 'repositoryGroup',
            'necessary_functions' => [
                'Svngroup/GetGroupList'
            ],
            'children' => [
                [
                    'title' => 'Create a new SVN group',
                    'expand' => false,
                    'checked' => false,
                    'disabled' => false,
                    'necessary_functions' => [
                        'Svngroup/CreateGroup',
                    ],
                    'children' => []
                ],
                [
                    'title' => 'Remarks',
                    'expand' => false,
                    'checked' => false,
                    'disabled' => false,
                    'necessary_functions' => [
                        'Svngroup/UpdGroupNote',
                    ],
                    'children' => []
                ],
                [
                    'title' => 'member',
                    'expand' => false,
                    'checked' => false,
                    'disabled' => false,
                    'necessary_functions' => [
                        'Svngroup/CreateGroup',
                    ],
                    'children' => [
                        [
                            'title' => 'Get a list of group members',
                            'expand' => false,
                            'checked' => false,
                            'disabled' => true,
                            'necessary_functions' => [
                                'Svngroup/CreateGroup',
                            ],
                            'children' => []
                        ],
                        [
                            'title' => 'Add or remove group members',
                            'expand' => false,
                            'checked' => false,
                            'disabled' => true,
                            'necessary_functions' => [
                                'Svngroup/UpdGroupMember',
                            ],
                            'children' => []
                        ],
                        [
                            'title' => 'object list component',
                            'expand' => false,
                            'checked' => false,
                            'disabled' => true,
                            'necessary_functions' => [],
                            'children' => [
                                [
                                    'title' => 'Get SVN user list',
                                    'expand' => false,
                                    'checked' => false,
                                    'disabled' => true,
                                    'necessary_functions' => [
                                        'Svnuser/GetUserList',
                                    ],
                                    'children' => []
                                ],
                                [
                                    'title' => 'Get SVN group list',
                                    'expand' => false,
                                    'checked' => false,
                                    'disabled' => true,
                                    'necessary_functions' => [
                                        'Svngroup/GetGroupList',
                                    ],
                                    'children' => [
                                        [
                                            'title' => 'Get SVN group members',
                                            'expand' => false,
                                            'checked' => false,
                                            'disabled' => true,
                                            'necessary_functions' => [
                                                'Svngroup/GetGroupMember',
                                            ],
                                            'children' => []
                                        ],
                                    ]
                                ],
                                [
                                    'title' => 'Get list of SVN aliases',
                                    'expand' => false,
                                    'checked' => false,
                                    'disabled' => true,
                                    'necessary_functions' => [
                                        'Svnaliase/GetAliaseList',
                                    ],
                                    'children' => []
                                ],
                            ]
                        ]
                    ]
                ],
                [
                    'title' => 'edit',
                    'expand' => false,
                    'checked' => false,
                    'disabled' => false,
                    'necessary_functions' => [
                        'Svngroup/UpdGroupName',
                    ],
                    'children' => []
                ],
                [
                    'title' => 'delete',
                    'expand' => false,
                    'checked' => false,
                    'disabled' => false,
                    'necessary_functions' => [
                        'Svngroup/DelGroup',
                    ],
                    'children' => []
                ],
            ]
        ],
        [
            'title' => 'Nhật ký hệ thống',
            'expand' => false,
            'checked' => false,
            'disabled' => false,
            'router_name' => 'logs',
            'necessary_functions' => [],
            'children' => [
                [
                    'title' => 'Get log list',
                    'expand' => false,
                    'checked' => false,
                    'disabled' => false,
                    'router_name' => 'Logs',
                    'necessary_functions' => [
                        'Logs/GetLogList',
                    ],
                    'children' => []
                ],
                [
                    'title' => 'clear log',
                    'expand' => false,
                    'checked' => false,
                    'disabled' => false,
                    'router_name' => 'Logs',
                    'necessary_functions' => [
                        'Logs/DelLogs',
                    ],
                    'children' => []
                ],
            ]
        ],
        [
            'title' => 'mission plan',
            'expand' => false,
            'checked' => false,
            'disabled' => false,
            'router_name' => 'crond',
            'necessary_functions' => [
                'Crond/GetCronStatus',
                'Crond/GetCrontabList',
                'Crond/GetRepList'
            ],
            'children' => [
                [
                    'title' => 'Add task plan',
                    'expand' => false,
                    'checked' => false,
                    'disabled' => false,
                    'router_name' => 'Crond',
                    'necessary_functions' => [
                        'Crond/CreateCrontab',
                    ],
                    'children' => []
                ],
                [
                    'title' => 'Enable or disable scheduled tasks',
                    'expand' => false,
                    'checked' => false,
                    'disabled' => false,
                    'router_name' => 'Crond',
                    'necessary_functions' => [
                        'Crond/UpdCrontabStatus',
                    ],
                    'children' => []
                ],
                [
                    'title' => 'other',
                    'expand' => false,
                    'checked' => false,
                    'disabled' => false,
                    'router_name' => 'Crond',
                    'necessary_functions' => [],
                    'children' => [
                        [
                            'title' => 'Log (view task plan execution log)',
                            'expand' => false,
                            'checked' => false,
                            'disabled' => false,
                            'router_name' => 'Crond',
                            'necessary_functions' => [
                                'Crond/GetCrontabLog',
                            ],
                            'children' => []
                        ],
                        [
                            'title' => 'Edit (edit task plan)',
                            'expand' => false,
                            'checked' => false,
                            'disabled' => false,
                            'router_name' => 'Crond',
                            'necessary_functions' => [
                                'Crond/UpdCrontab',
                            ],
                            'children' => []
                        ],
                        [
                            'title' => 'delete (delete task plan)',
                            'expand' => false,
                            'checked' => false,
                            'disabled' => false,
                            'router_name' => 'Crond',
                            'necessary_functions' => [
                                'Crond/DelCrontab',
                            ],
                            'children' => []
                        ],
                        [
                            'title' => 'Execute (immediately execute a task plan)',
                            'expand' => false,
                            'checked' => false,
                            'disabled' => false,
                            'router_name' => 'Crond',
                            'necessary_functions' => [
                                'Crond/TriggerCrontab',
                            ],
                            'children' => []
                        ],
                    ]
                ],
            ]
        ],
        [
            'title' => 'Quản lý thư mục admin ',
            'expand' => false,
            'checked' => true,
            'disabled' => true,
            'router_name' => 'personal',
            'necessary_functions' => [
                'Personal/UpdSubadminUserPass',
                'Setting/CheckUpdate',
                'Common/Logout'
            ],
            'children' => []
        ],
        [
            'title' => 'Cấu hình hệ thống',
            'expand' => false,
            'checked' => false,
            'disabled' => false,
            'router_name' => 'setting',
            'necessary_functions' => [],
            'children' => [
                [
                    'title' => 'host configuration',
                    'expand' => false,
                    'checked' => false,
                    'disabled' => true,
                    'necessary_functions' => [
                        'Setting/GetDcokerHostInfo',
                        'Setting/UpdDockerHostInfo',
                    ],
                    'children' => []
                ],
                [
                    'title' => 'path information',
                    'expand' => false,
                    'checked' => false,
                    'disabled' => true,
                    'necessary_functions' => [
                        'Setting/GetDirInfo'
                    ],
                    'children' => []
                ],
                [
                    'title' => 'svn protocol checkout',
                    'expand' => false,
                    'checked' => false,
                    'disabled' => true,
                    'necessary_functions' => [
                        'Setting/GetSvnInfo',

                        'Setting/UpdSvnEnable',

                        'Setting/UpdSvnserveStatusStop',
                        'Setting/UpdSvnserveStatusStart',

                        'Setting/UpdSvnservePort',
                        'Setting/UpdSvnserveHost',

                        'Setting/UpdSaslStatusStart',
                        'Setting/UpdSaslStatusStop',

                        'Setting/LdapTest',
                        'Setting/UpdSvnUsersource'
                    ],
                    'children' => []
                ],
                [
                    'title' => 'http protocol checkout',
                    'expand' => false,
                    'checked' => false,
                    'disabled' => true,
                    'necessary_functions' => [
                        'Setting/GetApacheInfo',

                        'Setting/UpdSubversionEnable',

                        'Setting/UpdHttpPort',
                        'Setting/UpdHttpPrefix',

                        'Setting/LdapTest',
                        'Setting/UpdHttpUsersource'
                    ],
                    'children' => []
                ],
                [
                    'title' => 'mail service',
                    'expand' => false,
                    'checked' => false,
                    'disabled' => true,
                    'necessary_functions' => [
                        'Setting/GetMailInfo',
                        'Setting/GetMailPushInfo',
                        'Setting/SendMailTest',
                        'Setting/UpdMailInfo'
                    ],
                    'children' => []
                ],
                [
                    'title' => 'message push',
                    'expand' => false,
                    'checked' => false,
                    'disabled' => true,
                    'necessary_functions' => [
                        'Setting/GetMailPushInfo',
                        'Setting/UpdPushInfo'
                    ],
                    'children' => []
                ],
                [
                    'title' => 'security configuration',
                    'expand' => false,
                    'checked' => false,
                    'disabled' => true,
                    'necessary_functions' => [
                        'Setting/GetSafeInfo',
                        'Setting/UpdSafeInfo'
                    ],
                    'children' => []
                ],
                [
                    'title' => 'system update',
                    'expand' => false,
                    'checked' => false,
                    'disabled' => true,
                    'necessary_functions' => [
                        'Setting/CheckUpdate'
                    ],
                    'children' => []
                ],
            ]
        ],
    ];

    /**
     *Định tuyến tất cả vai trò
     *
     *Mảng @var
     */
    public $route = [
        'name' => 'manage',
        'path' => '/',
        'redirect' => [
            'name' => 'login'
        ],
        'meta' => [
            'title' => 'SVNAdmin',
            'requireAuth' => false,
        ],
        'component' => 'layout/basicLayout/index.vue',
        'children' => [
            [
                'name' => 'index',
                'path' => '/index',
                'meta' => [
                    'title' => 'Thống kê thông tin',
                    'icon' => 'ios-stats',
                    'requireAuth' => true,
                    'user_role_id' => [1, 3],
                    'group' => [
                        'name' => 'Docs-BLT',
                        'num' => 1
                    ],
                    'id' => 1001
                ],
                'component' => 'index/index.vue'
            ],
            [
                'name' => 'repositoryInfo',
                'path' => '/repositoryInfo',
                'meta' => [
                    'title' => 'Thư mục lưu trữ',
                    'icon' => 'logo-buffer',
                    'requireAuth' => true,
                    'user_role_id' => [1, 2, 3],
                    'group' => [
                        'name' => 'Docs-BLT',
                        'num' => 1
                    ],
                    'id' => 1002
                ],
                'component' => 'repositoryInfo/index.vue'
            ],
            [
                'name' => 'repositoryUser',
                'path' => '/repositoryUser',
                'meta' => [
                    'title' => 'Người dùng SVN',
                    'icon' => 'md-person',
                    'requireAuth' => true,
                    'user_role_id' => [1, 3],
                    'group' => [
                        'name' => 'Docs-BLT',
                        'num' => 1
                    ],
                    'id' => 1003
                ],
                'component' => 'repositoryUser/index.vue'
            ],
            [
                'name' => 'repositoryGroup',
                'path' => '/repositoryGroup',
                'meta' => [
                    'title' => 'Nhóm SVN',
                    'icon' => 'md-people',
                    'requireAuth' => true,
                    'user_role_id' => [1, 3],
                    'group' => [
                        'name' => 'Docs-BLT',
                        'num' => 1
                    ],
                    'id' => 1004
                ],
                'component' => 'repositoryGroup/index.vue'
            ],
            [
                'name' => 'logs',
                'path' => '/logs',
                'meta' => [
                    'title' => 'Nhật ký hệ thống',
                    'icon' => 'md-bug',
                    'requireAuth' => true,
                    'user_role_id' => [1, 3],
                    'group' => [
                        'name' => 'Bảo trì',
                        'num' => 2
                    ],
                    'id' => 1005
                ],
                'component' => 'logs/index.vue'
            ],
            [
                'name' => 'crond',
                'path' => '/crond',
                'meta' => [
                    'title' => 'Backup',
                    'icon' => 'ios-alarm',
                    'requireAuth' => true,
                    'user_role_id' => [1, 3],
                    'group' => [
                        'name' => 'Bảo trì',
                        'num' => 2
                    ],
                    'id' => 1006
                ],
                'component' => 'crond/index.vue'
            ],
            [
                'name' => 'personal',
                'path' => '/personal',
                'meta' => [
                    'title' => 'Quản lý thư mục admin ',
                    'icon' => 'md-cube',
                    'requireAuth' => true,
                    'user_role_id' => [1, 2, 3],
                    'group' => [
                        'name' => 'advanced',
                        'num' => 3
                    ],
                    'id' => 1007
                ],
                'component' => 'personal/index.vue'
            ],
            [
                'name' => 'subadmin',
                'path' => '/subadmin',
                'meta' => [
                    'title' => 'Quản trị viên phụ',
                    'icon' => 'md-hand',
                    'requireAuth' => true,
                    'user_role_id' => [1],
                    'group' => [
                        'name' => 'advanced',
                        'num' => 3
                    ],
                    'id' => 1008
                ],
                'component' => 'subadmin/index.vue'
            ],
            [
                'name' => 'setting',
                'path' => '/setting',
                'meta' => [
                    'title' => 'Cấu hình hệ thống',
                    'icon' => 'md-settings',
                    'requireAuth' => true,
                    'user_role_id' => [1, 3],
                    'group' => [
                        'name' => 'advanced',
                        'num' => 3
                    ],
                    'id' => 1009
                ],
                'component' => 'setting/index.vue'
            ],
        ]
    ];

    function __construct($parm)
    {
        //thông tin cấu hình
        $this->configBin =  Config::get('bin');                       //đường dẫn file thực thi
        $this->configSvn = Config::get('svn');                        //nhà thư mục
        $this->configReg = Config::get('reg');                        //Thường xuyên
        $this->configSign = Config::get('sign');                      //chìa khóa

        $this->token = isset($parm['token']) ? $parm['token'] : '';

        global $database;
        if ($database) {
            $this->database = $database;
        } else {
            $configDatabase = Config::get('database');
            $configSvn = Config::get('svn');
            if (array_key_exists('database_file', $configDatabase)) {
                $configDatabase['database_file'] = sprintf($configDatabase['database_file'], $configSvn['home_path']);
            }
            try {
                $this->database = new Medoo($configDatabase);
            } catch (\Exception $e) {
                json1(200, 0, $e->getMessage());
            }
        }

        /**
         *1. Thu thập thông tin người dùng
         */
        if (empty($this->token)) {
            $this->userRoleId = isset($parm['payload']['userRoleId']) ? $parm['payload']['userRoleId'] : 0;
            $this->userName = isset($parm['payload']['userName']) ? $parm['payload']['userName'] : 0;
        } else {
            $array = explode($this->configSign['signSeparator'], $this->token);
            $this->userRoleId = $array[0];
            $this->userName = $array[1];
        }

        /**
         *2. Lấy thông tin file cấu hình authz và passwd
         */
        $this->authzContent = file_get_contents($this->configSvn['svn_authz_file']);
        $this->passwdContent = file_get_contents($this->configSvn['svn_passwd_file']);
        $this->httpPasswdContent = file_get_contents($this->configSvn['http_passwd_file']);
        $this->svnserveContent = file_get_contents($this->configSvn['svn_conf_file']);

        /**
         *3. Nhận tải trọng
         */
        $this->payload = isset($parm['payload']) ? $parm['payload'] : [];

        /**
         *4. đối tượng svnadmin
         */
        $this->SVNAdmin = new SVNAdmin();

        /**
         *5. Đối tượng kiểm tra
         */
        $this->checkService = new Check($this->configReg);

        /**
         *6. Thông tin máy chủ
         */
        $dockerHost = $this->database->get('options', [
            'option_id',
            'option_value'
        ], [
            'option_name' => '24_docker_host'
        ]);
        if (empty($dockerHost)) {
            $this->database->insert('options', [
                'option_value' => serialize([
                    'docker_host' => '127.0.0.1',
                    'docker_svn_port' => 3690,
                    'docker_http_port' => 80
                ]),
                'option_name' => '24_docker_host',
            ]);

            $this->dockerHost = '127.0.0.1';
            $this->dockerSvnPort = 3690;
            $this->dockerHttpPort = 80;
        } else {
            $dockerHost = unserialize($dockerHost['option_value']);

            $this->dockerHost = $dockerHost['docker_host'];
            $this->dockerSvnPort = $dockerHost['docker_svn_port'];
            $this->dockerHttpPort = $dockerHost['docker_http_port'];
        }

        /**
         *7. Thông tin địa phương
         */
        if (preg_match('/--listen-port[\s]+([0-9]+)/', file_get_contents($this->configSvn['svnserve_env_file']), $portMatchs)) {
            $this->localSvnPort = (int)trim($portMatchs[1]);
        }

        if (preg_match('/--listen-host[\s]+([\S]+)\b/', file_get_contents($this->configSvn['svnserve_env_file']), $hostMatchs)) {
            $this->localSvnHost = trim($hostMatchs[1]);
        }

        $this->localHttpHost = '127.0.0.1';

        $localHost = $this->database->get('options', [
            'option_id',
            'option_value'
        ], [
            'option_name' => '24_local_host'
        ]);
        if (empty($localHost)) {
            $this->database->insert('options', [
                'option_value' => serialize([
                    'local_http_port' => 80
                ]),
                'option_name' => '24_local_host',
            ]);

            $this->localHttpPort = 80;
        } else {
            $localHost = unserialize($localHost['option_value']);

            $this->localHttpPort = $localHost['local_http_port'];
        }

        /**
         *8. Giao thức hiện được kích hoạt
         */
        $this->enableCheckout = $this->database->get('options', [
            'option_id',
            'option_value'
        ], [
            'option_name' => '24_enable_checkout'
        ]);
        if (empty($this->enableCheckout)) {
            $this->database->insert('options', [
                'option_value' => 'svn',
                'option_name' => '24_enable_checkout',
            ]);

            $this->enableCheckout = 'svn';
        } else {
            $this->enableCheckout = $this->enableCheckout['option_value'];
        }


        /**
         *9. Nguồn dữ liệu
         */
        //svn
        $result = $this->database->get('options', [
            'option_id',
            'option_value'
        ], [
            'option_name' => '24_svn_datasource'
        ]);
        if (empty($result)) {
            $this->svnDataSource = [
                'user_source' => 'passwd',
                'group_source' => 'authz',
                'ldap' => [
                    //máy chủ ldap
                    'ldap_host' => 'ldap://127.0.0.1/',
                    'ldap_port' => 389,
                    'ldap_version' => 3,
                    'ldap_bind_dn' => '',
                    'ldap_bind_password' => '',

                    //liên quan đến người dùng
                    'user_base_dn' => '',
                    'user_search_filter' => '',
                    'user_attributes' => '',

                    //liên quan đến nhóm
                    'group_base_dn' => '',
                    'group_search_filter' => '',
                    'group_attributes' => '',
                    'groups_to_user_attribute' => '',
                    'groups_to_user_attribute_value' => ''
                ]
            ];
            $this->database->insert('options', [
                'option_name' => '24_svn_datasource',
                'option_value' => serialize($this->svnDataSource)
            ]);
        } else {
            $this->svnDataSource = unserialize($result['option_value']);
        }

        //http
        $result = $this->database->get('options', [
            'option_id',
            'option_value'
        ], [
            'option_name' => '24_http_datasource'
        ]);
        if (empty($result)) {
            $this->httpDataSource = [
                'user_source' => 'httpPasswd',
                'group_source' => 'authz',
                'ldap' => [
                    //máy chủ ldap
                    'ldap_host' => 'ldap://127.0.0.1/',
                    'ldap_port' => 389,
                    'ldap_version' => 3,
                    'ldap_bind_dn' => '',
                    'ldap_bind_password' => '',

                    //liên quan đến người dùng
                    'user_base_dn' => '',
                    'user_search_filter' => '',
                    'user_attributes' => '',

                    //liên quan đến nhóm
                    'group_base_dn' => '',
                    'group_search_filter' => '',
                    'group_attributes' => '',
                    'groups_to_user_attribute' => '',
                    'groups_to_user_attribute_value' => ''
                ]
            ];
            $this->database->insert('options', [
                'option_name' => '24_http_datasource',
                'option_value' => serialize($this->httpDataSource)
            ]);
        } else {
            $this->httpDataSource = unserialize($result['option_value']);
        }

        /**
         *10. Tiền tố thư mục truy cập http
         */
        $this->httpPrefix = $this->database->get('options', [
            'option_id',
            'option_value'
        ], [
            'option_name' => '24_http_prefix'
        ]);
        if (empty($this->httpPrefix)) {
            $this->database->insert('options', [
                'option_value' => '/svn',
                'option_name' => '24_http_prefix',
            ]);

            $this->httpPrefix = '/svn';
        } else {
            $this->httpPrefix = $this->httpPrefix['option_value'];
        }
    }

    /**
     *Nhận định tuyến động
     */
    public function GetDynamicRouting($userName, $userRole)
    {
        $route = $this->route;

        $functions = [];

        //Lọc các tuyến theo cây quyền (quản trị viên phụ)
        if ($userRole == 3) {
            $routerNames = array_column($route['children'], 'name');
            $subadminTree = $this->database->get('subadmin', 'subadmin_tree', [
                'subadmin_name' => $userName,
            ]);
            $subadminTree = json_decode($subadminTree, true);
            $subadminTree = empty($subadminTree) ? [] : $subadminTree;
            foreach ($subadminTree as $node) {
                $temp1 = [];
                //Nút cha đã được kiểm tra đầy đủ
                if ($node['checked']) {
                    $temp1 = array_merge($temp1, $node['necessary_functions']);
                    $temp1 = array_merge($temp1, $this->GetPriFunctions($node['children']));
                } else {
                    //Nút cha không được kiểm tra nhưng nút con được kiểm tra một phần
                    $temp2 = $this->GetPriFunctions($node['children']);
                    if (!empty($temp2)) {
                        $temp1 = array_merge($temp1, $node['necessary_functions']);
                        $temp1 = array_merge($temp1, $temp2);
                    }
                }

                if (empty($temp1)) {
                    if (($index = array_search($node['router_name'], $routerNames)) !== false) {
                        unset($route['children'][$index]);
                    }
                    continue;
                }
                $functions = array_merge($functions, $temp1);
            }
        }

        //Lọc các tuyến dựa trên giá trị meta (người dùng SVN)
        foreach ($route['children'] as $key => $value) {
            if (!in_array($userRole, $value['meta']['user_role_id'])) {
                unset($route['children'][$key]);
            }
        }

        $route['children'] = array_values($route['children']);

        if ($userRole == 1) {
            $functions = $this->GetPriFunctions($this->subadminTree);
        }

        return [
            'route' => $route,
            'functions' => $functions
        ];
    }

    /**
     *Nhận đúng chức năng
     *
     *Nếu nút con có giá trị thì giá trị của nút cha sẽ được hợp nhất
     */
    public function GetPriFunctions($tree)
    {
        if (empty($tree)) {
            return [];
        }

        $functions = [];
        foreach ($tree as $node) {
            //Nút cha đã được kiểm tra đầy đủ
            if ($node['checked']) {
                $functions = array_merge($functions, $node['necessary_functions']);
                $functions = array_merge($functions, $this->GetPriFunctions($node['children']));
            } else {
                //Nút cha không được kiểm tra nhưng nút con được kiểm tra một phần
                $temp = $this->GetPriFunctions($node['children']);
                if (!empty($temp)) {
                    $functions = array_merge($functions, $node['necessary_functions']);
                    $functions = array_merge($functions, $temp);
                }
            }
        }

        return $functions;
    }

    /**
     *Đọc lại giá trị của authz
     *
     *@return void
     */
    public function RereadAuthz()
    {
        $this->authzContent = file_get_contents($this->configSvn['svn_authz_file']);
    }

    /**
     *Đọc lại giá trị của passwd
     *
     *@return void
     */
    public function RereadPasswd()
    {
        $this->passwdContent = file_get_contents($this->configSvn['svn_passwd_file']);
    }

    /**
     *Đọc lại giá trị của httPpasswd
     *
     *@return void
     */
    public function RereadHttpPasswd()
    {
        $this->httpPasswdContent = file_get_contents($this->configSvn['http_passwd_file']);
    }

    /**
     *Đọc lại tệp biến môi trường svnserve
     *
     *@return void
     */
    public function RereadSvnserve()
    {
        if (preg_match('/--listen-port[\s]+([0-9]+)/', file_get_contents($this->configSvn['svnserve_env_file']), $portMatchs)) {
            $this->localSvnPort = (int)trim($portMatchs[1]);
        }
        if (preg_match('/--listen-host[\s]+([\S]+)\b/', file_get_contents($this->configSvn['svnserve_env_file']), $hostMatchs)) {
            $this->localSvnHost = trim($hostMatchs[1]);
        }
    }

    /**
     *Đọc lại nguồn dữ liệu
     *
     *@return void
     */
    public function ReloadDatasource()
    {
        //svn
        $result = $this->database->get('options', [
            'option_id',
            'option_value'
        ], [
            'option_name' => '24_svn_datasource'
        ]);

        $this->svnDataSource = unserialize($result['option_value']);

        //http
        $result = $this->database->get('options', [
            'option_id',
            'option_value'
        ], [
            'option_name' => '24_http_datasource'
        ]);

        $this->httpDataSource = unserialize($result['option_value']);
    }
}
