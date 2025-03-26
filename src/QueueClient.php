<?php

declare(strict_types=1);

namespace gaia\queue;

use Throwable;
use mon\log\Logger;
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
     * 重构send方法
     *
     * @param string $queue     队列名称
     * @param array $data       发布的具体消息，可以是数组或者字符串
     * @param integer $delay    延迟消费时间，单位秒，默认0
     * @param string $connect   连接的队列，默认空
     * @param mixed $cb         对调方法，默认空
     * @return void
     */
    public function send($queue, $data, $delay = 0, $connect = '', $cb = null)
    {
        static $_id = 0;
        $id = \microtime(true) . '.' . (++$_id);
        $now = time();
        $package_str = \json_encode([
            'id'        => $id,
            'time'      => $now,
            'delay'     => $delay,
            'attempts'  => 0,
            'connect'   => $connect,
            'queue'     => $queue,
            'data'      => $data
        ], JSON_UNESCAPED_UNICODE);
        if (\is_callable($delay)) {
            $cb = $delay;
            $delay = 0;
        }
        if ($cb) {
            $cb = function ($ret) use ($cb) {
                $cb((bool)$ret);
            };
            if ($delay == 0) {
                $this->_redisSend->lPush($this->_options['prefix'] . static::QUEUE_WAITING . $queue, $package_str, $cb);
            } else {
                $this->_redisSend->zAdd($this->_options['prefix'] . static::QUEUE_DELAYED, $now + $delay, $package_str, $cb);
            }
            return;
        }
        if ($delay == 0) {
            $this->_redisSend->lPush($this->_options['prefix'] . static::QUEUE_WAITING . $queue, $package_str);
        } else {
            $this->_redisSend->zAdd($this->_options['prefix'] . static::QUEUE_DELAYED, $now + $delay, $package_str);
        }
    }

    /**
     * 重载日志方法
     *
     * @param string $log   日志内容
     * @param string $level 日志等级
     * @return void
     */
    protected function log($log, string $level = 'error'): void
    {
        \call_user_func([Logger::instance()->channel(), $level], $log);
    }
}
