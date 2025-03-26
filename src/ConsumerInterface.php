<?php

declare(strict_types=1);

namespace gaia\queue;

/**
 * 队列消费回调接口
 * 
 * @author Mon <985558837@qq.com>
 * @version 1.0.0
 */
interface ConsumerInterface
{
    /**
     * 链接的队列配置名
     *
     * @return string
     */
    public function connection(): string;

    /**
     * 监听的队列名
     *
     * @return string
     */
    public function queue(): string;

    /**
     * 队列描述信息
     *
     * @return string
     */
    public function describe(): string;

    /**
     * 处理函数
     *
     * @param mixed $data
     * @return mixed
     */
    public function consume($data);
}
