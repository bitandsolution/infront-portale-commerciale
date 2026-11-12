<?php

namespace App\Domain\Proposals\Services;

use App\Domain\Proposals\Data\ApprovalReview;
use App\Domain\Proposals\Data\DraftEvaluation;
use App\Domain\Proposals\Data\InventoryNeed;
use App\Enums\InventoryResource;
use App\Models\Proposal;
use App\Models\Season;
use App\Support\LedDuration;
use App\Support\Text;

/**
 * Check of a group before its approval (M07 F-02, RB-05, §8; D-216, D-396, D-409; DM-03b): the only
 * implementation, used by the approval inside its lock and by the detail of the group.
 *
 * - Every variant is evaluated again on the current list and residuals (DraftEvaluator): a club no
 *   longer on sale, an option sold out, minutes or seats beyond the residual of the club, a club
 *   without elements or a variant without a period block the approval (D-409; the forcing is F5).
 * - What the variants ask together of each resource (GroupInventory) must fit its residual: all the
 *   variants commit at the approval (D-396).
 */
final class ApprovalCheck
{
    public function __construct(
        private readonly DraftEvaluator $evaluator,
        private readonly GroupInventory $inventory,
    ) {}

    /**
     * @param  iterable<Proposal>  $variants  of the same group, with season and working version
     */
    public function review(iterable $variants): ApprovalReview
    {
        $list = [];

        foreach ($variants as $variant) {
            $list[] = $variant;
        }

        usort($list, fn (Proposal $a, Proposal $b): int => strcmp((string) $a->variant_letter, (string) $b->variant_letter));
        $many = count($list) > 1;
        $evaluations = [];
        $byLetter = [];
        $season = null;

        $periodProblems = [];

        foreach ($list as $variant) {
            $letter = $many ? (string) $variant->variant_letter : '';
            $evaluation = $this->evaluator->evaluateProposal($variant);
            $evaluations[$variant->id] = $evaluation;
            $season ??= $variant->season;
            $version = $variant->workingVersion;

            if ($evaluation === null || $version === null || $version->starts_on === null || $version->ends_on === null) {
                $periodProblems[] = Text::get('proposals.approval_problems.period', ['letter' => (string) $variant->variant_letter]);

                continue;
            }

            $byLetter[$letter] = $evaluation;
        }

        $needs = $season instanceof Season ? $this->inventory->needs($season, $byLetter) : [];
        $short = [];

        foreach ($needs as $need) {
            if (! $need->isCovered()) {
                $short[$need->club->id.'|'.$need->resource->value] = true;
            }
        }

        // Errors of a variant on a resource that the group lacks are said once, with the numbers, by
        // the shortfall below (DM-03b collaudo): the agent's hints ("scegline un altro") do not repeat.
        $problems = $periodProblems;

        foreach ($byLetter as $letter => $evaluation) {
            foreach ($this->variantProblems($evaluation, $short) as $problem) {
                $problems[] = $letter !== ''
                    ? Text::get('proposals.approval_problems.variant', ['letter' => $letter, 'problem' => $problem])
                    : $problem;
            }
        }

        foreach ($needs as $need) {
            if (! $need->isCovered()) {
                $problems[] = self::shortfall($need);
            }
        }

        return new ApprovalReview($list, $evaluations, $needs, $problems);
    }

    /**
     * Quantity in the unit of the resource: "1′30″", "2 contratti", "4 posti".
     */
    public static function quantity(InventoryResource $resource, int $quantity): string
    {
        $quantity = max(0, $quantity);

        return match ($resource) {
            InventoryResource::Pool => LedDuration::format($quantity),
            InventoryResource::Partnership => $quantity === 1
                ? Text::get('proposals.approval_problems.contract')
                : Text::get('proposals.approval_problems.contracts', ['count' => (string) $quantity]),
            InventoryResource::Hospitality => $quantity === 1
                ? Text::get('proposals.approval_problems.seat')
                : Text::get('proposals.approval_problems.seats', ['count' => (string) $quantity]),
        };
    }

    /**
     * @param  array<string, true>  $short  "club id|resource" the group lacks
     * @return list<string>
     */
    private function variantProblems(DraftEvaluation $evaluation, array $short): array
    {
        $problems = [];

        if ($evaluation->clubs === []) {
            $problems[] = Text::get('proposals.approval_problems.no_clubs');
        }

        foreach ($evaluation->clubs as $club) {
            foreach ($club->errors as $field => $message) {
                $resource = self::resourceOf((string) $field);

                if ($resource !== null && isset($short[$club->club->id.'|'.$resource->value])) {
                    continue;
                }

                $problems[] = Text::get('proposals.approval_problems.club', ['club' => $club->club->name, 'message' => rtrim($message, '.')]);
            }
        }

        return $problems;
    }

    /**
     * The resource an error field of a club speaks of (DraftEvaluator: tier, led_format, led_seconds,
     * seats, seats.<variant id>); null for the others (club, matches, elements).
     */
    private static function resourceOf(string $field): ?InventoryResource
    {
        return match (true) {
            $field === 'tier' => InventoryResource::Partnership,
            $field === 'led_format', $field === 'led_seconds' => InventoryResource::Pool,
            $field === 'seats', str_starts_with($field, 'seats.') => InventoryResource::Hospitality,
            default => null,
        };
    }

    private static function shortfall(InventoryNeed $need): string
    {
        $values = [
            'club' => $need->club->name,
            'resource' => $need->name,
            'requested' => self::quantity($need->resource, $need->requested),
            'remaining' => self::quantity($need->resource, $need->remaining),
        ];

        return count($need->letters) > 1
            ? Text::get('proposals.approval_problems.shortfall_variants', [...$values, 'letters' => implode(', ', $need->letters)])
            : Text::get('proposals.approval_problems.shortfall', $values);
    }
}
