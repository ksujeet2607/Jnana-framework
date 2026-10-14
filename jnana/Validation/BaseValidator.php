<?php

namespace Jnana\Validation;

use DateTime;
use DI\Attribute\Inject;
use Jnana\Session\SessionManager;

abstract class BaseValidator
{
    protected array $data;
    protected array $rules = [];
    protected array $messages = [];
    protected array $errors = [];
    #[Inject]
    private SessionManager $sessionManager;

    public function validate(array $data): array
    {
        $this->data = $data;
        $this->errors = [];

        foreach ($this->rules as $field => $fieldRules) {

            $rulesArray = is_string($fieldRules) ? explode('|', $fieldRules) : $fieldRules;

            $fieldHasNoBail = in_array('no_bail', $rulesArray, true);
            $rulesArray = array_filter($rulesArray, fn($r) => $r !== 'no_bail');
            $resolvedFields = $this->resolveWildcardFields($field, $data);

            $isNullable = in_array('nullable', $rulesArray, true);

            foreach ($resolvedFields as $resolvedField => $value) {

                $isEmpty = ($value === null || $value === '');
                if ($isNullable && $isEmpty) {
                    continue 2; // skip all remaining rules for this field
                }

                foreach ($rulesArray as $rule) {

                    // FIXED: bail must check resolved field
                    if (isset($this->errors[$resolvedField]) && !$fieldHasNoBail) {
                        break;
                    }

                    if ($rule instanceof \Closure) {
                        $result = $rule($value, $data);

                        if ($result !== true) {
                            $this->addError(
                                $resolvedField,
                                is_string($result) ? $result : 'invalid'
                            );
                            if (!$fieldHasNoBail) {
                                break;
                            }
                        }
                        continue;
                    }

                    if (is_string($rule) && str_starts_with($rule, 'callback:')) {
                        $method = substr($rule, 9);
                        if (method_exists($this, $method)) {
                            $result = $this->$method($value, $data);
                            if ($result !== true) {
                                $key = "$resolvedField.callback";
                                if (isset($this->messages[$key])) {
                                    $this->addError($resolvedField, 'callback');
                                }else{
                                    $this->addError($resolvedField, is_string($result) ? $result : "$resolvedField is invalid.");
                                }
                            }
                        }
                        continue;
                    }
                    if (str_starts_with($rule, 'regex:')) {
                        $pattern = substr($rule, 6);
                        if (!preg_match($pattern, (string)$value)) {
                            if ($value === null || $value === '') {
                                continue;
                            }
                            $this->addError($resolvedField, 'regex');
                        }
                    }
                    elseif (str_starts_with($rule, 'min:')) {
                        if ($value === null || $value === '') {
                            continue;
                        }
                        $min = (int)substr($rule, 4);
                        if (is_numeric($value) && $value < $min) {
                            $this->addError($resolvedField, 'min', $min);
                        }
                    }
                    elseif (str_starts_with($rule, 'max:')) {
                        if ($value === null || $value === '') {
                            continue;
                        }
                        $max = (int)substr($rule, 4);
                        if (is_numeric($value) && $value > $max) {
                            $this->addError($resolvedField, 'max', $max);
                        }
                    }
                    elseif (str_starts_with($rule, 'minlength:')) {

                        $min = (int)substr($rule, 10); // strlen("minlength:")

                        if (is_string($value) && mb_strlen($value) < $min) {
                            $this->addError($resolvedField, 'minlength', $min);
                        }
                    }
                    elseif (str_starts_with($rule, 'maxlength:')) {

                        $max = (int)substr($rule, 10); // strlen("maxlength:")

                        if (is_string($value) && mb_strlen($value) > $max) {
                            $this->addError($resolvedField, 'maxlength', $max);
                        }
                    }
                    elseif ($rule === 'required') {
                        if ($value === null || $value === '' || (is_array($value) && $value === [])) {
                            $this->addError($resolvedField, 'required');
                        }
                    }
                    elseif ($rule === 'numeric') {
                        if ($value === null || $value === '') {
                            continue;
                        }
                        if (!is_numeric(str_replace(',', '', (string)$value))) {
                            $this->addError($resolvedField, 'numeric');
                        }
                    }
                    elseif ($rule === 'amount') {
                        if ($value === null || $value === '') {
                            continue;
                        }
                        $parsed = $this->parseAmount($value);
                        if ($parsed === null) {
                            $this->addError($resolvedField, 'amount');
                        } else {
                            $value = $parsed; // allow chaining
                            $this->data[$resolvedField] = $parsed;
                        }
                    }
                    elseif ($rule === 'array' && !is_array($value)) {
                        $this->addError($resolvedField, 'array');
                    }
                    elseif (preg_match('/^date(?::(.+))?$/', $rule, $matches)) {
                        if ($value === null || $value === '') {
                            continue;
                        }
                        $format = $matches[1] ?? 'm/d/Y';
                        if (!$this->validateDate($value, $format)) {
                            $this->addError($resolvedField, 'date', $format);
                        }
                    }
                    elseif ($rule === 'alphanum_dash' && !preg_match('/^[a-zA-Z0-9_-]*$/', (string)$value)) {
                        $this->addError($resolvedField, 'alphanum_dash');
                    }
                    elseif (preg_match('/^min_value:(\-?\d+(\.\d+)?)$/', $rule, $matches)) {
                        if ($value === null || $value === '') {
                            continue;
                        }
                        $num = $this->parseAmount($value);
                        if ($num === null || $num < (float)$matches[1]) {
                            $this->addError($resolvedField, 'min_value', $matches[1]);
                        }
                    }
                    elseif (preg_match('/^max_value:(\-?\d+(\.\d+)?)$/', $rule, $matches)) {
                        if ($value === null || $value === '') {
                            continue;
                        }
                        $num = $this->parseAmount($value);
                        if ($num === null || $num > (float)$matches[1]) {
                            $this->addError($resolvedField, 'max_value', $matches[1]);
                        }
                    }
                    elseif ($rule === 'email') {
                        if ($value === null || $value === '') {
                            continue;
                        }
                        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $this->addError($resolvedField, 'email');
                        }
                    }
                    elseif (preg_match('/^in:(.+)$/', $rule, $matches)) {
                        if ($value === null || $value === '') {
                            continue;
                        }
                        $options = explode(',', $matches[1]);
                        if (!in_array($value, $options, true)) {
                            $this->addError($resolvedField, 'in', implode(', ', $options));
                        }
                    }
                    elseif (preg_match('/^not_in:(.+)$/', $rule, $matches)) {
                        if ($value === null || $value === '') {
                            continue;
                        }
                        $options = explode(',', $matches[1]);
                        if (in_array($value, $options, true)) {
                            $this->addError($resolvedField, 'not_in', implode(', ', $options));
                        }
                    }
                }
            }
        }

        if (method_exists($this, 'afterValidate')) {
            $this->afterValidate($data);
        }

        if (!empty($this->errors)) {
            with_old_input($data);
        }

        return $this->errors;
    }
    protected function addError(string $field, string $rule, mixed $param = null): void
    {
        $key = "$field.$rule";
        if (isset($this->messages[$key])) {
            $message = $this->messages[$key];
        } else {
            $message = match ($rule) {
                'required' => ucfirst($field) . " is required.",
                'numeric' => ucfirst($field) . " must be numeric.",
                'amount' => ucfirst($field) . " must be a valid amount.",
                'array' => ucfirst($field) . " must be an array.",
                'date' => ucfirst($field) . " must be a valid date" . ($param ? " in format $param." : "."),
                'min' => ucfirst($field) . " must be at least $param characters.",
                'max' => ucfirst($field) . " must be at most $param characters.",
                'min_value' => ucfirst($field) . " must be at least $param.",
                'max_value' => ucfirst($field) . " must be at most $param.",
                'regex' => ucfirst($field) . " has invalid format.",
                'email' => ucfirst($field) . " must be a valid email address.",
                'in' => ucfirst($field) . " must be one of: $param.",
                'not_in' => ucfirst($field) . " must not be any of: $param.",
                default => $rule !=='' ? $rule : ucfirst($field) . " failed $rule validation."

            };

        }

        $this->errors[$field][] = $message;
    }

    public function failed(string $error): void
    {
        with_old_input($this->data);
        $this->sessionManager->error($error);
    }


    protected function parseAmount($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        // Remove commas and spaces
        $cleaned = str_replace([',', ' '], '', (string)$value);

        if (!is_numeric($cleaned)) {
            return null;
        }

        return (float)$cleaned;
    }

    protected function resolveWildcardFields(string $field, array $data): array
    {
        if (!str_contains($field, '*')) {
            return [$field => $data[$field] ?? null];
        }

        $segments = explode('.', $field);
        return $this->expandSegments($segments, $data);
    }

    protected function expandSegments(array $segments, $data, string $prefix = ''): array
    {
        // Base case: no more segments → return resolved value
        if (empty($segments)) {
            return [$prefix => $data];
        }

        // If data is not array but segments still exist → dead path
        if (!is_array($data)) {
            return [];
        }

        $segment = array_shift($segments);
        $results = [];

        if ($segment === '*') {
            foreach ($data as $key => $value) {
                $newPrefix = $prefix === '' ? (string)$key : "$prefix.$key";
                $results += $this->expandSegments($segments, $value, $newPrefix);
            }
        } else {
            if (!array_key_exists($segment, $data)) {
                return [];
            }

            $newPrefix = $prefix === '' ? $segment : "$prefix.$segment";
            $results += $this->expandSegments($segments, $data[$segment], $newPrefix);
        }

        return $results;
    }


    /* -----------------------------PLAIN STRING VALIDATION ---------------------------------- */

    protected function text(string $input)
    {
        return (!preg_match("/^[a-zA-Z ]+$/", $input)) ? false : $input;
    }

    /* ---------------------------PLAIN STRING VALIDATION END ---------------------------------- */

    /* ------------------------------ALPHA NUMERIC INPUT VALIDATION (Start with alpha)-------------------------------- */

    protected function alphaNumeric(string $input)
    {
        //return (!preg_match('/[^a-z_\-0-9]/i', $input))?false:$input;
        return (!preg_match('/^[a-zA-Z ]+[a-zA-Z0-9._ ]+$/', $input)) ? false : $input;
        //return (!preg_match('/^[a-zA-Z0-9]+$/',$input))?false:$input;
        //return (!ctype_alnum($input))?false:$input;
    }

    /* ---------------------------------ALPHA NUMERIC VALIDATION END----------------------------------------------- */

    /* ------------------------------ADDRESS VALIDATION AND SENETIZATION END -------------------------------------------------- */

    protected function address(string $input)
    {
        return (!preg_match('/^[A-Za-z0-9\-\\,. ]+$/', $input)) ? false : $input;
    }

    protected function senetizeAddress(string $input): string
    {
        return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    }

    /* -------------------------------ADDRESS VALIDATION AND SENETIZATION END -------------------------------------------------- */

    /* --------------------------NUMBER VALIDATION-------------------------------------------------- */

    protected function number($input)
    {
        return (!preg_match('/^[0-9]+$/', $input)) ? false : $input;
        /*
          return (!filter_var($int_a, FILTER_VALIDATE_INT)?false:$input;
          return (!ctype_digit($input))?false:$input;
         */
    }

    /* ------------------------NUMBER VALIDATION END------------------------------------------------------- */

    /* ---------------------------MOBILE VALIDATION ------------------------------------------------------- */

    protected function mobile($input)
    {
        return (!preg_match('/^[0-9]{10}+$/', $input)) ? false : $input;
        /*
          $filtered_phone_number = filter_var($phone, FILTER_SANITIZE_NUMBER_INT);
          // Remove "-" from number
          $phone_to_check = str_replace("-", "", $filtered_phone_number);
          // Check the lenght of number
          // This can be customized if you want phone number from a specific country
          if (strlen($phone_to_check) < 10 || strlen($phone_to_check) > 14) {
          return false;
          } else {
          return true;
          }
         */
    }

    /* ------------------------MOBILE VALIDATION END ---------------------------------------------------------------------- */

    /* -----------------------------E-MAIL VALIDATION AND SENETIZATION ----------------------------------------------------- */

    protected function email($input)
    {
        /* return (!preg_match('/^[A-Za-z0-9_\-]+[@][A-z0-9_\-]+([.][A-z0-9_\-]+)+[A-z.]{2,4}$/', $input))?false:$input; */
        return (!filter_var($input, FILTER_VALIDATE_EMAIL)) ? false : $input; // ALSO CAL BE DONE BY THIS
    }

    protected function senetizeEmail($input)
    {
        return (filter_var($input, FILTER_SANITIZE_EMAIL));
    }

    /* --------------------------E-MAIL VALIDATION AND SENETIZATION END ------------------------------------------------------ */

    /* ------------------------------URL Validation ----------------------------------------------------- */

    protected function url($input)
    {
        return (!preg_match("/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|]/i", $input)) ? false : $input;
    }

    /* ------------------------------URL Validation ----------------------------------------------------- */

    /* ---------------------IP VALIDATION AND SENETIZATION END -------------------------------------------- */

    protected function ip($input)
    {
        return (!filter_var($input, FILTER_VALIDATE_IP)) ? false : $input;
    }

    /* ---------------------E-MAIL VALIDATION AND SENETIZATION END ------------------------------------------------ */

    protected function validateDate(string|null $date, string $format = 'm/d/Y'): bool
    {
        if (!$date) {
            return false;
        }

        $d = DateTime::createFromFormat($format, $date);
        return $d !== false && $d->format($format) === $date;
    }



    protected function formatDate(string|null $date, string $format = 'd-m-Y', string $target_format = "Y-m-d"):string|null
    {
        if($this->validateDate($date, $format)){
            $d = DateTime::createFromFormat($format, $date);
            return $d->format($target_format);
        }

        return null;
    }

    /**
     * Allow Request to inject data
     */
    public function setData(array $data): void
    {
        $this->data = $data;
    }

    /**
     * Get validated / normalized data
     */
    public function validated(): array
    {
        return $this->data ?? [];
    }

    /**
     * Check if validation failed
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Get validation errors
     */
    public function errors(): array
    {
        return $this->errors;
    }

}