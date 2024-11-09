<?php

namespace Suovawp\Utils;

class File
{
    public static function toKilobytes($size)
    {
        if (is_numeric($size)) {
            return floatval($size);
        }
        $suffix = strtolower(substr($size, -2));
        $value = floatval($size);
        switch ($suffix) {
            case 'kb':
                $value *= 1024;
                break;
            case 'mb':
                $value *= 1024 * 1024;
                break;
            case 'gb':
                $value *= 1024 * 1024 * 1024;
                break;
            case 'tb':
                $value *= 1024 * 1024 * 1024 * 1024;
                break;
            default: throw new \InvalidArgumentException('Invalid file size suffix.');
                break;
        }
        return round($value);
    }

    public static function resolveMimeType($file)
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file);
        finfo_close($finfo);
        return $mime;
    }

    public static function isImageOrSvg($file, $filename, $mimes = null)
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return 'svg' === $extension ? static::isSvg($file, $filename) : static::isImage($file, $filename, $mimes);
    }

    public static function isSvg($file, $filename, $mime = null)
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ('svg' !== $extension) {
            return false;
        }
        if ($mime && $mime !== mime_content_type($file)) {
            return false;
        }
        $fileContent = file_get_contents($file);
        if (false === $fileContent) {
            return false;
        }
        if (false === strpos($fileContent, '<svg')) {
            return false;
        }
        libxml_use_internal_errors(true);
        $svg = simplexml_load_string($fileContent);
        if (false === $svg || 'svg' !== $svg->getName()) {
            return false;
        }
        return true;
    }

    public static function isImage($file, $filename, $mimes = null)
    {
        $info = static::getRealFileInfo($file, $filename, $mimes);
        return !empty($info['real_mime']) && Str::startsWith($info['real_mime'], 'image/');
    }

    public static function isVideo($file, $filename, $mimes = null)
    {
        $info = static::getRealFileInfo($file, $filename, $mimes);
        return !empty($info['real_mime']) && Str::startsWith($info['real_mime'], 'video/');
    }

    public static function isAudio($file, $filename, $mimes = null)
    {
        $info = static::getRealFileInfo($file, $filename, $mimes);
        return !empty($info['real_mime']) && Str::startsWith($info['real_mime'], 'audio/');
    }

    /**
     * 尝试解析真实的文件MIME类型.
     *
     * @param string     $file
     * @param string     $filename
     * @param array|null $mimes    若提供内置类型之外的类型，可能导致解析失败
     */
    public static function getRealFileInfo($file, $filename, $mimes = null)
    {
        if (!function_exists('wp_check_filetype_and_ext')) {
            require_once ABSPATH.'wp-admin/includes/file.php';
        }
        $callback = function ($valid, $file, $filename, $mimes, $realMime) use ($callback) {
            if (false === $valid['proper_filename']) {
                $ext = pathinfo($filename, PATHINFO_EXTENSION);
                if ($ext == $valid['ext'] && $realMime == $valid['type']) {
                    $valid['proper_filename'] = $filename;
                }
            }
            remove_action('wp_check_filetype_and_ext', $callback, 10);
            $valid['real_mime'] = $realMime;
            return $valid;
        };
        add_filter('wp_check_filetype_and_ext', $callback, 10, 5);
        $validate = wp_check_filetype_and_ext($file, $filename, $mimes);
        return $validate;
    }

    /**
     * 尝试确定文件是否是真正有效的mime类型.
     *
     * @param string                             $file
     * @param string                             $filename
     * @param array<string,string>|string[]|null $mimes    默认是内置的类型限制，参数只能缩小此范围。mime[]|[ext=>mime]|null
     */
    public static function checkFileMime($file, $filename, $mimes = null)
    {
        $validate = static::getRealFileInfo($file, $filename);
        ['ext' => $ext, 'type' => $type] = $validate;
        if (false === $validate['proper_filename'] || !$ext || !$type) {
            return false;
        }
        foreach (static::resolveAllowedExtMimes($mimes) as $extRegex => $mime) {
            if (($extRegex == $ext || preg_match('!^('.$extRegex.')$!i', $ext)) && $type === $mime) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<string,string>|string[]|null $mimes 默认是内置的类型限制，参数只能缩小此范围。mime[]|[ext=>mime]|null
     */
    public static function resolveAllowedExtMimes(?array $mimes = null)
    {
        if (!$mimes) {
            return get_allowed_mime_types();
        }
        if (Arr::isAssoc($mimes)) {
            return $mimes;
        }
        $allMimes = array_flip(get_allowed_mime_types());
        $newMimes = [];
        foreach ($mimes as $mime) {
            if (isset($allMimes[$mime])) {
                $newMimes[$allMimes[$mime]] = $mime;
            }
        }
        return $newMimes;
    }

    /**
     * @param string   $filename
     * @param string[] $mimes    允许的mime类型数组，进一步限制查询范围
     */
    public static function isWPAllowedMimeFilename($filename, $mimes = null)
    {
        $info = wp_check_filetype($filename, null === $mimes ? null : static::resolveAllowedExtMimes($mimes));
        return $info['type'] && $info['ext'];
    }
}
