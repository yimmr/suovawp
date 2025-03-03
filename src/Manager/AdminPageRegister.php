<?php

namespace Suovawp\Manager;

use Suovawp\AdminOptionPage;
use Suovawp\Context;

/**
 * @phpstan-type BuiltinType 'management'|'options'|'theme'|'plugins'|'users'|'dashboard'|'posts'|'media'|'links'
 * @phpstan-type PageEntry string|\Closure(Assets)|array{src:string,style?:string|string[],script?:string|string[],enable_option?:bool,disable_option?:bool}
 * @phpstan-type Page array{
 *      slug?:string,title:string,capability?:string,render?:callable,children?:(Page|array)[],
 *      menu_title?:string,menu_icon?:string,menu_position?:int|float,wp?:BuiltinType|string,inherit?:bool,slug_raw?:string,
 *      model?:class-string,view?:string,entry?:PageEntry,settings?:string,
 * }
 */
class AdminPageRegister
{
    public static $capability = 'manage_options';

    public static $defaultEntry = '';

    /** @var Context */
    public static $ctx;

    private static $viewBaseDir;

    /** @var array<string,Page> */
    private static $pages = [];

    /** @var Page|null */
    private static $current;

    /** @var array<string,Page> */
    private static $builtinPages = [];

    public static function setViewBaseDir($dir)
    {
        self::$viewBaseDir = rtrim($dir, '\/');
    }

    /**
     * @param Page[] $pages
     */
    public static function register(array $pages)
    {
        foreach ($pages as $page) {
            if (!isset($page['wp'])) {
                self::addMenuPage($page);
            } else {
                if (isset($page['entry'])) {
                    self::$builtinPages[$page['wp']] = $page;
                }
                if (!empty($page['children'])) {
                    foreach ($page['children'] as $subpage) {
                        self::addBuiltinPage($page['wp'], $subpage);
                    }
                }
            }
        }
        if (!empty(self::$builtinPages)) {
            add_action('current_screen', self::class.'::currentScreen');
        }
    }

    public static function currentScreen()
    {
        $id = $GLOBALS['hook_suffix'];
        if (!isset(self::$builtinPages[$id])) {
            $id = self::getCurrentScreenId();
            if (!$id || !isset(self::$builtinPages[$id])) {
                return;
            }
        }
        self::registerPageEntry(self::$builtinPages[$id]['entry']);
    }

    /** @param Page $page */
    public static function addMenuPage(array $page)
    {
        $childless = empty($page['children']);
        // 有子级且没有渲染回调时，视为不需渲染
        $render = ($childless || isset($page['render'])) ? [self::class, 'render'] : null;
        $parentCap = $page['capability'] ?? self::$capability;
        $hookSuffix = add_menu_page(
            $page['title'],
            $page['menu_title'] ?? $page['title'],
            $parentCap,
            $page['slug'],
            $render,
            $page['menu_icon'] ?? '',
            $page['menu_position'] ?? null
        );
        if ($childless) {
            self::registerPageHooks($hookSuffix, $page);
            return $hookSuffix;
        }
        $parentSlug = $page['slug'];
        if (!isset($render)) {
            $firstChild = array_shift($page['children']);
            $firstChild['slug_raw'] = $firstChild['slug'];
            $firstChild['slug'] = $parentSlug;
            self::addSubmenuPage($parentSlug, $firstChild);
        }
        foreach ($page['children'] as $child) {
            if ($child['inherit'] ?? true) {
                $child['slug_raw'] = $child['slug'];
                $child['slug'] = $parentSlug.'-'.$child['slug'];
            }
            $child['capability'] ??= $parentCap;
            self::addSubmenuPage($parentSlug, $child);
        }
        self::registerPageHooks($hookSuffix, $page);
        return $hookSuffix;
    }

    /** @param Page $page */
    public static function addSubmenuPage(string $parentSlug, array $page)
    {
        $render = [self::class, 'render'];
        $hookSuffix = add_submenu_page(
            $parentSlug,
            $page['title'],
            $page['menu_title'] ?? $page['title'],
            $page['capability'] ?? self::$capability,
            $page['slug'],
            $render,
            $page['menu_position'] ?? null
        );
        self::registerPageHooks($hookSuffix, $page);
        return $hookSuffix;
    }

    /**
     * @param BuiltinType $type
     * @param Page        $page
     */
    public static function addBuiltinPage(string $type, array $page)
    {
        $callback = "add_{$type}_page";
        if (!function_exists($callback)) {
            return;
        }
        $render = [self::class, 'render'];
        $hookSuffix = $callback(
            $page['title'],
            $page['menu_title'] ?? $page['title'],
            $page['capability'] ?? self::$capability,
            $page['slug'],
            $render,
            $page['menu_position'] ?? null
        );
        self::registerPageHooks($hookSuffix, $page);
        return $hookSuffix;
    }

    public static function render()
    {
        $page = self::getCurrentPageAndFlush($GLOBALS['hook_suffix'], true);
        if (!$page) {
            return;
        }
        if (!empty($page['render'])) {
            call_user_func($page['render']);
            return;
        }
        if (!empty($page['view'])) {
            $file = self::viewPath($page['view']);
        } else {
            $file = isset($page['slug_raw']) && is_file($file = self::viewPath($page['slug_raw']))
                ? $file : self::viewPath($page['slug']);
        }
        if (is_file($file)) {
            (function ($file) { include $file; })($file);
            return;
        }
        add_settings_error('admin_page_render', 'view_not_found', sprintf(
            __('The view file does not exist: %s', 'suovawp'), esc_html($file)
        ));
        settings_errors('admin_page_render');
    }

    protected static function viewPath($path = '')
    {
        if (!$path) {
            return self::$viewBaseDir ?: DIRECTORY_SEPARATOR;
        }
        $view = str_replace('/', DIRECTORY_SEPARATOR, ltrim($path, '/'));
        return (self::$viewBaseDir ? self::$viewBaseDir : '').DIRECTORY_SEPARATOR.$view.'.php';
    }

    public static function getCurrentScreenId()
    {
        $screen = get_current_screen();
        return $screen ? $screen->id : null;
    }

    /**
     * @param Page $page
     */
    public static function registerPageHooks(string $hookSuffix, array $page)
    {
        self::$pages[$hookSuffix] = &$page;
        if (isset($page['entry']) || isset($page['model'])) {
            add_action('load-'.$hookSuffix, [self::class, 'processPageLoad']);
        }
    }

    public static function processPageLoad()
    {
        $hookSuffix = substr(current_filter(), strlen('load-'));
        $page = self::getCurrentPageAndFlush($hookSuffix, false);
        if (!$page) {
            return;
        }
        $ctx = self::$ctx;
        if (isset($page['entry'])) {
            self::registerPageEntry($page['entry']);
        }
        $model = $page['model'] ?? (isset($page['settings']) ? AdminOptionPage::class : null);
        if (!$model) {
            if (isset($page['load'])) {
                ($page['load'])($page);
            }
            return;
        }
        $model = new $model($ctx, $page);
        if ($model instanceof AdminOptionPage) {
            self::$current['render'] = [$model, 'render'];
            $model->loadIf();
        } else {
            if (is_callable([$model, 'loadIf'])) {
                $model->loadIf();
            }
            if (is_callable([$model, 'render'])) {
                self::$current['render'] = [$model, 'render'];
            }
        }
    }

    protected static function getCurrentPageAndFlush($id, $isEnd = false)
    {
        $page = self::getCurrentPage($id);
        if ($isEnd) {
            self::$current = null;
        }
        self::$pages = [];
        return $page;
    }

    protected static function getCurrentPage(string $id)
    {
        if (isset(self::$current)) {
            return self::$current;
        }
        if (isset(self::$pages[$id])) {
            $page = self::$pages[$id];
        } else {
            $id = self::getCurrentScreenId();
            $page = $id ? (self::$pages[$id] ?? null) : null;
        }
        self::$current = $page;
        return $page;
    }

    /**
     * @param PageEntry $entry
     */
    private static function registerPageEntry($entry)
    {
        $ctx = self::$ctx;
        $instance = $ctx->assets;
        if ($entry instanceof \Closure) {
            $entry($instance);
        } elseif (is_array($entry)) {
            $instance->entry($src = $entry['src'] ?? self::$defaultEntry);
            if (false !== strpos($entry['src'], 'enhance-page')) {
                $instance->script('wp-api');
            }
            foreach (['script', 'style'] as $method) {
                if (isset($entry[$method])) {
                    $params = is_array($entry[$method]) ? $entry[$method] : [$entry[$method]];
                    $instance->{$method}(...$params);
                }
            }
            if ($entry['enable_option'] ?? false) {
                $instance->enableOption();
            }
            if ($entry['disable_option'] ?? false) {
                $instance->disableOption();
            }
            if ($entry['form'] ?? false) {
                $entry['media'] ??= true;
                $instance->script('wp-api');
                $instance->style('wp-components', 'font-inter');
            }
            if ($entry['media'] ?? false) {
                $instance->media();
            }
        }
    }
}
