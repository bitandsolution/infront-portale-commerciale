<?php

namespace App\Domain\Sports\Actions;

use App\Domain\Audit\Data\AuditSubject;
use App\Domain\Audit\Services\AuditLog;
use App\Enums\AuditAction;
use App\Models\ClubAlias;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Removal of a wrong alias (F-11): admin only. An alias is a pure child of the club (C3): it is
 * deleted, with an audit event club_alias.removed that keeps the text. Matches already imported
 * keep their clubs.
 */
final class RemoveClubAlias
{
    public function __construct(private readonly AuditLog $audit) {}

    public function handle(ClubAlias $alias, User $actor): void
    {
        DB::transaction(function () use ($alias, $actor): void {
            $club = $alias->club;
            $values = ['alias' => $alias->alias, 'source' => $alias->source->value];

            $alias->delete();

            $this->audit->record(AuditAction::ClubAliasRemoved, $actor, AuditSubject::club($club), $values, []);
        });
    }
}
