<?php

declare(strict_types=1);

namespace Arris;

/**
 * Contract for fluent request data reader.
 */
interface RequestInterface
{
    public static function from(string $field, ?array $source = null): static;

    public static function import(?array $source = null): static;

    public static function str(string $field, ?array $source = null): string;

    public function __invoke(string $field): static;

    // ── step methods ──────────────────────────────────────────────

    public function trim(): static;

    public function allowEmpty(bool $allow): static;

    public function allowEmptyArray(bool $allow = true): static;

    public function allowHtml(bool $allow = true): static;

    public function noEmptyContent(bool $allow = true): static;

    public function maxLength(int $length): static;

    public function default(mixed $default): static;

    public function apply(callable $callback): static;

    public function stripTags(): static;

    public function substr(int $start = 0, ?int $length = null): static;

    // ── terminal methods ──────────────────────────────────────────

    public function raw(): mixed;

    public function asString(): string;

    public function asStr(): string;

    public function asInt(): int;

    public function asFloat(): float;

    public function asBool(): bool;

    public function asArray(): array;

    public function asEmail(): string;

    public function asUrl(): string;

    public function asCheckbox(): int;

    public function asJson(): string;

    public function asText(): string;
}
