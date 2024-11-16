<?php

namespace Suovawp\Utils;

/**
 * @property string          $origin       包含协议名、域名和端口号
 * @property URLSearchParams $searchParams 查询参数实例
 */
class URL
{
    /** 完整URL */
    public string $href;
    private string $origin;
    /** 协议名带后缀':' */
    public string $protocol;
    /** 域名部分，若有端口还包含端口 */
    public string $host;
    /** 仅域名部分 */
    public string $hostname;
    /** 端口号 */
    public string $port;
    /** URL路径部分 */
    public string $pathname;
    /** 以'?'开头的查询参数字符串 */
    public string $search;
    private URLSearchParams $searchParams;
    /** 以'#'开头的锚点 */
    public string $hash;
    /** 域名前面指定的用户名 */
    public string $username;
    /** 域名前面指定的密码 */
    public string $password;

    private static array $blobUrls = [];
    public static string $tempDir = '/tmp/suovawp-blob-urls';

    public function __construct(string $url, ?string $base = null)
    {
        if ($base) {
            $url = $this->resolveUrl($url, $base);
        }

        $components = parse_url($url);

        if (!$components) {
            throw new \InvalidArgumentException('Invalid URL');
        }

        $this->href = $url;
        $this->protocol = ($components['scheme'] ?? '').':';
        $this->username = $components['user'] ?? '';
        $this->password = $components['pass'] ?? '';
        $this->hostname = $components['host'] ?? '';
        $this->port = $components['port'] ?? '';
        $this->pathname = $components['path'] ?? '';
        $this->search = isset($components['query']) ? '?'.$components['query'] : '';
        $this->hash = isset($components['fragment']) ? '#'.$components['fragment'] : '';
        $this->host = $this->hostname.($this->port ? ':'.$this->port : '');
        $this->origin = "{$this->protocol}//{$this->host}";
    }

    public function __get($name)
    {
        if ('origin' == $name) {
            return $this->origin;
        }
        if ('searchParams' == $name) {
            return $this->searchParams ??= new URLSearchParams($this->search);
        }
        return null;
    }

    private function resolveUrl(string $url, string $base): string
    {
        if (null !== parse_url($url, PHP_URL_SCHEME)) {
            return $url;
        }
        $baseUrl = parse_url($base);
        if (!$baseUrl) {
            throw new \InvalidArgumentException('base is not a valid URL');
        }
        if ('/' === $url[0]) {
            return '/' === $url[1] ? "{$baseUrl['scheme']}:{$url}" : "{$baseUrl['scheme']}://{$baseUrl['host']}{$url}";
        }
        $basePath = isset($baseUrl['path']) ? dirname($baseUrl['path']) : '/';
        return "{$baseUrl['scheme']}://{$baseUrl['host']}".rtrim($basePath, '/').'/'.ltrim($url, '/');
    }

    public static function createObjectURL($data): string
    {
        if (!is_dir(self::$tempDir)) {
            mkdir(self::$tempDir, 0777, true);
        }
        $blobId = uniqid('blob-', true);
        $filePath = self::$tempDir.DIRECTORY_SEPARATOR.$blobId;
        if (is_resource($data)) {
            stream_copy_to_stream($data, fopen($filePath, 'wb'));
        } else {
            file_put_contents($filePath, $data);
        }
        $blobUrl = 'blob:'.$blobId;
        self::$blobUrls[$blobUrl] = $filePath;
        return $blobUrl;
    }

    public static function revokeObjectURL(string $url): void
    {
        if (isset(self::$blobUrls[$url])) {
            $filePath = self::$blobUrls[$url];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            unset(self::$blobUrls[$url]);
        }
    }

    public static function getBlobData(string $url)
    {
        if (isset(self::$blobUrls[$url]) && file_exists(self::$blobUrls[$url])) {
            return file_get_contents(self::$blobUrls[$url]) ?: '';
        }
        return null;
    }

    public static function canParse(string $url, ?string $base = null): bool
    {
        try {
            if ($base && !parse_url($base)) {
                return false;
            }
            return !parse_url($url);
        } catch (\Exception $e) {
            return false;
        }
    }

    /** 安全解析方式，无法解析时返回null而不抛异常 */
    public static function parse(string $url, ?string $base = null)
    {
        try {
            return new URL($url, $base);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function toString()
    {
        return $this->__toString();
    }

    public function __toString()
    {
        return $this->href;
    }

    public function toJSON()
    {
        return $this->__toString();
    }
}
