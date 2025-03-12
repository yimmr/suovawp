<?php

namespace Suovawp\Database;

class WPQueryUtils
{
    public const POSTS_PARAM_MAP = [
        'page'     => 'paged',
        'per_page' => 'posts_per_page',
        'id'       => 'p',
        'ids'      => 'post__in',
        'search'   => 's',
        'status'   => 'post_status',
        'type'     => 'post_type',
        'user_id'  => 'author',
    ];

    public const TERMS_PARAM_MAP = [
        'paged'    => 'page',
        'per_page' => 'number',
        'id'       => 'include',
        'ids'      => 'include',
    ];

    public const COMMENTS_PARAM_MAP = [
        'page'     => 'paged',
        'per_page' => 'number',
        'id'       => 'comment__in',
        'ids'      => 'comment__in',
    ];

    public const USERS_PARAM_MAP = [
        'page'     => 'paged',
        'per_page' => 'number',
        'id'       => 'include',
        'ids'      => 'include',
    ];

    public static function wpQueryToQueryResultProps($params, $args, ?\WP_Query $query = null)
    {
        $query ??= new \WP_Query($args);
        return [
            'query'         => $query,
            'items'         => $query->posts ?: [],
            'total'         => $query->found_posts,
            'pages'         => (int) $query->max_num_pages,
            'per_page'      => (int) $query->get('posts_per_page', 1),
            'page'          => max(1, (int) $query->get('paged', 1)),
            'params'        => $params,
            'wp_query_args' => $args,
        ];
    }

    public static function wpTermQueryToQueryResultProps($params, $args)
    {
        if (isset($args['page'])) {
            $args['offset'] = (int) (($args['page'] - 1) * $args['number']);
            unset($args['page']);
        }
        $query = new \WP_Term_Query();
        if (isset($args['include']) && empty($args['include'])) {
            $query->query_vars = $args;
            $items = $query->terms = [];
            $total = 0;
        } else {
            $items = $query->query($args) ?: [];
            $total = null;
        }
        $offset = (int) ($query->query_vars['offset'] ?? 0);
        $number = (int) ($query->query_vars['number'] ?? 0);
        return [
            'query'          => $query,
            'items'          => $items,
            'per_page'       => $number,
            'page'           => $number ? (int) max(1, floor($offset / $number) + 1) : 1,
            'total'          => $total,
            'params'         => $params,
            'wp_query_args'  => $args,
            'total_callback' => fn () => is_numeric($count = (new \WP_Term_Query())->query(
                ['fields' => 'count', 'number' => null, 'offset' => null] + $args)) ? (int) $count : 0,
        ];
    }

    public static function wpCommentQueryToQueryResultProps($params, $args)
    {
        $query = new \WP_Comment_Query();
        $args = static::transformArgToArrayIf($args, 'comment__in');
        $items = $query->query($args);
        $vars = $query->query_vars;
        $perPage = intval($vars['number']);
        $page = empty($vars['paged']) ? floor((int) $vars['offset'] / max(1, $perPage)) + 1 : $vars['paged'];
        return [
            'query'          => $query,
            'items'          => $items,
            'per_page'       => $perPage,
            'page'           => (int) $page,
            'params'         => $params,
            'wp_query_args'  => $args,
            'total_callback' => fn () => is_numeric($count = get_comments(
                ['count' => true, 'paged' => null, 'number' => null, 'offset' => null] + $args)) ? (int) $count : 0,
        ];
    }

    public static function wpUserQueryToQueryResultProps($params, $args)
    {
        $args['count_total'] ??= false;
        $args = static::transformArgToArrayIf($args, 'include');
        $query = new \WP_User_Query($args);
        return [
            'query'          => $query,
            'items'          => $query->get_results(),
            'per_page'       => max(0, $query->query_vars['number'] ?? 1),
            'page'           => max(1, $query->query_vars['paged'] ?? 1),
            'total'          => $query->get_total() ?: null,
            'params'         => $params,
            'wp_query_args'  => $args,
            'total_callback' => fn () => is_numeric($count = (new \WP_User_Query(
                ['count_total' => true, 'paged' => null, 'number' => null, 'offset' => null] + $args))->get_total()) ? (int) $count : 0,
        ];
    }

    public static function transformArgToArrayIf($args, $key)
    {
        if (isset($args[$key]) && !is_array($args[$key])) {
            $args[$key] = [$args[$key]];
        }
        return $args;
    }

    /**
     * 创建一个可选的meta条件，类似 `metaWithNotExistsClause` 但没有`$notExistsKey`，不需排序时使用此方法.
     */
    public static function metaWithNotExists($key, $type = 'NUMERIC', $value = null, $compare = 'EXISTS', $relation = 'OR')
    {
        $meta = ['key' => $key, 'type' => $type, 'compare' => $compare];
        if (isset($value)) {
            $meta['value'] = $value;
        }
        return [
            $meta,
            ['key'     => $key, 'type' => $type, 'compare' => 'NOT EXISTS'],
            'relation' => $relation,
        ];
    }

    public static function metaWithNotExistsClause($notExistsKey, $key, $type = 'NUMERIC', $value = null, $compare = 'EXISTS', $relation = 'OR')
    {
        $meta = ['key' => $key, 'type' => $type, 'compare' => $compare];
        if (isset($value)) {
            $meta['value'] = $value;
        }
        return [
            $meta,
            $notExistsKey => ['key' => $key, 'type' => $type, 'compare' => 'NOT EXISTS'],
            'relation'    => $relation,
        ];
    }

    public static function metaClauseWrapNotExists($notExistsKey, $clause, $relation = 'OR')
    {
        $clause['type'] ??= 'NUMERIC';
        return [
            $clause,
            $notExistsKey => ['key' => $clause['key'], 'type' => $clause['type'], 'compare' => 'NOT EXISTS'],
            'relation'    => $relation,
        ];
    }

    public static function metaNotEmptyClause($metaKey)
    {
        return [
            'relation' => 'AND',
            [
                'key'     => $metaKey,
                'compare' => 'EXISTS',
            ],
            [
                'key'     => $metaKey,
                'value'   => ['', 'a:0:{}', '0'],
                'compare' => 'NOT IN',
            ],
        ];
    }

    public static function metaClause($key, $value = '', $compare = '=', $type = 'CHAR')
    {
        return [
            'key'     => $key,
            'value'   => $value,
            'compare' => $compare,
            'type'    => $type,
        ];
    }

    public static function taxClause($taxonomy, $terms, $field = 'term_id', $operator = 'IN')
    {
        return [
            'taxonomy' => $taxonomy,
            'field'    => $field,
            'terms'    => $terms,
            'operator' => $operator,
        ];
    }

    public static function paramToTaxQueryIf(&$args, $taxonomy, $key, $alias, &$has = false)
    {
        if (!empty($args[$key])) {
            $has = true;
            static::appendTaxQueryAutoAND($args, $taxonomy, $args[$key]);
            unset($args[$key]);
        } elseif (!empty($args[$alias])) {
            $has = true;
            static::appendTaxQueryAutoAND($args, $taxonomy, $args[$alias]);
            unset($args[$alias]);
        }
    }

    public static function appendTaxQueryAutoAND(&$args, $taxonomy, $terms, $field = 'term_id', $operator = 'IN')
    {
        $args['tax_query'][] = static::taxClause($taxonomy, $terms, $field, $operator);
        $args['tax_query']['relation'] ??= 'AND';
    }

    public static function appendTaxQueryAutoOR(&$args, $taxonomy, $terms, $field = 'term_id', $operator = 'IN')
    {
        $args['tax_query'][] = static::taxClause($taxonomy, $terms, $field, $operator);
        $args['tax_query']['relation'] ??= 'OR';
    }
}
