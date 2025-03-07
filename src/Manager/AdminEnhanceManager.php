<?php

namespace Suovawp\Manager;

use Suovawp\Context;
use Suovawp\Enhance\AdminEnhance;

/**
 * 核心思路：先提供一些少量标识和配置key，在实际需要时完整加载所需配置.
 *
 * @phpstan-type AdminEnhanceManagerConfig array{
 *      assets?:string|array{string,mixed}|callable(\Suovawp\Assets):void,
 *      fields?:array<string,mixed>
 *      metaboxes?:array<>
 * }
 */
class AdminEnhanceManager
{
    /** @var Context */
    public static $ctx;

    /** @var string|null */
    public static $defaultFormEntry;

    private static $registry = [];

    private static $globalRegistry = [];

    /**
     * @param array<string,string>   $registry 数组 `current_screen::id=>config_key`
     * @param array<string,string[]> $global   多页面共享的数组 `config_key=>current_screen::id[]`
     */
    public static function register($registry = [], $global = [])
    {
        self::$registry += $registry;
        self::$globalRegistry += $global;
        add_action('current_screen', [self::class, 'setupCurrentScreen']);
        add_action('saved_term', [self::class, 'handleTermSaved'], 10, 3);
    }

    public static function taxPage(string $taxonomy, $configKey)
    {
        self::$registry['edit-'.$taxonomy] = $configKey;
    }

    /**
     * @param string|array{edit:string,post:string} $configKey 字符串用作发布界面，数组可含有edit和post，edit是列表页
     */
    public static function postPage(string $postType, $configKey)
    {
        if (is_string($configKey)) {
            self::$registry[$postType] = $configKey;
            return;
        }
        if (isset($configKey['post'])) {
            self::$registry[$postType] = $configKey['post'];
        }
        if (isset($configKey['edit'])) {
            self::$registry['edit-'.$postType] = $configKey['edit'];
        }
    }

    /**
     * @param \WP_Screen $screen
     */
    public static function setupCurrentScreen($screen)
    {
        self::callEnhance($screen->id, static fn ($enhance) => $enhance->setScreen($screen)->loadIf());
    }

    public static function handleTermSaved($termId, $ttId, $taxonomy)
    {
        if (empty($_POST['action']) || !in_array($_POST['action'], ['add-tag', 'editedtag'])) {
            return;
        }
        self::callEnhance('edit-'.$taxonomy, static fn ($enhance) => $enhance->setTaxonomy($taxonomy)
            ->termSave($termId, $ttId, $taxonomy));
    }

    /**
     * @param string                 $id
     * @param \Closure(AdminEnhance) $func
     */
    public static function callEnhance($id, \Closure $func)
    {
        foreach (self::$globalRegistry as $key => $ids) {
            if (in_array($id, $ids)) {
                $func(self::getEnhance('global_'.$id, $key));
            }
        }
        if (!empty(self::$registry[$id])) {
            $func(self::getEnhance($id, self::$registry[$id]));
        }
    }

    /**
     * @param  string|array<string,mixed> $config
     * @return AdminEnhance
     */
    public static function getEnhance(string $id, $config)
    {
        $instanceId = 'admin_enhance_'.$id;
        $container = self::$ctx->getContainer();
        if ($container->hasInstance($instanceId)) {
            return $container->get($instanceId);
        }
        $instance = new AdminEnhance(self::$ctx, is_string($config) ? self::parseConfig($config) : $config);
        $container->instance($instanceId, $instance);
        return $instance;
    }

    protected static function parseConfig(string $configKey)
    {
        $props = self::$ctx->config->array($configKey);
        if (self::$defaultFormEntry && isset($props['assets'])) {
            if (empty($props['assets']['entry']) && ($props['assets']['form'] ?? false)) {
                $props['assets']['entry'] = self::$defaultFormEntry;
                $props['assets']['script'] = 'wp-api';
            }
        }
        return $props;
    }
}
