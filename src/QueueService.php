<?php

declare(strict_types=1);

namespace gaia\queue;

use mon\env\Config;
use mon\util\Network;
use RuntimeException;
use Workerman\RedisQueue\Client;
use support\service\RedisService;

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
     * @return Client
     */
    public static function connection(string $name = ''): Client
    {
        $name = $name ?: Config::instance()->get('queue.redis.default', 'default');
        if (!isset(static::$_connections[$name])) {
            $config = Config::instance()->get('queue.redis.connections.' . $name);
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
            $client = new Client($address, $options);
            static::$_connections[$name] = $client;
        }

        return static::$_connections[$name];
    }

    /**
     * 获取消息队列Reddis实例
     *
     * @param string $connection
     * @param integer $ping
     * @return \Redis
     */
    public static function queueRedis(string $connection = '', int $ping = 55)
    {
        // redis配置
        $name = $connection ?: Config::instance()->get('queue.redis.default', 'default');
        $config = Config::instance()->get('queue.redis.connections.' . $name);
        if (empty($config)) {
            throw new RuntimeException("Queue connection {$name} not found");
        }
        $config['ping'] = $ping;
        return RedisService::instance()->getRedis($config);
    }


    /**
     * 与消息队列进程通信
     *
     * @param string $messgae
     * @return string
     */
    public static function communication(string $messgae = 'ping'): string
    {
        $listen = Config::instance()->get('queue.process.config.listen');
        $parseList = explode('://', $listen);
        $domain = explode(':', $parseList[1]);
        $scheme = $parseList[0];
        $host = $domain[0];
        $port = $domain[1];
        $result = Network::instance()->sendTCP($host, (int)$port, $messgae . "\n");
        return trim($result['result']);
    }

    /**
     * 同步向队列发送一条消息
     *
     * @param string $queue  队列名
     * @param array $data   发布的具体消息，可以是数组或者字符串
     * @param integer $delay    延迟消费时间，单位秒，默认0
     * @param string $connection    连接的队列，默认空
     * @param integer $ping    RedisService 连接保活时间，一般不做修改即可
     * @return boolean
     */
    public static function syncSend(string $queue, array $data, int $delay = 0, string $connection = '', int $ping = 55): bool
    {
        // 队列包信息
        $now = time();
        $package = json_encode([
            'id'       => mt_rand(),
            'time'     => $now,
            'delay'    => $delay,
            'attempts' => 0,
            'queue'    => $queue,
            'data'     => $data
        ]);
        // redis配置
        $name = $connection ?: Config::instance()->get('queue.redis.default', 'default');
        $config = Config::instance()->get('queue.redis.connections.' . $name);
        if (empty($config)) {
            throw new RuntimeException("Queue connection {$name} not found");
        }
        $config['ping'] = $ping;
        // 发送
        if ($delay) {
            $send = RedisService::instance()->tryExecCommand($config, 'zAdd', Client::QUEUE_DELAYED, $now + $delay, $package);
        } else {
            $send = RedisService::instance()->tryExecCommand($config, 'lPush', Client::QUEUE_WAITING . $queue, $package);
        }

        return boolval($send);
    }

    /**
     * 异步向队列发送一条消息，需要 workerman 环境
     *
     * @param string $queue  队列名
     * @param array $data   发布的具体消息，可以是数组或者字符串
     * @param integer $delay    延迟消费时间，单位秒，默认0
     * @param string $connection    连接的队列，默认空
     * @return void
     */
    public static function asyncSend(string $queue, array $data, int $delay = 0, string $connection = '')
    {
        return static::connection($connection)->send($queue, $data, $delay);
    }
}
