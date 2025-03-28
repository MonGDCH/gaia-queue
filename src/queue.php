#!/usr/bin/env php
<?php

declare(strict_types=1);

use gaia\App;
use gaia\Gaia;
use support\Plugin;
use support\queue\process\Queue as QueueProcess;

/**
 * queue 应用启动入口
 *
 * Class Queue
 * @author Mon <985558837@qq.com>
 * @copyright Gaia
 * @version 1.0.0 2024-07-02 14:40:59
 */
class Queue
{
    /**
     * 应用名称
     *
     * @var string
     */
    protected $name = 'queue';

    /**
     * 启动进程
     *
     * @example 进程名 => 进程驱动类名, eg: ['test' => Test::class]
     * @var array
     */
    protected $process = [
        'queue' => QueueProcess::class
    ];

    /**
     * 开启插件支持
     *
     * @var boolean
     */
    protected $supportPlugin = true;

    /**
     * 构造方法
     */
    public function __construct()
    {
        // 加载composer autoload文件
        require_once __DIR__ . '/../vendor/autoload.php';
    }

    /**
     * 启动应用
     *
     * @return void
     */
    public function run()
    {
        if (empty($this->process)) {
            echo '未定义启动进程' . PHP_EOL;
            return;
        }
        if (empty($this->name)) {
            echo '未定义应用名称' . PHP_EOL;
            return;
        }

        // 初始化
        App::init($this->name);

        // 加载插件
        $this->supportPlugin && Plugin::register();

        // TODO 更多操作

        // 启动服务
        Gaia::instance()->runProcess($this->process);
    }

    /**
     * 获取应用名称
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * 获取应用启动进程
     *
     * @return array
     */
    public function getProcess(): array
    {
        return $this->process;
    }
}

// 启用应用
(new Queue)->run();
