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

class Svngroup extends Base
{
    /**
     *Các đối tượng lớp dịch vụ khác
     *
     *Đối tượng @var
     */
    private $ServiceLogs;
    private $ServiceLdap;

    function __construct($parm = [])
    {
        parent::__construct($parm);

        $this->ServiceLogs = new ServiceLogs($parm);
        $this->ServiceLdap = new ServiceLdap($parm);
    }

    /**
     *Thực hiện đồng bộ hóa
     *
     *Cung cấp các tùy chọn để người dùng chọn có xóa nhóm trước đó và quyền của người dùng hay không
     *
     *@return mảng
     */
    public function SyncGroup()
    {
        if ($this->enableCheckout == 'svn') {
            $dataSource = $this->svnDataSource;
        } else {
            $dataSource = $this->httpDataSource;
        }

        if ($dataSource['user_source'] == 'ldap' && $dataSource['group_source'] == 'ldap') {
            $result = $this->ServiceLdap->SyncLdapToAuthz();
            if ($result['status'] != 1) {
                return message($result['code'], $result['status'], $result['message'], $result['data']);
            }

            $result = $this->SyncAuthzToDb();
            if ($result['status'] != 1) {
                return message($result['code'], $result['status'], $result['message'], $result['data']);
            }
        } else {
            $result = $this->SyncAuthzToDb();
            if ($result['status'] != 1) {
                return message($result['code'], $result['status'], $result['message'], $result['data']);
            }
        }

        return message();
    }

    /**
     *nhóm(authz) => db
     *
     *@return mảng
     */
    private function SyncAuthzToDb()
    {
        /**
         *Xóa các mục được chèn nhiều lần trong bảng dữ liệu
         */
        $dbGroupList = $this->database->select('svn_groups', [
            'svn_group_id',
            'svn_group_name',
            'svn_group_note',
            'include_user_count [Int]',
            'include_group_count [Int]',
            'include_aliase_count [Int]'
        ], [
            'GROUP' => [
                'svn_group_name'
            ]
        ]);
        $dbGroupListAll = $this->database->select('svn_groups', [
            'svn_group_id',
            'svn_group_name',
        ]);

        $duplicates = array_diff(array_column($dbGroupListAll, 'svn_group_id'), array_column($dbGroupList, 'svn_group_id'));
        foreach ($duplicates as $value) {
            $this->database->delete('svn_groups', [
                'svn_group_id' => $value,
            ]);
        }

        /**
         *Thêm, xóa và sửa đổi dữ liệu so sánh
         */
        $old = array_column($dbGroupList, 'svn_group_name');
        $oldCombin = array_combine($old, $dbGroupList);

        $svnGroupList = $this->SVNAdmin->GetGroupInfo($this->authzContent);
        if (is_numeric($svnGroupList)) {
            if ($svnGroupList == 612) {
                return message(200, 0, 'định dạng tập tin sai(không tồn tại[groups]Logo)');
            } else {
                return message(200, 0, "mã lỗi$svnGroupList");
            }
        }

        $new = array_column($svnGroupList, 'groupName');
        $newCombin = array_combine($new, $svnGroupList);

        //xóa bỏ
        $delete = array_diff($old, $new);
        foreach ($delete as $value) {
            $this->database->delete('svn_groups', [
                'svn_group_name' => $value,
            ]);
        }

        //thêm vào
        $create = array_diff($new, $old);
        foreach ($create as $value) {
            $this->database->insert('svn_groups', [
                'svn_group_name' => $value,
                'include_user_count' => $newCombin[$value]['include']['users']['count'],
                'include_group_count' => $newCombin[$value]['include']['groups']['count'],
                'include_aliase_count' => $newCombin[$value]['include']['aliases']['count'],
                'svn_group_note' => '',
            ]);
        }

        //thay mới
        $update = array_intersect($old, $new);
        foreach ($update as $value) {
            if (
                $oldCombin[$value]['include_user_count'] !=  $newCombin[$value]['include']['users']['count'] ||
                $oldCombin[$value]['include_group_count'] !=  $newCombin[$value]['include']['groups']['count'] ||
                $oldCombin[$value]['include_aliase_count'] !=  $newCombin[$value]['include']['aliases']['count']
            ) {
                $this->database->update('svn_groups', [
                    'include_user_count' => $newCombin[$value]['include']['users']['count'],
                    'include_group_count' => $newCombin[$value]['include']['groups']['count'],
                    'include_aliase_count' => $newCombin[$value]['include']['aliases']['count']
                ], [
                    'svn_group_name' => $value
                ]);
            }
        }

        return message();
    }

    /**
     *Nhận danh sách được nhóm với phân trang
     */
    public function GetGroupList()
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
        if (!in_array($this->payload['sortName'], ['svn_group_id', 'svn_group_name'])) {
            return message(2000, 'trường sắp xếp không được phép');
        }
        if (!in_array($this->payload['sortType'], ['asc', 'desc', 'ASC', 'DESC'])) {
            return message(2000, 'loại sắp xếp không được phép');
        }

        $sync = $this->payload['sync'];
        $page = $this->payload['page'];
        $searchKeyword = trim($this->payload['searchKeyword']);

        if ($sync) {
            //Làm cho đồng bộ
            $syncResult = $this->SyncGroup();
            if ($syncResult['status'] != 1) {
                return message($syncResult['code'], $syncResult['status'], $syncResult['message'], $syncResult['data']);
            }
        }

        if ($page) {
            $pageSize = $this->payload['pageSize'];
            $currentPage = $this->payload['currentPage'];
            $begin = $pageSize * ($currentPage - 1);

            $result = $this->database->select('svn_groups', [
                'svn_group_id',
                'svn_group_name',
                'svn_group_note',
                'include_user_count [Int]',
                'include_group_count [Int]',
                'include_aliase_count [Int]',
            ], [
                'AND' => [
                    'OR' => [
                        'svn_group_name[~]' => $searchKeyword,
                        'svn_group_note[~]' => $searchKeyword,
                    ],
                ],
                'LIMIT' => [$begin, $pageSize],
                'ORDER' => [
                    $this->payload['sortName']  => strtoupper($this->payload['sortType'])
                ]
            ]);
        } else {
            $result = $this->database->select('svn_groups', [
                'svn_group_id',
                'svn_group_name',
                'svn_group_note',
                'include_user_count [Int]',
                'include_group_count [Int]',
                'include_aliase_count [Int]',
            ], [
                'AND' => [
                    'OR' => [
                        'svn_group_name[~]' => $searchKeyword,
                        'svn_group_note[~]' => $searchKeyword,
                    ],
                ],
                'ORDER' => [
                    $this->payload['sortName']  => strtoupper($this->payload['sortType'])
                ]
            ]);
        }

        $total = $this->database->count('svn_groups',  [
            'svn_group_id'
        ], [
            'AND' => [
                'OR' => [
                    'svn_group_name[~]' => $searchKeyword,
                    'svn_group_note[~]' => $searchKeyword,
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
                    'objectType' => 'group',
                    'objectName' => $value['svn_group_name']
                ], $filters)) {
                    unset($result[$key]);
                }
            }
        }

        return message(200, 1, 'thành công', [
            'data' => array_values($result),
            'total' => $total
        ]);
    }

    /**
     *Chỉnh sửa thông tin ghi chú nhóm
     */
    public function UpdGroupNote()
    {
        $this->database->update('svn_groups', [
            'svn_group_note' => $this->payload['svn_group_note']
        ], [
            'svn_group_name' => $this->payload['svn_group_name']
        ]);

        return message(200, 1, 'Đã lưu');
    }

    /**
     *Tạo nhóm SVN
     */
    public function CreateGroup()
    {
        if ($this->enableCheckout == 'svn') {
            $dataSource = $this->svnDataSource;
        } else {
            $dataSource = $this->httpDataSource;
        }

        if ($dataSource['user_source'] == 'ldap' && $dataSource['group_source'] == 'ldap') {
            return message(200, 0, 'Nguồn nhóm SVN hiện tại là LDAP -thao tác này không được hỗ trợ');
        }

        //Kiểm tra xem tên nhóm có hợp lệ không
        $checkResult = $this->checkService->CheckRepGroup($this->payload['svn_group_name']);
        if ($checkResult['status'] != 1) {
            return message($checkResult['code'], $checkResult['status'], $checkResult['message'], $checkResult['data']);
        }

        //kiểm tra xem nhóm có tồn tại không
        $result = $this->SVNAdmin->AddGroup($this->authzContent, $this->payload['svn_group_name']);
        if (is_numeric($result)) {
            if ($result == 612) {
                return message(200, 0, 'định dạng tập tin sai(không tồn tại[groups]Logo)');
            } elseif ($result == 820) {
                return message(200, 0, 'nhóm đã tồn tại');
            } else {
                return message(200, 0, "mã lỗi$result");
            }
        }

        //ghi file cấu hình
        funFilePutContents($this->configSvn['svn_authz_file'], $result);

        //ghi vào cơ sở dữ liệu
        $this->database->delete('svn_groups', [
            'svn_group_name' => $this->payload['svn_group_name']
        ]);
        $this->database->insert('svn_groups', [
            'svn_group_name' => $this->payload['svn_group_name'],
            'include_user_count' => 0,
            'include_group_count' => 0,
            'include_aliase_count' => 0,
            'svn_group_note' => $this->payload['svn_group_note'],
        ]);

        //nhật ký
        $this->ServiceLogs->InsertLog(
            '创建分组',
            sprintf("tên nhóm:%s", $this->payload['svn_group_name']),
            $this->userName
        );

        return message();
    }

    /**
     *Xóa nhóm SVN
     */
    public function DelGroup()
    {
        if ($this->enableCheckout == 'svn') {
            $dataSource = $this->svnDataSource;
        } else {
            $dataSource = $this->httpDataSource;
        }

        if ($dataSource['user_source'] == 'ldap' && $dataSource['group_source'] == 'ldap') {
            return message(200, 0, 'Nguồn nhóm SVN hiện tại là LDAP -thao tác này không được hỗ trợ');
        }

        //xóa khỏi tệp authz
        $result = $this->SVNAdmin->DelObjectFromAuthz($this->authzContent, $this->payload['svn_group_name'], 'group');
        if (is_numeric($result)) {
            if ($result == 612) {
                return message(200, 0, 'định dạng tập tin sai(không tồn tại[groups]Logo)');
            } elseif ($result == 901) {
                return message(200, 0, 'Loại đối tượng ủy quyền không được hỗ trợ');
            } else {
                return message(200, 0, "mã lỗi$result");
            }
        }

        funFilePutContents($this->configSvn['svn_authz_file'], $result);

        //xóa khỏi cơ sở dữ liệu
        $this->database->delete('svn_groups', [
            'svn_group_name' => $this->payload['svn_group_name']
        ]);

        //nhật ký
        $this->ServiceLogs->InsertLog(
            'xóa nhóm',
            sprintf("tên nhóm:%s", $this->payload['svn_group_name']),
            $this->userName
        );

        return message();
    }

    /**
     *Sửa đổi tên nhóm SVN
     */
    public function UpdGroupName()
    {
        if ($this->enableCheckout == 'svn') {
            $dataSource = $this->svnDataSource;
        } else {
            $dataSource = $this->httpDataSource;
        }

        if ($dataSource['user_source'] == 'ldap' && $dataSource['group_source'] == 'ldap') {
            return message(200, 0, 'Nguồn nhóm SVN hiện tại là LDAP -thao tác này không được hỗ trợ');
        }

        //Tên nhóm mới có hợp lệ không?
        $checkResult = $this->checkService->CheckRepGroup($this->payload['groupNameNew']);
        if ($checkResult['status'] != 1) {
            return message($checkResult['code'], $checkResult['status'], $checkResult['message'], $checkResult['data']);
        }

        //Đồng bộ hóa trước khi sửa đổi
        $syncResult = $this->SyncGroup();
        if ($syncResult['status'] != 1) {
            return message($syncResult['code'], $syncResult['status'], $syncResult['message'], $syncResult['data']);
        }

        $result = $this->SVNAdmin->UpdObjectFromAuthz($this->authzContent, $this->payload['groupNameOld'], $this->payload['groupNameNew'], 'group');
        if (is_numeric($result)) {
            if ($result == 611) {
                return message(200, 0, 'lỗi định dạng file authz(không tồn tại[aliases]Logo)');
            } elseif ($result == 612) {
                return message(200, 0, 'lỗi định dạng file authz(không tồn tại[groups]Logo)');
            } elseif ($result == 901) {
                return message(200, 0, 'Loại đối tượng ủy quyền không được hỗ trợ');
            } elseif ($result == 821) {
                return message(200, 0, 'Nhóm mới cần sửa đổi đã tồn tại');
            } elseif ($result == 831) {
                return message(200, 0, 'Bí danh mới để sửa đổi đã tồn tại');
            } elseif ($result == 731) {
                return message(200, 0, 'Bí danh để sửa đổi không tồn tại');
            } else {
                return message(200, 0, "mã lỗi$result");
            }
        }

        funFilePutContents($this->configSvn['svn_authz_file'], $result);

        //Đồng bộ hóa sau khi sửa đổi
        parent::RereadAuthz();
        $result = $this->SyncGroup();
        if ($result['status'] != 1) {
            return message($result['code'], $result['status'], $result['message'], $result['data']);
        }

        return message();
    }

    /**
     *Lấy danh sách thành viên của nhóm SVN
     */
    public function GetGroupMember()
    {
        //kiểm tra biểu mẫu
        $checkResult = funCheckForm($this->payload, [
            'searchKeyword' => ['type' => 'string', 'notNull' => false],
            'svn_group_name' => ['type' => 'string', 'notNull' => true],
            'svnn_user_pri_path_id' => ['type' => 'integer', 'required' => $this->userRoleId == 2]
        ]);
        if ($checkResult['status'] == 0) {
            return message($checkResult['code'], $checkResult['status'], $checkResult['message'] . ': ' . $checkResult['data']['column']);
        }

        $searchKeyword = trim($this->payload['searchKeyword']);

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
            if (!in_array([
                'objectType' => 'group',
                'objectName' => $this->payload['svn_group_name']
            ], $filters)) {
                return message(200, 0, 'Đối tượng hoạt động mà không được phép');
            }
        }

        $list = $this->SVNAdmin->GetGroupInfo($this->authzContent, $this->payload['svn_group_name']);
        if (is_numeric($list)) {
            if ($list == 612) {
                return message(200, 0, 'định dạng tập tin sai(không tồn tại[groups]Logo)');
            } elseif ($list == 720) {
                return message(200, 0, 'Nhóm được chỉ định không tồn tại');
            } else {
                return message(200, 0, "mã lỗi$list");
            }
        }

        $result = [];
        foreach ($list['include']['users']['list'] as $value) {
            if (empty($searchKeyword) || strstr($value, $searchKeyword)) {
                $result[] = [
                    'objectType' => 'user',
                    'objectName' => $value,
                ];
            }
        }
        foreach ($list['include']['groups']['list'] as $value) {
            if (empty($searchKeyword) || strstr($value, $searchKeyword)) {
                $result[] = [
                    'objectType' => 'group',
                    'objectName' => $value,
                ];
            }
        }
        foreach ($list['include']['aliases']['list'] as $value) {
            if (empty($searchKeyword) || strstr($value, $searchKeyword)) {
                $result[] = [
                    'objectType' => 'aliase',
                    'objectName' => $value,
                ];
            }
        }

        return message(200, 1, 'thành công', $result);
    }

    /**
     *Thêm hoặc xóa các đối tượng chứa trong nhóm
     *Đối tượng bao gồm: người dùng, nhóm, bí danh người dùng
     */
    public function UpdGroupMember()
    {
        if ($this->enableCheckout == 'svn') {
            $dataSource = $this->svnDataSource;
        } else {
            $dataSource = $this->httpDataSource;
        }
        
        if ($dataSource['user_source'] == 'ldap' && $dataSource['group_source'] == 'ldap') {
            return message(200, 0, 'Nguồn nhóm SVN hiện tại là LDAP -thao tác này không được hỗ trợ');
        }

        $result = $this->SVNAdmin->UpdGroupMember($this->authzContent, $this->payload['svn_group_name'], $this->payload['objectName'], $this->payload['objectType'], $this->payload['actionType']);
        if (is_numeric($result)) {
            if ($result == 612) {
                return message(200, 0, 'định dạng tập tin sai(không tồn tại[groups]Logo)');
            } elseif ($result == 720) {
                return message(200, 0, 'nhóm không tồn tại');
            } elseif ($result == 803) {
                return message(200, 0, 'Đối tượng được thêm đã tồn tại trong nhóm này');
            } elseif ($result == 703) {
                return message(200, 0, 'Đối tượng cần xóa không tồn tại trong nhóm này');
            } elseif ($result == 901) {
                return message(200, 0, 'loại đối tượng không hợp lệ user|group|aliase');
            } elseif ($result == 902) {
                return message(200, 0, 'loại hoạt động không hợp lệ add|delete');
            } elseif ($result == 802) {
                return message(200, 0, 'Không thể thao tác các nhóm có cùng tên');
            } else {
                return message(200, 0, "mã lỗi$result");
            }
        }
        if ($this->payload['objectType'] == 'group' && $this->payload['actionType'] == 'add') {
            //Kiểm tra xem có vấn đề lồng vòng lặp nhóm không
            //Lấy tất cả các nhóm chứa nhóm đó
            $groupGroupList = $this->SVNAdmin->GetSvnGroupAllGroupList($this->authzContent, $this->payload['svn_group_name']);

            if (in_array($this->payload['objectName'], $groupGroupList)) {
                return message(200, 0, 'Có các vòng nhóm lồng nhau');
            }
        }

        funFilePutContents($this->configSvn['svn_authz_file'], $result);

        //Đồng bộ hóa sau khi sửa đổi
        parent::RereadAuthz();
        $syncResult = $this->SyncGroup();
        if ($syncResult['status'] != 1) {
            return message($syncResult['code'], $syncResult['status'], $syncResult['message'], $syncResult['data']);
        }

        return message();
    }
}
