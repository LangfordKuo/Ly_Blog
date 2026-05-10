<?php

namespace LyBlog\Core;

class Validator
{
    private $data    = [];
    private $rules   = [];
    private $errors  = [];
    private $messages = [];
    private $labels  = [];
    private $passed  = false;

    private static $defaultMessages = [
        'required'  => '{field} 不能为空',
        'email'     => '{field} 格式不正确',
        'url'       => '{field} 格式不正确',
        'min'       => '{field} 不能少于 {param} 个字符',
        'max'       => '{field} 不能超过 {param} 个字符',
        'between'   => '{field} 必须在 {param0} 到 {param1} 之间',
        'numeric'   => '{field} 必须是数字',
        'integer'   => '{field} 必须是整数',
        'alpha'     => '{field} 只能包含字母',
        'alphaNum'  => '{field} 只能包含字母和数字',
        'slug'      => '{field} 格式不正确（只允许字母、数字、连字符）',
        'match'     => '{field} 与 {param} 不一致',
        'unique'    => '{field} 已存在',
        'in'        => '{field} 值不在允许范围内',
        'notIn'     => '{field} 包含不允许的值',
        'regex'     => '{field} 格式不正确',
        'date'      => '{field} 日期格式不正确',
        'ip'        => '{field} IP 地址格式不正确',
        'json'      => '{field} JSON 格式不正确',
        'minLength' => '{field} 不能少于 {param} 个字符',
        'maxLength' => '{field} 不能超过 {param} 个字符',
    ];

    public function __construct(array $data = [], array $rules = [])
    {
        $this->data  = $data;
        $this->rules = $rules;
    }

    public function make(array $data, array $rules): self
    {
        $this->data  = $data;
        $this->rules = $rules;
        $this->errors = [];
        $this->passed = false;
        return $this;
    }

    public function setLabels(array $labels): self
    {
        $this->labels = $labels;
        return $this;
    }

    public function setMessages(array $messages): self
    {
        $this->messages = array_merge($this->messages, $messages);
        return $this;
    }

    public function validate(): bool
    {
        $this->errors = [];

        foreach ($this->rules as $field => $ruleSet) {
            $rules = is_string($ruleSet) ? explode('|', $ruleSet) : $ruleSet;
            $value = $this->data[$field] ?? null;

            foreach ($rules as $rule) {
                $params = [];

                if (strpos($rule, ':') !== false) {
                    [$rule, $paramStr] = explode(':', $rule, 2);
                    $params = explode(',', $paramStr);
                }

                $method = 'validate' . ucfirst($rule);

                if (method_exists($this, $method)) {
                    $valid = $this->{$method}($field, $value, $params);

                    if ($valid !== true) {
                        $this->addError($field, $rule, $params, $valid);
                    }
                }
            }
        }

        $this->passed = empty($this->errors);
        return $this->passed;
    }

    public function passes(): bool
    {
        if ($this->passed === null) {
            $this->validate();
        }
        return $this->passed;
    }

    public function fails(): bool
    {
        return !$this->passes();
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function first(string $field = null): ?string
    {
        if ($field) {
            return isset($this->errors[$field]) ? $this->errors[$field][0] : null;
        }
        foreach ($this->errors as $fieldErrors) {
            return $fieldErrors[0] ?? null;
        }
        return null;
    }

    public function hasError(string $field): bool
    {
        return !empty($this->errors[$field]);
    }

    private function addError(string $field, string $rule, array $params, $message = null): void
    {
        if ($message === null || $message === true) {
            $message = $this->getMessage($field, $rule, $params);
        }

        $this->errors[$field][] = $message;
    }

    private function getMessage(string $field, string $rule, array $params): string
    {
        $label = $this->labels[$field] ?? $field;

        $message = $this->messages["{$field}.{$rule}"]
            ?? $this->messages[$rule]
            ?? self::$defaultMessages[$rule]
            ?? '{field} 验证失败';

        $message = str_replace('{field}', $label, $message);

        if (isset($params[0])) {
            $message = str_replace('{param}', $params[0], $message);
            $message = str_replace('{param0}', $params[0], $message);
        }
        if (isset($params[1])) {
            $message = str_replace('{param1}', $params[1], $message);
        }

        return $message;
    }

    private function validateRequired(string $field, $value, array $params): bool
    {
        if (is_null($value)) {
            return false;
        }
        if (is_string($value) && trim($value) === '') {
            return false;
        }
        if (is_array($value) && empty($value)) {
            return false;
        }
        return true;
    }

    private function validateEmail(string $field, $value, array $params): bool
    {
        if (empty($value)) return true;
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function validateUrl(string $field, $value, array $params): bool
    {
        if (empty($value)) return true;
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    private function validateMin(string $field, $value, array $params): bool
    {
        if (empty($value)) return true;
        $min = (int) ($params[0] ?? 0);
        if (is_numeric($value)) return $value >= $min;
        return mb_strlen((string) $value) >= $min;
    }

    private function validateMax(string $field, $value, array $params): bool
    {
        if (empty($value)) return true;
        $max = (int) ($params[0] ?? 0);
        if (is_numeric($value)) return $value <= $max;
        return mb_strlen((string) $value) <= $max;
    }

    private function validateBetween(string $field, $value, array $params): bool
    {
        if (empty($value)) return true;
        $min = (int) ($params[0] ?? 0);
        $max = (int) ($params[1] ?? 0);
        $len = is_numeric($value) ? $value : mb_strlen((string) $value);
        return $len >= $min && $len <= $max;
    }

    private function validateNumeric(string $field, $value, array $params): bool
    {
        if (empty($value) && $value !== '0') return true;
        return is_numeric($value);
    }

    private function validateInteger(string $field, $value, array $params): bool
    {
        if (empty($value) && $value !== '0') return true;
        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    private function validateAlpha(string $field, $value, array $params): bool
    {
        if (empty($value)) return true;
        return preg_match('/^[a-zA-Z]+$/', $value);
    }

    private function validateAlphaNum(string $field, $value, array $params): bool
    {
        if (empty($value)) return true;
        return preg_match('/^[a-zA-Z0-9]+$/', $value);
    }

    private function validateSlug(string $field, $value, array $params): bool
    {
        if (empty($value)) return true;
        return preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value);
    }

    private function validateMatch(string $field, $value, array $params): bool
    {
        $other = $params[0] ?? '';
        return $value === ($this->data[$other] ?? null);
    }

    private function validateIn(string $field, $value, array $params): bool
    {
        if (empty($value) && $value !== '0') return true;
        return in_array($value, $params, true);
    }

    private function validateNotIn(string $field, $value, array $params): bool
    {
        if (empty($value) && $value !== '0') return true;
        return !in_array($value, $params, true);
    }

    private function validateRegex(string $field, $value, array $params): bool
    {
        if (empty($value)) return true;
        return preg_match($params[0] ?? '//', $value);
    }

    private function validateDate(string $field, $value, array $params): bool
    {
        if (empty($value)) return true;
        $format = $params[0] ?? 'Y-m-d';
        $d = \DateTime::createFromFormat($format, $value);
        return $d && $d->format($format) === $value;
    }

    private function validateIp(string $field, $value, array $params): bool
    {
        if (empty($value)) return true;
        return filter_var($value, FILTER_VALIDATE_IP) !== false;
    }

    private function validateJson(string $field, $value, array $params): bool
    {
        if (empty($value)) return true;
        json_decode($value);
        return json_last_error() === JSON_ERROR_NONE;
    }

    private function validateUnique(string $field, $value, array $params): bool
    {
        if (empty($value)) return true;

        $table  = $params[0] ?? '';
        $column = $params[1] ?? $field;
        $except = $params[2] ?? null;

        if (empty($table) || empty($column)) {
            return true;
        }

        try {
            $db = Database::getInstance();
            if ($db === null) return true;

            $where = "`{$column}` = ?";
            $bindings = [$value];

            if ($except !== null) {
                $where .= " AND `id` != ?";
                $bindings[] = $except;
            }

            return !$db->exists($table, $where, $bindings);
        } catch (\Exception $e) {
            return true;
        }
    }

    private function validateMinLength(string $field, $value, array $params): bool
    {
        if (empty($value)) return true;
        $min = (int) ($params[0] ?? 0);
        return mb_strlen((string) $value) >= $min;
    }

    private function validateMaxLength(string $field, $value, array $params): bool
    {
        if (empty($value)) return true;
        $max = (int) ($params[0] ?? 0);
        return mb_strlen((string) $value) <= $max;
    }

    public static function quick(array $data, array $rules, array $labels = []): self
    {
        $validator = new self($data, $rules);
        if (!empty($labels)) {
            $validator->setLabels($labels);
        }
        $validator->validate();
        return $validator;
    }
}
