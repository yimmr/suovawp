<?php

namespace Suovawp;

class HandleException
{
    protected $themeOnly = true;

    public function __construct()
    {
        error_reporting(-1);
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
        register_shutdown_function([$this, 'handleShutdown']);
    }

    /**
     * 处理程序未捕获的异常.
     */
    public function handleException(\Throwable $e)
    {
        // throw $e;
        dump(['未捕获', $e]);
        // try {
        //     if (!$this->shouldHandleErrorFile($e->getFile())) {
        //         return;
        //     }
        //     $this->getExceptionHandler()->report($e);
        // } catch (\Exception $e) {
        // }

        // $this->getExceptionHandler()->render($e);
    }

    /**
     * 错误转为异常，简单错误不终止运行.
     *
     * @throws \ErrorException
     */
    public function handleError($level, $message, $file = '', $line = 0, $context = [])
    {
        if (!$this->shouldHandleErrorFile($file)) {
            return;
        }
        if (error_reporting() & $level) {
            $e = new \ErrorException($message, 0, $level, $file, $line);

            if ($this->isFatal($level)) {
                throw $e;
            }

            $this->handleException($e);
        }
    }

    /**
     * 处理运行结束后的错误.
     *
     * @throws \ErrorException
     */
    public function handleShutdown()
    {
        $error = error_get_last();

        if (is_null($error)) {
            return;
        }

        if ($this->shouldHandleErrorFile($error['file']) && $this->isFatal($error['type'])) {
            $this->handleException(new \ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']));
        }
    }

    /**
     * 返回处理异常的实例.
     */
    protected function getExceptionHandler()
    {
        // return zmoTheme()->getSingleton(ExceptionHandler::class);
    }

    /**
     * 是否是严重错误.
     *
     * @param int $type
     *
     * @return bool
     */
    protected function isFatal($type)
    {
        return in_array($type, [E_COMPILE_ERROR, E_CORE_ERROR, E_ERROR, E_PARSE]);
    }

    public function shouldHandleErrorFile($file)
    {
        return !$this->themeOnly || $this->isThemeFile($file);
    }

    public function isThemeFile($file)
    {
        return 0 === strpos($file, get_stylesheet_directory());
    }
}
