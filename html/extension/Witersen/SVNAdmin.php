<?php

/*
 *@Author: www.witersen.com
 *
 *@LastEditors: bibo318
 *
 *@Description: github: /bibo318
 */

namespace Witersen;

class SVNAdmin
{
    /**
     *The content of the file cannot be set with a unique value, so there will always be two users, groups, aliases, warehouse paths, etc. Scan and process todo
     *For many operations, such as authorizing the path of the warehouse, consider whether to directly add a record todo of the warehouse without returning an error code when the path is not scanned
     *The grouping that does not exist in the grouping section but exists in the warehouse path needs to be processed. In what form should it be processed? todo
     *When a function modifies multiple types of objects, it should be noted that it is modified under different sections and warehouse paths. The lvalue in the section has no sign, the rvalue in the section has a sign, and the warehouse path has a sign todo
     *The replacement operation not only checks whether the new value conflicts, but also checks whether the old value exists todo
     */

    /**
     *Function error code description
     *
     *0 unknown error
     *
     *num PCRE regex throws an error (the specific value is returned by preg_last_error())
     *
     *6xx
     *├─ 600 Parameter content or format error
     *│ └─── 601 $objectName cannot be empty
     *├─ 610 auth file format error
     *│ ├─── 611 authz file format error ([aliases] logo does not exist)
     *│ └─── 612 authz file format error (the [groups] logo does not exist)
     *└─ 620 passwd file format error
     *└───621 passwd file format error (the [users] logo does not exist)
     *
     *7xx
     *├─ 700 The target object does not exist
     *│ ├─── 701 There is no permission record for this object under the warehouse path
     *│ └─── 703 The object to be deleted does not exist in this group
     *├─ 710 User does not exist
*├─ 720 Group does not exist
     *├─ 730 Alias ​​does not exist
     *│ └─── 731 The alias to be modified does not exist
     *├─ 740 Warehouse does not exist
     *└─ 750 Warehouse path does not exist
     *└─── 751 The warehouse path does not exist
     *└─── 752 warehouse path needs to start with /
     *
     *8xx
     *├─ 800 The target object already exists
     *│ ├─── 801 The permission record of this object already exists under the warehouse path
     *│ ├─── 802 A group with the same name can be added
     *│ └─── 803 The object to be added already exists in this group
     *├─ 810 User already exists
     *│ └─── 811 The new user to be modified already exists
     *├─ 820 Group already exists
     *│ └─── 821 The new group to be modified already exists
     *├─ 830 Alias ​​already exists
     *│ └─── 831 The new alias to be modified already exists
     *├─ 840 warehouse already exists
*└─ 850 warehouse path already exists
     *└─── 851 The warehouse path already exists
     *
     *9xx
     *└─ 900 Parameter type error
     *├─── 901 Unsupported authorization object type
     *└─── 902 Unsupported operation type
     *
     */

    /**
     *@var string disable user prefix
     */
    private $reg_1 = '#disabled#';

    /**
     *@var string matches the specified section and its content
     */
    private $reg_2 = "/^[ \t]*\[%s\](((?!\n[ \t]*\[)[\s\S])*)/m";

    /**
     *@var string matches the %s=[rw] form
     */
    private $reg_3 = "/^[ \t]*(%s)[ \t]*=[ \t]*([rw]%s)[ \t]*$/m";

    /**
     *@var string matches %skey=[rw] form
     */
    private $reg_4 = "/^[ \t]*%s([A-Za-z0-9-_.一-龥]+)[ \t]*=[ \t]*([rw]%s)[ \t]*$/m";

    /**
     *@var string matches %s=value form
     */
    private $reg_5 = "/^[ \t]*(%s)[ \t]*=[ \t]*(.*)[ \t]*$/m";

    /**
     *@var string matches %s=value form
     */
    private $reg_8 = "/^[ \t]*(%s)[ \t]*:[ \t]*(.*)[ \t]*$/m";

    /**
     *@var string matches %s= form
     */
    private $reg_6 = "/^[ \t]*(%s)[ \t]*=/m";

    /**
     *@var string match repository path stanza and its contents
     */
    private $reg_7 = "/^[ \t]*(\[(.*):(.*)\])((?!\n[ \t]*\[)[\s\S])*\n[ \t]*%s[ \t]*=[ \t]*([rw]+)[ \t]*$/m";

    /**
     *@var array authorization type and corresponding prefix/content relationship
     */
    private $array_objectType = [
        'user' => '',
        'group' => '@',
        'aliase' => '&',
        '*' => '\*',
        '$authenticated' => '\$authenticated',
        '$anonymous' => '\$anonymous'
    ];

    function __construct()
    {
    }

    /**
     *Perform trim operation on each key value of the data
     *
     *@param string $value
     *@param string $key
     *@return void
     */
    private function ArrayValueTrim(&$value, $key)
    {
        $value = trim($value);
    }

    /**
     *Remove the #disabled# operation for each key value of the data
     *
     *@param string $value
     *@param string $key
     *@return void
     */
    private function ArrayValueEnable(&$value, $key)
    {
        $REG_SVN_USER_DISABLED = '#disabled#';

        if (substr($value, 0, strlen($REG_SVN_USER_DISABLED)) == $REG_SVN_USER_DISABLED) {
            $value = substr($value, strlen($REG_SVN_USER_DISABLED));
        }
    }

    /**
     *storehouse
     *----------------------------------------------------------------------------------------------------------------------------------------------
     */

    /**
     *Generate corresponding matching rules based on whether to reverse, authorization object type, and authorization object name to match the scenario where $key = $value
     *
     *@param string $objectType authorization type user group aliases *$authenticated $anonymous
     *@param boolean $invert Whether to invert true false
     *@param string $objectName authorization name user1 group1 alias1 *$authenticated $anonymous (empty means no $key value specified)
     *@return array|int
     *
     *901 Unsupported authorization object type
     *array returns the reversed and non-reversed modes of $key
     */
    private function GetReg($objectType, $invert = false, $objectName = '')
    {
        //parameter check
        if (!in_array($objectType, array_keys($this->array_objectType))) {
            return 901;
        }

        //*no inversion
        $invert = $objectName == '*' ? false : $invert;

        //Object names should not contain inverted symbols ~
        $objectName = substr($objectName, 0, 1) == '~' ? substr($objectName, 1) : $objectName;

        $invert = $invert ? '~' : '';

        if ($objectType == '*') {
            $normal = sprintf($this->reg_3, $this->array_objectType[$objectType], '*');
            return [
                'normal' => $normal,
                'noInvert' => $normal,
                'hasInvert' => $normal,
                'quote_normal' => $normal,
                'quote_noInvert' => $normal,
                'quote_hasInvert' => $normal,
            ];
        }

        if ($objectType == '$authenticated' || $objectType == '$anonymous') {
            return [
                'normal' => sprintf($this->reg_3, $invert . $this->array_objectType[$objectType], '*'),
                'noInvert' => sprintf($this->reg_3, $this->array_objectType[$objectType], '*'),
                'hasInvert' => sprintf($this->reg_3, '~' . $this->array_objectType[$objectType], '*'),
                'quote_normal' => sprintf($this->reg_3, $invert . $this->array_objectType[$objectType], '*'),
                'quote_noInvert' => sprintf($this->reg_3, $this->array_objectType[$objectType], '*'),
                'quote_hasInvert' => sprintf($this->reg_3, '~' . $this->array_objectType[$objectType], '*'),
            ];
        }

        return [
            'normal' => sprintf($objectName == '' ? $this->reg_4 : $this->reg_3, $invert . $this->array_objectType[$objectType] . $objectName, '*'),
            'noInvert' => sprintf($objectName == '' ? $this->reg_4 : $this->reg_3, $this->array_objectType[$objectType] . $objectName, '*'),
            'hasInvert' => sprintf($objectName == '' ? $this->reg_4 : $this->reg_3, '~' . $this->array_objectType[$objectType] . $objectName, '*'),
            'quote_normal' => sprintf($objectName == '' ? $this->reg_4 : $this->reg_3, $invert . $this->array_objectType[$objectType] . preg_quote($objectName), '*'),
            'quote_noInvert' => sprintf($objectName == '' ? $this->reg_4 : $this->reg_3, $this->array_objectType[$objectType] . preg_quote($objectName), '*'),
            'quote_hasInvert' => sprintf($objectName == '' ? $this->reg_4 : $this->reg_3, '~' . $this->array_objectType[$objectType] . preg_quote($objectName), '*'),
        ];
    }

    /**
     *Generate $key in the $key = $value scenario according to whether to reverse, authorization object type, authorization object name
     *
     *@param string $objectType authorization type user | group | aliase | *| $authenticated | $anonymous
     *@param boolean $invert Whether to invert true | false
     *@param string $objectName authorization name user1 | group1 | alias1 | *| $authenticated | $anonymous ($objectName cannot be empty)
     *@return array|int
     *
     *901 Unsupported authorization object type
     *601 $objectName cannot be empty
     *array returns the reversed and non-reversed modes of $key
     */
    private function GetKey($objectType, $invert = false, $objectName = '')
    {
        //parameter check
        if (!in_array($objectType, array_keys($this->array_objectType))) {
            return 901;
        }

        if ($objectName == '') {
            return 601;
        }

        //*no inversion
        $invert = $objectName == '*' ? false : $invert;

        //Object names should not contain inverted symbols ~
        $objectName = substr($objectName, 0, 1) == '~' ? substr($objectName, 1) : $objectName;

        $invert = $invert ? '~' : '';

        if ($objectType == '*') {
            return [
                'normal' => '*',
                'noInvert' => '*',
                'hasInvert' => '*',
                'quote_normal' => '*',
                'quote_noInvert' => '*',
                'quote_hasInvert' => '*'
            ];
        }

        if ($objectType == '$authenticated' || $objectType == '$anonymous') {
            return [
                'normal' => $invert . $objectName,
                'noInvert' => $objectName,
                'hasInvert' => '~' . $objectType,
                'quote_normal' => $invert . $objectName,
                'quote_noInvert' => $objectName,
                'quote_hasInvert' => '~' . $objectType,
            ];
        }

        return [
            'normal' => $invert . $this->array_objectType[$objectType] . $objectName,
            'noInvert' => $this->array_objectType[$objectType] . $objectName,
            'hasInvert' => '~' . $this->array_objectType[$objectType] . $objectName,
            'quote_normal' => $invert . $this->array_objectType[$objectType] . preg_quote($objectName),
            'quote_noInvert' => $this->array_objectType[$objectType] . preg_quote($objectName),
            'quote_hasInvert' => '~' . $this->array_objectType[$objectType] . preg_quote($objectName),
        ];
    }

    /**
     *Get the permission list of objects under the specified warehouse path
     *If no object is specified, the permission list of all objects will be obtained
     *
     *@param string $authzContent
     *@param string $repName
     *@param string $repPath
     *@param string $objectType authorization object user | group | alias | *| $authenticated | $anonymous
     *@return array|int
     *
     *Note*Reversal is not supported
     *
     *751 The warehouse path does not exist
     *752 Warehouse path needs to start with /
     *901 Unsupported authorization object type
     */
    public function GetRepPathPri($authzContent, $repName, $repPath, $objectType = '')
    {
        //does not start with /
        if (substr($repPath, 0, 1) != '/') {
            return 752;
        }

        //handle the end of the path
        if ($repPath != '/') {
            $repPath = rtrim($repPath, '/');
        }

        if ($objectType == '') {
            $regArray = [
                //for *
                [
                    'type' => '*',
                    'invert' => false,
                    'reg' => sprintf($this->reg_3, $this->array_objectType['*'], '*')
                ],

                //for $authenticated and with or without inversion
                [
                    'type' => '$authenticated',
                    'invert' => false,
                    'reg' => sprintf($this->reg_3, $this->array_objectType['$authenticated'], '*')
                ],
                [
                    'type' => '$authenticated',
                    'invert' => true,
                    'reg' => sprintf($this->reg_3, '~' . $this->array_objectType['$authenticated'], '*')
                ],

                //for $anonymous and with or without inversion
                [
                    'type' => '$anonymous',
                    'invert' => false,
                    'reg' => sprintf($this->reg_3, $this->array_objectType['$anonymous'], '*')
                ],
                [
                    'type' => '$anonymous',
                    'invert' => true,
                    'reg' => sprintf($this->reg_3, '~' . $this->array_objectType['$anonymous'], '*')
                ],

                //for other and with or without inversion
                [
                    'type' => 'user',
                    'invert' => false,
                    'reg' => sprintf($this->reg_4, $this->array_objectType['user'], '*')
                ],
                [
                    'type' => 'user',
                    'invert' => true,
                    'reg' => sprintf($this->reg_4, '~' . $this->array_objectType['user'], '*')
                ],
                [
                    'type' => 'group',
                    'invert' => false,
                    'reg' => sprintf($this->reg_4, $this->array_objectType['group'], '*')
                ],
                [
                    'type' => 'group',
                    'invert' => true,
                    'reg' => sprintf($this->reg_4, '~' . $this->array_objectType['group'], '*')
                ],
                [
                    'type' => 'aliase',
                    'invert' => false,
                    'reg' => sprintf($this->reg_4, $this->array_objectType['aliase'], '*')
                ],
                [
                    'type' => 'aliase',
                    'invert' => true,
                    'reg' => sprintf($this->reg_4, '~' . $this->array_objectType['aliase'], '*')
                ],
            ];
        } else {
            //type check
            if (!in_array($objectType, array_keys($this->array_objectType))) {
                return 901;
            }
        }

        preg_match_all(sprintf($this->reg_2, preg_quote($repName) . ':' . preg_quote($repPath, '/')), $authzContent, $authzContentPreg);
        if (preg_last_error() != 0) {
            return preg_last_error();
        }
        if (array_key_exists(0, $authzContentPreg[0])) {
            $temp1 = trim($authzContentPreg[1][0]);
            if (empty($temp1)) {
                return [];
            } else {
                if ($objectType == '') {
                    $result = [];
                    foreach ($regArray as $reg) {
                        preg_match_all($reg['reg'], $authzContentPreg[1][0], $resultPreg);
                        if (preg_last_error() != 0) {
                            return preg_last_error();
                        }
                        foreach ($resultPreg[0] as $key => $value) {
                            $result[] = [
                                'objectType' => $reg['type'],
                                'objectName' => $resultPreg[1][$key],
                                'objectPri' => trim($resultPreg[2][$key]) == '' ? 'no' : $resultPreg[2][$key],
                                'invert' => $reg['invert']
                            ];
                        }
                    }

                    return $result;
                } else {
                    $regArray = $this->GetReg($objectType);
                    if (is_numeric($regArray)) {
                        return $regArray;
                    }

                    preg_match_all($regArray['noInvert'], $authzContentPreg[1][0], $resultPreg1);
                    if (preg_last_error() != 0) {
                        return preg_last_error();
                    }
                    preg_match_all($regArray['hasInvert'], $authzContentPreg[1][0], $resultPreg2);
                    if (preg_last_error() != 0) {
                        return preg_last_error();
                    }
                    $result = [];
                    foreach ($resultPreg1[0] as $key => $value) {
                        array_push($result, [
                            'objectType' => $objectType,
                            'objectName' => $resultPreg1[1][$key],
                            'objectPri' => trim($resultPreg1[2][$key]) == '' ? 'no' : $resultPreg1[2][$key],
                            'invert' => 0
                        ]);
                    }
                    foreach ($resultPreg2[0] as $key => $value) {
                        if ($objectType == '*') {
                            break;
                        }
                        array_push($result, [
                            'objectType' => $objectType,
                            'objectName' => $resultPreg2[1][$key],
                            'objectPri' => trim($resultPreg2[2][$key]) == '' ? 'no' : $resultPreg2[2][$key],
                            'invert' => 1
                        ]);
                    }

                    return $result;
                }
            }
        } else {
            return 751;
        }
    }

    /**
     *Add permissions under a warehouse path
     *
     *@param string $authzContent
     *@param string $repName
     *@param string $repPath
     *@param string $objectType authorization object user | group | alias | *| $authenticated | $anonymous
     *@param boolean $invert Whether to invert true | false
     *@param string $objectName authorization name user1 | group1 | aliase1 | *| $authenticated | $anonymous : no need to carry @ &
     *@param string $privilege permission [rw]+
     *@return string|int
     *
*751 The warehouse path does not exist
     *752 Warehouse path needs to start with /
     *901 Unsupported authorization object type
     *801 The permission record for this object already exists under the warehouse path
     *string normal
     */
    public function AddRepPathPri($authzContent, $repName, $repPath, $objectType, $objectName, $privilege, $invert = false)
    {
        //does not start with /
        if (substr($repPath, 0, 1) != '/') {
            return 752;
        }

        //handle the end of the path
        if ($repPath != '/') {
            $repPath = rtrim($repPath, '/');
        }

        //$objectType check
        if (!in_array($objectType, array_keys($this->array_objectType))) {
            return 901;
        }

        //Handle object names and reverse relationships
        $objectKey = $this->GetKey($objectType, $invert, $objectName);
        if (is_numeric($objectKey)) {
            return $objectKey;
        }

        preg_match_all(sprintf($this->reg_2, preg_quote($repName) . ':' . preg_quote($repPath, '/')), $authzContent, $authzContentPreg);
        if (preg_last_error() != 0) {
            return preg_last_error();
        }
        if (array_key_exists(0, $authzContentPreg[0])) {
            $temp1 = trim($authzContentPreg[1][0]);
            if (empty($temp1)) {
                return str_replace($authzContentPreg[0][0], "[$repName:$repPath]\n" . $objectKey['normal'] . "=$privilege\n", $authzContent);
            } else {
                $regArray = $this->GetReg($objectType, $invert, $objectName);
                if (is_numeric($regArray)) {
                    return $regArray;
                }

                preg_match_all($regArray['quote_hasInvert'], $authzContentPreg[1][0], $resultPreg1);
                if (preg_last_error() != 0) {
                    return preg_last_error();
                }
                preg_match_all($regArray['quote_noInvert'], $authzContentPreg[1][0], $resultPreg2);
                if (preg_last_error() != 0) {
                    return preg_last_error();
                }

                //If there is a match in reverse or non-reverse, the prompt exists and returns
                if (array_key_exists(0, $resultPreg1[0]) || array_key_exists(0, $resultPreg2[0])) {
                    return 801;
                }

                return str_replace($authzContentPreg[0][0], trim($authzContentPreg[0][0]) . "\n" . $objectKey['normal'] . "=$privilege\n", $authzContent);
            }
        } else {
            return 751;
        }
    }

    /**
     *Modify permissions for a warehouse path
     *Including modifying read and write permissions and modifying reverse
     *
     *@param string $authzContent
     *@param string $repName
     *@param string $repPath
     *@param string $objectType authorization object user | group | alias | *| $authenticated | $anonymous
     *@param boolean $invert Whether to invert true | false
     *@param string $objectName authorization name user1 | group1 | aliase1 | *| $authenticated | $anonymous : no need to carry @ &
     *@param string $privilege permission [rw]+
     *@return string|int
*
     *751 The warehouse path does not exist
     *752 Warehouse path needs to start with /
     *901 Unsupported authorization object type
     *701 There is no permission record for this object under the warehouse path
     *string normal
     */
    public function EditRepPathPri($authzContent, $repName, $repPath, $objectType, $objectName, $privilege, $invert = false)
    {
        //does not start with /
        if (substr($repPath, 0, 1) != '/') {
            return 752;
        }

        //handle the end of the path
        if ($repPath != '/') {
            $repPath = rtrim($repPath, '/');
        }

        //$objectType check
        if (!in_array($objectType, array_keys($this->array_objectType))) {
            return 901;
        }

        //Handle object names and reverse relationships
        $objectKey = $this->GetKey($objectType, $invert, $objectName);
        if (is_numeric($objectKey)) {
            return $objectKey;
        }

        preg_match_all(sprintf($this->reg_2, preg_quote($repName) . ':' . preg_quote($repPath, '/')), $authzContent, $authzContentPreg);
        if (preg_last_error() != 0) {
            return preg_last_error();
        }
        if (array_key_exists(0, $authzContentPreg[0])) {
            $temp1 = trim($authzContentPreg[1][0]);
            if (empty($temp1)) {
                return str_replace($authzContentPreg[0][0], "[$repName:$repPath]\n" . $objectKey['normal'] . "=$privilege\n", $authzContent);
            } else {
                $regArray = $this->GetReg($objectType, $invert, $objectName);
                if (is_numeric($regArray)) {
                    return $regArray;
                }

                preg_match_all($regArray['quote_hasInvert'], $authzContentPreg[1][0], $resultPreg1);
                if (preg_last_error() != 0) {
                    return preg_last_error();
                }
                preg_match_all($regArray['quote_noInvert'], $authzContentPreg[1][0], $resultPreg2);
                if (preg_last_error() != 0) {
                    return preg_last_error();
                }

                //If neither reversal nor non-reversal exists, return no such path record prompt
                if (!array_key_exists(0, $resultPreg1[0]) && !array_key_exists(0, $resultPreg2[0])) {
                    return 701;
                }

                if (array_key_exists(0, $resultPreg1[0])) {
                    /**
                     *Now reversed
                     *If the non-inversion state is passed in, the state needs to be modified to non-inversion
                     */
                    if ($invert) {
                        /**
                         *No need to modify the inversion state
                         *Then modify the read and write permissions
                         */
                        return str_replace($authzContentPreg[0][0], "[$repName:$repPath]\n" . trim(preg_replace($regArray['quote_hasInvert'], $objectKey['hasInvert'] . "=$privilege", $authzContentPreg[1][0])), $authzContent);
                    } else {
                        /**
                         *It is necessary to modify the reversal state to remove the reversal
                         *Then modify the read and write permissions
                         */
                        return str_replace($authzContentPreg[0][0], "[$repName:$repPath]\n" . trim(preg_replace($regArray['quote_hasInvert'], $objectKey['noInvert'] . "=$privilege", $authzContentPreg[1][0])), $authzContent);
                    }
                } elseif (array_key_exists(0, $resultPreg2[0])) {
                    /**
                     *now non-inverted
                     *If the reverse state is passed in, the state needs to be modified to reverse
                     */
                    if ($invert) {
                        /**
                         *Need to modify the reversal status to add reversal
                         *Then modify the read and write permissions
                         */
                        return str_replace($authzContentPreg[0][0], "[$repName:$repPath]\n" . trim(preg_replace($regArray['quote_noInvert'], $objectKey['hasInvert'] . "=$privilege", $authzContentPreg[1][0])), $authzContent);
                    } else {
                        /**
                         *No need to modify the inversion state
                         *Then modify the read and write permissions
                         */
                        return str_replace($authzContentPreg[0][0], "[$repName:$repPath]\n" . trim(preg_replace($regArray['quote_noInvert'], $objectKey['noInvert'] . "=$privilege", $authzContentPreg[1][0])), $authzContent);
                    }
                }
            }
        } else {
            return 751;
        }
    }

    /**
     *Delete permissions for a warehouse path
     *
     *@param string $authzContent
     *@param string $repName
     *@param string $repPath
     *@param string $objectType authorization object user | group | alias | *| $authenticated | $anonymous
     *@param string $objectName authorization name user1 | group1 | aliase1 | *| $authenticated | $anonymous : no need to carry @ &
     *@return string|int
     *
     *751 The warehouse path does not exist
     *752 Warehouse path needs to start with /
     *901 Unsupported authorization object type
     *701 There is no permission record for this object under the warehouse path
*string normal
     */
    public function DelRepPathPri($authzContent, $repName, $repPath, $objectType, $objectName)
    {
        //does not start with /
        if (substr($repPath, 0, 1) != '/') {
            return 752;
        }

        //handle the end of the path
        if ($repPath != '/') {
            $repPath = rtrim($repPath, '/');
        }

        //$objectType check
        if (!in_array($objectType, array_keys($this->array_objectType))) {
            return 901;
        }

        preg_match_all(sprintf($this->reg_2, preg_quote($repName) . ':' . preg_quote($repPath, '/')), $authzContent, $authzContentPreg);
        if (preg_last_error() != 0) {
            return preg_last_error();
        }
        if (array_key_exists(0, $authzContentPreg[0])) {
            $temp1 = trim($authzContentPreg[1][0]);
            if (empty($temp1)) {
                return 701;
            } else {
                $regArray = $this->GetReg($objectType, false, $objectName);
                if (is_numeric($regArray)) {
                    return $regArray;
                }

                preg_match_all($regArray['quote_hasInvert'], $authzContentPreg[1][0], $resultPreg1);
                if (preg_last_error() != 0) {
                    return preg_last_error();
                }
                preg_match_all($regArray['quote_noInvert'], $authzContentPreg[1][0], $resultPreg2);
                if (preg_last_error() != 0) {
                    return preg_last_error();
                }

                //If neither reversal nor non-reversal exists, return no such path record prompt
                if (!array_key_exists(0, $resultPreg1[0]) && !array_key_exists(0, $resultPreg2[0])) {
                    return 701;
                }

                if (array_key_exists(0, $resultPreg1[0])) {
                    return str_replace($authzContentPreg[0][0], "[$repName:$repPath]\n" . trim(preg_replace($regArray['quote_hasInvert'], '', $authzContentPreg[1][0])), $authzContent);
                } elseif (array_key_exists(0, $resultPreg2[0])) {
                    return str_replace($authzContentPreg[0][0], "[$repName:$repPath]\n" . trim(preg_replace($regArray['quote_noInvert'], '', $authzContentPreg[1][0])), $authzContent);
                }
            }
        } else {
            return 751;
        }
    }

    /**
     *Write the warehouse path to the configuration file
     *
     *@param string $authzContent
     *@param string $repName
     *@param string $repPath
     *@return string|int
     *
     *851 The warehouse path already exists
     *752 Warehouse path needs to start with /
     *string normal
     */
    public function WriteRepPathToAuthz($authzContent, $repName, $repPath)
    {
        //does not start with /
        if (substr($repPath, 0, 1) != '/') {
            return 752;
        }

        //handle the end of the path
        if ($repPath != '/') {
            $repPath = rtrim($repPath, '/');
        }

        preg_match_all(sprintf($this->reg_2, preg_quote($repName) . ':' . preg_quote($repPath, '/')), $authzContent, $authzContentPreg);
        if (preg_last_error() != 0) {
            return preg_last_error();
        }
        if (array_key_exists(0, $authzContentPreg[0])) {
            return 851;
        } else {
            return $authzContent . "\n[$repName:$repPath]\n";
        }
    }

    /**
     *Delete the specified path of the specified warehouse from the configuration file
     *
     *@param string $authzContent
     *@param string $repName
     *@param string $repPath
     *@return string|int
     *
     *751 The warehouse path does not exist (removed)
     *752 Warehouse path needs to start with /
     *string normal
     */
    public function DelRepPathFromAuthz($authzContent, $repName, $repPath)
    {
        //does not start with /
        if (substr($repPath, 0, 1) != '/') {
            return 752;
        }

        //handle the end of the path
        if ($repPath != '/') {
            $repPath = rtrim($repPath, '/');
        }

        preg_match_all(sprintf($this->reg_2, preg_quote($repName) . ':' . preg_quote($repPath, '/')), $authzContent, $authzContentPreg);
        if (preg_last_error() != 0) {
            return preg_last_error();
        }
        if (array_key_exists(0, $authzContentPreg[0])) {
            return str_replace($authzContentPreg[0][0], '', $authzContent);
        } else {
            return 751;
        }
    }

    /**
     *Remove all paths to the specified repository from the configuration file
     *
     *@param string $authzContent
     *@param string $repName
     *@return string|int
     *
     *751 The warehouse path has been deleted
     *string normal
     */
    public function DelRepFromAuthz($authzContent, $repName)
    {
        preg_match_all(sprintf($this->reg_2, preg_quote($repName) . ':' . '.*'), $authzContent, $authzContentPreg);
        if (preg_last_error() != 0) {
            return preg_last_error();
        }
        if (array_key_exists(0, $authzContentPreg[0])) {
            foreach ($authzContentPreg[0] as $key => $value) {
                $authzContent = str_replace($value, '', $authzContent);
            }
            return $authzContent;
        } else {
            return 751;
        }
    }

    /**
     *Get a list of all repositories from the configuration file
     *
     *@param string $authzContent
     *@return array
     *
     *array normal
     */
    public function GetRepListFromAuthz($authzContent)
    {
        preg_match_all(sprintf($this->reg_2, '(.*?)' . ':' . '.*?'), $authzContent, $authzContentPreg);
        if (preg_last_error() != 0) {
            return preg_last_error();
        }
        return array_values(array_unique($authzContentPreg[1]));
    }

    /**
     *Modify the warehouse name from the configuration file
     *Include the name of the warehouse that modifies all paths of the warehouse
     *Did not verify whether the warehouse to be modified already exists. It needs to be verified by the upper layer function. It is not within the scope of work
     *
     *@param $authzContent
     *@param $oldRepName
     *@param $newRepName
     *@return array|int
     *
     *740 The warehouse path does not exist
     *array normal
     */
    public function UpdRepFromAuthz($authzContent, $oldRepName, $newRepName)
    {
        preg_match_all(sprintf($this->reg_2, preg_quote($oldRepName) . ':' . '(.*?)'), $authzContent, $authzContentPreg);
        if (preg_last_error() != 0) {
            return preg_last_error();
        }
        if (array_key_exists(0, $authzContentPreg[1])) {
            foreach ($authzContentPreg[0] as $key => $value) {
                $authzContent = str_replace($value, '[' . $newRepName . ':' . $authzContentPreg[1][$key] . ']' . $authzContentPreg[2][$key], $authzContent);
            }
            return $authzContent;
        } else {
            return 740;
        }
    }

    /**
     *group
     *----------------------------------------------------------------------------------------------------------------------------------------------
     */

    /**
     *add group
     *
     *@param $authzContent
     *@param $groupName
     *@return array|int
     *
     *612 File format error (the [groups] flag does not exist)
     *820 Group already exists
     *string normal
     */
    public function AddGroup($authzContent, $groupName)
    {
        $groupName = trim($groupName);
        preg_match_all(sprintf($this->reg_2, 'groups'), $authzContent, $authzContentPreg);
        if (preg_last_error() != 0) {
            return preg_last_error();
        }
        if (array_key_exists(0, $authzContentPreg[0])) {
            preg_match_all(sprintf($this->reg_5, preg_quote($groupName)), $authzContentPreg[1][0], $resultPreg);
            if (preg_last_error() != 0) {
                return preg_last_error();
            }
            if (array_key_exists(0, $resultPreg[0])) {
                return 820;
            } else {
                return preg_replace(sprintf($this->reg_2, 'groups'), trim($authzContentPreg[0][0]) . "\n$groupName=\n", $authzContent);
            }
        } else {
            return 612;
        }
    }

    /**
     *Remove user|group|alias and its inverse from all repository paths and groups
     *
     *@param $authzContent
     *@param $objectName
     *@param $type
     *@return array|int
     *
     *612 File format error (the [groups] flag does not exist)
     *901 Unsupported authorization object type
     *string normal
     */
    public function DelObjectFromAuthz($authzContent, $objectName, $objectType)
    {
        $objectName = trim($objectName);

        if ($objectType == 'user') {
        } elseif ($objectType == 'group') {
            $objectName = "@$objectName";
        } elseif ($objectType == 'aliase') {
            $objectName = "&$objectName";
        } else {
            return 901;
        }

        //Delete from the global warehouse path
        $authzContent = preg_replace(sprintf($this->reg_5, "(" . preg_quote($objectName) . ")|(~" . preg_quote($objectName) . ")"), '', $authzContent);

        //Remove from the [groups] section
        preg_match_all(sprintf($this->reg_2, 'groups'), $authzContent, $authzContentPreg);
        if (preg_last_error() != 0) {
            return preg_last_error();
        }
        if (array_key_exists(0, $authzContentPreg[0])) {
            preg_match_all(sprintf($this->reg_5, '[A-Za-z0-9-_.一-龥]+'), $authzContentPreg[1][0], $resultPreg);
            if (preg_last_error() != 0) {
                return preg_last_error();
            }
            $groupContent = "";
            foreach ($resultPreg[1] as $key => $groupStr) {
                if ($objectType == 'group') {
                    //delete the lvalue
                    if ($groupStr == substr($objectName, 1)) {
                        continue;
                    }
                }
                $userGroupStr = trim($resultPreg[2][$key]);
                $groupContent .= "$groupStr=";
                $userGroupArray = $userGroupStr == '' ? [] : explode(',', $userGroupStr);
                array_walk($userGroupArray, [$this, 'ArrayValueTrim']);
                //remove from rvalue
                foreach ($userGroupArray as $key => $value) {
                    if ($value == $objectName) {
                        unset($userGroupArray[$key]);
                        //break;
                    }
                }
                $groupContent .= implode(',', $userGroupArray) . "\n";
            }
            return preg_replace(sprintf($this->reg_2, 'groups'), "[groups]\n$groupContent", $authzContent);
        } else {
            return 612;
        }
    }

    /**
     *Modify users, groups, aliases and their inversions from all warehouse paths and groups
     *
     *@param $authzContent
     *@param $oldObjectName
     *@param $newObjectName
     *@param $objectType
     *@return int|string
     *
     *611 authz file format error ([aliases] flag does not exist)
     *612 The format of the authz file is wrong ([groups] does not exist)
     *901 Unsupported authorization object type
     *821 The new group to modify already exists
     *831 The new alias to modify already exists
     *731 The alias to modify does not exist
     *string normal
     */
    public function UpdObjectFromAuthz($authzContent, $oldObjectName, $newObjectName, $objectType)
    {
        $oldObjectName = trim($oldObjectName);
        $newObjectName = trim($newObjectName);

        if ($objectType == 'user') {
        } elseif ($objectType == 'group') {
            $oldObjectName = "@$oldObjectName";
            $newObjectName = "@$newObjectName";
        } elseif ($objectType == 'aliase') {
            $oldObjectName = "&$oldObjectName";
            $newObjectName = "&$newObjectName";
        } else {
            return 901;
        }

        //Modify users, groups, and aliases from the global warehouse path
        $authzContent = preg_replace(sprintf($this->reg_6, preg_quote($oldObjectName)), "$newObjectName=", $authzContent);
        $authzContent = preg_replace(sprintf($this->reg_6, '~' . preg_quote($oldObjectName)), "~$newObjectName=", $authzContent);

        //Modify aliases from the [aliases] section
        if ($objectType == 'aliase') {
            preg_match_all(sprintf($this->reg_2, 'aliases'), $authzContent, $authzContentPreg1);
            if (preg_last_error() != 0) {
                return preg_last_error();
            }
            if (array_key_exists(0, $authzContentPreg1[0])) {
                //The new alias to modify already exists
                preg_match_all(sprintf($this->reg_5, substr(preg_quote($newObjectName), 1)), $authzContentPreg1[1][0], $resultPreg1);
                if (preg_last_error() != 0) {
                    return preg_last_error();
                }
                if (array_key_exists(0, $resultPreg1[0])) {
                    return 831;
                }
                //Continue processing
                preg_match_all(sprintf($this->reg_5, substr(preg_quote($oldObjectName), 1)), $authzContentPreg1[1][0], $resultPreg1);
                if (preg_last_error() != 0) {
                    return preg_last_error();
                }
                if (array_key_exists(0, $resultPreg1[0])) {
                    $authzContent = preg_replace(sprintf($this->reg_2, 'aliases'), "[aliases]\n" . trim(preg_replace(sprintf($this->reg_5, substr(preg_quote($oldObjectName), 1)), substr($newObjectName, 1) . '=' . $resultPreg1[2][0], $authzContentPreg1[1][0])) . "\n", $authzContent);
                } else {
                    //The alias to be modified does not exist
                    return 731;
                }
            } else {
                return 611;
            }
        }

        //Modify group from lvalue, modify group, alias, user from rvalue from [groups] section
        preg_match_all(sprintf($this->reg_2, 'groups'), $authzContent, $authzContentPreg2);
        if (preg_last_error() != 0) {
            return preg_last_error();
        }
        if (array_key_exists(0, $authzContentPreg2[0])) {
            preg_match_all(sprintf($this->reg_5, '[A-Za-z0-9-_.一-龥]+'), $authzContentPreg2[1][0], $resultPreg2);
            if (preg_last_error() != 0) {
                return preg_last_error();
            }
            if ($objectType == 'group') {
                //The new group to modify already exists
                if (in_array(substr($newObjectName, 1), $resultPreg2[1])) {
                    return 821;
                }
            }
            $groupContent = "";
            foreach ($resultPreg2[1] as $key => $groupStr) {
                if ($objectType == 'group') {
                    //Modify the lvalue
                    if ($groupStr == substr($oldObjectName, 1)) {
                        $groupStr = substr($newObjectName, 1);
                    }
                }
                $userGroupStr = trim($resultPreg2[2][$key]);
                $groupContent .= "$groupStr=";
                $userGroupArray = $userGroupStr == '' ? [] : explode(',', $userGroupStr);
                array_walk($userGroupArray, [$this, 'ArrayValueTrim']);
                //modify from rvalue
                foreach ($userGroupArray as $key => $value) {
                    if ($value == $oldObjectName) {
                        $userGroupArray[$key] = $newObjectName;
                        //break;
                    }
                }
                $groupContent .= implode(',', $userGroupArray) . "\n";
            }
            return preg_replace(sprintf($this->reg_2, 'groups'), "[groups]\n$groupContent", $authzContent);
        } else {
            return 612;
        }
    }

    /**
     *Get group information
     *If no group name is specified, all group information will be returned
     *
     *@param string $authzContent
     *@param string $groupName
     *@return array|int
     *
     *612 File format error (the [groups] flag does not exist)
     *720 The specified group does not exist
     *array normal
     */
    public function GetGroupInfo($authzContent, $groupName = '')
    {
        preg_match_all(sprintf($this->reg_2, 'groups'), $authzContent, $authzContentPreg);
        if (preg_last_error() != 0) {
            return preg_last_error();
        }
        if (array_key_exists(0, $authzContentPreg[0])) {
            $temp1 = trim($authzContentPreg[1][0]);
            if (empty($temp1)) {
                return $groupName == '' ? [] : 720;
            } else {
                $list = [];
                preg_match_all(sprintf($this->reg_5, $groupName == '' ? '[A-Za-z0-9-_.一-龥]+' : preg_quote($groupName)), $authzContentPreg[1][0], $resultPreg);
                if (preg_last_error() != 0) {
                    return preg_last_error();
                }
                if (empty($resultPreg[0])) {
                    return $groupName == '' ? [] : 720;
                }
                foreach ($resultPreg[1] as $key => $groupStr) {
                    $userGroupStr = trim($resultPreg[2][$key]);
                    $userGroupArray = $userGroupStr == '' ? [] : explode(',', $userGroupStr);
                    $item = [
                        'groupName' => $groupStr,
                        'include' => [
                            'users' => [
                                'count' => 0,
                                'list' => []
                            ],
                            'groups' => [
                                'count' => 0,
                                'list' => []
                            ],
                            'aliases' => [
                                'count' => 0,
                                'list' => []
                            ]
                        ]
                    ];
                    foreach ($userGroupArray as $value) {
                        $value = trim($value);
                        $prefix = substr($value, 0, 1);
                        if ($prefix == '@') {
                            $item['include']['groups']['list'][] = substr($value, 1);
                            $item['include']['groups']['count'] = $item['include']['groups']['count'] + 1;
                        } elseif ($prefix == '&') {
                            $item['include']['aliases']['list'][] = substr($value, 1);
                            $item['include']['aliases']['count'] = $item['include']['aliases']['count'] + 1;
                        } else {
                            $item['include']['users']['list'][] = $value;
                            $item['include']['users']['count'] = $item['include']['users']['count'] + 1;
                        }
                    }
                    $list[] = $item;
                }
                return $groupName == '' ? $list : (empty($list) ? 720 : $list[0]);
            }
        } else {
            return 612;
        }
    }

    /**
     *Empty the content under [groups]
     *
     *@param string $authzContent
     *@return string|int
     */
    public function ClearGroupSection($authzContent)
    {
        preg_match_all(sprintf($this->reg_2, 'groups'), $authzContent, $authzContentPreg);
        if (preg_last_error() != 0) {
            return preg_last_error();
        }
        if (array_key_exists(0, $authzContentPreg[0])) {
            return preg_replace(sprintf($this->reg_2, 'groups'), "[groups]\n", $authzContent);
        } else {
            return 612;
        }
    }

    /**
     *Get the group list where the specified object is located, including only the direct containment relationship
     *Non-recursive access
     *
     *@param $authzContent
     *@param $objectName
     *@param $objectType user|group|aliase
     *@return array|int
     *
     *612 File format error (the [groups] flag does not exist)
     *700 Object does not exist
     *901 Unsupported authorization object type
     *array normal
     */
    public function GetObjBelongGroupList($authzContent, $objectName, $objectType)
    {
        $objectName = trim($objectName);
        preg_match_all(sprintf($this->reg_2, 'groups'), $authzContent, $authzContentPreg);
        if (preg_last_error() != 0) {
            return preg_last_error();
        }
        if (array_key_exists(0, $authzContentPreg[0])) {
            $temp1 = trim($authzContentPreg[1][0]);
            if (empty($temp1)) {
                return [];
            } else {
                preg_match_all(sprintf($this->reg_5, '[A-Za-z0-9-_.一-龥]+'), $authzContentPreg[1][0], $resultPreg);
                if (preg_last_error() != 0) {
                    return preg_last_error();
                }

                if ($objectType == 'user') {
                    //no operation
                } elseif ($objectType == 'group') {
                    $objectName = "@$objectName";
                } elseif ($objectType == 'aliase') {
                    $objectName = "&$objectName";
                } else {
                    return 901;
                }

                $groupArray = [];
                foreach ($resultPreg[1] as $key => $groupStr) {
                    $userGroupStr = trim($resultPreg[2][$key]);
                    $userGroupArray = $userGroupStr == '' ? [] : explode(',', $userGroupStr);
                    array_walk($userGroupArray, [$this, 'ArrayValueTrim']);
                    if (in_array($objectName, $userGroupArray)) {
                        $groupArray[] = $groupStr;
                    }
                }

                return $groupArray;
            }
        } else {
            return 612;
        }
    }

    /**
     *Get all the groups where the group is located, including direct inclusion and indirect inclusion relationships
     *Get recursively
     *
     *@param string $groupName
     *@return array|int
     *
     *612 File format error (the [groups] flag does not exist)
     *720 The specified group does not exist
     *700 Object does not exist
     *901 Unsupported authorization object type
     */
    public function GetSvnGroupAllGroupList($authzContent, $groupName)
    {
        $parentGroupName = $groupName;

        //list of all groups
        //list of all groups
        $groupInfo = $this->GetGroupInfo($authzContent);
        if (is_numeric($groupInfo)) {
            return $groupInfo;
        }
        $allGroupList = array_column($groupInfo, 'groupName');

        //The group list where the group is located
        $groupGroupList = $this->GetObjBelongGroupList($authzContent, $parentGroupName, 'group');
        if (is_numeric($groupGroupList)) {
            return $groupGroupList;
        }

        //remaining group list
        $leftGroupList = array_diff($allGroupList, $groupGroupList);

        //Loop match
        loop:
        $userGroupListBack = $groupGroupList;
        foreach ($groupGroupList as $group1) {
            $newList = $this->GetObjBelongGroupList($authzContent, $group1, 'group');
            if (is_numeric($newList)) {
                return $newList;
            }
            foreach ($leftGroupList as $key2 => $group2) {
                if (in_array($group2, $newList)) {
                    array_push($groupGroupList, $group2);
                    unset($leftGroupList[$key2]);
                }
            }
        }
        if ($groupGroupList != $userGroupListBack) {
            goto loop;
        }

        return $groupGroupList;
    }

    /**
     *Get all the groups the user is in, including direct inclusion and indirect inclusion relationships
     *Get recursively
     *
     *@param $authzContent
     *@param $userName
     *@return array|int
     *
     *612 File format error (the [groups] flag does not exist)
     *700 Object does not exist
     *901 Unsupported authorization object type
     *array normal
     */
    public function GetUserBelongGroupList($authzContent, $userName)
    {
        //list of all groups
        $groupInfo = $this->GetGroupInfo($authzContent);
        if (is_numeric($groupInfo)) {
            return $groupInfo;
        }
        $allGroupList = array_column($groupInfo, 'groupName');

        //User group list
        $userGroupList = $this->GetObjBelongGroupList($authzContent, $userName, 'user');
        if (is_numeric($userGroupList)) {
            return $userGroupList;
        }

        //remaining group list
        $leftGroupList = array_diff($allGroupList, $userGroupList);

        //Loop matching until the user group with permissions related to the user is matched
        loop:
        $userGroupListBack = $userGroupList;
        foreach ($userGroupList as $group1) {
            $newList = $this->GetObjBelongGroupList($authzContent, $group1, 'group');
            if (is_numeric($newList)) {
                return $newList;
            }
            foreach ($leftGroupList as $key2 => $group2) {
                if (in_array($group2, $newList)) {
                    array_push($userGroupList, $group2);
                    unset($leftGroupList[$key2]);
                }
            }
        }
        if ($userGroupList != $userGroupListBack) {
            goto loop;
        }

        return $userGroupList;
    }

    /**
     *Add or remove contained objects for the group
     *Objects include: user, group, user alias
     *
     *@param $authzContent
     *@param $groupName
     *@param $objectName unsigned user, group, user alias
     *@param $objectType user|group|aliase
     *@param $actionType add|delete
     *@return int|string
     *
     *612 File format error (the [groups] flag does not exist)
     *720 Group does not exist
     *803 The object to be added already exists in this group
     *703 The object to be deleted does not exist in this group
     *901 invalid object type user|group|aliase
     *902 Invalid operation type add|delete
     *802 cannot operate groups with the same name
*string normal
     */
    public function UpdGroupMember($authzContent, $groupName, $objectName, $objectType, $actionType)
    {
        $groupName = trim($groupName);
        $objectName = trim($objectName);
        //Cannot add groups with the same name
        if ($objectType == 'group' && $groupName == $objectName) {
            return 802;
        }
        preg_match_all(sprintf($this->reg_2, 'groups'), $authzContent, $authzContentPreg);
        if (preg_last_error() != 0) {
            return preg_last_error();
        }
        if (array_key_exists(0, $authzContentPreg[0])) {
            $temp1 = trim($authzContentPreg[1][0]);
            if (empty($temp1)) {
                return 720;
            } else {
                preg_match_all(sprintf($this->reg_5, preg_quote($groupName)), $authzContentPreg[1][0], $resultPreg);
                if (preg_last_error() != 0) {
                    return preg_last_error();
                }
                if (array_key_exists(0, $resultPreg[0])) {
                    foreach ($resultPreg[1] as $key => $groupStr) {
                        $userGroupStr = trim($resultPreg[2][$key]);
                        $userGroupArray = $userGroupStr == '' ? [] : explode(',', $userGroupStr);
                        array_walk($userGroupArray, [$this, 'ArrayValueTrim']);

                        if ($objectType == 'user') {
                            //no operation
                        } elseif ($objectType == 'group') {
                            $objectName = "@$objectName";
                        } elseif ($objectType == 'aliase') {
                            $objectName = "&$objectName";
                        } else {
                            return 901;
                        }

                        if ($actionType == 'add') {
                            if (in_array($objectName, $userGroupArray)) {
                                return 803;
                            } else {
                                //add operation
                                $userGroupArray[] = $objectName;
                                $groupContent = "$groupStr=" . implode(',', $userGroupArray);

                                //replace and return
                                return preg_replace(sprintf($this->reg_2, 'groups'), "[groups]\n" . trim(preg_replace(sprintf($this->reg_5, preg_quote($groupName)), $groupContent, $authzContentPreg[1][0])) . "\n", $authzContent);
                            }
                        } elseif ($actionType == 'delete') {
                            if (in_array($objectName, $userGroupArray)) {
                                //delete operation
                                unset($userGroupArray[array_search($objectName, $userGroupArray)]);
                                $groupContent = "$groupStr=" . implode(',', $userGroupArray);

                                //replace and return
                                return preg_replace(sprintf($this->reg_2, 'groups'), "[groups]\n" . trim(preg_replace(sprintf($this->reg_5, preg_quote($groupName)), $groupContent, $authzContentPreg[1][0])) . "\n", $authzContent);
                            } else {
                                return 703;
                            }
                        } else {
                            return 902;
                        }
                    }
                } else {
                    return 720;
                }
            }
        } else {
            return 612;
        }
    }

    /**
     *Get the list of warehouse paths with permissions for the group
     *
     *@param string $authzContent
     *@param string $groupName
     *@return array
     */
    public function GetGroupHasPri($authzContent, $groupName)
    {
        preg_match_all(sprintf($this->reg_7, "(@" . preg_quote($groupName) . ")"), $authzContent, $authzContentPreg);
        if (preg_last_error() != 0) {
            return preg_last_error();
        }

        $result = [];
        foreach ($authzContentPreg[5] as $key => $value) {
            $result[] = [
                'repName' => $authzContentPreg[2][$key],
                'priPath' => $authzContentPreg[3][$key],
                'repPri' => $authzContentPreg[6][$key],
            ];
        }

        return $result;
    }

    /**
     *user
     *----------------------------------------------------------------------------------------------------------------------------------------------
     */

    /**
     *Get the list of warehouse paths that the user has permission
     *
     *@param $authzContent
     *@param $userName
     *@return array
     *
     *612 File format error (the [groups] flag does not exist)
     *700 Object does not exist
     *901 Unsupported authorization object type
     *array normal
     */
    public function GetUserAllPri($authzContent, $userName)
    {
        /**
         *[a:/]
         *user1=[rw]+
         *
         *[b:/]
         *# user2 refers to all non-user1 users
         *~user2=[rw]+
         *needs to filter out ~user1=[rw]+ from the results
         *
         *[c:/]
         **=[rw]+
         *
         *[d:/]
         *# Indicates that anonymous users have [rw]+ but anonymous submission of modifications requires authenticated user identity
         *~$authenticated=[rw]+
         *
         *[e:/]
         *$anonymous=[rw]+
         *
         *[f:/]
         *$authenticated=[rw]+
         *
         *[g:/]
*# alias1 refers to all alias users not equal to user1
         *todo
         *~&aliase1=[rw]+
         *
         *[h:/]
         *# group1 directly or indirectly contains user1
         *@group1=[rw]+
         *
         *[i:/]
         *# group1 does not directly or indirectly contain user1
         *~@group1=[rw]+
         */

        //Non-capturing grouping reduces overhead
        $pregArray = [
            '(?:' . preg_quote($userName) . ')',
            '(?:~[A-Za-z0-9-_.一-龥]+)',
            '(?:\*)',
            '(?:~\$authenticated)',
            '(?:\$anonymous)',
            '(?:\$authenticated)',
            '(?:~&[A-Za-z0-9-_.一-龥]+)',
        ];

        //Get the list of all groups where user1 is located
        $part1 = $this->GetUserBelongGroupList($authzContent, $userName);
        if (is_numeric($part1)) {
            return $part1;
        }
        foreach ($part1 as $value) {
            $pregArray[] = '(?:@' . preg_quote($value) . ')';
        }

        //Get the group list where user1 is not
        $groupInfo = $this->GetGroupInfo($authzContent);
        if (is_numeric($groupInfo)) {
            return $groupInfo;
        }
        $all = array_column($groupInfo, 'groupName');
        $part2 = array_diff($all, $part1);
        foreach ($part2 as $value) {
            $pregArray[] = '(?:~@' . preg_quote($value) . ')';
        }

        preg_match_all(sprintf($this->reg_7, '(' . implode('|', $pregArray) . ')'), $authzContent, $authzContentPreg);
        if (preg_last_error() != 0) {
            return preg_last_error();
        }

        //Two problems that meet the conditions under the same path will be matched by one condition and will not be repeated, so there is no need to deduplicate

        //filter out ~user1=[rw]+ from the results
        $result = [];
        foreach ($authzContentPreg[5] as $key => $value) {
            if ($value == "~$userName") {
                unset($authzContentPreg[5][$key]);
            } else {
                $result[] = [
                    'repName' => $authzContentPreg[2][$key],
                    'priPath' => $authzContentPreg[3][$key],
                    'repPri' => $authzContentPreg[6][$key],
                    //'unique' => '' //Compatible with versions 2.3.3 and earlier, meaningless from version 2.3.3.1
                ];
            }
        }

        return $result;
    }

    /**
     *Add user
     *
     *@param $passwdContent
     *@param $userName
     *@param $userPass
     *@return int|string
     *
     *621 File format error (the [users] identifier does not exist)
     *810 User already exists
     *string normal
     */
    public function AddUser($passwdContent, $userName, $userPass)
    {
        $userName = trim($userName);
        $userPass = trim($userPass);
        preg_match_all(sprintf($this->reg_2, 'users'), $passwdContent, $passwdContentPreg);
        if (preg_last_error() != 0) {
            return preg_last_error();
        }
        if (array_key_exists(0, $passwdContentPreg[1])) {
            $temp1 = trim($passwdContentPreg[1][0]);
            if (empty($temp1)) {
                return preg_replace(sprintf($this->reg_2, 'users'), trim($passwdContentPreg[0][0]) . "\n$userName=$userPass\n", $passwdContent);
            } else {
                preg_match_all(sprintf($this->reg_5, '(' . $this->reg_1 . ')*[A-Za-z0-9-_.一-龥]+'), $passwdContentPreg[1][0], $resultPreg);
                if (preg_last_error() != 0) {
                    return preg_last_error();
                }
                array_walk($resultPreg[1], [$this, 'ArrayValueEnable']);
                if (in_array($userName, $resultPreg[1])) {
                    return 810;
                }
                return preg_replace(sprintf($this->reg_2, 'users'), trim($passwdContentPreg[0][0]) . "\n$userName=$userPass\n", $passwdContent);
            }
        } else {
            return 621;
        }
    }

    /**
     *Modify user's username from passwd file
     *Generally, there is no way to modify the user name. It is very necessary for a constant user to correspond to all the historical records of the SVN warehouse
     *
     *@param $passwdContent
     *@param $oldUserName
     *@param $newUserName
     *@return void
     *
     *621 File format error (the [users] identifier does not exist)
     *710 User does not exist
     *811 The new user to be modified already exists
     *string normal
     */
    public function UpdUserFromPasswd($passwdContent, $oldUserName, $newUserName, $isDisabledUser)
    {
        $oldUserName = trim($oldUserName);
        $newUserName = trim($newUserName);
        $oldUserName = $isDisabledUser ? ($this->reg_1 . preg_quote($oldUserName)) : preg_quote($oldUserName);
        $newUserName = $isDisabledUser ? ($this->reg_1 . $newUserName) : $newUserName;
        preg_match_all(sprintf($this->reg_2, 'users'), $passwdContent, $passwdContentPreg);
        if (preg_last_error() != 0) {
            return preg_last_error();
        }
        if (array_key_exists(0, $passwdContentPreg[1])) {
            $temp1 = trim($passwdContentPreg[1][0]);
            if (empty($temp1)) {
                return 710;
            } else {
                //Check if the target user already exists
                preg_match_all(sprintf($this->reg_5, '(' . $this->reg_1 . ')*[A-Za-z0-9-_.一-龥]+'), $passwdContentPreg[1][0], $resultPreg);
                if (preg_last_error() != 0) {
                    return preg_last_error();
                }
                array_walk($resultPreg[1], [$this, 'ArrayValueEnable']);
                if (in_array($newUserName, $resultPreg[1])) {
                    return 811;
                }
                //Continue processing
                preg_match_all(sprintf($this->reg_5, $oldUserName), $passwdContentPreg[1][0], $resultPreg);
                if (preg_last_error() != 0) {
                    return preg_last_error();
                }
                if (array_key_exists(0, $resultPreg[0])) {
                    return preg_replace(sprintf($this->reg_2, 'users'), "[users]\n" . trim(preg_replace(sprintf($this->reg_5, $oldUserName), $newUserName . '=' . $resultPreg[2][0], $passwdContentPreg[1][0])) . "\n", $passwdContent);
                } else {
                    return 710;
                }
            }
        } else {
            return 621;
        }
    }

    /**
     *Delete user from passwd file
     *
     *@param $passwdContent
     *@param $userName
     *@param $isDisabledUser
     *@return int|string
     *
     *621 File format error (the [users] identifier does not exist)
     *710 User does not exist
     *string normal
     */
    public function DelUserFromPasswd($passwdContent, $userName, $isDisabledUser = false)
    {
        $userName = trim($userName);
        $userName = $isDisabledUser ? ($this->reg_1 . preg_quote($userName)) : preg_quote($userName);
        preg_match_all(sprintf($this->reg_2, 'users'), $passwdContent, $passwdContentPreg);
        if (preg_last_error() != 0) {
            return preg_last_error();
        }
        if (array_key_exists(0, $passwdContentPreg[1])) {
            $temp1 = trim($passwdContentPreg[1][0]);
            if (empty($temp1)) {
                return 710;
            } else {
                preg_match_all(sprintf($this->reg_5, $userName), $passwdContentPreg[1][0], $resultPreg);
                if (preg_last_error() != 0) {
                    return preg_last_error();
                }
                if (array_key_exists(0, $resultPreg[0])) {
                    return preg_replace(sprintf($this->reg_2, 'users'), "[users]\n" . trim(preg_replace(sprintf($this->reg_5, $userName), '', $passwdContentPreg[1][0])) . "\n", $passwdContent);
                } else {
                    return 710;
                }
            }
        } else {
            return 621;
        }
    }

    /**
     *Get user information
     *If no user is specified, all user information will be returned
     *
     *@param $passwdContent
     *@param $userName
     *@return array|int
     *
     *621 File format error (the [users] identifier does not exist)
     *710 Specified user does not exist
     *array normal
     */
    public function GetUserInfo($passwdContent, $userName = '')
    {
        preg_match_all(sprintf($this->reg_2, 'users'), $passwdContent, $passwdContentPreg);
        if (preg_last_error() != 0) {
            return preg_last_error();
        }
        if (array_key_exists(0, $passwdContentPreg[1])) {
            $temp1 = trim($passwdContentPreg[1][0]);
            if (empty($temp1)) {
                return $userName == '' ? [] : 710;
            } else {
                preg_match_all(sprintf($this->reg_5, "($this->reg_1)*" . ($userName == '' ? '[A-Za-z0-9-_.一-龥]+' : preg_quote($userName))), $passwdContentPreg[1][0], $resultPreg);
                if (preg_last_error() != 0) {
                    return preg_last_error();
                }
                if (empty($resultPreg[0])) {
                    return $userName == '' ? [] : 710;
                }
                $result = [];
                foreach ($resultPreg[1] as $key => $value) {
                    $item = [];
                    if (substr($value, 0, strlen($this->reg_1)) == $this->reg_1) {
                        $item['userName'] = substr($value, strlen($this->reg_1));
                        $item['userPass'] = $resultPreg[3][$key];
                        $item['disabled'] = '1';
                    } else {
                        $item['userName'] = $value;
                        $item['userPass'] = $resultPreg[3][$key];
                        $item['disabled'] = '0';
                    }
                    $result[] = $item;
                }
                return $userName == '' ? $result : (empty($result) ? 710 : $result[0]);
            }
        } else {
            return 621;
        }
    }

    /**
     *Get user information http
     *If no user is specified, all user information will be returned
     *
     *@param string $passwdContent
     *@param string $userName
     *@return array
     *
     *710 Specified user does not exist
     *array normal
     */
    public function GetUserInfoHttp($passwdContent, $userName = '')
    {
        $passwdContent = trim($passwdContent);
        if (empty($passwdContent)) {
            return $userName == '' ? [] : 710;
        } else {
            preg_match_all(sprintf($this->reg_8, "($this->reg_1)*" . ($userName == '' ? '[A-Za-z0-9-_.一-龥]+' : preg_quote($userName))), $passwdContent, $resultPreg);
            if (preg_last_error() != 0) {
                return preg_last_error();
            }
            if (empty($resultPreg[0])) {
                return $userName == '' ? [] : 710;
            }
            $result = [];
            foreach ($resultPreg[1] as $key => $value) {
                $item = [];
                if (substr($value, 0, strlen($this->reg_1)) == $this->reg_1) {
                    $item['userName'] = substr($value, strlen($this->reg_1));
                    $item['userPass'] = $resultPreg[3][$key];
                    $item['disabled'] = '1';
                } else {
                    $item['userName'] = $value;
                    $item['userPass'] = $resultPreg[3][$key];
                    $item['disabled'] = '0';
                }
                $result[] = $item;
            }
            return $userName == '' ? $result : (empty($result) ? 710 : $result[0]);
        }
    }

    /**
     *Modify the password of the specified user
     *
     *@param string $passwdContent
     *@param string $userName
     *@param string $userPass
     *@param boolean $isDisabledUser
     *@return string|int
     *
     *621 File format error (the [users] identifier does not exist)
     *710 User does not exist
     *string normal
     */
    public function UpdUserPass($passwdContent, $userName, $userPass, $isDisabledUser = false)
    {
        $userName = trim($userName);
        $userName = $isDisabledUser ? ($this->reg_1 . $userName) : $userName;
        preg_match_all(sprintf($this->reg_2, 'users'), $passwdContent, $passwdContentPreg);
        if (preg_last_error() != 0) {
            return preg_last_error();
        }
        if (array_key_exists(0, $passwdContentPreg[1])) {
            $temp1 = trim($passwdContentPreg[1][0]);
            if (empty($temp1)) {
                return 710;
            } else {
                preg_match_all(sprintf($this->reg_5, preg_quote($userName)), $passwdContentPreg[1][0], $resultPreg);
                if (preg_last_error() != 0) {
                    return preg_last_error();
                }
                if (array_key_exists(0, $resultPreg[0])) {
                    return preg_replace(sprintf($this->reg_2, 'users'), "[users]\n" . trim(preg_replace(sprintf($this->reg_5, preg_quote($userName)), "$userName=$userPass", $passwdContentPreg[1][0])) . "\n", $passwdContent);
                } else {
                    return 710;
                }
            }
        } else {
            return 621;
        }
    }

    /**
     *Modify the password of the specified user http
     *
     *@param string $passwdContent
     *@param string $userName
     *@param string $userPass
     *@param boolean $isDisabledUser
     *@return string|int
     *
     *710 User does not exist
     *string normal
     */
    public function UpdUserPassHttp($passwdContent, $userName, $userPass, $isDisabledUser = false)
    {
        $userName = trim($userName);
        $userName = $isDisabledUser ? ($this->reg_1 . $userName) : $userName;

        $passwdContent = trim($passwdContent);
        if (empty($passwdContent)) {
            return 710;
        } else {
            preg_match_all(sprintf($this->reg_8, preg_quote($userName)), $passwdContent, $resultPreg);
            if (preg_last_error() != 0) {
                return preg_last_error();
            }
            if (array_key_exists(0, $resultPreg[0])) {
                return trim(preg_replace(sprintf($this->reg_8, preg_quote($userName)), "$userName:$userPass", $passwdContent)) . "\n";
            } else {
                return 710;
            }
        }
    }

    /**
     *Enable or disable users
     *
     *@param $passwdContent
     *@param $userName
     *@param $disable true originally enabled and now disabled false originally disabled and now enabled
     *@return int|string
     *
     *621 File format error (the [users] identifier does not exist)
     *710 User does not exist
     *string normal
     */
    public function UpdUserStatus($passwdContent, $userName, $disable = false)
    {
        $userName = trim($userName);
        preg_match_all(sprintf($this->reg_2, 'users'), $passwdContent, $passwdContentPreg);
        if (preg_last_error() != 0) {
            return preg_last_error();
        }
        if (array_key_exists(0, $passwdContentPreg[1])) {
            $temp1 = trim($passwdContentPreg[1][0]);
            if (empty($temp1)) {
                return 710;
            } else {
                $preg = $disable ? $userName : ($this->reg_1 . $userName);
                preg_match_all(sprintf($this->reg_5, preg_quote($preg)), $passwdContentPreg[1][0], $resultPreg);
                if (preg_last_error() != 0) {
                    return preg_last_error();
                }
                if (array_key_exists(0, $resultPreg[0])) {
                    $replace = ($disable ? $this->reg_1 : '') . $userName . '=' . $resultPreg[2][0];
                    return preg_replace(sprintf($this->reg_2, 'users'), "[users]\n" . trim(preg_replace(sprintf($this->reg_5, preg_quote($preg)), $replace, $passwdContentPreg[1][0])) . "\n", $passwdContent);
                } else {
                    return 710;
                }
            }
        } else {
            return 621;
        }
    }

    /**
     *Enable or disable user http
     *
     *@param $passwdContent
     *@param $userName
     *@param $disable true originally enabled and now disabled false originally disabled and now enabled
     *@return int|string
     *
     *710 User does not exist
     *string normal
     */
    public function UpdUserStatusHttp($passwdContent, $userName, $disable = false)
    {
        $userName = trim($userName);
        $passwdContent = trim($passwdContent);
        if (empty($passwdContent)) {
            return 710;
        } else {
            $preg = $disable ? $userName : ($this->reg_1 . $userName);
            preg_match_all(sprintf($this->reg_8, preg_quote($preg)), $passwdContent, $resultPreg);
            if (preg_last_error() != 0) {
                return preg_last_error();
            }
            if (array_key_exists(0, $resultPreg[0])) {
                $replace = ($disable ? $this->reg_1 : '') . $userName . ':' . $resultPreg[2][0];
                return trim(preg_replace(sprintf($this->reg_8, preg_quote($preg)), $replace, $passwdContent)) . "\n";
            } else {
                return 710;
            }
        }
    }

    /**
     *alias
     *----------------------------------------------------------------------------------------------------------------------------------------------
     */

    /**
     *Add an alias
     */
    public function AddAliase()
    {
    }

    /**
     *delete alias
     */
    public function DelAliase()
    {
    }

    /**
     *modify the alias
     */
    public function EditAliase()
    {
    }

    /**
     *Modify alias content
     */
    public function EditAliaseCon()
    {
    }

    /**
     *Enable or disable the specified alias
     */
    public function UpdAliaseStatus()
    {
    }

    /**
     *Get alias information
     *If no alias is specified, all alias information will be returned
     *
     *@param string $authzContent
     *@param string $aliaseName
     *@return array|int
     *
     *611 authz file format error ([aliases] flag does not exist)
     *730 The specified alias does not exist
     */
    public function GetAliaseInfo($authzContent, $aliaseName = '')
    {
        preg_match_all(sprintf($this->reg_2, 'aliases'), $authzContent, $authzContentPreg);
        if (preg_last_error() != 0) {
            return preg_last_error();
        }
        if (array_key_exists(0, $authzContentPreg[1])) {
            $temp1 = trim($authzContentPreg[1][0]);
            if (empty($temp1)) {
                return $aliaseName == '' ? [] : 730;
            } else {
                preg_match_all(sprintf($this->reg_5, ($aliaseName == '' ? '[A-Za-z0-9-_.一-龥]+' : preg_quote($aliaseName))), $authzContentPreg[1][0], $resultPreg);
                if (preg_last_error() != 0) {
                    return preg_last_error();
                }
                if (empty($resultPreg[0])) {
                    return 730;
                }
                $result = [];
                foreach ($resultPreg[1] as $key => $value) {
                    $item = [];
                    $item['aliaseName'] = $value;
                    $item['aliaseCon'] = $resultPreg[2][$key];
                    $result[] = $item;
                }
                return $aliaseName == '' ? $result : (empty($result) ? 730 : $result[0]);
            }
        } else {
            return 611;
        }
    }
}
