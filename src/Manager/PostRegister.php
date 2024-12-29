<?php

namespace Suovawp\Manager;

class PostRegister
{
    public static function register(array $posttypes, array $taxonomies = [])
    {
        self::posttype($posttypes);
        self::taxonomy($taxonomies);
    }

    /**
     * 注册类型及分类法等相关功能.
     */
    public static function posttype(array $posttypes)
    {
        global $wp_post_types;

        $override = [];
        $linkFilters = [];

        foreach ($posttypes as $posttype => $params) {
            $params = apply_filters('suovawp_post_type_params', $params, $posttype);

            if (isset($params['admin_enhance'])) {
                AdminEnhanceManager::postPage($posttype, $params['admin_enhance']);
                unset($params['admin_enhance']);
            }

            if (isset($params['meta'])) {
                foreach ($params['meta'] as $key => $args) {
                    register_post_meta($posttype, $key, $args);
                }
                unset($params['meta']);
            }

            if (isset($params['taxonomies'])) {
                self::taxonomy($params['taxonomies']);
                $params['taxonomies']['object_type'] = $posttype;
                unset($params['taxonomies']);
            }

            if (isset($params['link_filter'])) {
                $filter = $params['link_filter'];
                unset($params['link_filter']);
                if (($params['link_filter_level'] ?? 10) == 10) {
                    $linkFilters[$posttype] = $filter;
                } else {
                    $func = static fn ($link, $post, $leavename) => $post->post_type === $posttype ? $filter($link, $post, $leavename) : $link;
                    add_filter('post_link', $func, 10, 3, $params['link_filter_level']);
                }
            }

            if (isset($wp_post_types[$posttype])) {
                $override[$posttype] = $params;
            } else {
                register_post_type($posttype, $params);
            }
        }

        if ($override) {
            $func = static fn ($args, $type) => !isset($override[$type]) ? $args : array_replace_recursive($args, $override[$type]);
            add_filter('register_post_type_args', $func, 10, 2);
        }

        if ($linkFilters) {
            $func = static fn ($link, $post, $leavename) => isset($linkFilters[$post->post_type])
                ? ($linkFilters[$post->post_type])($link, $post, $leavename) : $link;
            add_filter('post_link', $func, 10, 3);
        }
    }

    /**
     * 注册分类法和相关功能.
     */
    public static function taxonomy(array $taxonomies)
    {
        global $wp_taxonomies;

        $override = [];
        $linkFilters = [];

        foreach ($taxonomies as $taxonomy => $params) {
            $params = apply_filters('suovawp_taxonomy_params', $params, $taxonomy);

            $posttype = $params['object_type'] ?? '';
            unset($params['object_type']);

            if (isset($params['admin_enhance'])) {
                AdminEnhanceManager::taxPage($taxonomy, $params['admin_enhance']);
                unset($params['admin_enhance']);
            }

            if (isset($params['meta'])) {
                foreach ($params['meta'] as $key => $args) {
                    register_term_meta($taxonomy, $key, $args);
                }
                unset($params['meta']);
            }

            if (isset($params['link_filter'])) {
                $filter = $params['link_filter'];
                unset($params['link_filter']);
                if (($params['link_filter_level'] ?? 10) == 10) {
                    $linkFilters[$taxonomy] = $filter;
                } else {
                    $func = static fn ($link, $term, $tax) => $tax === $taxonomy ? $filter($link, $term, $tax) : $link;
                    add_filter('term_link', $func, 10, 3, $params['link_filter_level']);
                }
            }

            if (isset($wp_taxonomies[$taxonomy])) {
                $overrideverride[$taxonomy] = $params;
            } else {
                register_taxonomy($taxonomy, $posttype, $params);
            }
        }

        if ($override) {
            $func = static fn ($args, $tax) => !isset($override[$tax]) ? $args : array_replace_recursive($args, $override[$tax]);
            add_filter('register_taxonomy_args', $func, 10, 2);
        }

        if ($linkFilters) {
            $func = static fn ($link, $term, $tax) => isset($linkFilters[$tax]) ? ($linkFilters[$tax])($link, $term, $tax) : $link;
            add_filter('term_link', $func, 10, 3);
        }
    }

    /**
     * 旧的方式. 注册元框.
     *
     * @param string $posttype
     */
    public static function metaBoxes($posttype, array $metaBoxes)
    {
        if (!is_admin() || empty($metaBoxes)) {
            return;
        }

        add_action("save_post_{$posttype}", function ($postid) use ($metaBoxes) {
            foreach ($metaBoxes as $box) {
                (new $box[2]())->save($postid);
            }
        });

        add_action("add_meta_boxes_{$posttype}", function () use ($metaBoxes) {
            array_map(function ($box) {
                $box[2] = [new $box[2](), 'render'];
                add_meta_box(...$box);
            }, $metaBoxes);
        });
    }

    /**
     * 旧的方式.分类法添加自定义字段.
     *
     * @param string $taxonomy
     */
    public static function addTaxField($taxonomy, array $fields = [])
    {
        array_map(function ($field) use ($taxonomy) {
            $object = new $field($taxonomy);

            add_action("{$taxonomy}_add_form_fields", [$object, 'addFields']);
            add_action("{$taxonomy}_edit_form_fields", [$object, 'editFields']);

            if (method_exists($object, 'save')) {
                add_action("saved_{$taxonomy}", function ($termid) use ($object) {
                    if (isset($_POST['action']) && in_array($_POST['action'], ['add-tag', 'editedtag'])) {
                        $object->save($termid);
                    }
                });
            }
        }, $fields);
    }

    /**
     * 旧的方式.全部分类法添加自定义字段.
     *
     * @param array $exclude 排除的分类法
     */
    public static function addGlobalTaxField(array $fields, array $exclude = [])
    {
        array_map(function ($taxonomy) use ($fields, $exclude) {
            in_array($taxonomy, $exclude) || self::addTaxField($taxonomy, $fields);
        }, get_taxonomies(['show_ui' => true, '_builtin' => false]), ['category', 'post_tag']);
    }
}
