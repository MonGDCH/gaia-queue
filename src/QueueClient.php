<?php

declare(strict_types=1);

namespace gaia\queue;

use Throwable;
use Workerman\Timer;
use Workerman\RedisQueue\Client;
use Workerman\RedisQueue\UnretryableException;

/**
 * 重载 workerman/redis-queue 库，扩展功能
 * 
 * @author Mon <985558837@qq.com>
 * @version 1.0.0
 */
class QueueClient extends Client
{
    /**
     * 消费成功回调
     *
     * @var callable
     */
    protected $_consumeSuccess = null;

    /**
     * 设置消费成功回调
     *
     * @param callable $callback
     * @return void
     */
    public function onConsumeSuccess(callable $callback)
    {
        $this->_consumeSuccess = $callback;
    }

    /**
     * 重新pull方法
     *
     * @return void
     */
    public function pull()
    {
        $this->tryToPullDelayQueue();
        if (!$this->_subscribeQueues || $this->_redisSubscribe->brPoping) {
            return;
        }
        $cb = function ($data) use (&$cb) {
            if ($data) {
                $this->_redisSubscribe->brPoping = 0;
                $redis_key = $data[0];
                $package_str = $data[1];
                $package = json_decode($package_str, true);
                if (!$package) {
                    $this->_redisSend->lPush($this->_options['prefix'] . static::QUEUE_FAILED, $package_str);
                } else {
                    if (!isset($this->_subscribeQueues[$redis_key])) {
                        // 取消订阅，放回队列
                        $this->_redisSend->rPush($redis_key, $package_str);
                    } else {
                        $callback = $this->_subscribeQueues[$redis_key];
                        try {
                            // 记录执行时间
                            $package['consume_time'] = date('Y-m-d H:i:s', time());
                            $package['consume_msec'] = microtime(true);
                            $res = \call_user_func($callback, $package['data']);
                            $isSuccess = true;
                        } catch (UnretryableException $e) {
                            $isSuccess = false;
                            $this->log((string)$e, 'error');
                            $package['max_attempts'] = $this->_options['max_attempts'];
                            $package['error'] = $e->getMessage();
                            $this->fail($package);
                        } catch (Throwable $e) {
                            $isSuccess = false;
                            $this->log((string)$e, 'error');
                            $package['max_attempts'] = $this->_options['max_attempts'];
                            $package['error'] = $e->getMessage();
                            $package_modified = null;
                            if ($this->_consumeFailure) {
                                try {
                                    $package_modified = \call_user_func($this->_consumeFailure, $e, $package);
                                } catch (Throwable $ta) {
                                    $this->log((string)$ta, 'error');
                                }
                            }
                            if (is_array($package_modified)) {
                                $package['data'] = $package_modified['data'] ?? $package['data'];
                                $package['attempts'] = $package_modified['attempts'] ?? $package['attempts'];
                                $package['max_attempts'] = $package_modified['max_attempts'] ?? $package['max_attempts'];
                                $package['error'] = $package_modified['error'] ?? $package['error'];
                            }
                            if (++$package['attempts'] > $package['max_attempts']) {
                                $this->fail($package);
                            } else {
                                $this->retry($package);
                            }
                        }
                        // 消费成功，并无错误，则执行成功回调
                        if ($isSuccess && $this->_consumeSuccess) {
                            try {
                                \call_user_func($this->_consumeSuccess, $res, $package);
                            } catch (Throwable $e) {
                                $this->log((string)$e, 'error');
                            }
                        }
                    }
                }
            }
            if ($this->_subscribeQueues) {
                $this->_redisSubscribe->brPoping = 1;
                Timer::add(0.000001, [$this->_redisSubscribe, 'brPop'], [\array_keys($this->_subscribeQueues), 1, $cb], false);
            }
        };
        $this->_redisSubscribe->brPoping = 1;
        $this->_redisSubscribe->brPop(\array_keys($this->_subscribeQueues), 1, $cb);
    }

    /**
     * 重载日志方法
     *
     * @param string $log   日志内容
     * @param string $level 日志等级
     * @return void
     */
    protected function log($log, string $level = 'info'): void
    {
        if ($this->_log) {
            \call_user_func([$this->_log, $level], $log);
            return;
        }

        echo $log . PHP_EOL;
    }
}
