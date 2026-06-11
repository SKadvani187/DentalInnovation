<?php
// Shared server-side input validators for admin write paths. The combos/offers pages
// already validate authoritatively; customers/coupons/categories/events historically passed
// client fields straight to SQL. Use these to enforce shape before INSERT/UPDATE.
//
// Usage:
//   $v = new Validator($data);
//   $v->required('name')->maxLen('name', 120);
//   $v->emailOpt('email')->phoneOpt('phone')->inOpt('customer_type', ['individual','clinic',...]);
//   if ($v->fails()) { echo json_encode(['success'=>false,'message'=>$v->firstError()]); exit; }

if (!class_exists('Validator')) {

class Validator {
    private array $data;
    private array $errors = [];

    public function __construct(array $data) { $this->data = $data; }

    private function val(string $key) {
        return array_key_exists($key, $this->data) ? $this->data[$key] : null;
    }
    private function add(string $key, string $msg): void {
        if (!isset($this->errors[$key])) $this->errors[$key] = $msg;
    }

    public function required(string $key, ?string $label = null): self {
        $v = $this->val($key);
        if ($v === null || trim((string)$v) === '') $this->add($key, ($label ?? ucfirst($key)) . ' is required.');
        return $this;
    }

    public function maxLen(string $key, int $max, ?string $label = null): self {
        $v = $this->val($key);
        if ($v !== null && mb_strlen((string)$v) > $max) $this->add($key, ($label ?? ucfirst($key)) . " is too long (max $max).");
        return $this;
    }

    // Email required iff present+non-empty (admin customer email is optional).
    public function emailOpt(string $key): self {
        $v = $this->val($key);
        if ($v !== null && trim((string)$v) !== '' && !filter_var(trim((string)$v), FILTER_VALIDATE_EMAIL)) {
            $this->add($key, 'Enter a valid email address.');
        }
        return $this;
    }

    public function phoneOpt(string $key): self {
        $v = $this->val($key);
        if ($v !== null && trim((string)$v) !== '') {
            $digits = preg_replace('/\D/', '', (string)$v);
            if (!preg_match('/^[6-9]\d{9}$/', $digits)) $this->add($key, 'Enter a valid 10-digit mobile number.');
        }
        return $this;
    }

    public function pincodeOpt(string $key): self {
        $v = $this->val($key);
        if ($v !== null && trim((string)$v) !== '' && !preg_match('/^\d{6}$/', trim((string)$v))) {
            $this->add($key, 'Enter a valid 6-digit pincode.');
        }
        return $this;
    }

    // Value (when present) must be one of $allowed.
    public function inOpt(string $key, array $allowed): self {
        $v = $this->val($key);
        if ($v !== null && trim((string)$v) !== '' && !in_array($v, $allowed, true)) {
            $this->add($key, ucfirst($key) . ' has an invalid value.');
        }
        return $this;
    }

    public function numericOpt(string $key, ?float $min = null, ?float $max = null): self {
        $v = $this->val($key);
        if ($v !== null && $v !== '') {
            if (!is_numeric($v)) { $this->add($key, ucfirst($key) . ' must be a number.'); return $this; }
            $n = (float)$v;
            if ($min !== null && $n < $min) $this->add($key, ucfirst($key) . " must be >= $min.");
            if ($max !== null && $n > $max) $this->add($key, ucfirst($key) . " must be <= $max.");
        }
        return $this;
    }

    // Optional date in Y-m-d (or any strtotime-parseable) — rejects gibberish.
    public function dateOpt(string $key): self {
        $v = $this->val($key);
        if ($v !== null && trim((string)$v) !== '' && strtotime((string)$v) === false) {
            $this->add($key, ucfirst($key) . ' is not a valid date.');
        }
        return $this;
    }

    public function fails(): bool { return !empty($this->errors); }
    public function firstError(): string { return $this->errors ? reset($this->errors) : ''; }
    public function errors(): array { return $this->errors; }
}

}
