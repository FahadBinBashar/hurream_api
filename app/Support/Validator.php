<?php

namespace App\Support;

use App\Core\Database;

class Validator
{
    public static function make(array $data, array $rules): array
    {
        $errors = [];
        foreach ($rules as $field => $ruleString) {
            if ($ruleString === '') {
                continue;
            }

            $rulesList = explode('|', $ruleString);
            foreach ($rulesList as $rule) {
                $rule = trim($rule);
                if ($rule === '') {
                    continue;
                }

                if ($rule === 'required' && (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null)) {
                    $errors[$field][] = 'The ' . $field . ' field is required.';
                    continue;
                }

                if (!array_key_exists($field, $data) || $data[$field] === null || $data[$field] === '') {
                    continue;
                }

                $value = $data[$field];

                if ($rule === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[$field][] = 'The ' . $field . ' must be a valid email address.';
                }

                if ($rule === 'numeric' && !is_numeric($value)) {
                    $errors[$field][] = 'The ' . $field . ' must be numeric.';
                }

                if ($rule === 'array' && !is_array($value)) {
                    $errors[$field][] = 'The ' . $field . ' must be an array.';
                }

                if ($rule === 'boolean' && !in_array($value, [0, 1, true, false, '0', '1'], true)) {
                    $errors[$field][] = 'The ' . $field . ' field must be true or false.';
                }

                if ($rule === 'date' && strtotime((string)$value) === false) {
                    $errors[$field][] = 'The ' . $field . ' is not a valid date.';
                }

                if (str_starts_with($rule, 'regex:')) {
                    $pattern = substr($rule, 6);
                    if (@preg_match($pattern, '') === false) {
                        $errors[$field][] = 'Invalid regex pattern for ' . $field . '.';
                    } elseif (!preg_match($pattern, (string)$value)) {
                        $errors[$field][] = 'The ' . $field . ' format is invalid.';
                    }
                }

                if (str_starts_with($rule, 'min:')) {
                    $min = (float)substr($rule, 4);
                    if (is_array($value) && count($value) < $min) {
                        $errors[$field][] = 'The ' . $field . ' must have at least ' . $min . ' items.';
                    } elseif (is_numeric($value) && (float)$value < $min) {
                        $errors[$field][] = 'The ' . $field . ' must be at least ' . $min . '.';
                    } elseif (!is_array($value) && !is_numeric($value) && strlen((string)$value) < $min) {
                        $errors[$field][] = 'The ' . $field . ' must be at least ' . $min . ' characters.';
                    }
                }

                if (str_starts_with($rule, 'in:')) {
                    $options = explode(',', substr($rule, 3));
                    if (!in_array($value, $options, true)) {
                        $errors[$field][] = 'The ' . $field . ' must be one of: ' . implode(', ', $options) . '.';
                    }
                }

                if (str_starts_with($rule, 'unique:')) {
                    $segments = explode(',', substr($rule, 7));
                    $table = $segments[0] ?? null;
                    $column = $segments[1] ?? $field;
                    $ignoreValue = $segments[2] ?? null;
                    $ignoreColumn = $segments[3] ?? 'id';

                    if ($table && $column) {
                        $pdo = Database::connection();
                        $query = "SELECT COUNT(*) FROM {$table} WHERE {$column} = :value";
                        $params = ['value' => $value];

                        if ($ignoreValue !== null && $ignoreValue !== '') {
                            $query .= " AND {$ignoreColumn} != :ignore";
                            $params['ignore'] = $ignoreValue;
                        }

                        $stmt = $pdo->prepare($query);
                        $stmt->execute($params);
                        if ((int)$stmt->fetchColumn() > 0) {
                            $errors[$field][] = 'The ' . $field . ' has already been taken.';
                        }
                    }
                }
            }
        }

        return $errors;
    }
}
