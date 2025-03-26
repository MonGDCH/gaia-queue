<?php

declare(strict_types=1);

namespace gaia\queue\driver;

use mon\env\Config;
use mon\log\Logger;
use mon\thinkORM\Db;
use gaia\queue\DriverInterface;

/**
 * Mysql处理回调
 * 
 * @see 需要采用Think-ORM，需自行导入`queue.sql`，并初始化数据库
 * @author Mon <985558837@qq.com>
 * @version 1.0.0
 */
class Mysql implements DriverInterface
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
        $status = $status ? 1 : 0;
        $save = Db::table($this->getTable())->insert([
            'connection'    => $connection,
            'queue'         => $queue,
            'send_time'     => $package['time'],
            'run_time'      => $run_time,
            'running_time'  => $running_time,
            'status'        => $status,
            'result'        => $result,
            'create_time'   => $this->getTime()
        ]);
        if (!$save) {
            Logger::instance()->channel()->error('Record queue result log faild');
            return false;
        }

        return true;
    }

    /**
     * 获取操作表名
     *
     * @return string
     */
    protected function getTable(): string
    {
        return Config::instance()->get('queue.app.log_table', 'queue_log');
    }

    /**
     * 获取时间
     *
     * @return void
     */
    protected function getTime()
    {
        $now = time();
        $format = Config::instance()->get('queue.app.log_time_format', '');
        return $format ? date($format, $now) : $now;
    }
}
