<?php

namespace App\Domain\Proposals\Data;

use App\Models\Proposal;
use App\Models\ProposalVersion;

/**
 * Fields of a draft in the audit events of the area "proposals" (D-372): the header of the steps 1
 * and 2. The season is written as season_code, because AuditLog drops the fields named "code".
 */
final class ProposalsAuditValues
{
    /**
     * @return array<string, int|string|null>
     */
    public static function draft(Proposal $proposal, ProposalVersion $version): array
    {
        return [
            'number' => $proposal->number,
            'client_id' => $proposal->client_id,
            'client_name' => $proposal->client?->company_name,
            'season_id' => $proposal->season_id,
            'season_code' => $proposal->season?->code,
            'starts_on' => $version->starts_on?->toDateString(),
            'ends_on' => $version->ends_on?->toDateString(),
        ];
    }
}
