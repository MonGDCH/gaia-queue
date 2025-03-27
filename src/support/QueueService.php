<?php

declare(strict_types=1);

namespace support\queue;

use mon\env\Config;
use mon\util\Network;
use RuntimeException;
use mon\util\Container;
use gaia\queue\QueueClient;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use support\queue\process\Queue;
use support\service\RedisService;
use gaia\queue\ConsumerInterface;

/**
 * 消息队列客户端
 * 
 * @author Mon <985558837@qq.com>
 * @version 1.0.0
 */
class QueueService
{
    /**
     * 缓存实例列表
     *
     * @var Client[]
     */
    protected static $_connections = [];

    /**
     * 连接队列
     *
     * @param string $name  队列配置名
     * @return QueueClient
     */
    public static function connection(string $name = '')
    {
        $name = $name ?: Config::instance()->get('queue.queue.default', 'default');
        if (!isset(static::$_connections[$name])) {
            $config = Config::instance()->get('queue.queue.connections.' . $name);
            if (empty($config)) {
                throw new RuntimeException("Queue connection {$name} not found");
            }
            $address = "{$config['scheme']}://{$config['host']}:{$config['port']}";
            $options = [
                'auth' => $config['auth'],
                'db' => $config['database'],
                'prefix' => $config['prefix'],
                'max_attempts' => $config['max_attempts'],
                'retry_seconds' => $config['retry_seconds']
            ];
            $client = new QueueClient($address, $options);
            static::$_connections[$name] = $client;
        }

        return static::$_connections[$name];
    }

    /**
     * 与消息队列进程通信
     *
     * @param string $messgae   通信信息
     * @return string
     */
    public static function communication(string $messgae = 'ping'): string
    {
        $host = Queue::getListenHost();
        $port = Queue::getListenPort();
        $result = Network::instance()->sendTCP($host, $port, $messgae . "\n", false);
        return trim((string)$result['result']);
    }

    /**
     * 同步向队列发送一条消息
     *
     * @param string $queue     队列名
     * @param array $data       发布的具体消息，可以是数组或者字符串
     * @param integer $delay    延迟消费时间，单位秒，默认0
     * @param string $connect   连接的队列，默认空
     * @param integer $ping     RedisService 连接保活时间，一般不做修改即可
     * @return boolean
     */
    public static function syncSend(string $queue, array $data, int $delay = 0, string $connect = '', int $ping = 55): bool
    {
        // 队列包信息
        $now = time();
        $package = json_encode([
            'id'        => mt_rand(),
            'time'      => $now,
            'delay'     => $delay,
            'attempts'  => 0,
            'connect'   => $connect,
            'queue'     => $queue,
            'data'      => $data
        ], JSON_UNESCAPED_UNICODE);
        // redis配置
        $config = static::getQueueConfig($connect, $ping);
        // 发送
        if ($delay) {
            $send = RedisService::instance()->tryExecCommand($config, 'zAdd', QueueClient::QUEUE_DELAYED, $now + $delay, $package);
        } else {
            $send = RedisService::instance()->tryExecCommand($config, 'lPush', QueueClient::QUEUE_WAITING . $queue, $package);
        }

        return boolval($send);
    }

    /**
     * 异步向队列发送一条消息，需要 workerman 环境
     *
     * @param string $queue     队列名
     * @param array $data       发布的具体消息，可以是数组或者字符串
     * @param integer $delay    延迟消费时间，单位秒，默认0
     * @param string $connect   连接的队列，默认空
     * @return void
     */
    public static function asyncSend(string $queue, array $data, int $delay = 0, string $connect = '')
    {
        return static::connection($connect)->send($queue, $data, $delay, $connect);
    }

    /**
     * 获取消息队列配置信息
     *
     * @param string $connect   连接的队列，默认空
     * @param integer $ping     RedisService 连接保活时间，一般不做修改即可
     * @return array
     */
    public static function getQueueConfig(string $connect = '', int $ping = 55): array
    {
        // redis配置
        $name = $connect ?: Config::instance()->get('queue.queue.default', 'default');
        $config = Config::instance()->get('queue.queue.connections.' . $name);
        if (empty($config)) {
            throw new RuntimeException("Queue connection {$name} not found");
        }
        $config['ping'] = $ping;

        return $config;
    }

    /**
     * 获取当前正在运行的任务
     *
     * @throws \Throwable    服务进程链接失败抛出异常
     * @return array
     */
    public static function getPool(): array
    {
        $cammad = json_encode(['fn' => 'getPool', 'data' => []], JSON_UNESCAPED_UNICODE);
        $ret = static::communication($cammad);
        $data = json_decode($ret, true);
        if (!$data || $data['code'] != '1') {
            throw new RuntimeException('获取运行中的任务失败：' . $data['msg']);
        }

        return $data['data'];
    }

    /**
     * 获取所有定义的消息队列
     *
     * @return array
     */
    public static function getQueue(): array
    {
        // 获取所有消息队列
        $queueList = [];
        // 消费者回调控制器目录
        $detault = SUPPORT_PATH . DIRECTORY_SEPARATOR . 'queue' . DIRECTORY_SEPARATOR . 'consumers' . DIRECTORY_SEPARATOR;
        $consumers_path = Config::instance()->get('queue.app.consumers_path', $detault);
        // 消费者回调控制器命名空间
        $namespance = Config::instance()->get('queue.app.namespace', '\\support\queue\consumers');
        $dir_iterator = new RecursiveDirectoryIterator($consumers_path, RecursiveDirectoryIterator::SKIP_DOTS | RecursiveDirectoryIterator::FOLLOW_SYMLINKS);
        $iterator = new RecursiveIteratorIterator($dir_iterator);
        /** @var RecursiveDirectoryIterator $iterator */
        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                // 获取对象名称
                $dirname = dirname(str_replace($consumers_path, '', $file->getPathname()));
                $beforName = str_replace(DIRECTORY_SEPARATOR, '\\', $dirname);
                $beforNamespace = ($beforName == '\\' || $beforName == '.') ? '' : ('\\' . $beforName);
                $className = $namespance . $beforNamespace . '\\' . $file->getBasename('.php');
                if (!is_subclass_of($className, ConsumerInterface::class)) {
                    continue;
                }
                /** @var ConsumerInterface $consumer */
                $consumer = Container::instance()->get($className);
                $connection = $consumer->connection();
                $queue = $consumer->queue();
                $key = $connection . '_' . $queue;
                if (isset($queueList[$key])) {
                    throw new RuntimeException("queue {$key} is duplicated");
                }

                $queueList[$key] = [
                    'connection'    => $connection,
                    'queue'         => $queue,
                    'describe'      => $consumer->describe(),
                    'handler'       => $className,
                ];
            }
        }

        return $queueList;
    }
}
