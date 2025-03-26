<?php

declare(strict_types=1);

namespace support\queue\process;

use Exception;
use mon\env\Config;
use mon\log\Logger;
use RuntimeException;
use mon\thinkORM\ORM;
use Workerman\Worker;
use gaia\ProcessTrait;
use mon\util\Container;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use support\queue\QueueService;
use support\cache\CacheService;
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
     * 消费者路径
     *
     * @var string
     */
    protected $consumers_path = '';

    /**
     * 消费者对象命名空间
     *
     * @var string
     */
    protected $namespance = '';

    /**
     * 日志通道
     *
     * @var string
     */
    protected $log_channel = 'queue';

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
     * 构造方法
     */
    public function __construct()
    {
        // 消费者回调控制器目录
        $detault = SUPPORT_PATH . DIRECTORY_SEPARATOR . 'queue' . DIRECTORY_SEPARATOR . 'consumers' . DIRECTORY_SEPARATOR;
        $this->consumers_path = Config::instance()->get('queue.app.consumers_path', $detault);
        // 消费者回调控制器命名空间
        $this->namespance = Config::instance()->get('queue.app.namespace', '\\support\queue\consumers');
        // 注册日志服务
        $this->log_channel = Config::instance()->get('queue.app.log.channel', 'queue');
        Logger::instance()->createChannel($this->log_channel, Config::instance()->get('queue.app.log.config', []));
        Logger::instance()->setDefaultChannel($this->log_channel);
    }

    /**
     * 进程启动
     *
     * @param Worker $worker worker进程
     * @return void
     */
    public function onWorkerStart(Worker $worker): void
    {
        // 进程启动初始化业务
        if (!is_dir($this->consumers_path)) {
            echo "[warn] Consumer directory {$this->consumers_path} not exists" . PHP_EOL;
            return;
        }

        // 定义数据库配置，自动识别是否已安装ORM库
        if (class_exists(ORM::class)) {
            $dbConfig = Config::instance()->get('database', []);
            // 注册ORM
            $cache_store = class_exists(CacheService::class) ? CacheService::instance()->getService()->store() : null;
            ORM::register(false, $dbConfig, Logger::instance()->channel(), $cache_store);
        }

        // 回调处理器驱动
        $handlerDriver = Config::instance()->get('queue.app.handler_driver', '');
        // 迭代获取所有消费回调
        $queueList = [];
        $dir_iterator = new RecursiveDirectoryIterator($this->consumers_path, RecursiveDirectoryIterator::SKIP_DOTS | RecursiveDirectoryIterator::FOLLOW_SYMLINKS);
        $iterator = new RecursiveIteratorIterator($dir_iterator);
        /** @var RecursiveDirectoryIterator $iterator */
        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                // 获取对象名称
                $dirname = dirname(str_replace($this->consumers_path, '', $file->getPathname()));
                $beforName = str_replace(DIRECTORY_SEPARATOR, '\\', $dirname);
                $beforNamespace = ($beforName == '\\' || $beforName == '.') ? '' : ('\\' . $beforName);
                $className = $this->namespance . $beforNamespace . '\\' . $file->getBasename('.php');
                if (!is_subclass_of($className, ConsumerInterface::class)) {
                    continue;
                }
                /** @var ConsumerInterface $consumer */
                $consumer = Container::instance()->get($className);
                $connection = $consumer->connection();
                $queue = $consumer->queue();
                if (!isset($queueList[$connection])) {
                    $queueList[$connection] = [];
                }
                // 检查队列是否重复，一个连接下不能订阅相同的队列
                if (in_array($queue, $queueList[$connection])) {
                    throw new RuntimeException("queue {$queue} is duplicated");
                }
                $queueList[$connection][] = $queue;
                $queueClient = QueueService::connection($connection);
                // 绑定日志服务
                $queueClient->logger(Logger::instance()->channel());
                // 监听队列
                $queueClient->subscribe($queue, [$consumer, 'consume']);
                // 消费失败时回调
                $queueClient->onConsumeFailure(function (Exception $exeption, array $package) use ($consumer, $handlerDriver, $connection, $queue) {
                    // 记录执行信息
                    $key = $this->getKey($connection, $queue);
                    $this->pool[$key]['failure']++;
                    $this->pool[$key]['last_running_time'] = $package['consume_time'] ?? date('Y-m-d H:i:s', time());

                    // 执行回调，记录日志
                    if ($handlerDriver && is_subclass_of($handlerDriver, DriverInterface::class)) {
                        $handler = Container::instance()->get($handlerDriver);
                        call_user_func([$handler, 'handeler'], $connection, $queue, false, $exeption->getMessage(), $package);
                    }

                    // 执行队列回调
                    if (method_exists($consumer, 'onConsumeFailure')) {
                        return call_user_func([$consumer, 'onConsumeFailure'], $exeption, $package);
                    }
                });
                // 消费成功回调
                $queueClient->onConsumeSuccess(function ($result, array $package) use ($consumer, $handlerDriver, $connection, $queue) {
                    // 记录执行信息
                    $key = $this->getKey($connection, $queue);
                    $this->pool[$key]['success']++;
                    $this->pool[$key]['last_running_time'] = $package['consume_time'] ?? date('Y-m-d H:i:s', time());

                    // 执行回调，记录日志
                    if ($handlerDriver && is_subclass_of($handlerDriver, DriverInterface::class)) {
                        $handler = Container::instance()->get($handlerDriver);
                        call_user_func([$handler, 'handeler'], $connection, $queue, true, 'ok', $package);
                    }

                    // 执行回调
                    if (method_exists($consumer, 'onConsumeSuccess')) {
                        return call_user_func([$consumer, 'onConsumeSuccess'], $result, $package);
                    }
                });
                // 注册任务池
                $key = $this->getKey($connection, $queue);
                $this->pool[$key] = [
                    // 链接db
                    'connection' => $connection,
                    // 队列名
                    'queue' => $queue,
                    // 描述信息
                    'describe' => $consumer->describe(),
                    // 成功数
                    'success' => 0,
                    // 失败数
                    'failure' => 0,
                    // 最近运行时间
                    'last_running_time' => '',
                    // 启动时间
                    'create_time' => date('Y-m-d H:i:s', time()),
                ];
                Logger::instance()->channel()->info('init queue subscribe => ' . $queue);
            }
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
