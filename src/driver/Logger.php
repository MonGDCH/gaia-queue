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
     * @param array $package        消费数据包
     * @param boolean $status       消费状态，true成功，false失败
     * @param string $result        消费结果
     * @return void
     */
    public function handeler(array $package, bool $status, string $result = '')
    {
        // 运行时间
        $nowMsec = microtime(true);
        $running_time = 0;
        if (isset($package['consume_msec'])) {
            $running_time = round($nowMsec - $package['consume_msec'], 6);
        }
        $run_time = $package['consume_time'] ?? date('Y-m-d H:i:s', time());
        // 消费结果
        $status = $status ? 'Success' : 'Fail';
        // 投递时间
        $send_time = date('Y-m-d H:i:s', $package['time']);
        // 投递数据
        $send_data = is_array($package['data']) ? json_encode($package['data'], JSON_UNESCAPED_UNICODE) : $package['data'];
        $log = "[{$package['connect']}] [{$package['queue']}] [{$send_time}] {$send_data} [{$run_time}] {$result} [runing_time: {$running_time}]";
        if ($status) {
            LogLogger::instance()->channel()->info($log);
        } else {
            LogLogger::instance()->channel()->error($log);
        }

        return true;
    }
}
