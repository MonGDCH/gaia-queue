<?php

declare(strict_types=1);

namespace support\queue\process;

use Exception;
use mon\env\Config;
use mon\log\Logger;
use mon\thinkORM\ORM;
use Workerman\Worker;
use gaia\ProcessTrait;
use mon\util\Container;
use support\queue\QueueService;
use gaia\queue\DriverInterface;
use gaia\queue\ConsumerInterface;
use gaia\interfaces\ProcessInterface;
use Workerman\Connection\TcpConnection;

/**
 * 消息队列消费进程
 *
 * @author Mon <985558837@qq.com>
 * @copyright Gaia
 * @version 1.0.0 2023-11-23
 */
class Queue implements ProcessInterface
{
    use ProcessTrait;

    /**
     * 队列任务池
     * 
     * @var array
     */
    protected $pool = [];

    /**
     * 允许指令调用调用方法名
     *
     * @var array
     */
    protected $allow_fn = ['getPool'];

    /**
     * 获取进程配置
     *
     * @return array
     */
    public static function getProcessConfig(): array
    {
        return Config::instance()->get('queue.app.process', []);
    }

    /**
     * 进程启动
     *
     * @param Worker $worker worker进程
     * @return void
     */
    public function onWorkerStart(Worker $worker)
    {
        // 注册日志服务
        $channel = Config::instance()->get('queue.app.log.channel', 'queue');
        Logger::instance()->createChannel($channel, Config::instance()->get('queue.app.log.config', []));
        Logger::instance()->setDefaultChannel($channel);

        // 定义数据库配置，自动识别是否已安装ORM库
        if (class_exists(ORM::class)) {
            ORM::register(true);
        }

        // 回调处理器驱动
        $handlerDriver = Config::instance()->get('queue.app.handler_driver', '');
        // 获取所有消息队列
        $queueList = QueueService::getQueue();
        // 注册消息队列
        foreach ($queueList as $key => $queue) {
            /** @var ConsumerInterface $consumer */
            $consumer = Container::instance()->get($queue['handler']);
            // 注册队列池
            $this->pool[$key] = [
                // 链接db
                'connection'        => $queue['connection'],
                // 队列名
                'queue'             => $queue['queue'],
                // 描述信息
                'describe'          => $queue['describe'],
                // 成功数
                'success'           => 0,
                // 失败数
                'failure'           => 0,
                // 最近运行时间
                'last_running_time' => '',
                // 启动时间
                'create_time'       => date('Y-m-d H:i:s', time()),
                // 消费者实例
                'consumer'          => $consumer
            ];

            // 创建队列客户端
            $queueClient = QueueService::connection($queue['connection']);
            // 消费失败时回调
            $queueClient->onConsumeFailure(function (Exception $exeption, array $package) use ($handlerDriver) {
                // 任务表示
                $key = $this->getKey($package['connect'], $package['queue']);
                // 记录执行信息
                $this->pool[$key]['failure']++;
                $this->pool[$key]['last_running_time'] = $package['consume_time'] ?? date('Y-m-d H:i:s', time());

                // 执行回调，记录日志
                if ($handlerDriver && is_subclass_of($handlerDriver, DriverInterface::class)) {
                    $handler = Container::instance()->get($handlerDriver);
                    call_user_func([$handler, 'handeler'], $package, false, $exeption->getMessage());
                }

                // 执行队列回调
                $consumer = $this->pool[$key]['consumer'];
                if (method_exists($consumer, 'onConsumeFailure')) {
                    return call_user_func([$consumer, 'onConsumeFailure'], $exeption, $package);
                }
            });
            // 消费成功回调
            $queueClient->onConsumeSuccess(function ($result, array $package) use ($handlerDriver) {
                // 任务表示
                $key = $this->getKey($package['connect'], $package['queue']);

                // 记录执行信息
                $this->pool[$key]['success']++;
                $this->pool[$key]['last_running_time'] = $package['consume_time'] ?? date('Y-m-d H:i:s', time());

                // 执行回调，记录日志
                if ($handlerDriver && is_subclass_of($handlerDriver, DriverInterface::class)) {
                    $handler = Container::instance()->get($handlerDriver);
                    call_user_func([$handler, 'handeler'], $package, true, $result);
                }

                // 执行回调
                $consumer = $this->pool[$key]['consumer'];
                if (method_exists($consumer, 'onConsumeSuccess')) {
                    return call_user_func([$consumer, 'onConsumeSuccess'], $result, $package);
                }
            });
            // 监听队列
            $queueClient->subscribe($queue['queue'], [$consumer, 'consume']);
            // 记录启动监听日志
            Logger::instance()->channel()->info('init queue subscribe => ' . $key);
        }
    }

    /**
     * 当客户端通过连接发来数据时触发的回调函数
     *
     * @param TcpConnection $connection
     * @param mixed $data
     * @return void
     */
    public function onMessage(TcpConnection $connection, $data)
    {
        // 存活判断
        if ($data == 'ping') {
            $connection->send('pong');
            return;
        }

        $data = json_decode($data, true);
        if ($data && isset($data['fn']) && in_array($data['fn'], $this->allow_fn)) {
            $params = $data['data'] ?? [];
            $connection->send(call_user_func([$this, $data['fn']], $params));
            return;
        }

        $connection->send('Not support fn');
    }

    /**
     * 获取任务池数据
     *
     * @return string
     */
    protected function getPool(): string
    {
        $data = [];
        foreach ($this->pool as $key => $item) {
            $data[] = [
                'id'                => $key,
                'connection'        => $item['connection'],
                'queue'             => $item['queue'],
                'describe'          => $item['describe'],
                'success'           => $item['success'],
                'failure'           => $item['failure'],
                'last_running_time' => $item['last_running_time'],
                'create_time'       => $item['create_time'],
            ];
        }
        return json_encode(['code' => 1, 'msg' => 'ok', 'data' => $data], JSON_UNESCAPED_UNICODE);
    }

    /**
     * 获取key值
     *
     * @param string $connection    链接标识
     * @param string $queue         队列名称
     * @return string
     */
    protected function getKey(string $connection, string $queue): string
    {
        return $connection . '_' . $queue;
    }
}
