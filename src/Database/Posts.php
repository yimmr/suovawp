<?php

namespace Suovawp\Database;

/**
 * @extends Schema<object,\WP_Post,\WP_Query>
 */
class Posts extends Schema
{
    public const TABLE = 'posts';

    public const ID = 'ID';

    public static function getDefaultQueryParams()
    {
        return [
            'orderby'             => 'date',
            'order'               => 'DESC',
            'post_type'           => 'post',
            'ignore_sticky_posts' => 1,
        ];
    }

    public static function execQuery($params)
    {
        $args = static::createWPQueryRefiner($params)
            ->transform(static::class.'::transformArgs')
            ->sort(static::class.'::transformOrderby', static::class.'::buildSQLOrderby', ['sticky'])
            ->getArgs();
        return WPQueryUtils::wpQueryToQueryResultProps($params, $args);
    }

    public static function transformArgs($args)
    {
        if (!empty($args['taxonomy']) && !empty($args['term_id'])) {
            $args['tax_query'][] = [
                'taxonomy' => $args['taxonomy'],
                'terms'    => $args['term_id'],
                'field'    => 'term_id',
            ];
        }
        if (isset($args['ids'])) {
            if (empty($args['ids'])) {
                $args['p'] = -1;
            } else {
                $args['post__in'] = is_array($args['ids']) ? $args['ids'] : [$args['ids']];
                $args['posts_per_page'] = count($args['post__in']);
            }
            unset($args['ids']);
        }
        if (!empty($args['include'])) {
            $incposts = wp_parse_id_list($args['include']);
            $args['posts_per_page'] = count($incposts);
            $args['post__in'] = $incposts;
        } elseif (!empty($args['exclude'])) {
            $args['post__not_in'] = wp_parse_id_list($args['exclude']);
        }
        if (empty($args['post_status'])) {
            $args['post_status'] = ('attachment' === $args['post_type']) ? 'inherit' : 'publish';
        }
        return $args;
    }

    public static function transformOrderby($orderby, $sort, &$args)
    {
        switch ($orderby) {
            case 'old':
                return ['date', 'asc'];
            case 'unhot':
                return ['hot', 'asc'];
            case 'likes':
                $args['meta_query'][] = WPQueryUtils::metaWithNotExistsClause($by = 'like_clause', '_like_count');
                return [$by, $sort];
            default:
                return [$orderby, $sort];
        }
    }

    public static function buildSQLOrderby($orderby, $sort = 'ASC')
    {
        global $wpdb;
        switch ($orderby) {
            case 'sticky':
                $idstr = static::getStickyPostIdsStr();
                return $idstr ? "CASE WHEN {$wpdb->posts}.ID IN ({$idstr}) THEN 0 ELSE 1 END {$sort}" : '';
            default:
                return '';
        }
    }

    public static function createWPQueryRefiner($params)
    {
        return (new WPQueryRefiner($params, 'posts'))->map(WPQueryUtils::POSTS_PARAM_MAP);
    }

    public static function getStickyPostIds()
    {
        return (array) get_option('sticky_posts', []);
    }

    public static function getStickyPostIdsStr()
    {
        return implode(',', static::getStickyPostIds());
    }
}
