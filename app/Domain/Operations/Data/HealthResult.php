<?php

namespace App\Domain\Operations\Data;

/**
 * Outcome of one check of the internal health (PR-09): the name of the check, whether it passed and
 * a short detail for the server-side report (health:check, log). The detail never leaves the server:
 * the public endpoint /health answers only "ok" or "fail".
 */
final readonly class HealthResult
{
    public function __construct(
        public string $check,
        public bool $ok,
        public string $detail,
    ) {}

    public static function pass(string $check, string $detail): self
    {
        return new self($check, true, $detail);
    }

    public static function fail(string $check, string $detail): self
    {
        return new self($check, false, $detail);
    }
}
