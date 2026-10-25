<?php

namespace App\Http\Controllers\Sports;

use App\Domain\Sports\Actions\ConfirmCalendarImport;
use App\Domain\Sports\Actions\DiscardCalendarImport;
use App\Domain\Sports\Actions\MapCalendarColumns;
use App\Domain\Sports\Actions\RefreshCalendarPreview;
use App\Domain\Sports\Actions\ResolveCalendarName;
use App\Domain\Sports\Actions\UploadCalendar;
use App\Domain\Sports\Data\CalendarPreview;
use App\Domain\Sports\Services\CurrentSeason;
use App\Enums\Ability;
use App\Enums\CalendarColumn;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sports\CalendarColumnsRequest;
use App\Http\Requests\Sports\CalendarNameRequest;
use App\Http\Requests\Sports\CalendarUploadRequest;
use App\Models\CalendarImport;
use App\Models\Club;
use App\Models\User;
use App\Support\Navigation\LayoutProps;
use App\Support\Navigation\PortalPages;
use App\Support\Sports\SportsPresenter;
use App\Support\Text;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Calendar import (M02 F-09, F-10, FL-02; D-137: admin and manager, M02-CA8): upload of the file for
 * a competition of the season of the top bar, preview with errors and warnings, columns chosen by
 * hand, unknown names associated with clubs as aliases, recalculation, confirmation and summary
 * (D-319, D-321, D-322, D-325). The rules are in the Actions and in CalendarAnalyzer.
 */
class CalendarImportController extends Controller
{
    public function index(Request $request, CurrentSeason $currentSeason): Response
    {
        Gate::authorize('viewAny', CalendarImport::class);

        $season = $currentSeason->get();
        $imports = [];

        if ($season !== null) {
            $imports = CalendarImport::query()
                ->whereHas('competitionSeason', fn ($query) => $query->where('season_id', $season->id))
                ->with(['competitionSeason.competition', 'competitionSeason.season', 'file', 'uploader'])
                ->latest('id')
                ->limit(30)
                ->get()
                ->map(fn (CalendarImport $import): array => SportsPresenter::importRow($import))
                ->values()
                ->all();
        }

        $edition = $request->integer('edizione');

        return Inertia::render('Sports/Imports/Index', [
            'layout' => LayoutProps::for($this->actor($request), 'sports_import'),
            'season' => $season !== null ? ['code' => $season->code, 'readOnly' => $season->isReadOnly()] : null,
            'editions' => $season !== null ? SportsPresenter::editionOptions($season) : [],
            'selectedEdition' => $edition > 0 ? (string) $edition : '',
            'imports' => $imports,
            'uploadUrl' => route('sports.imports.store'),
        ]);
    }

    public function store(CalendarUploadRequest $request, UploadCalendar $upload): RedirectResponse
    {
        $import = $upload->handle($request->edition(), $request->calendarFile(), $this->actor($request));

        return redirect()->route('sports.imports.show', $import);
    }

    public function show(Request $request, CalendarImport $calendarImport): Response
    {
        Gate::authorize('view', $calendarImport);

        $calendarImport->load(['competitionSeason.competition', 'competitionSeason.season', 'file', 'uploader']);
        $preview = $calendarImport->previewData();
        $edition = $calendarImport->competitionSeason;
        $user = $this->actor($request);

        return Inertia::render('Sports/Imports/Show', [
            'layout' => LayoutProps::for($user, 'sports_import'),
            'calendarImport' => SportsPresenter::importRow($calendarImport),
            'readOnly' => ! $calendarImport->isOpen() || $edition->season->isReadOnly(),
            'counts' => $preview->counts(),
            'rows' => array_map(fn (array $row): array => SportsPresenter::importPreviewRow($row), $preview->rows),
            'unknownNames' => $preview->unknownNames,
            'missingMatches' => $preview->missingMatches,
            'columns' => $this->columnFields($preview),
            'missingColumns' => $preview->missingColumns,
            'clubs' => $calendarImport->isOpen() ? $this->clubOptions($edition->season->discipline_id) : [],
            'enrollmentsUrl' => Ability::SportsStructureManage->allows($user->role) ? route('sports.editions.show', $edition) : null,
            'matchesUrl' => PortalPages::url('sports_matches').'?edizione='.$edition->id,
            'indexUrl' => PortalPages::url('sports_import'),
            'columnsUrl' => route('sports.imports.columns', $calendarImport),
            'resolveUrl' => route('sports.imports.resolve', $calendarImport),
            'refreshUrl' => route('sports.imports.refresh', $calendarImport),
            'confirmUrl' => route('sports.imports.confirm', $calendarImport),
            'discardUrl' => route('sports.imports.discard', $calendarImport),
        ]);
    }

    public function columns(CalendarColumnsRequest $request, CalendarImport $calendarImport, MapCalendarColumns $mapColumns): RedirectResponse
    {
        $mapColumns->handle($calendarImport, $request->columns());

        return back()->with('toast', Toast::success(Text::get('sports.toasts.import_columns_saved')));
    }

    public function resolve(CalendarNameRequest $request, CalendarImport $calendarImport, ResolveCalendarName $resolve): RedirectResponse
    {
        $club = $request->club();
        $alias = $resolve->handle($calendarImport, $request->string('name')->toString(), $club, $this->actor($request));

        return back()->with('toast', Toast::success(Text::get('sports.toasts.import_name_resolved', ['name' => $alias->alias, 'club' => $club->name])));
    }

    public function refresh(Request $request, CalendarImport $calendarImport, RefreshCalendarPreview $refresh): RedirectResponse
    {
        Gate::authorize('update', $calendarImport);

        $refresh->handle($calendarImport);

        return back()->with('toast', Toast::success(Text::get('sports.toasts.import_refreshed')));
    }

    public function confirm(Request $request, CalendarImport $calendarImport, ConfirmCalendarImport $confirm): RedirectResponse
    {
        Gate::authorize('update', $calendarImport);

        $import = $confirm->handle($calendarImport, $this->actor($request));

        return back()->with('toast', Toast::success(Text::get('sports.toasts.import_confirmed', [
            'inserted' => (string) $import->inserted_count,
            'updated' => (string) $import->updated_count,
            'discarded' => (string) $import->discarded_count,
        ])));
    }

    public function discard(Request $request, CalendarImport $calendarImport, DiscardCalendarImport $discard): RedirectResponse
    {
        Gate::authorize('update', $calendarImport);

        $discard->handle($calendarImport);

        return redirect(PortalPages::url('sports_import'))->with('toast', Toast::success(Text::get('sports.toasts.import_discarded')));
    }

    /**
     * Fields of the layout with the column of the file they read, and the columns of the file.
     *
     * @return array{fields: list<array{field: string, label: string, required: bool, column: string}>, headers: list<array{value: string, label: string}>}
     */
    private function columnFields(CalendarPreview $preview): array
    {
        $fields = array_map(fn (CalendarColumn $column): array => [
            'field' => $column->value,
            'label' => $column->label(),
            'required' => $column->isRequired(),
            'column' => isset($preview->mapping[$column->value]) ? (string) $preview->mapping[$column->value] : '',
        ], CalendarColumn::cases());

        $headers = [];

        foreach ($preview->headers as $index => $header) {
            $headers[] = [
                'value' => (string) $index,
                'label' => Text::get('sports.import.column_option', ['letter' => self::letter($index), 'header' => $header !== '' ? $header : '—']),
            ];
        }

        return ['fields' => $fields, 'headers' => $headers];
    }

    /**
     * Excel letter of a column: 0 → A, 26 → AA.
     */
    private static function letter(int $index): string
    {
        $letter = '';

        for ($index++; $index > 0; $index = intdiv($index - 1, 26)) {
            $letter = chr(65 + (($index - 1) % 26)).$letter;
        }

        return $letter;
    }

    /**
     * Clubs a name can be associated with: the active clubs of the discipline (D-325).
     *
     * @return list<array{value: string, label: string}>
     */
    private function clubOptions(int $disciplineId): array
    {
        return Club::query()
            ->where('discipline_id', $disciplineId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Club $club): array => ['value' => (string) $club->id, 'label' => $club->name])
            ->values()
            ->all();
    }

    private function actor(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
