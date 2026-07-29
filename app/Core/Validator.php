<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Declarative input validator.
 *
 * $v = Validator::make($data, [
 *     'email'  => 'required|email|max:150',
 *     'amount' => 'required|numeric|min:0',
 *     'role'   => 'required|in:administrator,principal',
 * ]);
 * if ($v->fails()) Response::error('Validation failed', 422, $v->errors());
 */
class Validator
{
    private array $errors = [];

    private function __construct(private array $data, private array $rules)
    {
        $this->validate();
    }

    public static function make(array $data, array $rules): self
    {
        return new self($data, $rules);
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Only the fields that had validation rules (whitelisting inputs).
     */
    public function validated(): array
    {
        $out = [];
        foreach (array_keys($this->rules) as $field) {
            if (array_key_exists($field, $this->data)) {
                $out[$field] = is_string($this->data[$field]) ? trim($this->data[$field]) : $this->data[$field];
            }
        }
        return $out;
    }

    private function validate(): void
    {
        foreach ($this->rules as $field => $ruleString) {
            $value = $this->data[$field] ?? null;
            $rules = explode('|', $ruleString);
            $isRequired = in_array('required', $rules, true);
            // min/max compare the VALUE only for fields declared numeric or
            // integer; for text fields they compare the character length.
            // (Otherwise a numeric-looking string like a phone number
            // "9876543210" would be compared as the number 9.8 billion.)
            $isNumericField = in_array('numeric', $rules, true) || in_array('integer', $rules, true);

            foreach ($rules as $rule) {
                [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);

                $empty = $value === null || $value === '' || $value === [];
                if ($name !== 'required' && $empty && !$isRequired) {
                    continue; // optional empty field — skip remaining checks
                }

                $error = match ($name) {
                    'required' => $empty ? 'This field is required.' : null,
                    'email'    => !filter_var((string) $value, FILTER_VALIDATE_EMAIL) ? 'Enter a valid email address.' : null,
                    'numeric'  => !is_numeric($value) ? 'Must be a number.' : null,
                    'integer'  => filter_var($value, FILTER_VALIDATE_INT) === false ? 'Must be an integer.' : null,
                    'min'      => $isNumericField
                        ? (is_numeric($value) && (float) $value < (float) $param ? "Must be at least $param." : null)
                        : (mb_strlen((string) $value) < (int) $param ? "Must be at least $param characters." : null),
                    'max'      => $isNumericField
                        ? (is_numeric($value) && (float) $value > (float) $param ? "Must not exceed $param." : null)
                        : (mb_strlen((string) $value) > (int) $param ? "Must not exceed $param characters." : null),
                    'minlen'   => mb_strlen((string) $value) < (int) $param ? "Must be at least $param characters." : null,
                    'in'       => !in_array((string) $value, explode(',', (string) $param), true) ? 'Invalid value selected.' : null,
                    'date'     => strtotime((string) $value) === false ? 'Enter a valid date.' : null,
                    default    => null,
                };

                if ($error !== null) {
                    $this->errors[$field] = $error;
                    break;
                }
            }
        }
    }
}
