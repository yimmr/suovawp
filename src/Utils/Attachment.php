<?php

namespace Suovawp\Utils;

class Attachment
{
    public static function isId($id, $type = 'image')
    {
        return is_numeric($id) && $id > 0 && static::is($id, $type);
    }

    /**
     * @param int|WP_Post|string $id   除了内置功能，还支持通过url判断
     * @param string             $type image|audio|video
     *
     * @return bool
     */
    public static function is($id, $type = 'image')
    {
        if (empty($id)) {
            return false;
        }
        $result = is_string($id) && !is_numeric($id) ? static::getIDByURL($id, $type, true) : wp_attachment_is($type, $id);
        return (bool) $result;
    }

    public static function isWithParent($id, $type = 'image', $parentPostId = 0)
    {
        if (empty($id)) {
            return false;
        }
        if (is_string($id) && !is_numeric($id)) {
            $id = static::getIDByURL($id, $type, true);
        } elseif (!wp_attachment_is($type, $id)) {
            return false;
        }
        $post = get_post($id);
        if (!($post instanceof \WP_Post)) {
            return false;
        }
        return $post->post_parent == $parentPostId;
    }

    public static function getIDByURL($url, $type = '', $likePath = false)
    {
        global $wpdb;
        $id = attachment_url_to_postid($url);
        if ($id <= 0 && $likePath) {
            $path = parse_url($url, PHP_URL_PATH);
            $sql = "SELECT ID,post_mime_type FROM $wpdb->posts WHERE guid LIKE %s AND post_type = 'attachment'";
            if ($type) {
                if (false !== strpos($type, '/')) {
                    $sql .= ' AND post_mime_type = %s';
                } else {
                    $sql .= ' AND post_mime_type LIKE %s';
                    $type = $wpdb->esc_like($type).'%';
                }
                $id = $wpdb->get_var($wpdb->prepare($sql, '%'.$wpdb->esc_like($path), $type));
            } else {
                $id = $wpdb->get_var($wpdb->prepare($sql, '%'.$wpdb->esc_like($path)));
            }
        }
        return (int) $id;
    }

    public static function getByURL($url, $type = '', $likePath = false)
    {
        $id = static::getIDByURL($url, $type, $likePath);
        return $id ? get_post($id) : null;
    }
}
