<?php
/*
 *@Tác giả: bibo318
 *
 *@LastEditors: bibo318
 *
 *@Mô tả: github: /bibo318
 */

namespace app\service;

use app\service\Logs as ServiceLogs;
use app\service\Ldap as ServiceLdap;
use app\service\Apache as ServiceApache;

class Svnuser extends Base
{
    /**
     *Các đối tượng lớp dịch vụ khác
     *
     *Đối tượng @var
     */
    private $ServiceLogs;
    private $ServiceLdap;
    private $ServiceApache;

    function __construct($parm = [])
    {
        parent::__construct($parm);

        $this->ServiceLogs = new ServiceLogs($parm);
        $this->ServiceLdap = new ServiceLdap($parm);
        $this->ServiceApache = new ServiceApache($parm);
    }

    /**
     *Thực hiện đồng bộ hóa
     *
     *@return mảng
     */
    public function SyncUser()
    {
        if ($this->enableCheckout == 'svn') {
            $dataSource = $this->svnDataSource;
        } else {
            $dataSource = $this->httpDataSource;
        }

        if ($dataSource['user_source'] == 'ldap') {
            $result = $this->SyncLdapToDb();
            if ($result['status'] != 1) {
                return message($result['code'], $result['status'], $result['message'], $result['data']);
            }
        } elseif ($this->enableCheckout == 'svn') {
            $result = $this->SyncPasswdToDb();
            if ($result['status'] != 1) {
                return message($result['code'], $result['status'], $result['message'], $result['data']);
            }
        } else {
            $result = $this->SyncHttpPasswdToDb();
            if ($result['status'] != 1) {
                return message($result['code'], $result['status'], $result['message'], $result['data']);
            }
        }

        return message();
    }

    /**
     *người dùng(passwd) => db
     *
     *@return mảng
     */
    private function SyncPasswdToDb()
    {
        /**
         *Xóa các mục được chèn nhiều lần trong bảng dữ liệu
         */
        $dbUserList = $this->database->select('svn_users', [
            'svn_user_id',
            'svn_user_name',
            'svn_user_pass',
            'svn_user_status',
            'svn_user_note'
        ], [
            'GROUP' => [
                'svn_user_name'
            ]
        ]);
        $dbUserListAll = $this->database->select('svn_users', [
            'svn_user_id',
            'svn_user_name',
        ]);

        $duplicates = array_diff(array_column($dbUserListAll, 'svn_user_id'), array_column($dbUserList, 'svn_user_id'));
        foreach ($duplicates as $value) {
            $this->database->delete('svn_users', [
                'svn_user_id' => $value,
            ]);
        }

        /**
         *Thêm, xóa và sửa đổi dữ liệu so sánh
         */
        $old = array_column($dbUserList, 'svn_user_name');
        $oldCombin = array_combine($old, $dbUserList);
        $svnUserList =  $this->SVNAdmin->GetUserInfo($this->passwdContent);
        if (is_numeric($svnUserList)) {
            if ($svnUserList == 621) {
                return message(200, 0, 'Lỗi định dạng tệp (số nhận dạng [người dùng] không tồn tại)');
            } elseif ($svnUserList == 710) {
                return message(200, 0, 'người dùng không tồn tại');
            } else {
                return message(200, 0, "mã lỗi$svnUserList");
            }
        }
        $new = array_column($svnUserList, 'userName');
        $newCombin = array_combine($new, $svnUserList);

        //xóa bỏ
        $delete = array_diff($old, $new);
        foreach ($delete as $value) {
            $this->database->delete('svn_users', [
                'svn_user_name' => $value,
            ]);
        }

        //thêm vào
        $create = array_diff($new, $old);
        foreach ($create as $value) {
            $this->database->insert('svn_users', [
                'svn_user_name' => $value,
                'svn_user_pass' => $newCombin[$value]['userPass'],
                'svn_user_status' => $newCombin[$value]['disabled'] == 1 ? 0 : 1,
                'svn_user_note' => ''
            ]);
        }

        //thay mới
        $update = array_intersect($old, $new);
        foreach ($update as $value) {
            if (
                $oldCombin[$value]['svn_user_pass'] != $newCombin[$value]['userPass'] ||
                $oldCombin[$value]['svn_user_status'] != ($newCombin[$value]['disabled'] == 1 ? 0 : 1)
            ) {
                $this->database->update('svn_users', [
                    'svn_user_pass' => $newCombin[$value]['userPass'],
                    'svn_user_status' => $newCombin[$value]['disabled'] == 1 ? 0 : 1,
                ], [
                    'svn_user_name' => $value
                ]);
            }
        }

        return message();
    }

    /**
     *người dùng(ldap) => db
     *
     *@return mảng
     */
    private function SyncLdapToDb()
    {
        $ldapUsers = $this->ServiceLdap->GetLdapUsers();
        if ($ldapUsers['status'] != 1) {
            return message($ldapUsers['code'], $ldapUsers['status'], $ldapUsers['message'], $ldapUsers['data']);
        }

        $ldapUsers = $ldapUsers['data']['users'];

        //Kiểm tra xem tên người dùng có hợp lệ không
        foreach ($ldapUsers as $key => $user) {
            $checkResult = $this->checkService->CheckRepUser($user);
            if ($checkResult['status'] != 1) {
                unset($ldapUsers[$key]);
            }
        }

        $ldapUsers = array_values($ldapUsers);

        /**
         *Xóa các mục được chèn nhiều lần trong bảng dữ liệu
         */
        $dbUserList = $this->database->select('svn_users', [
            'svn_user_id',
            'svn_user_name',
            'svn_user_pass',
            'svn_user_status',
            'svn_user_note'
        ], [
            'GROUP' => [
                'svn_user_name'
            ]
        ]);
        $dbUserListAll = $this->database->select('svn_users', [
            'svn_user_id',
            'svn_user_name',
        ]);

        $duplicates = array_diff(array_column($dbUserListAll, 'svn_user_id'), array_column($dbUserList, 'svn_user_id'));
        foreach ($duplicates as $value) {
            $this->database->delete('svn_users', [
                'svn_user_id' => $value,
            ]);
        }

        /**
         *Thêm, xóa và sửa đổi dữ liệu so sánh
         */
        $old = array_column($dbUserList, 'svn_user_name');
        $oldCombin = array_combine($old, $dbUserList);
        $new = $ldapUsers;

        //xóa bỏ
        $delete = array_diff($old, $new);
        foreach ($delete as $value) {
            $this->database->delete('svn_users', [
                'svn_user_name' => $value,
            ]);
        }

        //thêm vào
        $create = array_diff($new, $old);
        foreach ($create as $value) {
            $this->database->insert('svn_users', [
                'svn_user_name' => $value,
                'svn_user_pass' => '',
                'svn_user_status' => 1,
                'svn_user_note' => ''
            ]);
        }

        return message();
    }

    /**
     *người dùng(httpPasswd) => db
     *
     *@return mảng
     */
    private function SyncHttpPasswdToDb()
    {
        /**
         *Xóa các mục được chèn nhiều lần trong bảng dữ liệu
         */
        $dbUserList = $this->database->select('svn_users', [
            'svn_user_id',
            'svn_user_name',
            'svn_user_pass',
            'svn_user_status',
            'svn_user_note'
        ], [
            'GROUP' => [
                'svn_user_name'
            ]
        ]);
        $dbUserListAll = $this->database->select('svn_users', [
            'svn_user_id',
            'svn_user_name',
        ]);

        $duplicates = array_diff(array_column($dbUserListAll, 'svn_user_id'), array_column($dbUserList, 'svn_user_id'));
        foreach ($duplicates as $value) {
            $this->database->delete('svn_users', [
                'svn_user_id' => $value,
            ]);
        }

        /**
         *Thêm, xóa và sửa đổi dữ liệu so sánh
         */
        $old = array_column($dbUserList, 'svn_user_name');
        $oldCombin = array_combine($old, $dbUserList);
        $svnUserList =  $this->SVNAdmin->GetUserInfoHttp($this->httpPasswdContent);
        if (is_numeric($svnUserList)) {
            if ($svnUserList == 710) {
                return message(200, 0, 'người dùng không tồn tại');
            } else {
                return message(200, 0, "mã lỗi$svnUserList");
            }
        }
        $new = array_column($svnUserList, 'userName');
        $newCombin = array_combine($new, $svnUserList);

        //xóa bỏ
        $delete = array_diff($old, $new);
        foreach ($delete as $value) {
            $this->database->delete('svn_users', [
                'svn_user_name' => $value,
            ]);
        }

        //thêm vào
        $create = array_diff($new, $old);
        foreach ($create as $value) {
            $this->database->insert('svn_users', [
                'svn_user_name' => $value,
                'svn_user_pass' => '',
                'svn_user_status' => $newCombin[$value]['disabled'] == 1 ? 0 : 1,
                'svn_user_note' => ''
            ]);
        }

        //thay mới
        $update = array_intersect($old, $new);
        foreach ($update as $value) {
            if ($oldCombin[$value]['svn_user_status'] != ($newCombin[$value]['disabled'] == 1 ? 0 : 1)) {
                $this->database->update('svn_users', [
                    'svn_user_status' => $newCombin[$value]['disabled'] == 1 ? 0 : 1,
                ], [
                    'svn_user_name' => $value
                ]);
            }
        }

        return message();
    }

    /**
     *Nhận người dùng SVN bằng phân trang
     *
     *Chỉ chứa tên người dùng và trạng thái kích hoạt
     *
     *Người quản lý
     *Người dùng SVN
     */
    public function GetUserList()
    {
        //kiểm tra biểu mẫu
        $checkResult = funCheckForm($this->payload, [
            'sync' => ['type' => 'boolean'],
            'page' => ['type' => 'boolean'],
            'pageSize' => ['type' => 'integer', 'required' => isset($this->payload['page']) && $this->payload['page'] ? true : false],
            'currentPage' => ['type' => 'integer', 'required' => isset($this->payload['page']) && $this->payload['page'] ? true : false],
            'searchKeyword' => ['type' => 'string', 'notNull' => false],
            'sortName' => ['type' => 'string', 'notNull' => true],
            'sortType' => ['type' => 'string', 'notNull' => true],
            'svnn_user_pri_path_id' => ['type' => 'integer', 'required' => $this->userRoleId == 2]
        ]);
        if ($checkResult['status'] == 0) {
            return message($checkResult['code'], $checkResult['status'], $checkResult['message'] . ': ' . $checkResult['data']['column']);
        }

        //kiểm tra trường sắp xếp
        if (!in_array($this->payload['sortName'], ['svn_user_id', 'svn_user_name', 'svn_user_status', 'svn_user_last_login'])) {
            return message(2000, 'trường sắp xếp không được phép');
        }
        if (!in_array($this->payload['sortType'], ['asc', 'desc', 'ASC', 'DESC'])) {
            return message(2000, 'loại sắp xếp không được phép');
        }

        $sync = $this->payload['sync'];
        $page = $this->payload['page'];
        $searchKeyword = trim($this->payload['searchKeyword']);

        //Đồng bộ hóa dữ liệu người dùng SVN vào cơ sở dữ liệu
        if ($sync) {
            $syncResult = $this->SyncUser();
            if ($syncResult['status'] != 1) {
                return message($syncResult['code'], $syncResult['status'], $syncResult['message'], $syncResult['data']);
            }
        }

        if ($page) {
            $pageSize = $this->payload['pageSize'];
            $currentPage = $this->payload['currentPage'];
            $begin = $pageSize * ($currentPage - 1);

            $result = $this->database->select('svn_users', [
                'svn_user_id',
                'svn_user_name',
                'svn_user_pass',
                'svn_user_status [Int]',
                'svn_user_note',
                'svn_user_last_login',
                'svn_user_token'
            ], [
                'AND' => [
                    'OR' => [
                        'svn_user_name[~]' => $searchKeyword,
                        'svn_user_note[~]' => $searchKeyword,
                    ],
                ],
                'LIMIT' => [$begin, $pageSize],
                'ORDER' => [
                    $this->payload['sortName']  => strtoupper($this->payload['sortType'])
                ]
            ]);
        } else {
            $result = $this->database->select('svn_users', [
                'svn_user_id',
                'svn_user_name',
                'svn_user_pass',
                'svn_user_status [Int]',
                'svn_user_note',
                'svn_user_last_login',
                'svn_user_token'
            ], [
                'AND' => [
                    'OR' => [
                        'svn_user_name[~]' => $searchKeyword,
                        'svn_user_note[~]' => $searchKeyword,
                    ],
                ],
                'ORDER' => [
                    $this->payload['sortName']  => strtoupper($this->payload['sortType'])
                ]
            ]);
        }

        $total = $this->database->count('svn_users', [
            'svn_user_id'
        ], [
            'AND' => [
                'OR' => [
                    'svn_user_name[~]' => $searchKeyword,
                    'svn_user_note[~]' => $searchKeyword,
                ],
            ],
        ]);

        //Lọc các đối tượng người dùng SVN có thể quản lý
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
                if (!in_array([
                    'objectType' => 'user',
                    'objectName' => $value['svn_user_name']
                ], $filters)) {
                    unset($result[$key]);
                }
            }
        }

        $time = time();
        foreach ($result as $key => $value) {
            $result[$key]['svn_user_status'] = $value['svn_user_status'] == 1 ? true : false;
            $result[$key]['online'] = (empty($value['svn_user_token']) || $value['svn_user_token'] == '-') ? false : (explode($this->configSign['signSeparator'], $value['svn_user_token'])[3] > $time);
            unset($result[$key]['svn_user_token']);
        }

        return message(200, 1, 'thành công', [
            'data' => array_values($result),
            'total' => $total
        ]);
    }

    /**
     *Tự động xác định danh sách người dùng trong file passwd và trả về
     */
    public function UserScan()
    {
        if ($this->enableCheckout == 'svn') {
            $dataSource = $this->svnDataSource;
        } else {
            $dataSource = $this->httpDataSource;
        }

        if ($dataSource['user_source'] == 'ldap') {
            return message(200, 0, 'Nguồn người dùng SVN hiện tại là LDAP -thao tác này không được hỗ trợ');
        }

        //kiểm tra biểu mẫu
        $checkResult = funCheckForm($this->payload, [
            'passwd' => ['type' => 'string', 'notNull' => true]
        ]);
        if ($checkResult['status'] == 0) {
            return message($checkResult['code'], $checkResult['status'], $checkResult['message'] . ': ' . $checkResult['data']['column']);
        }

        if ($this->enableCheckout == 'svn') {
            $svnUserPassList = $this->SVNAdmin->GetUserInfo($this->payload['passwd']);
            if (is_numeric($svnUserPassList)) {
                if ($svnUserPassList == 621) {
                    return message(200, 0, 'lỗi định dạng tập tin (không tồn tại[users]标识)');
                } elseif ($svnUserPassList == 710) {
                    return message(200, 0, 'người dùng không tồn tại');
                } else {
                    return message(200, 0, "mã lỗi$svnUserPassList");
                }
            }
        } else {
            $svnUserPassList = $this->SVNAdmin->GetUserInfoHttp($this->payload['passwd']);
            if (is_numeric($svnUserPassList)) {
                if ($svnUserPassList == 710) {
                    return message(200, 0, 'người dùng không tồn tại');
                } else {
                    return message(200, 0, "mã lỗi$svnUserPassList");
                }
            }
        }

        return message(200, 1, 'thành công', $svnUserPassList);
    }

    /**
     *Nhập hàng loạt người dùng
     */
    public function UserImport()
    {
        if ($this->enableCheckout == 'svn') {
            $dataSource = $this->svnDataSource;
        } else {
            $dataSource = $this->httpDataSource;
        }

        if ($dataSource['user_source'] == 'ldap') {
            return message(200, 0, 'Nguồn người dùng SVN hiện tại là LDAP -thao tác này không được hỗ trợ');
        }

        //kiểm tra biểu mẫu
        $checkResult = funCheckForm($this->payload, [
            'users' => ['type' => 'array', 'notNull' => true]
        ]);
        if ($checkResult['status'] == 0) {
            return message($checkResult['code'], $checkResult['status'], $checkResult['message'] . ': ' . $checkResult['data']['column']);
        }

        $users = $this->payload['users'];

        $all = [];
        if ($this->enableCheckout == 'svn') {
            $passwdContent = $this->passwdContent;

            foreach ($users as $user) {
                $checkResult = $this->checkService->CheckRepUser($user['userName']);
                if ($checkResult['status'] != 1) {
                    $all[] = [
                        'userName' => $user['userName'],
                        'status' => 0,
                        'reason' => 'Tên người dùng không hợp lệ'
                    ];
                    continue;
                }

                if (empty($user['userPass']) || trim($user['userPass']) == '') {
                    $all[] = [
                        'userName' => $user['userName'],
                        'status' => 0,
                        'reason' => 'mật khẩu không thể để trống'
                    ];
                    continue;
                }

                $result = $this->SVNAdmin->AddUser($passwdContent, $user['userName'], $user['userPass']);
                if (is_numeric($result)) {
                    if ($result == 621) {
                        return message(200, 0, 'Lỗi định dạng tệp (số nhận dạng [người dùng] không tồn tại)');
                    } elseif ($result == 810) {
                        $all[] = [
                            'userName' => $user['userName'],
                            'status' => 0,
                            'reason' => 'người dùng đã tồn tại'
                        ];
                        continue;
                    } else {
                        $all[] = [
                            'userName' => $user['userName'],
                            'status' => 0,
                            'reason' => "mã lỗi$result"
                        ];
                        continue;
                    }
                }

                $passwdContent = $result;

                //vô hiệu hóa việc xử lý người dùng
                if ($user['disabled'] == '1') {
                    $result = $this->SVNAdmin->UpdUserStatus($passwdContent, $user['userName'], true);
                    if (is_numeric($result)) {
                        if ($result == 621) {
                            return message(200, 0, 'Lỗi định dạng tệp (số nhận dạng [người dùng] không tồn tại)');
                        } elseif ($result == 710) {
                            return message(200, 0, 'người dùng không tồn tại');
                        } else {
                            return message(200, 0, "mã lỗi$result");
                        }
                    }

                    $passwdContent = $result;
                }

                $all[] = [
                    'userName' => $user['userName'],
                    'status' => 1,
                    'reason' => ''
                ];

                $this->database->delete('svn_users', [
                    'svn_user_name' => $user['userName'],
                ]);
                $this->database->insert('svn_users', [
                    'svn_user_name' => $user['userName'],
                    'svn_user_pass' => $user['userPass'],
                    'svn_user_status' => $user['disabled'] == '1' ? 0 : 1,
                    'svn_user_note' => ''
                ]);
            }

            //ghi file cấu hình
            funFilePutContents($this->configSvn['svn_passwd_file'], $passwdContent);
        } else {
            $passwdContent = $this->httpPasswdContent;

            $currentUsers =  $this->SVNAdmin->GetUserInfoHttp($passwdContent);
            if (is_numeric($currentUsers)) {
                if ($currentUsers == 710) {
                    return message(200, 0, 'người dùng không tồn tại');
                } else {
                    return message(200, 0, "mã lỗi$currentUsers");
                }
            }
            $currentUsers = array_column($currentUsers, 'userName');

            foreach ($users as $user) {
                $checkResult = $this->checkService->CheckRepUser($user['userName']);
                if ($checkResult['status'] != 1) {
                    $all[] = [
                        'userName' => $user['userName'],
                        'status' => 0,
                        'reason' => 'Tên người dùng không hợp lệ'
                    ];
                    continue;
                }

                if (empty($user['userPass']) || trim($user['userPass']) == '') {
                    $all[] = [
                        'userName' => $user['userName'],
                        'status' => 0,
                        'reason' => 'mật khẩu không thể để trống'
                    ];
                    continue;
                }

                if (in_array($user['userName'], $currentUsers)) {
                    $all[] = [
                        'userName' => $user['userName'],
                        'status' => 0,
                        'reason' => 'người dùng đã tồn tại'
                    ];
                    continue;
                }

                $passwdContent = trim($passwdContent) . "\n" . trim($user['userName']) . ':' . $user['userPass'] . "\n";

                //vô hiệu hóa việc xử lý người dùng
                if ($user['disabled'] == '1') {
                    $result = $this->SVNAdmin->UpdUserStatusHttp($passwdContent, $user['userName'], true);
                    if (is_numeric($result)) {
                        if ($result == 621) {
                            return message(200, 0, 'Lỗi định dạng tệp (số nhận dạng [người dùng] không tồn tại)');
                        } elseif ($result == 710) {
                            return message(200, 0, 'người dùng không tồn tại');
                        } else {
                            return message(200, 0, "mã lỗi$result");
                        }
                    }

                    $passwdContent = $result;
                }

                $all[] = [
                    'userName' => $user['userName'],
                    'status' => 1,
                    'reason' => ''
                ];

                $this->database->delete('svn_users', [
                    'svn_user_name' => $user['userName'],
                ]);
                $this->database->insert('svn_users', [
                    'svn_user_name' => $user['userName'],
                    'svn_user_pass' => '',
                    'svn_user_status' => $user['disabled'] == '1' ? 0 : 1,
                    'svn_user_note' => ''
                ]);
            }

            //ghi file cấu hình
            funFilePutContents($this->configSvn['http_passwd_file'], $passwdContent);
        }

        //nhật ký
        //$this->ServiceLogs->InsertLog(
        //'Nhập hàng loạt người dùng',
        //sprintf("Tên người dùng: %s", implode(',', array_column($success, 'userName'))),
        //$this->tên người dùng
        //);

        return message(200, 1, 'thành công', $all);
    }

    /**
     *Kích hoạt hoặc vô hiệu hóa người dùng
     */
    public function UpdUserStatus()
    {
        if ($this->enableCheckout == 'svn') {
            $dataSource = $this->svnDataSource;
        } else {
            $dataSource = $this->httpDataSource;
        }

        if ($dataSource['user_source'] == 'ldap') {
            return message(200, 0, 'Nguồn người dùng SVN hiện tại là LDAP -thao tác này không được hỗ trợ');
        }

        if ($this->enableCheckout == 'svn') {
            //trạng thái đúng cho phép người dùng vô hiệu hóa người dùng sai
            $result = $this->SVNAdmin->UpdUserStatus($this->passwdContent, $this->payload['svn_user_name'], !$this->payload['status']);
            if (is_numeric($result)) {
                if ($result == 621) {
                    return message(200, 0, 'Lỗi định dạng tệp (số nhận dạng [người dùng] không tồn tại)');
                } elseif ($result == 710) {
                    return message(200, 0, 'người dùng không tồn tại');
                } else {
                    return message(200, 0, "mã lỗi$result");
                }
            }

            funFilePutContents($this->configSvn['svn_passwd_file'], $result);
        } else {
            //trạng thái đúng cho phép người dùng vô hiệu hóa người dùng sai
            $result = $this->SVNAdmin->UpdUserStatusHttp($this->httpPasswdContent, $this->payload['svn_user_name'], !$this->payload['status']);
            if (is_numeric($result)) {
                if ($result == 710) {
                    return message(200, 0, 'người dùng không tồn tại');
                } else {
                    return message(200, 0, "mã lỗi$result");
                }
            }

            funFilePutContents($this->configSvn['http_passwd_file'], $result);
        }

        $this->database->update('svn_users', [
            'svn_user_status' => $this->payload['status'] ? 1 : 0,
        ], [
            'svn_user_name' => $this->payload['svn_user_name']
        ]);

        return message();
    }

    /**
     *Sửa đổi nhận xét của người dùng SVN
     */
    public function UpdUserNote()
    {
        $this->database->update('svn_users', [
            'svn_user_note' => $this->payload['svn_user_note']
        ], [
            'svn_user_name' => $this->payload['svn_user_name']
        ]);

        return message(200, 1, 'Đã lưu');
    }

    /**
     *Tạo người dùng SVN mới
     */
    public function CreateUser()
    {
        if ($this->enableCheckout == 'svn') {
            $dataSource = $this->svnDataSource;
        } else {
            $dataSource = $this->httpDataSource;
        }

        if ($dataSource['user_source'] == 'ldap') {
            return message(200, 0, 'Nguồn người dùng SVN hiện tại là LDAP -thao tác này không được hỗ trợ');
        }

        //Kiểm tra xem tên người dùng có hợp lệ không
        $checkResult = $this->checkService->CheckRepUser($this->payload['svn_user_name']);
        if ($checkResult['status'] != 1) {
            return message($checkResult['code'], $checkResult['status'], $checkResult['message'], $checkResult['data']);
        }

        //kiểm tra xem mật khẩu có trống không
        if (trim($this->payload['svn_user_pass']) == '') {
            return message(200, 0, 'mật khẩu không thể để trống');
        }

        if ($this->enableCheckout == 'svn') {
            //kiểm tra xem người dùng đã tồn tại chưa
            $result = $this->SVNAdmin->AddUser($this->passwdContent, $this->payload['svn_user_name'], $this->payload['svn_user_pass']);
            if (is_numeric($result)) {
                if ($result == 621) {
                    return message(200, 0, 'Lỗi định dạng tệp (số nhận dạng [người dùng] không tồn tại)');
                } elseif ($result == 810) {
                    return message(200, 0, 'người dùng đã tồn tại');
                } else {
                    return message(200, 0, "mã lỗi$result");
                }
            }

            //ghi file cấu hình
            funFilePutContents($this->configSvn['svn_passwd_file'], $result);
        } else {
            $result = $this->SVNAdmin->GetUserInfoHttp($this->httpPasswdContent, $this->payload['svn_user_name']);
            if (is_numeric($result)) {
                if ($result != 710) {
                    return message(200, 0, "mã lỗi$result");
                }
            } else {
                return message(200, 0, 'người dùng đã tồn tại');
            }

            $result = $this->ServiceApache->CreateUser($this->payload['svn_user_name'], $this->payload['svn_user_pass']);
            if ($result['status'] != 1) {
                return message2($result);
            }
        }

        //ghi vào cơ sở dữ liệu
        $this->database->delete('svn_users', [
            'svn_user_name' => $this->payload['svn_user_name'],
        ]);
        $this->database->insert('svn_users', [
            'svn_user_name' => $this->payload['svn_user_name'],
            'svn_user_pass' => $this->payload['svn_user_pass'],
            'svn_user_status' => 1,
            'svn_user_note' => $this->payload['svn_user_note']
        ]);

        //nhật ký
        $this->ServiceLogs->InsertLog(
            'tạo người dùng',
            sprintf("tên tài thư mụcản:%s", $this->payload['svn_user_name']),
            $this->userName
        );

        return message();
    }

    /**
     *Sửa đổi mật khẩu của người dùng SVN
     */
    public function UpdUserPass()
    {
        if ($this->enableCheckout == 'svn') {
            $dataSource = $this->svnDataSource;
        } else {
            $dataSource = $this->httpDataSource;
        }

        if ($dataSource['user_source'] == 'ldap') {
            return message(200, 0, 'Nguồn người dùng SVN hiện tại là LDAP -thao tác này không được hỗ trợ');
        }

        if (trim($this->payload['svn_user_pass']) == '') {
            return message(200, 0, 'mật khẩu không thể để trống');
        }

        if ($this->enableCheckout == 'svn') {
            //kiểm tra xem người dùng đã tồn tại chưa
            $result = $this->SVNAdmin->UpdUserPass($this->passwdContent, $this->payload['svn_user_name'], $this->payload['svn_user_pass'], !$this->payload['svn_user_status']);
            if (is_numeric($result)) {
                if ($result == 621) {
                    return message(200, 0, 'Lỗi định dạng tệp (số nhận dạng [người dùng] không tồn tại)');
                } elseif ($result == 710) {
                    return message(200, 0, 'Người dùng không tồn tại, vui lòng thử lại sau khi đồng bộ hóa người dùng');
                } else {
                    return message(200, 0, "mã lỗi$result");
                }
            }

            //ghi file cấu hình
            funFilePutContents($this->configSvn['svn_passwd_file'], $result);
        } else {
            $result = $this->ServiceApache->UpdUserPass($this->payload['svn_user_name'], $this->payload['svn_user_pass']);
            if ($result['status'] != 1) {
                return message2($result);
            }
        }

        //ghi vào cơ sở dữ liệu
        $this->database->update('svn_users', [
            'svn_user_pass' => $this->payload['svn_user_pass'],
        ], [
            'svn_user_name' => $this->payload['svn_user_name']
        ]);

        return message();
    }

    /**
     *Xóa người dùng SVN
     */
    public function DelUser()
    {
        if ($this->enableCheckout == 'svn') {
            $dataSource = $this->svnDataSource;
        } else {
            $dataSource = $this->httpDataSource;
        }

        if ($dataSource['user_source'] == 'ldap') {
            return message(200, 0, 'Nguồn người dùng SVN hiện tại là LDAP -thao tác này không được hỗ trợ');
        }

        if ($this->enableCheckout == 'svn') {
            //Xóa toàn cục khỏi tệp passwd
            $resultPasswd = $this->SVNAdmin->DelUserFromPasswd($this->passwdContent, $this->payload['svn_user_name'], !$this->payload['svn_user_status']);
            if (is_numeric($resultPasswd)) {
                if ($resultPasswd == 621) {
                    return message(200, 0, 'Lỗi định dạng tệp (số nhận dạng [người dùng] không tồn tại)');
                } elseif ($resultPasswd == 710) {
                    return message(200, 0, 'người dùng không tồn tại');
                } else {
                    return message(200, 0, "mã lỗi$resultPasswd");
                }
            }

            funFilePutContents($this->configSvn['svn_passwd_file'], $resultPasswd);
        } else {
            $result = $this->ServiceApache->DelUser($this->payload['svn_user_name']);
            if ($result['status'] != 1) {
                return message2($result);
            }
        }

        //xóa khỏi tệp authz
        $resultAuthz = $this->SVNAdmin->DelObjectFromAuthz($this->authzContent, $this->payload['svn_user_name'], 'user');
        if (is_numeric($resultAuthz)) {
            if ($resultAuthz == 621) {
                return message(200, 0, 'Lỗi định dạng tệp (số nhận dạng [người dùng] không tồn tại)');
            } elseif ($resultAuthz == 901) {
                return message(200, 0, 'Loại đối tượng ủy quyền không được hỗ trợ');
            } else {
                return message(200, 0, "mã lỗi$resultAuthz");
            }
        }

        //xóa khỏi cơ sở dữ liệu
        $this->database->delete('svn_users', [
            'svn_user_name' => $this->payload['svn_user_name']
        ]);

        funFilePutContents($this->configSvn['svn_authz_file'], $resultAuthz);

        //nhật ký
        $this->ServiceLogs->InsertLog(
            'xóa người dùng',
            sprintf("tên tài thư mụcản:%s", $this->payload['svn_user_name']),
            $this->userName
        );

        return message();
    }
}
