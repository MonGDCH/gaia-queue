<?php

declare(strict_types=1);

namespace gaia\queue\driver;

use gaia\queue\DriverInterface;
use mon\log\Logger as LogLogger;

/**
 * Logger队列处理回调
 * 
 * @author Mon <985558837@qq.com>
 * @version 1.0.0
 */
class Logger implements DriverInterface
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
    public function handeler(string $connection, string $queue, bool $status, string $result, array $package)
    {
        // 运行时间
        $nowMsec = microtime(true);
        $running_time = 0;
        if (isset($package['consume_msec'])) {
            $running_time = $nowMsec - $package['consume_msec'];
        }
        $run_time = $package['consume_time'] ?? date('Y-m-d H:i:s', time());
        // 消费结果
        $status = $status ? 'Success' : 'Fail';

        $log = "[{$connection}] [{$queue}] [{$package['time']}] [{$run_time}] {$result} [runing_time: {$running_time}]";
        if ($status) {
            LogLogger::instance()->channel()->info($log);
        } else {
            LogLogger::instance()->channel()->error($log);
        }

        return true;
    }
}
