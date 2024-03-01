<?php

/*
|--------------------------------------------------------------------------
| 消息队列配置文件
|--------------------------------------------------------------------------
| 定义消息队列配置信息
|
*/


return [
    // 是否启用消息队列
    'enable'            => false,
    // 消费者进程存放目录路径
    'consumers_path'    => PLUGIN_PATH . DIRECTORY_SEPARATOR . 'queue' . DIRECTORY_SEPARATOR . 'consumers' . DIRECTORY_SEPARATOR,
    // 命名空间
    'namespace'         => '\\plugins\queue\consumers',
    // 消息队列进程 Queue 配置
    'process'           => [
        // 监听协议端口，采用text协议，方便通信
        'listen'        => 'text://127.0.0.1:7123',
        // 额外参数
        'context'       => [],
        // 进程数
        'count'         => \gaia\App::cpuCount() * 2,
        // 通信协议，一般不需要修改
        'transport'     => 'tcp',
        // 进程用户
        'user'          => '',
        // 进程用户组
        'group'         => '',
        // 是否开启端口复用
        'reusePort'     => false,
        // 是否允许进程重载
        'reloadable'    => true,
    ],
    // 日志配置
    'log'           => [
        // 日志通道名
        'channel'   => 'queue',
        // 日志配置
        'config'    => [
            // 解析器
            'format'        => [
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
            'record'        => [
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
