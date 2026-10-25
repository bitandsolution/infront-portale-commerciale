<?php

namespace App\Domain\Sports\Actions;

use App\Domain\Sports\Exceptions\SportsActionNotAllowed;
use App\Enums\AliasSource;
use App\Models\CalendarImport;
use App\Models\Club;
use App\Models\ClubAlias;
use App\Models\User;
use App\Support\Sports\NameNormalizer;
use Illuminate\Support\Facades\DB;

/**
 * A team name of the file that no club recognizes is associated with an existing club (FL-02
 * step 3, F-11; D-325): the name is saved as an alias with origin "import" (AddClubAlias, with
 * its rules and audit event; also by the manager, D-321) and the preview is rebuilt, so the next
 * imports recognize it by themselves (M02-CA2). New clubs are created by the admin in the Clubs
 * page, never from the import (D-137, D-325).
 */
final class ResolveCalendarName
{
    public function __construct(
        private readonly AddClubAlias $addAlias,
        private readonly RefreshCalendarPreview $preview,
    ) {}

    public function handle(CalendarImport $import, string $name, Club $club, User $actor): ClubAlias
    {
        return DB::transaction(function () use ($import, $name, $club, $actor): ClubAlias {
            $import = CalendarImport::query()->with('competitionSeason.season')->lockForUpdate()->findOrFail($import->id);

            RefreshCalendarPreview::ensureOpen($import);

            if (! $import->previewData()->isUnknownName(NameNormalizer::normalize($name))) {
                throw SportsActionNotAllowed::importNameNotUnknown($name);
            }

            $alias = $this->addAlias->handle($club, $name, AliasSource::Import, $actor);
            $this->preview->handle($import);

            return $alias;
        });
    }
}
