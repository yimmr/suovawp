<?php

namespace Suovawp\Enhance;

class Metabox extends Enhance
{
    protected $postType;

    protected $options = [];

    protected $vendor;

    protected $transientKey;

    private static $lastBoxId = 0;

    public function __construct($options = [])
    {
        $this->options = $options;
        $this->vendor = $options['vendor'] ?? 'suovawp';
        $this->postType = $options['post_type'];
        $this->transientKey = $this->vendor.'_'.$this->postType.'_metabox_errors';
    }

    public function setupPage()
    {
        add_action("save_post_{$this->postType}", [$this, 'save']);

        add_action("add_meta_boxes_{$this->postType}", function () {
            add_meta_box(
                $this->options['id'] ?? $this->generateMetaboxId(),
                $this->options['title'] ?? 'Metabox',
                [$this, 'renderMetabox'],
                $this->postType,
                $this->options['context'] ?? 'advanced',
                $this->options['priority'] ?? 'default',
                $this->options['callback_args'] ?? null
            );
        });
    }

    protected function generateMetaboxId()
    {
        return $this->vendor.'-'.$this->postType.'-metabox-'.(++self::$lastBoxId);
    }

    /**
     * @param \WP_Post $post
     */
    public function renderMetabox($post)
    {
        echo static::enhanceRoot($this->getEnhanceRootData($post->ID));
    }

    public function getMeta($id, $key = '', $single = false)
    {
        return get_post_meta($id, $key, $single);
    }

    public function updateMeta($id, $key, $value, $prevValue = '')
    {
        return update_post_meta($id, $key, $value, $prevValue);
    }

    protected function filterExistedMetaKeys($id, $keys = [])
    {
        global $wpdb;
        $keystr = '\''.implode("','", $keys).'\'';
        $sql = "SELECT meta_key FROM $wpdb->postmeta WHERE post_id = %d AND meta_key IN ($keystr)";
        $sql = $wpdb->prepare($sql, $id);
        $rows = $wpdb->get_results($sql, ARRAY_A);
        return array_map(fn ($row) => $row['meta_key'], $rows);
    }
}
