<?php
declare(strict_types=1);

namespace Nivc\Core;

/** Small validation/sanitization helper. Messages are translated keys. */
final class Validator
{
    /** @var array<string,string> */
    private array $errors = [];

    public function __construct(private readonly array $in)
    {
    }

    public function str(string $key, bool $required = false, int $max = 190, bool $multiline = false): string
    {
        $v = $this->in[$key] ?? '';
        $v = is_scalar($v) ? (string) $v : '';
        $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? '';
        $v = $multiline ? trim($v) : trim(preg_replace('/\s+/u', ' ', $v) ?? '');
        if ($required && $v === '') {
            $this->errors[$key] = t('validation.required');
        } elseif (mb_strlen($v) > $max) {
            $this->errors[$key] = t('validation.too_long', ['max' => $max]);
        }
        return $v;
    }

    public function email(string $key, bool $required = true): string
    {
        $v = $this->str($key, $required, 190);
        if ($v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$key] = t('validation.email');
        }
        return mb_strtolower($v);
    }

    public function url(string $key, bool $required = false): string
    {
        $v = $this->str($key, $required, 500);
        if ($v !== '') {
            if (!preg_match('#^https?://#i', $v)) {
                $v = 'https://' . $v;
            }
            $host = parse_url($v, PHP_URL_HOST);
            if (!filter_var($v, FILTER_VALIDATE_URL) || !$host || !str_contains((string) $host, '.')) {
                $this->errors[$key] = t('validation.url');
            }
        }
        return $v;
    }

    public function date(string $key, bool $required = true): ?string
    {
        $v = $this->str($key, $required, 10);
        if ($v === '') {
            return null;
        }
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $v);
        if (!$d || $d->format('Y-m-d') !== $v || (int) $d->format('Y') < 2000 || (int) $d->format('Y') > 2100) {
            $this->errors[$key] = t('validation.date');
            return null;
        }
        return $v;
    }

    public function money(string $key, bool $required = false): ?float
    {
        $raw = $this->in[$key] ?? '';
        if ($raw === '' || $raw === null) {
            if ($required) {
                $this->errors[$key] = t('validation.required');
            }
            return null;
        }
        if (!is_numeric($raw) || (float) $raw < 0 || (float) $raw > 99999999) {
            $this->errors[$key] = t('validation.amount');
            return null;
        }
        return round((float) $raw, 2);
    }

    /** @param string[] $allowed */
    public function enum(string $key, array $allowed, ?string $default = null): string
    {
        $v = (string) ($this->in[$key] ?? '');
        if ($v === '' && $default !== null) {
            return $default;
        }
        if (!in_array($v, $allowed, true)) {
            $this->errors[$key] = t('validation.invalid');
            return $default ?? $allowed[0];
        }
        return $v;
    }

    public function int(string $key, ?int $min = null, ?int $max = null, bool $required = false): ?int
    {
        $raw = $this->in[$key] ?? '';
        if ($raw === '' || $raw === null) {
            if ($required) {
                $this->errors[$key] = t('validation.required');
            }
            return null;
        }
        if (filter_var($raw, FILTER_VALIDATE_INT) === false || ($min !== null && (int) $raw < $min) || ($max !== null && (int) $raw > $max)) {
            $this->errors[$key] = t('validation.invalid');
            return null;
        }
        return (int) $raw;
    }

    public function fail(string $key, string $message): void
    {
        $this->errors[$key] = $message;
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function check(): void
    {
        if ($this->errors) {
            throw HttpException::invalid($this->errors, t('validation.failed'));
        }
    }
}
