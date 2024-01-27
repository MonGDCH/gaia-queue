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
    // 日志配置
    'log'   => [
        // 日志通道名
        'channel' => 'queue',
        // 日志配置
        'config'    => [
            // 解析器
            'format'    => [
                // 类名
                'handler'   => \mon\log\format\LineFormat::class,
                // 配置信息
                'config'    => [
                    // 日志是否包含级别
                    'level'         => true,
                    // 日志是否包含时间
                    'date'          => true,
                    // 时间格式，启用日志时间时有效
                    'date_format'   => 'Y-m-d H:i:s',
                    // 是否启用日志追踪
                    'trace'         => false,
                    // 追踪层级，启用日志追踪时有效
                    'layer'         => 3
                ]
            ],
            // 记录器
            'record'    => [
                // 类名
                'handler'   => \mon\log\record\FileRecord::class,
                // 配置信息
                'config'    => [
                    // 是否自动写入文件
                    'save'      => false,
                    // 写入文件后，清除缓存日志
                    'clear'     => true,
                    // 日志名称，空则使用当前日期作为名称       
                    'logName'   => '',
                    // 日志文件大小
                    'maxSize'   => 20480000,
                    // 日志目录
                    'logPath'   => RUNTIME_PATH . DIRECTORY_SEPARATOR . 'log' . DIRECTORY_SEPARATOR . 'queue',
                    // 日志滚动卷数   
                    'rollNum'   => 3
                ]
            ]
        ]
    ]
];
