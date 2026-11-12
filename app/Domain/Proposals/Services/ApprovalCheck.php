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
        $problems = [];
        $season = null;

        foreach ($list as $variant) {
            $letter = $many ? (string) $variant->variant_letter : '';
            $evaluation = $this->evaluator->evaluateProposal($variant);
            $evaluations[$variant->id] = $evaluation;
            $season ??= $variant->season;
            $version = $variant->workingVersion;

            if ($evaluation === null || $version === null || $version->starts_on === null || $version->ends_on === null) {
                $problems[] = Text::get('proposals.approval_problems.period', ['letter' => (string) $variant->variant_letter]);

                continue;
            }

            foreach ($this->variantProblems($evaluation) as $problem) {
                $problems[] = $many
                    ? Text::get('proposals.approval_problems.variant', ['letter' => $letter, 'problem' => $problem])
                    : $problem;
            }

            $byLetter[$letter] = $evaluation;
        }

        $needs = $season instanceof Season ? $this->inventory->needs($season, $byLetter) : [];

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
     * @return list<string>
     */
    private function variantProblems(DraftEvaluation $evaluation): array
    {
        $problems = [];

        if ($evaluation->clubs === []) {
            $problems[] = Text::get('proposals.approval_problems.no_clubs');
        }

        foreach ($evaluation->clubs as $club) {
            foreach ($club->errors as $message) {
                $problems[] = Text::get('proposals.approval_problems.club', ['club' => $club->club->name, 'message' => rtrim($message, '.')]);
            }
        }

        return $problems;
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
