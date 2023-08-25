<?php
/*
 *@Author: bibo318
 *
 *@LastEditors: bibo318
 *
 *@Description: github: /bibo318
 */

/**
 *After modifying the configuration file, you need to restart the daemon program (svnadmind.php)
 */

return [
    /**
     *The maximum transfer byte (B) of socket_read and socket_write
     *
     *Default value 500KB
     *
     *1MB = 1024KB = 1024*1024B
     */
    'socket_data_length' => 500 * 1024,

    /**
     *The socket handles the maximum queue length concurrently
     */
    'socket_listen_backlog' => 2000,
];
