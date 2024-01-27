<?php

/*
|--------------------------------------------------------------------------
| 自定义进程 Queue 服务启动配置文件
|--------------------------------------------------------------------------
| 定义自定义进程 Queue 服务启动配置
|
*/

return [
    // 启用
    'enable'    => env('QUEUE_SERVER_ENABLE', false),
    // 进程配置
    'config'    => [
        // 监听协议端口，采用text协议，方便通信
        'listen'        => 'text://127.0.0.1:' . env('QUEUE_SERVER_PORT', 7123),
        // 额外参数
        'context'       => [],
        // 进程数
        'count'         => \gaia\App::cpuCount() * 4,
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
    ]
];
