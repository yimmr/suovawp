<?php

namespace Suovawp\Validation\Types;

use Suovawp\Utils\File as UtilsFile;

/**
 * @template TV of array{tmp_name:string,name:string,size:int,error:int,full_path?:string}
 * @template TD of array{tmp_name:string,name:string,size:int,error:int,full_path?:string}
 *
 * @extends Any<TV,TD>
 */
class File extends Any
{
    public const TYPE = 'file';

    protected $name = '文件';

    public function is($value)
    {
        if (!is_array($value)) {
            return false;
        }
        if (!isset($value['tmp_name'],$value['name'],$value['size'],$value['error'])) {
            return false;
        }
        return is_uploaded_file($value['tmp_name']);
    }

    public function cast($value)
    {
        return;
    }

    public function check(&$value, $method, $params = null)
    {
        switch ($method) {
            case 'ok':
                return UPLOAD_ERR_OK === $value['error'];
            case 'video':
                return UtilsFile::isVideo($value['tmp_name'], $value['name'], $params[0]);
            case 'audio':
                return UtilsFile::isAudio($value['tmp_name'], $value['name'], $params[0]);
            case 'image':
                return UtilsFile::isImage($value['tmp_name'], $value['name'], $params[0]);
            case 'svg':
                return UtilsFile::isSvg($value['tmp_name'], $value['name']);
            case 'imageOrSvg':
                return UtilsFile::isImageOrSvg($value['tmp_name'], $value['name'], $params[0]);
            case 'size':
                return $value['size'] == UtilsFile::toKilobytes($params);
            case 'max':
                return $value['size'] <= UtilsFile::toKilobytes($params);
            case 'min':
                return $value['size'] >= UtilsFile::toKilobytes($params);
            case 'between':
                return $value['size'] >= UtilsFile::toKilobytes($params[0]) && $value['size'] <= UtilsFile::toKilobytes($params[1]);
            case 'ext':
                $ext = pathinfo($value['name'], \PATHINFO_EXTENSION);
                return in_array(strtolower($ext), $params[0]);
            case 'mime':
                return UtilsFile::checkFileMime($value['tmp_name'], $value['name'], $params[0]);
            case 'filenameMime':
                return UtilsFile::isWPAllowedMimeFilename($value['name'], $params[0]);
            default:
                return parent::check($value, $method, $params);
        }
    }

    public function video($mimes = null, $message = '%s不是有效的视频文件')
    {
        return $this->addRule('video', $message, [$mimes]);
    }

    public function image($mimes = null, $message = '%s不是有效的图片')
    {
        return $this->addRule('image', $message, [$mimes]);
    }

    public function svg($mimes = null, $message = '%s不是有效的SVG')
    {
        return $this->addRule('svg', $message, [$mimes]);
    }

    public function imageOrSvg($mimes = null, $message = '%s不是有效的图片或SVG')
    {
        return $this->addRule('imageOrSvg', $message, [$mimes]);
    }

    /** imageOrSvg 的别名 */
    public function imagesvg($mimes = null, $message = '%s不是有效的图片或SVG')
    {
        return $this->addRule('imageOrSvg', $message, [$mimes]);
    }

    public function audio($mimes = null, $message = '%s不是有效的音频文件')
    {
        return $this->addRule('audio', $message, [$mimes]);
    }

    public function size($size, $message = '%1$s文件大小必须是%2$s')
    {
        return $this->addRule('size', $message, $size);
    }

    public function max($size, $message = '%1$s文件大小不能超过%2$s')
    {
        return $this->addRule('max', $message, $size);
    }

    public function min($size, $message = '%1$s文件大小不能小于%2$s')
    {
        return $this->addRule('min', $message, $size);
    }

    public function between($min, $max, $message = '%1$s文件大小必须在%2$s和%3$s之间')
    {
        return $this->addRule('between', $message, [$min, $max]);
    }

    /**
     * 检查文件是否上传成功，没有错误.
     */
    public function ok($message = '%1$s文件上传失败')
    {
        return $this->addRule('ok', $message);
    }

    /**
     * 尝试检查文件真实的MIME类型.
     *
     * @param array<string,string>|string[]|null $mimes 默认是内置的类型限制，参数只能缩小此范围。mime[]|[ext=>mime]|null
     */
    public function mime($mimes = null, $message = '%1$s文件的MIME类型不正确')
    {
        return $this->addRule('mime', $message, [$mimes]);
    }

    /**
     * 仅通过文件名检查MIME类型.
     *
     * @param string[]|null $mimes 指定MIME数组将预先筛选内置类型再匹配
     */
    public function filenameMime($mimes = null, $message = '未能识别%1$s文件的MIME类型')
    {
        return $this->addRule('filenameMime', $message, [$mimes]);
    }

    /**
     * 验证上传文件的扩展名.
     *
     * @param string[] $exts    仅全小写扩展名有效
     * @param string   $message
     */
    public function ext(array $exts, $message = '%1$s的文件扩展名只能是 %2$s')
    {
        return $this->addRule('ext', $message, [$exts]);
    }
}
