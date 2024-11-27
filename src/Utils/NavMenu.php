<?php

namespace Suovawp\Utils;

/**
 * @phpstan-type NavMenuItem \WP_Post&object{
 *     db_id: positive-int,
 *     menu_item_parent: string,
 *     object_id: string,
 *     object: string,
 *     type: string,
 *     type_label: string,
 *     url: string,
 *     title: string,
 *     target: string,
 *     attr_title: string,
 *     description: string,
 *     classes: array<int, string>,
 *     xfn: string
 * }
 * @phpstan-type NavMenuItemWithChildren NavMenuItem&object{children: NavMenuItemWithChildren[]}
 */
class NavMenu
{
    public static function menu($args = [])
    {
        return wp_nav_menu($args);
    }

    /**
     * @param  string|int|\WP_Term $id 支持主题位置、菜单ID/slug/name和菜单对象
     * @return NavMenuItem[]
     */
    public static function getItems($id)
    {
        $menu = wp_get_nav_menu_object($id);
        if (!$menu) {
            $menu = self::getMenuByLocation($id);
        }
        if (!$menu) {
            return [];
        }
        $items = wp_get_nav_menu_items($menu->term_id, ['update_post_term_cache' => false]);
        return $items ?: [];
    }

    /** 通过菜单位置获取菜单 */
    public static function getMenuByLocation(string $location)
    {
        $locations = get_nav_menu_locations();
        if ($locations && isset($locations[$location])) {
            return wp_get_nav_menu_object($locations[$location]) ?: null;
        }
        return null;
    }

    /** 获取存在菜单项的第一个菜单 */
    public static function getFirstMenuWithItems()
    {
        $menus = wp_get_nav_menus();
        foreach ($menus as $menu) {
            if ($menu->count > 0) {
                return $menu;
            }
        }
        return null;
    }

    /**
     * 获取结构的菜单项.
     *
     * @template I
     * @param  string|int|\WP_Term                                 $id
     * @param  \Closure(NavMenuItemWithChildren):I                 $filter 过滤器
     * @return ($filter is null ? NavMenuItemWithChildren[] : I[])
     */
    public static function getItemsTree($id, $filter = null)
    {
        $items = self::getItems($id);
        $parentMap = [];
        $childMap = [];
        $result = [];
        foreach ($items as $item) {
            $id = $item->ID;
            $parentId = $item->menu_item_parent;
            $newItem = $filter ? $filter($item) : $item;
            if (!isset($parentMap[$id])) {
                $newItem->children = $childMap[$id] ?? [];
                $parentMap[$id] = $newItem;
            }
            if ($parentId) {
                if (isset($parentMap[$parentId])) {
                    $parentMap[$parentId]->children[] = $newItem;
                } else {
                    $childMap[$parentId][] = $newItem;
                }
            } else {
                $result[] = $newItem;
            }
        }
        return $result;
    }
}
