<?php

namespace Suovawp\Enhance;

use Suovawp\Utils\FormField;

abstract class Enhance
{
    protected $options = [];

    protected $vendor;

    protected $transientKey;

    abstract public function getMeta($id, $key = '', $single = false);

    abstract public function updateMeta($id, $key, $value, $prevValue = '');

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
            'fields' => FormField::toClientFields($this->getFields(), $initValue, $this->vendor),
            'errors' => $errors,
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
                $this->saveData($id, $partialData);
            }
            return;
        }
        $this->saveData($id, $result['data']);
    }

    public function saveData($id, array $data)
    {
        if ($this->shouldbeCompact()) {
            $result = $this->updateMeta($id, $this->getCompactMetaKey(), $data);
            return $result;
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

    public static function enhanceRoot($data)
    {
        return '<div id="enhance-root"><script type="application/json">'.json_encode($data).'</script></div>';
    }
}
