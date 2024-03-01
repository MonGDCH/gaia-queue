<?php

/*
|--------------------------------------------------------------------------
| 消息队列Redis配置文件
|--------------------------------------------------------------------------
| 定义消息队列Redis配置信息
|
*/

return [
    // 链接的配置
    'default'   => 'default',
    // 配置信息
    'connections'   => [
        'default'   => [
            // 链接scheme，只支持redis
            'scheme'        => 'redis',
            // 链接host
            'host'          => env('QUEUE_HOST', env('REDIS_HOST', '127.0.0.1')),
            // 链接端口
            'port'          => env('QUEUE_PORT', env('REDIS_PORT', 6379)),
            // 链接密码
            'auth'          => env('QUEUE_AUTH', env('REDIS_AUTH', '')),
            // 自定义键前缀
            'prefix'        => env('QUEUE_PREFIX', env('REDIS_PREFIX', '')),
            // redis数据库
            'database'      => env('QUEUE_DB', 5),
            // 消费失败后，重试次数
            'max_attempts'  => env('QUEUE_ATTEMPTS', 5),
            // 重试间隔，单位秒
            'retry_seconds' => env('QUEUE_RETRY', 5)
        ]
    ],
];
