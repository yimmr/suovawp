<?php

namespace Suovawp\Utils;

class Entity
{
    /**
     * @param scalar[] $ids
     *
     * @return int[]
     */
    public static function postIdFilter(array $ids)
    {
        global $wpdb;
        $value = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE ID IN (".implode(', ', $ids).')');
        return is_array($value) ? array_map('intval', $value) : [];
    }
}
