<?php

namespace App\Domain\Sports\Data;

/**
 * Outcome of the import of the clubs of the old system (D-318, D-329): what was created, what was
 * already there, and the anomalies to check by hand.
 */
final class LegacyClubReport
{
    /** @var list<string> clubs created, as "CODE – Name" */
    public array $created = [];

    /** @var list<string> clubs already in the portal, left as they are (D-329) */
    public array $existing = [];

    public int $aliases = 0;

    public int $references = 0;

    /** @var list<string> */
    public array $anomalies = [];
}
