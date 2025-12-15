<?php

declare(strict_types=1);

namespace app\queue;

use Throwable;
use mon\util\Network;
use mon\util\Instance;
use support\queue\QueueService;
use gaia\queue\ConsumerInterface;

/**
 * 发送HTTP请求消息队列消费者服务
 * 
 * @author Mon <985558837@qq.com>
 * @version 1.0.0
 */
class HttpQueue implements ConsumerInterface
{
    use Instance;

    /**
     * 链接的队列配置名，空则使用默认队列配置
     *
     * @return string
     */
    public function connection(): string
    {
        return 'default';
    }

    /**
     * 监听的队列名
     *
     * @return string
     */
    public function queue(): string
    {
        return 'send-http';
    }

    /**
     * 队列描述信息
     *
     * @return string
     */
    public function describe(): string
    {
        return '异步发送HTTP请求队列';
    }

    /**
     * 处理函数
     *
     * @param mixed $data
     * @return mixed
     */
    public function consume($data)
    {
        if (!is_array($data)) {
            return '未发起请求：队列调用传参格式错误';
        }
        $queryConfig = array_merge([
            // 请求的URl
            'url'       => '',
            // 请求方式
            'method'    => 'GET',
            // 请求数据
            'data'      => [],
            // 请求头
            'header'    => [],
            // 请求user-agent
            'agent'     => '',
            // 响应超时时间
            'timeout'   => 10,
        ], $data);
        if (empty($queryConfig['url'])) {
            return '未发起请求：请求地址URL不能为空';
        }

        try {
            $ret = Network::sendHTTP($queryConfig['url'], $queryConfig['data'],  $queryConfig['method'], $queryConfig['header'], false, $queryConfig['timeout'], $queryConfig['agent']);
            if (isset($data['saveRet']) && $data['saveRet'] == 1) {
                return $ret;
            }
            return '请求成功';
        } catch (Throwable $e) {
            return '请求失败：' . $e->getMessage();
            // // 抛出异常，触发重试机制
            // throw $e;
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
    public function sendQuery(string $url, string $method = 'GET', array $data = [], array $header = [], string $agent = '', int $timeout = 10, bool $saveRet = false, int $delay = 0): bool
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

        return QueueService::syncSend($this->queue(), $queueData, $delay, $this->connection());
    }
}
