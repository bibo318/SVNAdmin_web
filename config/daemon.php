<?php
/*
 * @Author: bibo318
 * 
 * @LastEditors: bibo318
 * 
 * @Description: github: /bibo318
 */

/**
 * 修改该配置文件后需要重启守护进程程序(svnadmind.php)
 */

return [
    /**
     * socket_read 和 socket_write 的最大传输字节(B)
     * 
     * 默认值 500 KB
     * 
     * 1MB = 1024KB = 1024*1024B
     */
    'socket_data_length' => 500 * 1024,

    /**
     * socket 处理并发的最大队列长度
     */
    'socket_listen_backlog' => 2000,
];
