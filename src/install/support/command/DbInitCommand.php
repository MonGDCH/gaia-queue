<?php

declare(strict_types=1);

namespace support\queue\command;

use mon\env\Config;
use mon\thinkORM\Db;
use mon\console\Input;
use mon\console\Output;
use mon\console\Command;

/**
 * 定时任务数据库初始化安装
 *
 * @author Mon <98555883@qq.com>
 * @version 1.0.0
 */
class DbInitCommand extends Command
{
    /**
     * 指令名
     *
     * @var string
     */
    protected static $defaultName = 'queue:dbinit';

    /**
     * 指令描述
     *
     * @var string
     */
    protected static $defaultDescription = 'init queue database table';

    /**
     * 指令分组
     *
     * @var string
     */
    protected static $defaultGroup = 'mon-queue';

    /**
     * 执行指令
     *
     * @param  Input  $in  输入实例
     * @param  Output $out 输出实例
     * @return integer  exit状态码
     */
    public function execute(Input $in, Output $out)
    {
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `queue_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `connection` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '链接名称',
  `queue` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '队列名称',
  `send_time` datetime NOT NULL COMMENT '投递时间',
  `send_data` varchar(2000) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '投递数据',
  `run_time` datetime NOT NULL COMMENT '执行任务时间',
  `running_time` float unsigned NOT NULL COMMENT '执行所用时间',
  `status` tinyint(1) unsigned NOT NULL COMMENT '执行返回状态: 0-失败 1-成功',
  `result` varchar(2000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '任务执行结果描述',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建日期',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `item` (`connection`,`queue`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC COMMENT='消息队列执行日志表';
SQL;
        // 建表
        Db::setConfig(Config::instance()->get('database', []));
        Db::execute($sql);

        return $out->block('Init success!', 'SUCCESS');
    }
}
