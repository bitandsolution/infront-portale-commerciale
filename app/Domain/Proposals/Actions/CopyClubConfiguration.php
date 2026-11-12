<?php

namespace App\Domain\Proposals\Actions;

use App\Domain\Proposals\Data\ClubEvaluation;
use App\Domain\Proposals\Data\ClubsCopied;
use App\Domain\Proposals\Data\DraftClubData;
use App\Domain\Proposals\Data\DraftData;
use App\Domain\Proposals\Exceptions\DraftConflict;
use App\Domain\Proposals\Exceptions\ProposalsActionNotAllowed;
use App\Domain\Proposals\Services\DraftEvaluator;
use App\Domain\Settings\Services\Settings;
use App\Models\Proposal;
use App\Models\ProposalVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * "Copia su altri club" (M06 §5, M06-CA7; F4-05): the package, the LED format with its minutes and the
 * hospitality seats of one club of the draft are copied on other clubs of the same draft.
 *
 * - The copy replaces the configuration of every target club: what the source does not have (e.g. no
 *   LED) is taken away from the target too.
 * - Every target is evaluated with the copied configuration by DraftEvaluator, the same evaluation of
 *   the automatic save. An option that cannot be sold on the target (sold out, not managed, without a
 *   price), beyond the residual, or the LED without home matches in the period, is skipped and reported
 *   club by club with the message of its field; for that element the target keeps what it had. A
 *   target that cannot be configured at all (not selectable, not enrolled in a league) is skipped as a
 *   whole. Kept seats that would exceed the maximum per club with the copied ones are taken away.
 * - The draft is then saved by SaveDraft, in the same transaction: lock version, prices calculated by
 *   the server and one audit event proposal.draft_updated with the changed clubs (D-372).
 */
final class CopyClubConfiguration
{
    public function __construct(
        private readonly DraftEvaluator $evaluator,
        private readonly SaveDraft $saveDraft,
        private readonly Settings $settings,
    ) {}

    /**
     * @param  list<int>  $targetClubIds
     */
    public function handle(Proposal $proposal, int $sourceClubId, array $targetClubIds, int $lockVersion, User $actor): ClubsCopied
    {
        return DB::transaction(function () use ($proposal, $sourceClubId, $targetClubIds, $lockVersion, $actor): ClubsCopied {
            /** @var Proposal $locked */
            $locked = Proposal::query()->whereKey($proposal->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isEditable()) {
                throw ProposalsActionNotAllowed::notDraft($locked->number);
            }

            if ($locked->lock_version !== $lockVersion) {
                throw new DraftConflict;
            }

            /** @var ProposalVersion $version */
            $version = $locked->workingVersion()->firstOrFail();
            $saved = [];

            foreach (SaveDraft::fromRows(SaveDraft::rows($version)) as $club) {
                $saved[$club->clubId] = $club;
            }

            $source = $saved[$sourceClubId] ?? null;
            $targets = array_values(array_unique(array_filter($targetClubIds, fn (int $id): bool => $id !== $sourceClubId && isset($saved[$id]))));

            if ($source === null || $targets === []) {
                throw ProposalsActionNotAllowed::nothingToCopy();
            }

            // The draft with the copied configuration on every target, evaluated as a whole.
            $candidates = array_map(
                fn (DraftClubData $club): DraftClubData => in_array($club->clubId, $targets, true) ? self::copy($source, $club->clubId) : $club,
                array_values($saved),
            );
            $evaluation = $this->evaluator->evaluate(
                $locked->season()->first(),
                $version->starts_on?->toDateString(),
                $version->ends_on?->toDateString(),
                $candidates,
            );

            $clubs = [];
            $copied = [];
            $skipped = [];

            foreach (array_values($saved) as $club) {
                $candidate = in_array($club->clubId, $targets, true) ? $evaluation->club($club->clubId) : null;

                if ($candidate === null) {
                    $clubs[] = $club;

                    continue;
                }

                [$merged, $items] = $this->merge($candidate, $club);
                $clubs[] = $merged;

                if (! isset($candidate->errors['club'])) {
                    $copied[] = $candidate->club->name;
                }

                if ($items !== []) {
                    $skipped[] = ['club' => $candidate->club->name, 'items' => $items];
                }
            }

            $result = $this->saveDraft->handle($locked, new DraftData(
                clientId: (int) $locked->client_id,
                seasonId: $locked->season_id,
                startsOn: $version->starts_on?->toDateString(),
                endsOn: $version->ends_on?->toDateString(),
                clubs: $clubs,
            ), $lockVersion, $actor);

            return new ClubsCopied($result->lockVersion, $copied, $skipped, $result->changed);
        });
    }

    private static function copy(DraftClubData $source, int $clubId): DraftClubData
    {
        return new DraftClubData(
            clubId: $clubId,
            tierId: $source->tierId,
            ledFormatId: $source->ledFormatId,
            ledSeconds: $source->ledSeconds,
            seats: $source->seats,
        );
    }

    /**
     * The configuration of a target: the copied elements that are valid, the previous ones for the
     * elements skipped, and the messages of what was skipped.
     *
     * @return array{DraftClubData, list<string>}
     */
    private function merge(ClubEvaluation $candidate, DraftClubData $previous): array
    {
        $errors = $candidate->errors;

        if (isset($errors['club'])) {
            return [$previous, [$errors['club']]];
        }

        $copy = $candidate->data;
        $items = [];

        $tierId = $copy->tierId;
        if (isset($errors['tier'])) {
            $tierId = $previous->tierId;
            $items[] = $errors['tier'];
        }

        $ledFormatId = $copy->ledFormatId;
        $ledSeconds = $copy->ledSeconds;
        $ledErrors = array_values(array_filter([$errors['led_format'] ?? null, $errors['led_seconds'] ?? null, $errors['matches'] ?? null]));
        if ($ledErrors !== []) {
            $ledFormatId = $previous->ledFormatId;
            $ledSeconds = $previous->ledSeconds;
            $items = [...$items, ...$ledErrors];
        }

        $seats = [];
        $kept = [];
        foreach ($copy->seats as $variantId => $count) {
            $field = 'seats.'.$variantId;

            if (isset($errors[$field])) {
                $items[] = $errors[$field];

                if (($previous->seats[$variantId] ?? 0) > 0) {
                    $seats[$variantId] = $previous->seats[$variantId];
                    $kept[] = $variantId;
                }

                continue;
            }

            if ($count > 0) {
                $seats[$variantId] = $count;
            }
        }

        // Seats kept from the target and copied ones together never exceed the maximum per club.
        if (array_sum($seats) > $this->settings->hospitalityMaxSeats()) {
            foreach ($kept as $variantId) {
                unset($seats[$variantId]);
            }
        }

        return [new DraftClubData(
            clubId: $previous->clubId,
            tierId: $tierId,
            ledFormatId: $ledFormatId,
            ledSeconds: $ledFormatId !== null ? $ledSeconds : 0,
            seats: $seats,
        ), $items];
    }
}
