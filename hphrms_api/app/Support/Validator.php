<?php

namespace App\Support;

class Validator
{
    public static function make(array $data, array $rules): array
    {
        $errors = [];
        foreach ($rules as $field => $ruleString) {
            $rulesList = explode('|', $ruleString);
            foreach ($rulesList as $rule) {
                $rule = trim($rule);
                if ($rule === 'required' && (!isset($data[$field]) || $data[$field] === '')) {
                    $errors[$field][] = 'The ' . $field . ' field is required.';
                }
                if ($rule === 'email' && isset($data[$field]) && !filter_var($data[$field], FILTER_VALIDATE_EMAIL)) {
                    $errors[$field][] = 'The ' . $field . ' must be a valid email address.';
                }
                if (str_starts_with($rule, 'in:')) {
                    $options = explode(',', substr($rule, 3));
                    if (isset($data[$field]) && !in_array($data[$field], $options, true)) {
                        $errors[$field][] = 'The ' . $field . ' must be one of: ' . implode(', ', $options) . '.';
                    }
                }
            }
        }

        return $errors;
    }
}
