<?php

declare(strict_types=1);

namespace process\queue;

use mon\env\Config;
use mon\log\Logger;
use Workerman\Worker;
use gaia\ProcessTrait;
use mon\util\Container;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use support\queue\QueueService;
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
     * 是否启用进程
     *
     * @return boolean
     */
    public static function enable(): bool
    {
        return Config::instance()->get('queue.process.enable', false);
    }

    /**
     * 获取进程配置
     *
     * @return array
     */
    public static function getProcessConfig(): array
    {
        return Config::instance()->get('queue.process.config', []);
    }

    /**
     * 构造方法
     */
    public function __construct()
    {
        // 消费者回调控制器目录
        $detault = APP_PATH . DIRECTORY_SEPARATOR . 'queue' . DIRECTORY_SEPARATOR . 'consumers' . DIRECTORY_SEPARATOR;;
        $this->consumers_path = Config::instance()->get('queue.app.consumers_path', $detault);
        // 消费者回调控制器命名空间
        $this->namespance = Config::instance()->get('queue.app.namespace', '');
        // 注册日志服务
        $this->log_channel = Config::instance()->get('queue.app.log.channel', 'queue');
        Logger::instance()->createChannel($this->log_channel, Config::instance()->get('queue.app.log.config', []));
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

        // 迭代获取所有消费回调
        $queueList = [];
        $dir_iterator = new RecursiveDirectoryIterator($this->consumers_path, RecursiveDirectoryIterator::SKIP_DOTS | RecursiveDirectoryIterator::FOLLOW_SYMLINKS);
        $iterator = new RecursiveIteratorIterator($dir_iterator);
        /** @var RecursiveDirectoryIterator $iterator */
        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                $name = $file->getBasename('.php');
                $className = $this->namespance . '\\' . $name;
                if (!is_subclass_of($className, ConsumerInterface::class)) {
                    continue;
                }
                /** @var ConsumerInterface $consumer */
                $consumer = Container::instance()->get($className);
                $queue = $consumer->queue();
                if (in_array($queue, $queueList)) {
                    throw new \RuntimeException("queue {$queue} is duplicated");
                }
                $queueList[] = $queue;
                $queueClient = QueueService::connection($consumer->connection());
                $queueClient->subscribe($queue, [$consumer, 'consume']);
                Logger::instance()->channel($this->log_channel)->info('init queue subscribe => ' . $queue);
            }
        }
    }

    /**
     * onConnect事件仅仅代表客户端与Workerman完成了TCP三次握手
     * 这时客户端还没有发来任何数据，此时除了通过$connection->getRemoteIp()获得对方ip，没有其他可以鉴别客户端的数据或者信息
     * UDP通信方式不会触发，onClose事件同理
     *
     * @param TcpConnection $connection
     * @return void
     */
    public function onConnect(TcpConnection $connection)
    {
        // echo "new connection from ip " . $connection->getRemoteIp() . "\n";
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
    }
}
