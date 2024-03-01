<?php

declare(strict_types=1);

namespace plugins\queue;

use gaia\interfaces\PluginInterface;

/**
 * 插件启动
 * 
 * @author Mon <985558837@qq.com>
 * @version 1.0.0
 */
class Bootstrap implements PluginInterface
{
    /**
     * 是否启用插件
     *
     * @return boolean
     */
    public static function enable(): bool
    {
        return true;
    }

    /**
     * 初始化插件
     *
     * @return void
     */
    public static function register()
    {
    }
    /**
     * 安装
     *
     * @return void
     */
    public static function install()
    {
    }

    /**
     * 卸载
     *
     * @return void
     */
    public static function uninstall()
    {
    }
}
