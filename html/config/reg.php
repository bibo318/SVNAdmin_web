<?php
/*
 *@Author: bibo318
 *
 *@LastEditors: bibo318
 *
 *@Description: github: /bibo318
 */

/**
 *Regular matching rules
 */

return [
    /**
     *Verify the SVN warehouse name
     *
     *1. Can contain Chinese characters, letters, numbers, underscores, dashes, dots
     *2. Cannot start or end with a dot
     */
    'REG_SVN_REP_NAME' => "/^[A-Za-z0-9-_一-龥]+(\.+[A-Za-z0-9-_一-龥]+)*$/",

    /**
     *Verify SVN user name
     *
     *1. Can contain letters, numbers, underscores, dashes, dots
     *2. If the string contains spaces, it will not be matched
     */
    'REG_SVN_USER_NAME' => "/^[A-Za-z0-9-_.一-龥]+$/",

    /**
     *Verify SVN user group name
     *
     *1. Can contain letters, numbers, underscores, dashes, dots
     *2. If the string contains spaces, it will not be matched
     */
    'REG_SVN_GROUP_NAME' => "/^[A-Za-z0-9-_.一-龥]+$/",

    /**
     *Mailbox format verification
     */
    'REG_MAIL' => "/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,})$/",

    /**
     *Custom configuration file read
     *
     *%s => $key
     */
    'REG_CONFIG' => "/define\(\"*'*%s'*\"*\s*,\s*'*(.*?)'*\)/",

    /**
     *Match subversion version number
     */
    'REG_SUBVERSION_VERSION' => "/svnserve.*?\b([0-9.]+)\b/m",
];
