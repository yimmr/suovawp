<?php

namespace Suovawp\Enhance;

/**
 * @phpstan-import-type Field from FormField
 */
class TaxForm extends Enhance
{
    protected $taxonomy;

    public function __construct($options = [])
    {
        parent::__construct($options);
        $this->taxonomy = $options['taxonomy'];
        $this->transientKey = $this->vendor.'_taxonomy_errors';
    }

    public function setupPage()
    {
        add_action("{$this->taxonomy}_add_form_fields", [$this, 'addFormFields']);
        add_action("{$this->taxonomy}_edit_form_fields", [$this, 'editFormFields']);
    }

    /** 回调可以有个 $taxonomy 参数. */
    public function addFormFields()
    {
        echo static::enhanceRoot($this->getEnhanceRootData());
    }

    /**
     * @param \WP_Term $term
     */
    public function editFormFields($term)
    {
        static::editFormField('', static::enhanceRoot($this->getEnhanceRootData($term->term_id)));
    }

    public function getMeta($id, $key = '', $single = false)
    {
        return get_term_meta($id, $key, $single);
    }

    public function updateMeta($id, $key, $value, $prevValue = '')
    {
        return update_term_meta($id, $key, $value, $prevValue);
    }

    protected function filterExistedMetaKeys($termid, $keys = [])
    {
        global $wpdb;
        $keystr = '\''.implode("','", $keys).'\'';
        $sql = "SELECT meta_key FROM $wpdb->termmeta WHERE term_id = %d AND meta_key IN ($keystr)";
        $sql = $wpdb->prepare($sql, $termid);
        $rows = $wpdb->get_results($sql, ARRAY_A);
        return array_map(fn ($row) => $row['meta_key'], $rows);
    }

    /**
     * 新建标签时的字段.
     *
     * @param string $label
     * @param string $content
     * @param string $tips
     */
    public static function addFormField($label, $content = '', $tips = '')
    {
        echo '<div class="form-field">';
        echo '<label>'.$label.'</label>';
        echo $content;
        echo '</div>';
        echo $tips ? "<p>{$tips}</p>" : '';
    }

    /**
     * 编辑标签时的字段.
     *
     * @param string $label
     * @param string $content
     * @param string $tips
     */
    public static function editFormField($label, $content = '', $tips = '')
    {
        echo '<tr class="form-field">';
        echo '<th scope="row">'.$label.'</th>';
        echo '<td>'.$content.'</td>';
        echo '</tr>';
        echo $tips ? '<p class="description">'.$tips.'</p>' : '';
    }
}
