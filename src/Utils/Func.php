<?php

namespace Suovawp\Utils;

class Func
{
    /**
     * 包装需要重试的函数，若函数抛出错误则尝试重新执行，反之返回执行结果.
     *
     * @template R
     * @template FR
     * @param callable():R $fn
     * @param array{
     *  retries?:int,
     *  delay?:int,
     *  retryIf?:callable(\Throwable,int):bool
     *  onRetry?:callable(\Throwable,int),
     *  onFinalError?:callable(\Throwable):FR,
     *  backoff?:int,
     * } $options 选项数组
     * - delay 单位毫秒，默认300
     * - onFinalError 当重试次数耗尽后执行，若提供则返回其执行结果
     * - backoff 指数退避，每次重试的延迟时间逐渐变长，设为0不变
     *
     * @throws \Throwable 当重试次数耗尽且未提供`onFinalError`时抛出
     */
    public static function retry($fn, array $options = [])
    {
        $attempt = 0;
        $retries = $options['retries'] ?? 3;
        $baseDelay = $options['delay'] ?? 300;
        $backoff = $options['backoff '] ?? 1.0;
        $lastError = null;
        while ($attempt < $retries) {
            try {
                return $fn();
            } catch (\Throwable $th) {
                ++$attempt;
                $lastError = $th;
                if (isset($options['retryIf']) && !($options['retryIf'])($th, $attempt)) {
                    break;
                }
                if (!empty($options['onRetry'])) {
                    ($options['onRetry'])($th, $attempt);
                }
                if ($baseDelay > 0) {
                    $delay = $backoff > 0 ? intval($baseDelay * ($backoff ** ($attempt - 1))) : $baseDelay;
                    if ($delay > 0) {
                        usleep($delay * 1000);
                    }
                }
            }
        }
        if (!empty($options['onFinalError'])) {
            return ($options['onFinalError'])($lastError);
        }
        throw $lastError;
    }

    /**
     * 包装需要简单重试的函数.
     *
     * @template R
     * @param  callable():R        $fn
     * @param  callable(R):bool    $retryIf
     * @param  (callable():R)|null $onFinal 应返回类型一致的数据或抛出异常
     * @return R
     */
    public static function retryWithCondition($fn, callable $retryIf, ?callable $onFinal = null, int $retries = 3)
    {
        $res = $fn();
        if (!$retryIf($res)) {
            return $res;
        }
        if ($retries <= 0) {
            return $onFinal ? $onFinal($res) : $res;
        }
        return static::retryWithCondition($fn, $retryIf, $onFinal, --$retries);
    }
}
