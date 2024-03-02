<?php

declare(strict_types=1);

namespace support\queue\consumers;

use mon\util\Instance;
use support\queue\QueueService;
use gaia\queue\ConsumerInterface;

/**
 * 演示消息队列
 * 
 * @author Mon <985558837@qq.com>
 * @version 1.0.0
 */
class DemoQueue implements ConsumerInterface
{
    use Instance;

    /**
     * 链接的队列配置名，空则使用默认队列配置
     *
     * @return string
     */
    public function connection(): string
    {
        return '';
    }

    /**
     * 监听的队列名
     *
     * @return string
     */
    public function queue(): string
    {
        return 'demo-queue';
    }

    /**
     * 处理函数
     *
     * @param mixed $data   队列参数
     * @return mixed
     */
    public function consume($data)
    {
        dd($data);
    }

    /**
     * 发送队列消息
     *
     * @param array $data       队列参数
     * @param integer $delay    队列延迟执行事件
     * @return bool 是否发送成功
     */
    public function sendQuery(array $data = [], int $delay = 0): bool
    {
        return QueueService::syncSend($this->queue(), $data, $delay, $this->connection());
    }
}
