<?php

namespace Suovawp\Utils;

/**
 * WordPress后台的时区设置不影响服务器时区，PHP函数和WP函数的调用结果可能不一样，该类的方法尽可能保持一致性。
 * - `wp_date`函数根据时区设置进行格式化并国际化翻译
 * - `current_time`函数返回本地时间戳，而不是Unix时间戳，若要格式化应该用`date`函数
 * - 直接用`strtotime`函数无法得到预期结果，应该使用\DateTime并明确指定时区.
 * - now等根据时区转换日期时间，未设置时区时结果可能跟本地时间不一致.
 */
class Date
{
    public const ISO_FORMAT = 'Y-m-d\TH:i:s.u\Z';

    /**
     * 创建DateTime对象，自动设置WP时区.
     *
     * @param string|int         $datetime 支持Unix时间戳或日期时间
     *                                     - 若提供 `current_time` 时间戳，格式化结果可能不一样
     * @param \DateTimeZone|null $timezone 默认用WP时区
     */
    public static function create($datetime = 'now', $timezone = null)
    {
        $timezone ??= wp_timezone();
        if (is_numeric($datetime) && ctype_digit((string) $datetime)) {
            return (new \DateTime('@'.$datetime))->setTimezone($timezone);
        }
        if (isset($datetime[0]) && '@' === $datetime[0] && ctype_digit(substr($datetime, 1))) {
            return (new \DateTime($datetime))->setTimezone($timezone);
        }
        return new \DateTime($datetime, $timezone);
    }

    /**
     * 创建DateTime对象，失败不抛异常.
     *
     * @template T
     * @param  string                        $datetime
     * @param  \DateTimeZone|null            $timezone
     * @param  T|callable(\Throwable $th): T $catchValue 失败时返回值或回调函数的执行结果
     * @return \DateTime|T
     */
    public static function safeCreate($datetime = 'now', $timezone = null, $catchValue = null)
    {
        try {
            return static::create($datetime, $timezone);
        } catch (\Throwable $th) {
            return is_callable($catchValue) ? call_user_func($catchValue, $th) : $catchValue;
        }
    }

    /**
     * 日期时间转为时间戳.
     *
     * @param string             $datetime
     * @param \DateTimeZone|null $timezone 默认用WP时区
     */
    public static function strtotime($datetime = 'now', $timezone = null)
    {
        try {
            return static::create($datetime, $timezone)->getTimestamp();
        } catch (\Throwable $th) {
            return false;
        }
    }

    /**
     * 日期时间或时间戳通用的格式化.
     *
     * @template T
     * @param string             $format
     * @param string|int         $datetime 支持Unix时间戳或日期时间
     *                                     -  应使用`date`函数格式化 `current_time` 的时间戳
     * @param T                  $default  失败时的返回值
     * @param \DateTimeZone|null $timezone 默认用WP时区
     */
    public static function format($format, $datetime = 'now', $default = '', $timezone = null)
    {
        try {
            return static::create($datetime, $timezone)->format($format);
        } catch (\Throwable $th) {
            return $default;
        }
    }

    /**
     * 修改后再格式化，日期时间和时间戳通用.
     *
     * @template T
     * @param string             $format
     * @param string|int         $datetime 支持Unix时间戳或日期时间
     * @param string             $modify
     * @param T                  $default  失败时返回值
     * @param \DateTimeZone|null $timezone 默认用WP时区
     */
    public static function modifyFormat($format, $datetime, $modify, $default = '', $timezone = null)
    {
        try {
            return static::create($datetime, $timezone)->modify($modify)->format($format);
        } catch (\Throwable $th) {
            return $default;
        }
    }

    /**
     * 使用DateTime格式化时间戳.
     *
     * @param string             $format
     * @param int|null           $timestamp 应使用`date`函数格式化 `current_time` 的时间戳
     * @param \DateTimeZone|null $timezone  默认用WP时区
     */
    public static function date($format, $timestamp = null, $timezone = null)
    {
        $dateTime = new \DateTime($timestamp ? '@'.$timestamp : 'now');
        return $dateTime->setTimezone($timezone ?? wp_timezone())->format($format);
    }

    /**
     * 获取两个日期时间的差异，支持Unix时间戳或日期时间.
     *
     * @param string|int        $start
     * @param string|int        $end
     * @param DateTimeZone|null $timezone 默认用WP时区
     */
    public static function diff($start, $end, $timezone = null)
    {
        return static::create($start, $timezone)->diff(static::create($end, $timezone));
    }

    /**
     * 判断时期时间是否是指定格式.
     *
     * @param string $datetime
     */
    public static function isFormat($datetime, $format = 'Y-m-d')
    {
        $dt = $datetime ? \DateTime::createFromFormat($format, $datetime) : false;
        return false !== $dt && $dt->format($format) === $datetime;
    }

    /**
     * 判断时期时间是否像指定格式，不一定完全一样.
     *
     * @param string $datetime
     */
    public static function isFormatLike($datetime, $format = 'Y-m-d')
    {
        // return $datetime && (($time = strtotime($datetime)) !== false) && (strtotime(date($format, $time)) == $time);
        return false !== \DateTime::createFromFormat($format, $datetime);
    }

    /**
     * 判断任意个日期时间是否都是指定格式.
     *
     * @param  string $format
     * @param  mixed  $dates  任意个日期时间字符串
     * @return bool
     */
    public static function isSameFormat($format, ...$dates)
    {
        foreach ($dates as $date) {
            if (!static::isFormat($date, $format)) {
                return false;
            }
        }
        return true;
    }

    /**
     *  判断给定两个时间格式化后是否一样.
     *
     * @param  mixed $dates 支持时间戳或日期时间字符串
     * @return bool
     */
    public static function isDatesFormatedEqual($format = 'Y-m-d', ...$dates)
    {
        try {
            $lastFormated = null;
            foreach ($dates as $date) {
                if (!$date) {
                    return false;
                }
                $formated = static::create($date)->format($format);
                if (null !== $lastFormated && ($lastFormated !== $formated)) {
                    return false;
                }
                $lastFormated = $formated;
            }
            return true;
        } catch (\Throwable $th) {
            return false;
        }
    }

    /**
     * 判断给定值是否是合法时间戳.
     *
     * @param  string $since 起始时间，还可用于限制范围
     * @return bool
     */
    public static function isTimestamp($value, $since = '@0')
    {
        if (!is_numeric($value) || $value <= 0 || $value > PHP_INT_MAX) {
            return false;
        }
        return $value > static::strtotime($since);
    }

    public static function checkMax($date, $maxDate)
    {
        try {
            return static::create($date) <= static::create($maxDate);
        } catch (\Throwable $th) {
            return false;
        }
    }

    public static function checkMin($date, $minDate)
    {
        try {
            return static::create($date) >= static::create($minDate);
        } catch (\Throwable $th) {
            return false;
        }
    }

    public static function isToday($time)
    {
        return static::isDatesFormatedEqual('Y-m-d', $time, 'today');
    }

    public static function isYesterday($time)
    {
        return static::isDatesFormatedEqual('Y-m-d', $time, 'yesterday');
    }

    /**
     * 是否仅包含日期.
     */
    public static function isDateOnly($datetime)
    {
        return static::isFormatLike($datetime, 'Y-m-d');
    }

    /**
     * 补全时间.
     *
     * @param string $separator 日期和时间之间的分隔符
     */
    public static function fillTime(string $date, string $separator = ' ', string $defaultTime = '00:00:00'): string
    {
        if (empty($date)) {
            return '';
        }
        if (false === strpos($date, $separator)) {
            return $date.$separator.$defaultTime;
        }
        [$datePart, $timePart] = explode($separator, $date);
        $timeSegments = explode(':', $timePart);
        $defaultSegments = explode(':', $defaultTime);
        $time = [];
        for ($i = 0; $i < 3; ++$i) {
            $time[] = $timeSegments[$i] ?? $defaultSegments[$i];
        }
        return $datePart.$separator.implode(':', $time);
    }

    /**
     * 补全时间间隔对，天则补一天，小时补一小时....
     */
    public static function fillTimeInterval($date, $separator = ' ')
    {
        return [static::fillTime($date, $separator), static::fillTime($date, $separator, '23:59:59')];
    }

    /**
     * 生成时间数组.
     */
    public static function generateDateTimeArray(string $start, string $end, string $intervalTime, string $format = 'Y-m-d H:i:s', $withEnd = true)
    {
        if (!$start && !$end) {
            return [];
        }
        $start = new \DateTime($start, $timezone = wp_timezone());
        $end = new \DateTime($end, $timezone);
        $interval = \DateInterval::createFromDateString($intervalTime);
        $period = new \DatePeriod($start, $interval, $end);
        $result = [];
        foreach ($period as $date) {
            $result[] = $date->format($format);
        }
        if ($withEnd && (false === array_search($lastTime = $end->format($format), $result))) {
            $result[] = $lastTime;
        }
        return $result;
    }

    public static function formatSeconds($seconds)
    {
        if ($seconds <= 60) {
            return $seconds.'秒';
        }
        $minutes = $seconds / 60;
        if ($minutes < 60) {
            return floor($minutes).'分钟';
        }
        $hours = $minutes / 60;
        if ($hours < 60) {
            return floor($hours).'小时';
        }
        return floor($hours / 24).'天';
    }
}
