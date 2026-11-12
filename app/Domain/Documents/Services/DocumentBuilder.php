<?php

namespace App\Domain\Documents\Services;

use App\Domain\Content\Data\ComposedSlide;
use App\Domain\Content\Data\CompositionInput;
use App\Domain\Content\Data\SlideInstance;
use App\Domain\Content\Services\DocumentComposer;
use App\Domain\Documents\Data\BuiltDocument;
use App\Domain\Documents\Data\DocumentPage;
use App\Domain\Documents\Data\NetworkRecap;
use App\Domain\Documents\Data\PdfMetadata;
use App\Domain\Documents\Data\RecapRow;
use App\Domain\Documents\Data\RecapTotals;
use App\Domain\Documents\Data\StaticSlidePage;
use App\Domain\Documents\Exceptions\DocumentNotComposable;
use App\Domain\Documents\Layouts\CoverLayout;
use App\Domain\Documents\Layouts\NetworkRecapLayout;
use App\Domain\Documents\Layouts\RecapStyle;
use App\Enums\DiscountType;
use App\Enums\DocumentLayout;
use App\Enums\DocumentType;
use App\Enums\ProposalStatus;
use App\Enums\SlideKind;
use App\Models\Club;
use App\Models\Proposal;
use App\Models\ProposalGroup;
use App\Models\ProposalVersion;
use App\Models\ProposalVersionClub;
use App\Models\ProposalVersionClubSeat;
use App\Models\Season;
use App\Models\StoredFile;
use App\Support\Amounts;
use App\Support\Documents\DocumentNumbers;
use App\Support\Text;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Composes the document of a group of variants (M09 §6; D-111, D-396, D-403…D-405, D-413…D-416;
 * DM-04) from the frozen versions of its approved variants: after the choice of the client only the
 * chosen one is approved, so a regeneration contains only that variant (D-414).
 *
 * - Slides: DocumentComposer on those versions (one implementation of the composition), only those
 *   included by the choice frozen with the versions (mode and excluded slides, D-418, D-419).
 * - Cover: the logo of the client frozen on the first version, the date of its approval (D-252).
 * - Recap: one per variant, "Proposta A" above the table when there are two or more variants; the
 *   network grid for the network document, the same grid in the colour of the club for the club
 *   document (D-415); the values are the frozen ones, never recalculated (D-198).
 * - Static slides: their image. The LED simulation is DM-06: skipped and listed in the page model.
 *
 * A missing cover or recap, or a background without its image, stops the generation with a clear
 * message (DocumentNotComposable, D-416).
 */
final class DocumentBuilder
{
    /** D-093: one spot every 15 seconds. */
    private const SPOT_SECONDS = 15;

    /** C7: system times are shown in the time of Rome. */
    private const TIMEZONE = 'Europe/Rome';

    public function __construct(private readonly DocumentComposer $composer) {}

    public function build(ProposalGroup $group): BuiltDocument
    {
        $variants = Proposal::query()
            ->where('proposal_group_id', $group->id)
            ->where('status', ProposalStatus::Approved->value)
            ->whereNotNull('current_version_id')
            ->orderBy('variant_letter')
            ->with(['season', 'currentVersion'])
            ->get();

        /** @var array<string, ProposalVersion> $versions */
        $versions = [];

        foreach ($variants as $variant) {
            if ($variant->currentVersion !== null) {
                $versions[(string) $variant->variant_letter] = $variant->currentVersion;
            }
        }

        /** @var Proposal|null $first */
        $first = $variants->first();
        $season = $first?->season;

        if ($first === null || $versions === [] || ! $season instanceof Season) {
            throw DocumentNotComposable::noApprovedVariants();
        }

        /** @var ProposalVersion $firstVersion */
        $firstVersion = $versions[array_key_first($versions)];
        $composition = $this->composer->compose(CompositionInput::fromVersions($season, $versions));
        $type = $composition->documentType;

        if ($type === null) {
            throw DocumentNotComposable::noApprovedVariants();
        }

        $this->ensureMandatorySlides($composition->slides, $type, $season);

        $rows = $this->clubRows(array_values($versions));
        $pages = [];
        $model = [];
        $skipped = [];

        foreach ($composition->includedSlides() as $composed) {
            foreach ($composed->instances as $instance) {
                $built = $this->pages($composed, $instance, $type, $versions, $rows, $firstVersion);

                if ($built === null) {
                    $skipped[] = ['slide_id' => $composed->slide->id, 'layout' => $composed->slide->layout_code?->value, 'club_id' => $instance->clubId];

                    continue;
                }

                array_push($pages, ...$built);
                $model[] = [
                    'slide_id' => $composed->slide->id,
                    'title' => $composed->slide->title,
                    'layout' => $composed->slide->layout_code?->value,
                    'variant' => $instance->variantLetter,
                    'club_id' => $instance->clubId,
                    'pages' => count($built),
                ];
            }
        }

        $clientName = (string) ($firstVersion->client_name ?? '');

        return new BuiltDocument(
            type: $type,
            firstProposal: $first,
            firstVersion: $firstVersion,
            pages: $pages,
            metadata: new PdfMetadata(
                Text::get('documents.metadata.title', ['client' => $clientName]),
                Text::get('documents.metadata.subject', ['season' => $season->code]),
            ),
            fileName: self::fileName($clientName, $type, $rows, $season, $firstVersion),
            pageModel: [
                'variants' => array_map(fn (string $letter, ProposalVersion $version): array => [
                    'letter' => $letter,
                    'proposal_id' => $version->proposal_id,
                    'version_id' => $version->id,
                    'version' => $version->number,
                ], array_keys($versions), array_values($versions)),
                'document_mode' => $composition->selection->mode->value,
                'excluded_slide_ids' => array_values(array_map(
                    fn (ComposedSlide $composed): int => $composed->slide->id,
                    array_filter($composition->slides, fn (ComposedSlide $composed): bool => ! $composed->included),
                )),
                'slides' => $model,
                'skipped' => $skipped,
                'warnings' => $composition->warnings,
            ],
            kpiSnapshot: self::kpis($versions),
        );
    }

    /**
     * D-416: without cover or recap the document is not generated.
     *
     * @param  list<ComposedSlide>  $slides
     */
    private function ensureMandatorySlides(array $slides, DocumentType $type, Season $season): void
    {
        $layouts = array_map(fn (ComposedSlide $slide): ?DocumentLayout => $slide->slide->layout_code, $slides);
        $recap = $type === DocumentType::Network ? DocumentLayout::NetworkRecap : DocumentLayout::ClubRecap;
        $document = mb_strtolower($type->label());
        $missing = [];

        if (! in_array(DocumentLayout::Cover, $layouts, true)) {
            $missing[] = Text::get('content.composition.missing_cover', ['document' => $document]);
        }

        if (! in_array($recap, $layouts, true)) {
            $missing[] = Text::get('content.composition.missing_recap', ['document' => $document]);
        }

        if ($missing !== []) {
            throw DocumentNotComposable::missingSlides($missing, $season->code);
        }
    }

    /**
     * The pages of one instance of a slide; null for a layout not built yet (LED simulation, DM-06).
     *
     * @param  array<string, ProposalVersion>  $versions
     * @param  array<int, list<ProposalVersionClub>>  $rows  version id → club rows
     * @return list<DocumentPage>|null
     */
    private function pages(ComposedSlide $composed, SlideInstance $instance, DocumentType $type, array $versions, array $rows, ProposalVersion $firstVersion): ?array
    {
        $slide = $composed->slide;

        if ($slide->kind === SlideKind::Static || $slide->layout_code === null) {
            return [new StaticSlidePage($this->slideImage($slide->image_file_id, $slide->title))];
        }

        if ($slide->layout_code === DocumentLayout::LedMockup) {
            return null;
        }

        $background = $this->slideImage($slide->image_file_id, $slide->title);

        if ($slide->layout_code === DocumentLayout::Cover) {
            return [CoverLayout::page(
                $background,
                $type,
                $firstVersion->frozen_at?->copy()->setTimezone(self::TIMEZONE)->format('d/m/Y') ?? '',
                $this->filePath($firstVersion->client_logo_file_id),
            )];
        }

        $version = $versions[(string) $instance->variantLetter] ?? null;

        if ($version === null) {
            return [];
        }

        $title = count($versions) > 1 ? Text::get('documents.recap.variant_title', ['letter' => (string) $instance->variantLetter]) : null;
        $clubRows = $rows[$version->id] ?? [];

        if ($slide->layout_code === DocumentLayout::ClubRecap) {
            $club = $clubRows[0]->club ?? null;

            return NetworkRecapLayout::pages(
                $this->clubRecap($version, $clubRows),
                $background,
                RecapStyle::club($club?->primary_color, (string) $club?->name),
                $title,
            );
        }

        return NetworkRecapLayout::pages($this->networkRecap($version, $clubRows), $background, RecapStyle::network(), $title);
    }

    /**
     * Network recap of a frozen version: a row per club (name, logo, "TOP PARTNER · 1ª fila"), a row
     * per hospitality variant under its club (layout-dinamici §4.8).
     *
     * @param  list<ProposalVersionClub>  $clubRows
     */
    private function networkRecap(ProposalVersion $version, array $clubRows): NetworkRecap
    {
        $rows = [];

        foreach ($clubRows as $row) {
            $seconds = self::ledSeconds($row);
            $rows[] = new RecapRow(
                label: $row->club->name,
                logoPath: $this->filePath($row->club->logo_file_id),
                format: self::format($row),
                matches: $seconds !== null ? (int) $row->home_matches : null,
                secondsPerMatch: $seconds,
                spotsPerMatch: $seconds !== null ? intdiv($seconds, self::SPOT_SECONDS) : null,
                listPrice: (string) ($row->club_total ?? '0.00'),
            );
            array_push($rows, ...self::hospitalityRows($row));
        }

        return new NetworkRecap($rows, self::totals($version, $clubRows));
    }

    /**
     * Club recap of a frozen version (D-415): partnership, LED and hospitality rows of the club, only
     * those present; no price per row, the merged cell carries the total.
     *
     * @param  list<ProposalVersionClub>  $clubRows
     */
    private function clubRecap(ProposalVersion $version, array $clubRows): NetworkRecap
    {
        $rows = [];

        foreach ($clubRows as $row) {
            $tier = $row->partnershipTier;

            if ($tier !== null && ! $tier->is_free_package) {
                $rows[] = new RecapRow(
                    label: Text::get('documents.recap.partnership', ['tier' => $tier->name]),
                    logoPath: null,
                    format: Text::get('documents.network_recap.none'),
                    matches: null,
                    secondsPerMatch: null,
                    spotsPerMatch: null,
                    listPrice: (string) ($row->partnership_price ?? '0.00'),
                );
            }

            $seconds = self::ledSeconds($row);

            if ($seconds !== null && $row->ledFormat !== null) {
                $rows[] = new RecapRow(
                    label: Text::get('documents.recap.led'),
                    logoPath: null,
                    format: $row->ledFormat->name,
                    matches: (int) $row->home_matches,
                    secondsPerMatch: $seconds,
                    spotsPerMatch: intdiv($seconds, self::SPOT_SECONDS),
                    listPrice: (string) ($row->led_price ?? '0.00'),
                );
            }

            array_push($rows, ...self::hospitalityRows($row));
        }

        return new NetworkRecap($rows, self::totals($version, $clubRows));
    }

    private static function ledSeconds(ProposalVersionClub $row): ?int
    {
        $seconds = (int) ($row->led_seconds_per_match ?? 0);

        return $row->led_format_id !== null && $seconds > 0 ? $seconds : null;
    }

    /**
     * "{LIVELLO} PARTNER · {format}" with a partnership, the format alone with the Libera, "—" with
     * neither (layout-dinamici §4.8).
     */
    private static function format(ProposalVersionClub $row): string
    {
        $parts = [];
        $tier = $row->partnershipTier;

        if ($tier !== null && ! $tier->is_free_package) {
            $parts[] = mb_strtoupper(Text::get('documents.recap.partnership', ['tier' => $tier->name]));
        }

        if (self::ledSeconds($row) !== null && $row->ledFormat !== null) {
            $parts[] = $row->ledFormat->name;
        }

        return $parts === [] ? Text::get('documents.network_recap.none') : implode(' · ', $parts);
    }

    /**
     * @return list<RecapRow>
     */
    private static function hospitalityRows(ProposalVersionClub $row): array
    {
        $rows = [];

        foreach ($row->seats as $seat) {
            /** @var ProposalVersionClubSeat $seat */
            if ($seat->seats <= 0) {
                continue;
            }

            $rows[] = new RecapRow(
                label: Text::get($seat->seats === 1 ? 'documents.recap.hospitality_one' : 'documents.recap.hospitality', ['variant' => $seat->variant->name, 'seats' => (string) $seat->seats]),
                logoPath: null,
                format: '',
                matches: null,
                secondsPerMatch: null,
                spotsPerMatch: null,
                listPrice: (string) ($seat->price ?? '0.00'),
                isHospitality: true,
            );
        }

        return $rows;
    }

    /**
     * Totals of the LED (matches, seconds and spots of the season) and the frozen amounts.
     *
     * @param  list<ProposalVersionClub>  $clubRows
     */
    private static function totals(ProposalVersion $version, array $clubRows): RecapTotals
    {
        $matches = 0;
        $seconds = 0;
        $spots = 0;

        foreach ($clubRows as $row) {
            $perMatch = self::ledSeconds($row);

            if ($perMatch === null) {
                continue;
            }

            $count = (int) $row->home_matches;
            $matches += $count;
            $seconds += $perMatch * $count;
            $spots += intdiv($perMatch, self::SPOT_SECONDS) * $count;
        }

        $label = match ($version->discount_type) {
            DiscountType::Percent => Text::get('documents.network_recap.discount_percent', ['value' => Amounts::italian((string) $version->discount_value)]),
            DiscountType::Amount => Text::get('documents.network_recap.discount_amount', ['value' => DocumentNumbers::amount((string) $version->discount_amount)]),
            null => null,
        };

        return new RecapTotals(
            $matches,
            $seconds,
            $spots,
            $version->list_total,
            $label,
            $version->final_total ?? $version->list_total,
        );
    }

    /**
     * The club rows of every version, with what the recaps read.
     *
     * @param  list<ProposalVersion>  $versions
     * @return array<int, list<ProposalVersionClub>>
     */
    private function clubRows(array $versions): array
    {
        $rows = [];

        foreach ($versions as $version) {
            $rows[$version->id] = array_values($version->clubs()
                ->with(['club', 'partnershipTier', 'ledFormat', 'seats.variant'])
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->all());
        }

        return $rows;
    }

    /**
     * KPI of every variant as frozen on its version (D-111): the document keeps them even if the
     * data change later.
     *
     * @param  array<string, ProposalVersion>  $versions
     * @return array<string, mixed>
     */
    private static function kpis(array $versions): array
    {
        $snapshot = [];

        foreach ($versions as $letter => $version) {
            $snapshot[$letter] = [
                'campaign_starts_on' => $version->campaign_starts_on?->toDateString(),
                'campaign_months' => $version->campaign_months,
                'total_matches' => $version->total_matches,
                'total_spots' => $version->total_spots,
                'top_matches' => $version->top_matches,
                'list_total' => $version->list_total,
                'final_total' => $version->final_total,
            ];
        }

        return $snapshot;
    }

    /**
     * M09 RB-09: Infront_Proposta_[Cliente]_[Club o Network]_[Stagione]_v[n].pdf.
     *
     * @param  array<int, list<ProposalVersionClub>>  $rows
     */
    private static function fileName(string $client, DocumentType $type, array $rows, Season $season, ProposalVersion $version): string
    {
        $club = null;

        foreach ($rows as $versionRows) {
            $club ??= $versionRows[0]->club ?? null;
        }

        $target = $type === DocumentType::Club && $club instanceof Club ? $club->short_name : 'Network';
        $part = fn (string $value): string => trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', Str::ascii($value)), '-');

        return implode('_', array_filter([
            'Infront',
            'Proposta',
            $part($client),
            $part($target),
            $part($season->code),
            'v'.($version->number ?? 1),
        ], fn (string $value): bool => $value !== '')).'.pdf';
    }

    private function slideImage(?int $fileId, string $title): string
    {
        if ($fileId === null) {
            throw DocumentNotComposable::slideWithoutImage($title);
        }

        return $this->filePath($fileId) ?? throw DocumentNotComposable::fileMissing($title);
    }

    /**
     * Absolute path of a stored file on the private disk, null without a file.
     */
    private function filePath(?int $fileId): ?string
    {
        if ($fileId === null) {
            return null;
        }

        $file = StoredFile::query()->find($fileId);

        if ($file === null) {
            return null;
        }

        $path = Storage::disk(StoredFile::DISK)->path($file->path);

        return is_file($path) ? $path : null;
    }
}
