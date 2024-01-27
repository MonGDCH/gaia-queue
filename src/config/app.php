<?php

/*
|--------------------------------------------------------------------------
| 消息队列配置文件
|--------------------------------------------------------------------------
| 定义消息队列配置信息
|
*/

return [
    // 消费者进程存放目录路径
    'consumers_path' => APP_PATH . DIRECTORY_SEPARATOR . 'queue' . DIRECTORY_SEPARATOR . 'consumers' . DIRECTORY_SEPARATOR,
    // 命名空间
    'namespace' => '\\app\queue\consumers',
];
