<?php

namespace Suovawp\Validation;

use Suovawp\Utils\Arr;
use Suovawp\Utils\File;
use Suovawp\Utils\Str;

class Sanitize
{
    /**
     * @template T
     *
     * @param T      $value
     * @param string $type   过滤类型：
     *                       - 首先根据类型查找类似 `sanitize_{$type}_field` | `sanitize_{$type}` 的函数
     *                       - 未找到对应函数时，若`$type`是可调用，则调用该函数并返回函数结果
     *                       - 均未匹配则原值返回
     * @param mixed  $params 依序传给过滤器的参数
     *
     * @return T|mixed
     */
    public static function sanitize($value, $type = 'text', ...$params)
    {
        if (function_exists($func = 'sanitize_'.$type.'_field')) {
            return $func($value, ...$params);
        } elseif (function_exists('sanitize_'.$type)) {
            return $func($value, ...$params);
        }
        return $value;
    }

    public static function stripAllTags(string $value)
    {
        return wp_strip_all_tags($value);
    }

    /**
     * 保留基本格式.
     */
    public static function pre(string $value)
    {
        return wp_kses($value, [
            'br'     => [],
            'em'     => [],
            'strong' => [],
            'b'      => [],
            'i'      => [],
            's'      => [],
        ]);
    }

    /**
     * 清理过滤内容.
     *
     * @param string       $content
     * @param string       $subtype        清理类型
     *                                     - post 和 `wp_kses_post` 一致
     *                                     - tinymce 适用于tinymce编辑器
     *                                     - md|markdown 适用于markdown编辑器
     *                                     - 其他 不允许任何HTML标签
     * @param bool|array   $allowedDomains 参考 `static::isSafeURL`
     * @param string|false $urlType        参考 `static::isSafeURL`
     */
    public static function ksesUserContent($content, $subtype = 'tinymce', $allowedDomains = true, $urlType = false)
    {
        $allowedProtocols = ['http', 'https', 'mailto', 'data'];
        $allowedHtml = [
            'a' => [
                'href'   => [],
                'title'  => [],
                'target' => [],
                'rel'    => [],
            ],
            'img' => [
                'class'    => true,
                'src'      => true,
                'alt'      => true,
                'width'    => true,
                'height'   => true,
                'data-src' => true,
                'srcset'   => true,
                'sizes'    => true,
            ],
            'p'          => [],
            'strong'     => [],
            'em'         => [],
            's'          => [],
            'br'         => [],
            'ul'         => [],
            'ol'         => [],
            'li'         => [],
            'blockquote' => [],
            'pre'        => [],
            'summary'    => [],
            'code'       => [],
            'table'      => [],
            'tbody'      => [],
            'thead'      => [],
            'tr'         => [],
            'td'         => [],
            'th'         => [],
            'div'        => [],
            'span'       => [],
        ];
        foreach (['video', 'audio', 'source'] as $tag) {
            $allowedHtml[$tag] = [
                'id'       => true,
                'class'    => true,
                'src'      => true,
                'width'    => true,
                'height'   => true,
                'controls' => true,
                'autoplay' => true,
                'loop'     => true,
                'muted'    => true,
                'poster'   => true,
                'preload'  => true,
            ];
            if ('source' === $tag) {
                $allowedHtml[$tag] += [
                    'srcset' => true,
                    'type'   => true,
                    'sizes'  => true,
                    'media'  => true,
                ];
            }
        }
        foreach (['h1', 'h2', 'h3', 'h4', 'h5', 'h6'] as $tag) {
            $allowedHtml[$tag] = ['id' => true];
        }
        switch ($subtype) {
            case 'post':
                return wp_kses_post($content);
                break;
            case 'tinymce':
                unset($allowedHtml['code']);
                break;
            case 'markdown':
            case 'md':
                foreach (['div', 'span', 'pre', 'code'] as $tag) {
                    $allowedHtml[$tag] = ['id' => true, 'class' => true, 'style' => true];
                }
                $allowedHtml['span'] += ['data-link-label' => true];
                $allowedHtml['pre'] += ['data-render' => true];
                $allowedHtml['code'] += ['data-type' => true];
                $allowedHtml['img'] += ['decoding' => true, 'data-was-processed' => true];
                $allowedHtml['p'] = ['data-block' => true];
                $allowedHtml['ul'] = ['data-tight' => true, 'data-marker' => true, 'data-block' => true];
                $allowedHtml['li'] = ['data-marker' => true, 'class' => true, 'data-block' => true];
                $allowedHtml['input'] = ['checked' => ['', 'checked'], 'type' => ['checkbox', 'radio']];
                $allowedHtml['canvas'] = ['data-zr-dom-id' => true, 'width' => true, 'height' => true, 'style' => true, 'class' => true];
                break;
            default:
                $allowedHtml = [];
                break;
        }
        $content = wp_kses($content, $allowedHtml, $allowedProtocols);
        $content = static::sanitizeContentURL($content, $allowedDomains, $urlType);
        return $content;
    }

    /**
     * 清理内容中的标签URL. 参考 `static::isSafeURL`.
     */
    public static function sanitizeContentURL($content, $allowedDomains = false, $urlType = false)
    {
        return preg_replace_callback(
            '/<(a|img|video|iframe|audio)[^>]+(href|src)=["\']([^"\']+)["\'][^>]*>/i',
            fn ($matches) => static::isSafeURL($matches[3], $allowedDomains, $urlType) ? $matches[0] : '<'.$matches[1].'>',
            $content
        );
    }

    /**
     * 检查是否是安全链接，站内域名(端口不限)直接安全，站外可进行额外检查.
     *
     * @param string       $url
     * @param bool|array   $allowedDomains false表示只允许站内，true表示不限，数组表示仅允许站内和数组指定域名
     * @param string|false $urlType        是否检查URL具体内容。false不检查，'image'需是图片，'page'需是HTML页面
     *
     * @return bool
     */
    public static function isSafeURL($url, $allowedDomains = false, $urlType = false)
    {
        $urlHost = parse_url($url, PHP_URL_HOST);
        if (!$urlHost) {
            return false;
        }
        $isSelfHostURL = static::isSelfHostURL($url);
        if ($isSelfHostURL) {
            return true;
        }
        if (true !== $allowedDomains) {
            if (is_array($allowedDomains)) {
                if (!Arr::some($allowedDomains, fn ($d) => $d === $urlHost)) {
                    return false;
                }
            } elseif (!$isSelfHostURL) {
                return false;
            }
        }
        if ('image' === $urlType) {
            return static::isRemoteImage($url);
        } elseif ('page' === $urlType) {
            return static::isValidHtmlPage($url);
        }
        return true;
    }

    public static function isSelfHostURL($url)
    {
        $urlHost = parse_url($url, PHP_URL_HOST);
        $selfHost = parse_url(home_url(), PHP_URL_HOST);
        return $urlHost === $selfHost || $urlHost === 'www.'.$selfHost;
    }

    public static function isValidHtmlPage($url)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_NOBODY, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        if (200 !== $httpCode) {
            return false;
        }
        // 提取响应头和响应体
        $header = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);
        if (false === strpos(strtolower($header), 'content-type: text/html')) {
            return false;
        }
        if (preg_match('/<html.*?>/i', $body)) {
            return true;
        }
        return false;
    }

    /**
     * @param string[] $types
     */
    public static function isRemoteImage(string $url, $maxSize = false, $types = [])
    {
        $imageInfo = static::getRemoteImageInfo($url);
        if (!$imageInfo) {
            return false;
        }
        if ($maxSize && $imageInfo['size'] > File::toKilobytes($maxSize)) {
            return false;
        }
        if ($types && !Arr::some($types, fn ($t) => strtolower($imageInfo['mime']) === 'image/'.strtolower($t))) {
            return false;
        }
        return true;
    }

    public static function getRemoteImageInfo($url)
    {
        if (!function_exists('download_url')) {
            include \ABSPATH.'wp-admin/includes/image.php';
            require_once \ABSPATH.'wp-admin/includes/file.php';
        }
        $tempFile = download_url(esc_url($url));
        if (is_wp_error($tempFile)) {
            return false;
        }
        $imageInfo = @getimagesize($tempFile);
        if ($imageInfo && is_array($imageInfo)) {
            $imageInfo['mime'] ??= '';
            $imageInfo['size'] = @filesize($tempFile);
        } else {
            $imageInfo = false;
        }
        @unlink($tempFile);
        return $imageInfo;
    }

    public static function escRemoteIcoURL($url)
    {
        if (Str::startsWith($url, '://')) {
            $url = 'http'.$url;
        } elseif (Str::startsWith($url, '//')) {
            $url = 'http:'.$url;
        }
        return $url;
    }
}
