<?php

declare(strict_types=1);

namespace support\queue;

use gaia\App;
use gaia\interfaces\PluginInterface;

class Bootstrap implements PluginInterface
{
    /**
     * 启动插件
     *
     * @return void
     */
    public static function start()
    {
        $namespace = '\support\queue\command';
        App::console()->load(__DIR__ . '/command', $namespace);
    }
}
