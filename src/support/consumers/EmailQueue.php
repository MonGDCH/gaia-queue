<?php

declare(strict_types=1);

namespace support\queue\consumers;

use Exception;
use Throwable;
use mon\util\View;
use mon\util\Instance;
use InvalidArgumentException;
use support\queue\QueueService;
use gaia\queue\ConsumerInterface;
use support\service\MailerService;

/**
 * 发送邮件消息队列
 * 
 * @author Mon <985558837@qq.com>
 * @version 1.0.0
 */
class EmailQueue implements ConsumerInterface
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
        return 'send-email';
    }

    /**
     * 队列描述信息
     *
     * @return string
     */
    public function describe(): string
    {
        return '异步发送邮件通知队列';
    }

    /**
     * 处理函数
     *
     * @param mixed $data   队列参数
     * @return mixed
     */
    public function consume($data)
    {
        // 邮件内容
        $content = $data['content'] ?? '';
        if (!$content && $data['tmp']) {
            try {
                $view = new View();
                $view->setPath(__DIR__ . '/email/');
                $view->assign($data['data']);
                $content = $view->fetch($data['tmp']);
            } catch (Throwable $e) {
                // 获取模板内容异常，不发送邮件，记录错误信息
                return '获取邮件模板内容异常：' . $e->getMessage();
            }
        }

        // 发送邮件
        $send = MailerService::instance()->send($data['title'], $content, $data['to'], $data['cc'], $data['bcc'], $data['attachment']);
        if (!$send) {
            // 发送失败，抛出异常重试
            throw new Exception(MailerService::instance()->getError());
        }

        // 成功
        return '发送成功';
    }

    /**
     * 发送队列消息
     *
     * @param array $to         接收人
     * @param string $title     邮件标题
     * @param string $content   邮件内容, 不为空是tmp、data参数无效
     * @param string $tmp       使用的模板文件
     * @param array $data       模板渲染内容
     * @param array $cc         抄送人
     * @param array $bcc        秘密抄送人
     * @param array $attachment 附件
     * @param integer $delay    队列延迟执行时间
     * @throws InvalidArgumentException
     * @return bool 是否发送成功
     */
    public function sendQuery(array $to, string $title, string $content = '', string $tmp = '', array $data = [], array $cc = [], array $bcc = [], array $attachment = [], int $delay = 0): bool
    {
        if (empty($to)) {
            throw new InvalidArgumentException('收件人不能为空');
        }
        $info = [
            'to'            => $to,
            'title'         => $title,
            'content'       => $content,
            'tmp'           => $tmp,
            'data'          => $data,
            'cc'            => $cc,
            'bcc'           => $bcc,
            'attachment'    => $attachment,
        ];
        return QueueService::syncSend($this->queue(), $info, $delay, $this->connection());
    }
}
