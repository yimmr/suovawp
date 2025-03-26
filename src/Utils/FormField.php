<?php

namespace Suovawp\Utils;

use Suovawp\Validation\Types\Str as TypesStr;
use Suovawp\Validation\Validator as v;

/**
 * @phpstan-type FieldType 'custom'|'text'|'textarea'|'input'|'number'|'range'|'toggle'|'checkbox'|'radio'|'select'|'tree-select'|
 *      'date-picker'|'color-palette'|'media'|'upload'|'code'|'group'|'fieldset'
 * @phpstan-type Tree array{id:string,name:string,children?:Tree[]}
 * @phpstan-type HTMLInputType 'text'|'password'|'email'|'number'|'tel'|'url'|'search'|'date'|'time'|'datetime-local'|'color';
 * @phpstan-type MediaType 'image'|'video'|'audio'|'file'|'application'
 * @phpstan-type Field array{
 *      id:string,
 *      type:FieldType,
 *      label:string,
 *      default?:mixed,name?:string,
 *      disabled?:bool,required?:bool,optional?:bool,
 *      min?:mixed,max?:mixed,step?:number,
 *      rows?:int,cols?:int,
 *      between?:array,refine?:callable,transform?:callable,sanitize?:array|string,
 *      rule?:string,
 *      width?:'full'|'1/2'|'1/3'|string,className?:string,
 *      value?:string,multiple?:bool,
 *      options?:array<scalar,string>|array<label:string,value:scalar>[],
 *      inline?:bool,width?:'full'|'1/2'|'1/3'|'20'|string,className?:string|string[],style?:string,
 *      help?:string,placeholder?:string,description?:string,
 *      tree?:Tree[],
 *      children?:string,html?:bool,
 *      variant?: HTMLInputType|'default'|'minimal',
 *      show_count?:bool,
 *      checked?:bool,format?:string,show_time?:boolean,
 *      colors?:array{color:string,name:string}[],
 *      accept?: MediaType|MediaType[],query?:array{author?:number,uploadedTo?:number},
 *      modalTitle?:string,modalButtonText?:string,buttonText?:string,
 *      cards?:array{
 *          showTitle?:boolean,replaceText?: string,removeText?: string,
 *          layout?: 'single'|'normal'|'compact'|'loose'|'icon'|Cols,gap?:number,
 *          ratio?: 'auto'|'square'|'auto'|'3/4'|string,
 *          objectFit?: 'cover'|'contain'|'fill'|'none'|'scale-down'
 *      },
 *      opened?:bool,initOpen?:bool,
 *      height?:string,language?:string,lang?:string,
 *      theme?:"light"|"dark"|"github"|"github-light"|"github-dark"|"duotone"|"duotone-light"|"duotone-dark"|"sublime"|"atomone"|"dracula",
 */
class FormField
{
    /**
     * 内部组件没有能本地化翻译，一种临时解决方案.
     */
    public static function getLocaleText()
    {
        return [
            'Time'      => '时间',
            'Date'      => '日期',
            'January'   => '1月',
            'February'  => '2月',
            'March'     => '3月',
            'April'     => '4月',
            'May'       => '5月',
            'June'      => '6月',
            'July'      => '7月',
            'August'    => '8月',
            'September' => '9月',
            'October'   => '10月',
            'November'  => '11月',
            'December'  => '12月',
            'Sun'       => '周日',
            'Mon'       => '周一',
            'Tue'       => '周二',
            'Wed'       => '周三',
            'Thu'       => '周四',
            'Fri'       => '周五',
            'Sat'       => '周六',
        ];
    }

    /**
     * @param Field[] $fields
     * @param array   $current 当前数据库中的值作为参考，对应id键存在时不设置默认值。
     *                         - 此项用于避免提交空表单时意外地重置为默认值，应显式操作重置按钮
     */
    public static function createValidator(array $fields, array $current = [])
    {
        $schema = [];
        foreach ($fields as $field) {
            if (empty($field['id'])) {
                continue;
            }
            $id = $field['id'];
            $type = self::createFieldSchema($field, $current);
            if (!$type) {
                continue;
            }
            $type->label($field['label'] ?? '');
            $hasDefault = array_key_exists('default', $field);
            if ($current && !array_key_exists($id, $current) && $hasDefault) {
                $type = $type->default($field['default'])->optional();
            }
            if ($hasDefault || ($field['optional'] ?? false)) {
                $type = $type->optional();
            }
            if ($field['required'] ?? false) {
                $type = $type->required();
            }
            foreach (['min', 'max', 'refine', 'transform', 'format', 'code'] as $key) {
                if (isset($field[$key]) && method_exists($type, $key)) {
                    $type = $type->{$key}($field[$key]);
                }
            }
            if (!empty($field['between']) && method_exists($type, 'between')) {
                $type = $type->between($field['between'][0], $field['between'][1]);
            }
            if (!isset($field['sanitize'])) {
                if ($type instanceof TypesStr && 'code' !== $field['type']) {
                    $type->sanitize('text');
                }
            } elseif ($field['sanitize']) {
                $params = is_array($field['sanitize']) ? $field['sanitize'] : [$field['sanitize']];
                $type = $type->sanitize(...$params);
            }
            if (!empty($field['rule'])) {
                $type->useStrRule($field['rule']);
            }
            $schema[$id] = $type;
        }
        return v::array($schema);
    }

    /**
     * @param Field $field
     */
    public static function createFieldSchema(array $field, array $current = [])
    {
        switch ($field['type']) {
            case 'text':
            case 'textarea':
                return v::string();
            case 'number':
                return v::number()->coerce();
            case 'checkbox':
                $type = v::string()->coerce();
                return self::isSingleCheck($field) ? $type : v::array($type);
            case 'radio':
                return v::string();
            case 'toggle':
                return v::boolean()->coerce();
            case 'range':
                return v::number()->coerce();
            case 'select':
                $type = v::string()->coerce();
                return self::isMultiple($field) ? v::array($type) : $type;
            case 'tree-select':
                return v::string();
            case 'date-picker':
                return v::date();
            case 'color-palette':
                return v::string();
            case 'media':
                $type = v::integer()->coerce();
                return self::isMultiple($field) ? v::array($type) : $type;
            case 'upload':
                return v::string();
            case 'custom':
                return null;
            case 'lazy':
                return v::any();
            case 'fieldset':
                return self::createValidator($field['fields'], $current[$field['id']] ?? []);
            case 'group':
                $item = self::createValidator($field['fields']);
                return v::array($item);
            case 'code':
                return v::string()->code($field['lang'] ?? ($field['language'] ?? 'html'));
            default:
                return v::string();
        }
    }

    /**
     * @param Field[] $fields
     */
    public static function extractDefaultValue(array $fields)
    {
        $data = [];
        foreach ($fields as $field) {
            if (!isset($field['id'])) {
                continue;
            }
            if (isset($field['fields'])) {
                if ('group' === $field['type']) {
                    $item = self::extractDefaultValue($field['fields']);
                    if (isset($field['default'])) {
                        $value = [];
                        foreach ($field['default'] as $_item) {
                            $value[] = Arr::mergeRecursive($item, $_item);
                        }
                        $data[$field['id']] = $value;
                    } else {
                        $data[$field['id']] = [$item];
                    }
                } else {
                    $data[$field['id']] = self::extractDefaultValue($field['fields']);
                }
            } elseif (isset($field['default'])) {
                $data[$field['id']] = $field['default'];
            }
        }
        return $data;
    }

    /**
     * @param Field[] $fields
     */
    public static function toClientFields(array $fields, array $data = [], $parentName = '')
    {
        $omitKeys = ['sanitize', 'rule', 'refine', 'transform', 'optional', 'default'];
        $newFields = [];
        if ($parentName) {
            // dump($parentName, $data);
        }

        foreach ($fields as $field) {
            $newField = [];
            $initValue = null;

            foreach ($field as $key => $value) {
                if (in_array($key, $omitKeys)) {
                    continue;
                }
                $key = false !== strpos($key, '_') ? Str::camel($key, false) : $key;
                $newField[$key] = $value;
            }

            $type = $newField['type'] ??= 'text';

            if ('lazy' === $type) {
                $newField = $field['callback']($newField, empty($field['id']) ? null : ($data[$field['id']] ?? null));
                unset($newField['callback']);
                $field = $newField;
            }

            if (!isset($field['default']) && !($field['optional'] ?? false)) {
                $newField['required'] = true;
            }

            if (!empty($field['id'])) {
                $newField['name'] ??= self::generateFieldName($field, $parentName);
                if (isset($data[$field['id']])) {
                    $initValue = $data[$field['id']];
                }
            }

            if (!isset($initValue) && isset($field['default'])) {
                $initValue = $field['default'];
            }

            if (isset($initValue)) {
                if (self::isSingleCheck($field)) {
                    $newField['checked'] = isset($field['value']) ? $initValue == $field['value'] : boolval($initValue);
                } else {
                    $newField['value'] = $initValue;
                }
            }

            if (!empty($field['fields'])) {
                $newField['fields'] = self::toClientFields(
                    $field['fields'],
                    // 组由前端解析，因此不需再传入
                    'group' != $type ? (array) ($newField['value'] ?? []) : [],
                    $newField['name'] ?? $parentName
                );
            }

            if ('code' == $newField['type']) {
                $newField['lang'] ??= 'html';
            }
            $newFields[] = $newField;
        }
        return $newFields;
    }

    /**
     * @param Field $field
     */
    public static function generateFieldName(array $field, $parentName = '')
    {
        $name = $parentName ? $parentName.'['.$field['id'].']' : $field['id'];
        switch ($field['type']) {
            case 'media':
            case 'select':
                return $field['multiple'] ?? false ? $name.'[]' : $name;
            case 'checkbox':
                return isset($field['options']) && is_array($field['options']) ? $name.'[]' : $name;
            case 'group':
                return $name.'[{{idx}}]';
            default:
                return $name;
        }
    }

    /**
     * @param  Field $field
     * @return bool
     */
    public static function isSingleCheck(array $field)
    {
        if ('checkbox' != $field['type']) {
            return false;
        }
        if (isset($field['options']) && is_array($field['options'])) {
            return false;
        }
        return true;
    }

    public static function isMultiple(array $field)
    {
        return $field['multiple'] ?? false;
    }
}
