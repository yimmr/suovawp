<?php

namespace Suovawp\Validation;

/**
 * @template Type of Types\Any
 *
 * @phpstan-type ValidatorIssue array{code:string,message:string,params:array,...<string|int,mixed>}
 */
class ValidatorException extends \Exception
{
    /** @var ValidatorIssue[] */
    protected $issues = [];

    /** @var Type */
    protected $type;

    protected static $messages = [
        'INVALID_TYPE'      => '%s的类型不正确',
        'REQUIRED'          => '请提供%s',
        'UNKNOWN_CONDITION' => '未知条件[%s]',
        'CUSTOM'            => '%s未通过自定义验证',
    ];

    /**
     * @param ValidatorIssue[] $issues
     * @param Type             $type
     */
    public function __construct($issues = [], $type = null, ?\Throwable $previous = null)
    {
        $this->issues = $issues ? array_map([$this, 'resolveValidatorIssue'], $issues) : [];
        $this->type = $type;
        parent::__construct('', 400, $previous);
    }

    /**
     * @param ValidatorIssue[] $issues
     */
    public function addIssues($issues)
    {
        foreach ($issues as $issue) {
            $this->addIssue($issue);
        }
    }

    /** @param ValidatorIssue $issue */
    public function addIssue($issue)
    {
        $this->issues[] = $this->resolveValidatorIssue($issue);
    }

    /** @param ValidatorIssue $issue */
    protected function resolveValidatorIssue($issue)
    {
        $params = [];
        if (isset($issue['label'])) {
            $params[] = $issue['label'];
        }
        if (!empty($issue['params'])) {
            $params = array_merge($params, array_map(fn ($p) => is_array($p) ? implode(',', $p) : $p, $issue['params']));
        }
        $message = $issue['message'] ?: (static::$messages[$issue['code']] ?? static::$messages['CUSTOM']);
        $issue['message'] = $this->parseMessage($message, $params);
        if (isset($issue['union_errors'])) {
            $issue['union_errors'] = array_map([$this, 'resolveValidatorIssue'], $issue['union_errors']);
        }
        return $issue;
    }

    /**
     * 解析消息模板.
     *
     * @param string $message
     * @param array  $params
     */
    protected function parseMessage($message, $params = [])
    {
        if (false === strpos($message, '%')) {
            return $message;
        }
        $count = substr_count($message, '%');
        if (count($params) < $count) {
            $params = array_pad($params, $count, '');
        }
        return vsprintf($message, $params);
    }

    public function format($errorKey = '_errors')
    {
        $result = [];
        foreach ($this->issues as $issue) {
            if ($issue['path']) {
                $keys = explode('.', $issue['path']);
                $field = &$result;
                foreach ($keys as $key) {
                    $field[$key] ??= [];
                    $field = &$field[$key];
                }
                $field[$errorKey][] = $issue['message'];
            } else {
                $result[$errorKey][] = $issue['message'];
            }
        }
        return $result;
    }

    public function flatten()
    {
        $fields = [];
        $form = [];
        foreach ($this->issues as $issue) {
            if ($issue['path']) {
                $key = $issue['path'];
                $dotpos = strpos($key, '.');
                $key = false === $dotpos ? $key : substr($key, 0, $dotpos);
                $fields[$key][] = $issue['message'];
            } else {
                $form[] = $issue['message'];
            }
        }
        return ['form' => $form, 'fields' => $fields];
    }

    /**
     * @return Type
     */
    public function getType()
    {
        return $this->type;
    }

    public function isEmpty()
    {
        return empty($this->issues);
    }

    public function getErrors()
    {
        return $this->issues;
    }

    public function getMsg()
    {
        return $this->__toString();
    }

    public function __toString()
    {
        return json_encode($this->issues);
    }

    public function toArray()
    {
        return $this->getErrors();
    }

    // public function jsonSerialize()
    // {
    //     return $this->toArray();
    // }
}
