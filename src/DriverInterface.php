<?php

declare(strict_types=1);

namespace gaia\queue;

/**
 * 队列消费完成事件回调接口
 * 
 * @author Mon <985558837@qq.com>
 * @version 1.0.0
 */
interface DriverInterface
{
    /**
     * 处理回调方法
     *
     * @param string $connection    连接标识
     * @param string $queue         队列标识
     * @param boolean $status       消费状态，true成功，false失败
     * @param string $result        消费结果
     * @param array $package        消费数据包
     * @return void
     */
    public function handeler(string $connection, string $queue, bool $status, string $result, array $package);
}
