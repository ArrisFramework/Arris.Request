<?php

declare(strict_types=1);

namespace Arris;

use Arris\Request\Dataset;

/**
 * Fluent request data reader.
 *
 * Reads a named field from an array source (defaults to $_REQUEST) and
 * lets you chain type coercion, default values, trimming and custom transforms.
 *
 * Minimal forms:
 *   Request::str('name')                    — string from $_REQUEST
 *   Request::from('name')->asInt()          — int from $_REQUEST
 *   (new Request('name'))->asString()       — same
 *   Request::import($_POST)->('name')->asBool()  — __invoke shorthand
 *   Request::import($_POST)->asJson()       — whole source as JSON
 *
 * Chain example:
 *   Request::from('email', $data)
 *       ->trim()
 *       ->default('n/a')
 *       ->maxLength(100)
 *       ->apply(fn($v) => strtolower($v))
 *       ->asString();
 */
class Request implements RequestInterface
{
    private string $field;

    private array $source;

    private mixed $default = '';

    private int $maxLength = 0;

    private bool $trim = false;

    private bool $allowEmpty = true;

    private bool $allowEmptyArray = false;

    private bool $allowHtml = false;

    private bool $noEmptyContent = true;

    private bool $prepared_for_invoke = false;

    /** @var list<callable(mixed): mixed> */
    private array $steps = [];

    public function __construct(string $field, ?array $source = null)
    {
        $this->field  = $field;
        $this->source = $source ?? $_REQUEST;
    }

    /**
     * Shorthand for `new self(...)`.
     */
    public static function from(string $field, ?array $source = null): static
    {
        return new static($field, $source);
    }

    /**
     * Bind a source once (defaults to $_REQUEST), read many fields via __invoke.
     * The returned "source reader" exposes the whole array through
     * raw()/asArray()/asJson(): Request::import()->('field')->asString().
     */
    public static function import(?array $source = null): static
    {
        $reader = new static('', $source);
        $reader->prepared_for_invoke = true;
        return $reader;
    }

    /**
     * One-liner: Request::str('field') === Request::from('field')->asString().
     */
    public static function str(string $field, ?array $source = null): string
    {
        return (new static($field, $source))->asString();
    }

    /**
     * __invoke shorthand, for example: Request('field')->asInt().
     */
    public function __invoke(string $field): static
    {
        return new static($field, $this->source);
    }

    // ── step methods (return $this) ────────────────────────────────

    public function trim(): static
    {
        $this->trim = true;
        return $this;
    }

    public function allowEmpty(bool $allow): static
    {
        $this->allowEmpty = $allow;
        return $this;
    }

    public function allowEmptyArray(bool $allow = true): static
    {
        $this->allowEmptyArray = $allow;
        return $this;
    }

    public function maxLength(int $length): static
    {
        $this->maxLength = $length;
        return $this;
    }

    public function default(mixed $default): static
    {
        $this->default = $default;
        return $this;
    }

    public function allowHtml(bool $allow = true): static
    {
        $this->allowHtml = $allow;
        return $this;
    }

    public function noEmptyContent(bool $allow = true): static
    {
        $this->noEmptyContent = $allow;
        return $this;
    }

    /**
     * Append a transformation step. Runs in order of registration,
     * after trim/maxLength but before the terminal method.
     */
    public function apply(callable $callback): static
    {
        $this->steps[] = $callback;
        return $this;
    }

    /**
     * Shorthand for apply('strip_tags'): strips HTML/PHP tags from string values.
     */
    public function stripTags(): static
    {
        $this->steps[] = 'strip_tags';
        return $this;
    }

    /**
     * Slice the string by CHARACTERS (always mb_substr, so multibyte-safe).
     * Use before/after trim()/stripTags() as needed.
     */
    public function substr(int $start = 0, ?int $length = null): static
    {
        $this->steps[] = static function ($value) use ($start, $length): mixed {
            return is_string($value) ? mb_substr($value, $start, $length, 'UTF-8') : $value;
        };
        return $this;
    }

    // ── terminal methods (return value) ────────────────────────────

    /**
     * Raw value from the source, no steps, no coercion.
     */
    public function raw(): mixed
    {
        return $this->prepared_for_invoke ? $this->source : ($this->source[$this->field] ?? $this->default);
    }

    public function asString(): string
    {
        return (string)$this->pipe();
    }

    public function asStr(): string
    {
        return $this->asString();
    }

    public function asInt(): int
    {
        $value = $this->pipe();
        $cast  = filter_var($value, FILTER_VALIDATE_INT);
        return $cast === false ? (int)$this->default : $cast;
    }

    public function asFloat(): float
    {
        $value = $this->pipe();
        $cast  = filter_var($value, FILTER_VALIDATE_FLOAT);
        return $cast === false ? (float)$this->default : $cast;
    }

    public function asBool(): bool
    {
        $value = $this->pipe();

        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $lower = strtolower($value);
            if (in_array($lower, ['true', '1', 'on', 'yes'], true)) {
                return true;
            }
            if (in_array($lower, ['false', '0', 'off', 'no', ''], true)) {
                return false;
            }
        }

        if (is_numeric($value)) {
            return (int)$value !== 0;
        }

        return (bool)$this->default;
    }

    public function asArray(): array
    {
        if ($this->prepared_for_invoke) {
            return $this->source;
        }

        $value = $this->pipe();
        if (!is_array($value)) {
            return (array)$this->default;
        }
        if (!$this->allowEmptyArray && $value === []) {
            return (array)$this->default;
        }
        return $value;
    }

    /**
     * Returns as string, validated by FILTER_VALIDATE_EMAIL.
     * Invalid → default.
     */
    public function asEmail(): string
    {
        $value = $this->asString();
        if ($value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return $value;
        }
        return (string)$this->default;
    }

    /**
     * Returns as string, validated by FILTER_VALIDATE_URL.
     * Invalid → default.
     */
    public function asUrl(): string
    {
        $value = $this->asString();
        if ($value !== '' && filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }
        return (string)$this->default;
    }

    /**
     * Checkbox shorthand: truthy → 1, falsy → 0. Ready for INT DB column.
     */
    public function asCheckbox(): int
    {
        return $this->asBool() ? 1 : 0;
    }

    /**
     * JSON of the resolved value: whole source for import()-reader, piped field value otherwise.
     * Delegates to Dataset::jsonize() for canonical flags (UNESCAPED_UNICODE, THROW_ON_ERROR).
     */
    public function asJson(): string
    {
        return Dataset::jsonize($this->prepared_for_invoke ? $this->source : $this->pipe());
    }

    /**
     * Sanitized text: strips tags and escapes special chars unless allowHtml(),
     * removes empty containers and collapses whitespace unless noEmptyContent(false).
     */
    public function asText(): string
    {
        $value = (string)$this->pipe();

        if (!$this->allowHtml) {
            $value = strip_tags($value);
            $value = htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        if ($this->noEmptyContent) {
            do {
                $oldValue = $value;

                $value = preg_replace('/<br\s*\/?>/i', '', $value);
                $value = preg_replace('/<(div|p)[^>]*>\s*(?:<br\s*\/?>\s*)*\s*<\/\1>/i', '', $value);
                $value = preg_replace('/<p[^>]*>\s*(?:&nbsp;\s*)+\s*<\/p>/i', '', $value);
                $value = preg_replace('/<p[^>]*>[\s\x{00A0}]*<\/p>/iu', '', $value);
            } while ($oldValue !== $value);

            $value = preg_replace('/\s+/', ' ', $value);
            $value = trim($value);

            if ($value === '') {
                return '';
            }
        }

        return $value;
    }

    // ── internals ──────────────────────────────────────────────────

    /**
     * Apply steps pipeline: raw → trim → maxLength → allowEmpty → callbacks → result.
     */
    private function pipe(): mixed
    {
        $value = $this->source[$this->field] ?? $this->default;

        if ($this->trim && is_string($value)) {
            $value = trim($value);
        }

        if ($this->maxLength > 0 && is_string($value)) {
            $value = mb_substr($value, 0, $this->maxLength, 'UTF-8');
        }

        if (!$this->allowEmpty && is_string($value) && $value === '') {
            return $this->default;
        }

        foreach ($this->steps as $step) {
            $value = $step($value);
        }

        return $value;
    }
}
