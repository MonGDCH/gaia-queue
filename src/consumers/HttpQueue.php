<?php

declare(strict_types=1);

namespace app\queue\consumers;

use Throwable;
use mon\log\Logger;
use mon\util\Network;
use gaia\queue\QueueService;
use gaia\queue\contract\ConsumerInterface;

/**
 * 发送HTTP请求消息队列消费者服务
 * 
 * @author Mon <985558837@qq.com>
 * @version 1.0.0
 */
class HttpQueue implements ConsumerInterface
{
    /**
     * 队列名
     */
    const QUEUE_NAME = 'send-http';

    /**
     * 链接队列配置名
     */
    const QUERE_CONN = '';

    /**
     * 链接的队列配置名，空则使用默认队列配置
     *
     * @return string
     */
    public function connection(): string
    {
        return self::QUERE_CONN;
    }

    /**
     * 监听的队列名
     *
     * @return string
     */
    public function queue(): string
    {
        return self::QUEUE_NAME;
    }

    /**
     * 处理函数
     *
     * @param mixed $data
     * @return mixed
     */
    public function consume($data)
    {
        Logger::instance()->channel('queue')->log('start', 'http queue runing');
        if (!is_array($data)) {
            Logger::instance()->channel('queue')->error('http queue data error');
            return false;
        }
        $queryConfig = array_merge([
            // 请求的URl
            'url'       => '',
            // 请求方式
            'method'    => 'get',
            // 请求数据
            'data'      => [],
            // 请求头
            'header'    => [],
            // 请求user-agent
            'agent'     => '',
            // 响应超时时间
            'timeout'   => 10,
            // 是否保持响应结果集，1保存，其他不保存
            'saveRet'   => 1,
        ], $data);
        if (empty($queryConfig['url'])) {
            Logger::instance()->channel('queue')->error('http queue query url is empty');
            return false;
        }

        try {
            Logger::instance()->channel('queue')->info('http queue query url: ' . $queryConfig['url'] . ' method: ' . $queryConfig['method']);
            Logger::instance()->channel('queue')->info('http queue query data: ' . json_encode($queryConfig['data'], JSON_UNESCAPED_UNICODE));
            Logger::instance()->channel('queue')->info('http queue query header: ' . json_encode($queryConfig['header'], JSON_UNESCAPED_UNICODE));
            Logger::instance()->channel('queue')->info('http queue query agent: ' . $queryConfig['agent']);
            $ret = Network::instance()->sendHTTP($queryConfig['url'], $queryConfig['data'],  $queryConfig['method'], $queryConfig['header'], false, $queryConfig['timeout'], $queryConfig['agent']);
            if ($queryConfig['saveRet'] == 1) {
                Logger::instance()->channel('queue')->info('http queue query result: ' . var_export($ret, true));
            }

            Logger::instance()->channel('queue')->log('end', 'http queue runing end', ['save' => true]);
            return true;
        } catch (Throwable $e) {
            Logger::instance()->channel('queue')->error('http queue query error: ' . $e->getMessage(), ['save' => true]);
            return false;
        }
    }

    /**
     * 发送HTTP队列消息
     *
     * @param string $url       请求URL
     * @param string $method    请求类型
     * @param array $data       请求参数
     * @param array $header     请求头
     * @param string $agent     请求user-agent
     * @param integer $timeout  请求超时时间
     * @param boolean $saveRet  是否保存响应结果集
     * @param integer $delay    队列延迟执行事件
     * @return bool 是否发送成功
     */
    public static function sendQuery(string $url, string $method = 'GET', array $data = [], array $header = [], string $agent = '', int $timeout = 10, bool $saveRet = false, int $delay = 0)
    {
        // 发送消息队列数据
        $queueData = [
            'url'       => $url,
            'method'    => $method,
            'data'      => $data,
            'header'    => $header,
            'agent'     => $agent,
            'timeout'   => $timeout,
            'saveRet'   => $saveRet ? 1 : 0
        ];

        return QueueService::syncSend(self::QUEUE_NAME, $queueData, $delay, self::QUERE_CONN);
    }
}
