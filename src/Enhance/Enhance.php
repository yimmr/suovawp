<?php

namespace Suovawp\Enhance;

use Suovawp\Utils\Arr;
use Suovawp\Utils\FormField;

abstract class Enhance
{
    protected $options = [];

    protected $vendor;

    protected $transientKey;

    abstract public function getMeta($id, $key = '', $single = false);

    abstract public function updateMeta($id, $key, $value, $prevValue = '');

    abstract public function deleteMeta($id, $key, $value = '');

    /**
     * @param array{vendor?:string,compact?:bool,compact_prefix?:string,fields:Field[]} $options
     */
    public function __construct($options = [])
    {
        $this->options = $options;
        $this->vendor = $options['vendor'] ?? 'suovawp';
    }

    public function setupPage()
    {
    }

    public function getEnhanceRootData($id = null)
    {
        $initValue = $id ? $this->getData($id) : [];
        $errors = $this->getFormErrors();
        return [
            'fields'     => FormField::toClientFields($this->getFields(), [], $this->vendor),
            'data'       => $initValue,
            'errors'     => $errors,
            'root_class' => $this->options['root_class'] ?? '',
        ];
    }

    public function save($id)
    {
        if (empty($_POST[$this->vendor]) || !is_array($_POST[$this->vendor])) {
            return;
        }
        $input = $_POST[$this->vendor];
        $validator = FormField::createValidator($this->getFields());
        $result = $validator->safeParse($input);
        if (!$result['success']) {
            $this->addFormErrors($result['error']->format());
            $partialData = $validator->getValidated();
            if ($partialData) {
                $oldData = $this->getData($id);
                $partialData = Arr::mergeRecursive($oldData, $partialData);
                $this->saveData($id, $partialData);
            }
            return;
        }
        $this->saveData($id, $result['data']);
    }

    public function saveData($id, array $data)
    {
        $filter = $this->options['save_filter'] ?? null;
        if (!empty($filter)) {
            $data = $filter($data, $id);
        }
        $data = apply_filters('suovawp_enhance_save_data', $data, $id);
        $allKeys = array_column($this->getFields(), 'id');
        if ($this->shouldbeCompact()) {
            $result = $this->updateMeta($id, $this->getCompactMetaKey(), $data);
            return $result;
        }
        $deleteKeys = array_diff($allKeys, array_keys($data));
        foreach ($deleteKeys as $key) {
            $this->deleteMeta($id, $key);
        }
        foreach ($data as $key => $value) {
            $this->updateMeta($id, $key, $value);
        }
    }

    public function getData($id)
    {
        if (!$id) {
            return [];
        }
        if ($this->shouldbeCompact()) {
            $data = $this->getMeta($id, $this->getCompactMetaKey());
            return is_array($data) ? $data : [];
        }
        $data = [];
        $keys = [];
        foreach ($this->getFields() as $field) {
            empty($field['id']) || ($keys[] = $field['id']);
        }
        $keys = $this->filterExistedMetaKeys($id, $keys);
        foreach ($keys as $key) {
            $data[$key] = $this->getMeta($id, $key, true);
        }
        $filter = $this->options['get_filter'] ?? null;
        if (!empty($filter)) {
            $data = $filter($data, $id);
        }
        $data = apply_filters('suovawp_enhance_get_data', $data, $id);
        return $data;
    }

    protected function filterExistedMetaKeys($id, $keys = [])
    {
        return $keys;
    }

    public function addFormErrors($data)
    {
        set_transient($this->transientKey, $data, 60);
    }

    public function getFormErrors()
    {
        $errors = get_transient($this->transientKey);
        delete_transient($this->transientKey);
        if ($errors) {
            $this->addSettingsError('partial_error', __('部分选项因校验失败未更新，请找到对应字段查看原因。', 'suovawp'));
        }
        return $errors ?: null;
    }

    public function shouldbeCompact()
    {
        return $this->options['compact'] ?? false;
    }

    public function getCompactMetaKey()
    {
        return $this->options['compact_prefix'] ?? $this->vendor;
    }

    public function getFields()
    {
        if (isset($this->options['fields'])) {
            return is_array($this->options['fields']) ? $this->options['fields'] : [];
        }
        return [];
    }

    public function addSettingsError($code, $message, $type = 'error')
    {
        add_settings_error('enhance-settings', $code, $message, 'error');
    }

    public static function enhanceRoot($data)
    {
        settings_errors('enhance-settings');
        $attr = ' class="enhance-root'.(isset($data['root_class']) ? ' '.$data['root_class'] : '').'"';
        return '<div id="enhance-root"'.$attr.'><script type="application/json">'.json_encode($data).'</script></div>';
    }
}
