<?php

declare(strict_types=1);

namespace support\command\queue;

use mon\util\Sql;
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
    protected static $defaultDescription = 'init queue db table';

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
        $sqlFile = __DIR__ . '/sql/queue.sql';
        $sqls = Sql::parseFile($sqlFile);
        // 建表
        Db::setConfig(Config::instance()->get('database', []));
        foreach ($sqls as $sql) {
            Db::execute($sql);
        }

        return $out->block('Init success!', 'SUCCESS');
    }
}
