<?php

namespace Suovawp;

/**
 * @phpstan-type ComponentOptions array{
 *  id:string,
 *  title:string,
 *  type?:'string'|'number'|'integer'|'boolean'|'null'|'array'|'object',
 *  attributes:array<string,array>,
 *  supports:array<string>,
 * }
 */
class Component
{
    /**
     * @param ComponentOptions $options 除了基本属性类型，也支持OptionField的配置
     */
    public static function createSettingsField(array $options)
    {
        $args = $options['field_args'] ?? [];
        $field = [
            'id'        => $options['id'],
            'label'     => $options['title'],
            'type'      => 'fieldset',
            'init_open' => $args['init_open'] ?? false,
        ];
        $fields = [
            [
                'id'      => 'enabled',
                'label'   => __('启用', 'suovawp'),
                'type'    => 'toggle',
                'default' => $args['enabled'] ?? true,
            ],
        ];
        foreach ($options['attributes'] as $key => $info) {
            $fields[] = self::createAttributeField($key, $info);
        }
        $supports = $options['supports'] ?? [];
        $supports['responsive'] ??= true;
        foreach ($supports as $type => $info) {
            if (false === $info) {
                continue;
            }
            if (is_string($info)) {
                $type = $info;
                $info = [];
            } elseif (true === $info) {
                $info = [];
            }
            $fields[] = self::createSupportsField($type, $info);
        }
        $field['fields'] = $fields;
        return $field;
    }

    public static function createAttributeField(string $id, $args = [])
    {
        $field = $args;
        $field['id'] = $id;
        switch ($args['type'] ?? 'string') {
            case 'string':
                $field['type'] = 'text';
                return $field;
            case 'boolean':
                $field['type'] = 'toggle';
                return $field;
            case 'number':
            case 'integer':
                $field['type'] = 'number';
                return $field;
            case 'object':
                $field['type'] = 'fieldset';
                $fields = [];
                foreach ($args['properties'] as $key => $info) {
                    $fields[] = self::createAttributeField($key, $info);
                }
                $field['fields'] = $fields;
                return $field;
            case 'array':
                if (isset($args['properties'])) {
                    $field['type'] = 'group';
                    $fields = [];
                    foreach ($args['properties'] as $key => $info) {
                        $fields[] = self::createAttributeField($key, $info);
                    }
                    $field['fields'] = $fields;
                } else {
                    $field['type'] = 'checkbox';
                }
                return $field;
            default:
                return $field;
        }
    }

    public static function createSupportsField(string $type, array $args = [])
    {
        switch ($type) {
            case 'responsive':
                return [
                    'id'      => 'display_mode',
                    'label'   => __('响应式可见性', 'suovawp'),
                    'type'    => 'radio',
                    'options' => [
                        'all'     => __('默认', 'suovawp'),
                        'mobile'  => __('移动端', 'suovawp'),
                        'desktop' => __('桌面端', 'suovawp'),
                    ],
                    'default' => $args['default'] ?? 'all',
                ];
            case 'class':
                return [
                    'id'      => 'class',
                    'label'   => __('CSS类名', 'suovawp'),
                    'type'    => 'text',
                    'default' => $args['default'] ?? '',
                ];
            case 'style':
                return [
                    'id'      => 'style',
                    'label'   => __('CSS样式', 'suovawp'),
                    'type'    => 'text',
                    'default' => $args['default'] ?? '',
                ];
            case 'background':
                $default = $args['default'] ?? [];
                return [
                    'id'        => 'background',
                    'label'     => __('背景', 'suovawp'),
                    'type'      => 'fieldset',
                    'init_open' => $args['init_open'] ?? false,
                    'fields'    => [
                        [
                            'id'      => 'color',
                            'label'   => __('背景颜色', 'suovawp'),
                            'type'    => 'text',
                            'default' => $default['color'] ?? '',
                        ],
                        [
                            'id'      => 'image',
                            'label'   => __('背景图片', 'suovawp'),
                            'type'    => 'media',
                            'accept'  => 'image',
                            'default' => $default['image'] ?? '',
                        ],
                        [
                            'id'      => 'position',
                            'label'   => __('位置', 'suovawp'),
                            'type'    => 'select',
                            'options' => [
                                'left top'      => __('左上', 'suovawp'),
                                'left center'   => __('左中', 'suovawp'),
                                'left bottom'   => __('左下', 'suovawp'),
                                'center top'    => __('中上', 'suovawp'),
                                'center center' => __('中中', 'suovawp'),
                                'center bottom' => __('中下', 'suovawp'),
                                'right top'     => __('右上', 'suovawp'),
                                'right center'  => __('右中', 'suovawp'),
                                'right bottom'  => __('右下', 'suovawp'),
                            ],
                            'default' => $default['position'] ?? 'center center',
                        ],
                        [
                            'id'      => 'repeat',
                            'label'   => __('重复', 'suovawp'),
                            'type'    => 'select',
                            'options' => [
                                'no-repeat' => __('不重复', 'suovawp'),
                                'repeat'    => __('重复', 'suovawp'),
                                'repeat-x'  => __('水平重复', 'suovawp'),
                                'repeat-y'  => __('垂直重复', 'suovawp'),
                            ],
                            'default' => $default['repeat'] ?? 'no-repeat',
                        ],
                        [
                            'id'      => 'size',
                            'label'   => __('尺寸', 'suovawp'),
                            'type'    => 'select',
                            'options' => [
                                'auto'    => __('自动', 'suovawp'),
                                'cover'   => __('覆盖', 'suovawp'),
                                'contain' => __('包含', 'suovawp'),
                            ],
                            'default' => $default['size'] ?? 'auto',
                        ],
                    ],
                ];
            case 'background-color':
            case 'bg-color':
                return [
                    'id'      => 'bg_color',
                    'label'   => __('背景颜色', 'suovawp'),
                    'type'    => 'color',
                    'default' => $args['default'] ?? '',
                ];
            case 'background-image':
            case 'bg-image':
                return [
                    'id'      => 'bg_image',
                    'label'   => __('背景图片', 'suovawp'),
                    'type'    => 'media',
                    'accept'  => 'image',
                    'default' => $args['default'] ?? '',
                ];
            default:
                return [
                    'id'      => $type,
                    'label'   => __('Custom', 'suovawp').' '.$type,
                    'type'    => 'text',
                    'default' => $args['default'] ?? '',
                ];
        }
    }
}
