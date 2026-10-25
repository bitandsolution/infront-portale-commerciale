<?php

namespace App\Domain\Sports\Actions;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Domain\Sports\Exceptions\SportsActionNotAllowed;
use App\Enums\AliasSource;
use App\Enums\AuditAction;
use App\Models\Club;
use App\Models\ClubAlias;
use App\Models\User;
use App\Support\Sports\NameNormalizer;
use Illuminate\Support\Facades\DB;

/**
 * New alias of a club (F-11, D-185): typed by the admin in the club page, or saved while resolving
 * an unknown name in a calendar import (FL-02 step 3, F1-04, also by the manager, D-321).
 *
 * The normalized alias never points to two clubs and never repeats the name or the short name of a
 * club (it would make the recognition ambiguous). Audit club_alias.created on the club.
 * A null author is the system (server command of F1-06).
 */
final class AddClubAlias
{
    public function __construct(private readonly AuditLog $audit) {}

    public function handle(Club $club, string $alias, AliasSource $source, ?User $actor): ClubAlias
    {
        $alias = trim($alias);
        $normalized = NameNormalizer::normalize($alias);

        if ($normalized === '') {
            throw SportsActionNotAllowed::aliasEmpty();
        }

        return DB::transaction(function () use ($club, $alias, $normalized, $source, $actor): ClubAlias {
            $owner = $this->owner($club->discipline_id, $normalized);

            if ($owner !== null) {
                throw SportsActionNotAllowed::aliasTaken($alias, $owner->name);
            }

            $clubAlias = ClubAlias::query()->create([
                'club_id' => $club->id,
                'discipline_id' => $club->discipline_id,
                'alias' => mb_substr($alias, 0, 150),
                'source' => $source,
            ]);

            $this->audit->record(
                AuditAction::ClubAliasCreated,
                $actor,
                AuditSubject::club($club),
                [],
                ['alias' => $clubAlias->alias, 'source' => $source->value],
            );

            return $clubAlias;
        });
    }

    /**
     * Club that already answers to this normalized name: by an alias, a name or a short name.
     */
    private function owner(int $disciplineId, string $normalized): ?Club
    {
        $alias = ClubAlias::query()
            ->where('discipline_id', $disciplineId)
            ->where('alias_normalized', $normalized)
            ->lockForUpdate()
            ->first();

        if ($alias !== null) {
            return $alias->club;
        }

        return Club::query()
            ->where('discipline_id', $disciplineId)
            ->get(['id', 'name', 'short_name'])
            ->first(fn (Club $club): bool => NameNormalizer::normalize($club->name) === $normalized
                || NameNormalizer::normalize($club->short_name) === $normalized);
    }
}
