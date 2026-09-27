<?php

declare(strict_types=1);

namespace App\Utils;

class Validator
{
    private array $data;
    private array $rules;
    private array $errors = [];

    public function __construct(array $data, array $rules)
    {
        $this->data = $data;
        $this->rules = $rules;
    }

    public static function validate(array $data, array $rules): array
    {
        $validator = new self($data, $rules);
        if (!$validator->passes()) {
            return [
                'valid' => false,
                'errors' => $validator->getErrors(),
            ];
        }

        return [
            'valid' => true,
            'data' => $validator->getValidated(),
        ];
    }

    public function passes(): bool
    {
        $this->errors = [];

        foreach ($this->rules as $field => $fieldRules) {
            $ruleList = is_string($fieldRules) ? explode('|', $fieldRules) : $fieldRules;
            $val = $this->data[$field] ?? null;

            foreach ($ruleList as $ruleStr) {
                $parts = explode(':', $ruleStr, 2);
                $rule = $parts[0];
                $param = $parts[1] ?? null;

                if ($rule === 'required') {
                    if ($val === null || $val === '' || (is_array($val) && empty($val))) {
                        $this->addError($field, "The {$field} field is required.");
                        break;
                    }
                } elseif ($rule === 'nullable') {
                    if ($val === null || $val === '') {
                        break;
                    }
                } elseif ($val !== null && $val !== '') {
                    $this->applyRule($field, $val, $rule, $param);
                }
            }
        }

        return empty($this->errors);
    }

    private function applyRule(string $field, mixed $val, string $rule, ?string $param): void
    {
        switch ($rule) {
            case 'email':
                if (!filter_var($val, FILTER_VALIDATE_EMAIL)) {
                    $this->addError($field, "The {$field} must be a valid email address.");
                }
                break;

            case 'string':
                if (!is_string($val)) {
                    $this->addError($field, "The {$field} must be a string.");
                }
                break;

            case 'integer':
                if (!filter_var($val, FILTER_VALIDATE_INT) && !is_int($val)) {
                    $this->addError($field, "The {$field} must be an integer.");
                }
                break;

            case 'numeric':
                if (!is_numeric($val)) {
                    $this->addError($field, "The {$field} must be numeric.");
                }
                break;

            case 'in':
                $allowed = explode(',', (string) $param);
                if (!in_array((string) $val, $allowed, true)) {
                    $this->addError($field, "The {$field} must be one of: " . implode(', ', $allowed) . ".");
                }
                break;

            case 'min':
                $min = (int) $param;
                if (is_string($val) && mb_strlen($val) < $min) {
                    $this->addError($field, "The {$field} must be at least {$min} characters.");
                } elseif (is_numeric($val) && (float) $val < $min) {
                    $this->addError($field, "The {$field} must be at least {$min}.");
                }
                break;

            case 'max':
                $max = (int) $param;
                if (is_string($val) && mb_strlen($val) > $max) {
                    $this->addError($field, "The {$field} must not exceed {$max} characters.");
                } elseif (is_numeric($val) && (float) $val > $max) {
                    $this->addError($field, "The {$field} must not exceed {$max}.");
                }
                break;

            case 'date':
                if (!is_string($val) || strtotime($val) === false) {
                    $this->addError($field, "The {$field} must be a valid date format.");
                }
                break;
        }
    }

    private function addError(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getValidated(): array
    {
        $validated = [];
        foreach (array_keys($this->rules) as $field) {
            if (array_key_exists($field, $this->data)) {
                $validated[$field] = $this->data[$field];
            }
        }
        return $validated;
    }
}
