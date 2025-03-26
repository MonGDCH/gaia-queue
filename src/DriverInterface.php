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
     * @param array $package        消费数据包
     * @param boolean $status       消费状态，true成功，false失败
     * @param string $result        消费结果
     * @return void
     */
    public function handeler(array $package, bool $status, string $result = '');
}
