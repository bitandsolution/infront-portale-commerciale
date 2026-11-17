<?php

namespace App\Support\Proposals;

use App\Domain\Proposals\Actions\SaveNewVersion;
use App\Domain\Proposals\Data\AppliedDiscount;
use App\Domain\Proposals\Data\DiscountInput;
use App\Domain\Proposals\Data\DraftEvaluation;
use App\Domain\Proposals\Data\ProposalsAuditValues;
use App\Domain\Proposals\Exceptions\ProposalsActionNotAllowed;
use App\Domain\Proposals\Services\ApprovalCheck;
use App\Domain\Proposals\Services\Discounts;
use App\Domain\Proposals\Services\GroupInventory;
use App\Domain\Settings\Services\Settings;
use App\Enums\DiscountType;
use App\Models\Club;
use App\Models\HospitalityVariant;
use App\Models\LedFormat;
use App\Models\PartnershipTier;
use App\Models\Proposal;
use App\Models\ProposalGroup;
use App\Models\ProposalVersion;
use App\Models\User;
use App\Support\Amounts;
use App\Support\Text;
use Illuminate\Support\Carbon;

/**
 * Versions of an approved variant (M07 F-06, FL-04; D-096, D-466…D-468; F5-04):
 *
 * - versions(): the frozen versions of a variant for the detail of its group, the most recent first,
 *   with author, date, forcing, list total and the main differences from the previous one; admin and
 *   manager also see discount and final total, the agent only the list (D-417);
 * - revision(): the box "Nuova versione" of the configurator while the admin changes an approved
 *   variant: differences from the current version, discount with its preview (calculated here with
 *   Discounts, never by the page), problems of the check on the difference (D-465) and forcing.
 *
 * The values compared are those of SaveNewVersion::values() (period, clubs in one line, list total,
 * discount), so the page says exactly what the save will find changed (D-467).
 */
final class ProposalVersionsPresenter
{
    private const TIMEZONE = 'Europe/Rome';

    /** @var array{tiers: array<string, string>, formats: array<string, string>, variants: array<string, string>}|null */
    private ?array $names = null;

    public function __construct(
        private readonly ApprovalCheck $check,
        private readonly GroupInventory $inventory,
        private readonly Discounts $discounts,
        private readonly Settings $settings,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function versions(Proposal $variant, bool $isReviewer): array
    {
        /** @var list<ProposalVersion> $versions */
        $versions = array_values($variant->versions()->whereNotNull('number')->with('frozenBy')->orderBy('number')->get()->all());
        $entries = [];
        $before = null;

        foreach ($versions as $version) {
            $values = SaveNewVersion::values($variant, $version, SaveNewVersion::rows($version));
            $discount = $this->discounts->apply($version->list_total, $version->discount_type, $version->discount_value);
            $author = $version->frozenBy;
            $isCurrent = $version->id === $variant->current_version_id;

            $entries[] = [
                'id' => $version->id,
                'number' => $version->number,
                'title' => Text::get('ui.proposals.versions.title', ['number' => (string) $version->number]),
                'at' => $version->frozen_at instanceof Carbon ? $version->frozen_at->copy()->setTimezone(self::TIMEZONE)->format('d/m/Y H:i') : null,
                'author' => $author !== null ? $author->first_name.' '.$author->last_name : null,
                'current' => $isCurrent,
                'forced' => $version->is_forced,
                'listTotal' => Amounts::italian($version->list_total),
                'discount' => $isReviewer ? self::discountLabel($discount) : null,
                'finalTotal' => $isReviewer && ! $discount->isNone() ? Amounts::italian($discount->finalTotal) : null,
                'changes' => $before !== null ? $this->differences($before, $values, $isReviewer) : [],
                // The current version is already the summary of the variant; the others open here.
                'draft' => $isCurrent ? null : ProposalsPresenter::frozen($variant, $version),
            ];
            $before = $values;
        }

        return array_reverse($entries);
    }

    /**
     * The box "Nuova versione" of the configurator (D-467): $preview is the discount typed by the admin
     * (query of the page), otherwise the discount of the current version.
     *
     * @return array<string, mixed>
     */
    public function revision(Proposal $proposal, User $actor, ?DiscountInput $preview = null, ?string $previewError = null): array
    {
        /** @var ProposalVersion $current */
        $current = $proposal->currentVersion()->firstOrFail();
        $proposal->loadMissing(['season', 'workingVersion', 'client']);
        $review = $this->check->review([$proposal], $proposal->id, $this->inventory->committedBy($proposal));
        $evaluation = $review->evaluation($proposal);
        $input = $preview ?? new DiscountInput($current->discount_type, $current->discount_value);
        $applied = null;
        $error = $previewError;

        if ($evaluation instanceof DraftEvaluation && $error === null) {
            try {
                $applied = $this->discounts->apply($evaluation->listTotal, $input->type, $input->value);
            } catch (ProposalsActionNotAllowed $exception) {
                $error = $exception->getMessage();
            }
        }

        $changes = [];

        if ($evaluation instanceof DraftEvaluation && $proposal->workingVersion !== null) {
            $after = [
                ...ProposalsAuditValues::draft($proposal, $proposal->workingVersion),
                ...ProposalsAuditValues::clubsOf($evaluation),
                'list_total' => $evaluation->listTotal,
                ...($applied !== null ? SaveNewVersion::discountValues($applied) : []),
            ];
            $changes = $this->differences(SaveNewVersion::values($proposal, $current, SaveNewVersion::rows($current)), $after, true);
        }

        $currentDiscount = $this->discounts->apply($current->list_total, $current->discount_type, $current->discount_value);
        $group = $proposal->proposal_group_id !== null ? ProposalGroup::query()->find($proposal->proposal_group_id) : null;

        return [
            'versionNumber' => $current->number,
            'nextNumber' => ((int) $proposal->versions()->max('number')) + 1,
            'groupUrl' => $group !== null ? route('proposals.groups.show', $group) : null,
            'current' => [
                'listTotal' => Amounts::italian($current->list_total),
                'discount' => self::discountLabel($currentDiscount),
                'finalTotal' => Amounts::italian($currentDiscount->finalTotal),
            ],
            'discountType' => $input->type?->value ?? '',
            'discountValue' => $input->value !== null ? Amounts::italian(Amounts::normalize($input->value)) : '',
            'discountError' => $error,
            'listTotal' => Amounts::italian($evaluation !== null ? $evaluation->listTotal : '0'),
            'preview' => $applied !== null ? [
                'discount' => self::discountLabel($applied),
                'finalTotal' => Amounts::italian($applied->finalTotal),
            ] : null,
            'changes' => $changes,
            'problems' => $review->problems,
            'blocking' => $review->blocking,
            'forceable' => $review->canBeForced(),
            'canForce' => $review->canBeForced() && $actor->can('force', ProposalGroup::class),
            'forceNeeds' => $review->canBeForced() ? array_map(ProposalGroupsPresenter::forceNeed(...), $review->forceable()) : [],
            'forcedReasonMin' => $this->settings->forcedReasonMinLength(),
            'saveUrl' => route('proposals.revision.save', $proposal),
            'discardUrl' => route('proposals.revision.discard', $proposal),
        ];
    }

    /**
     * Main differences between two versions (D-468), in Italian, one line each: period, clubs added,
     * removed or changed, list total and, for admin and manager, discount.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return list<string>
     */
    public function differences(array $before, array $after, bool $withDiscount): array
    {
        $lines = [];

        if (($before['starts_on'] ?? null) !== ($after['starts_on'] ?? null) || ($before['ends_on'] ?? null) !== ($after['ends_on'] ?? null)) {
            $lines[] = Text::get('ui.proposals.versions.change_period', ['from' => self::period($before), 'to' => self::period($after)]);
        }

        $codes = [];

        foreach ([...array_keys($before), ...array_keys($after)] as $key) {
            if (str_starts_with($key, 'club_')) {
                $codes[substr($key, 5)] = true;
            }
        }

        $clubNames = $codes !== [] ? Club::query()->whereIn('code', array_keys($codes))->pluck('name', 'code')->all() : [];

        foreach (array_keys($codes) as $code) {
            $key = ProposalsAuditValues::clubKey((string) $code);
            $old = is_string($before[$key] ?? null) ? $before[$key] : null;
            $new = is_string($after[$key] ?? null) ? $after[$key] : null;
            $club = is_string($clubNames[$code] ?? null) ? $clubNames[$code] : (string) $code;

            if ($old === $new) {
                continue;
            }

            $lines[] = match (true) {
                $old === null && $new !== null => Text::get('ui.proposals.versions.club_added', ['club' => $club, 'config' => $this->readable($new)]),
                $new === null => Text::get('ui.proposals.versions.club_removed', ['club' => $club]),
                default => Text::get('ui.proposals.versions.club_changed', ['club' => $club, 'from' => $this->readable((string) $old), 'to' => $this->readable($new)]),
            };
        }

        if (array_key_exists('list_total', $after) && self::amount($before['list_total'] ?? null) !== self::amount($after['list_total'])) {
            $lines[] = Text::get('ui.proposals.versions.change_list_total', [
                'from' => Amounts::italian(self::amount($before['list_total'] ?? null)),
                'to' => Amounts::italian(self::amount($after['list_total'])),
            ]);
        }

        if ($withDiscount && array_key_exists('discount_type', $after)
            && (($before['discount_type'] ?? null) !== $after['discount_type'] || ($before['discount_value'] ?? null) !== ($after['discount_value'] ?? null))) {
            $lines[] = Text::get('ui.proposals.versions.change_discount', [
                'from' => self::discountText($before['discount_type'] ?? null, $before['discount_value'] ?? null),
                'to' => self::discountText($after['discount_type'], $after['discount_value'] ?? null),
            ]);
        }

        return $lines;
    }

    /**
     * "TOP | FILA_2 1′00″ | PREMIUM 2" (ProposalsAuditValues::club) with the names of the catalogue:
     * "Top · 2ª fila 1′00″ · Premium 2".
     */
    private function readable(string $line): string
    {
        $names = $this->names();
        $parts = explode(' | ', $line);
        $tier = $parts[0] ?? '—';
        $led = $parts[1] ?? '—';
        $seats = $parts[2] ?? '—';
        $text = [];

        if ($tier !== '—') {
            $text[] = $names['tiers'][$tier] ?? $tier;
        }

        if ($led !== '—') {
            [$format, $duration] = array_pad(explode(' ', $led, 2), 2, '');
            $text[] = trim(($names['formats'][$format] ?? $format).' '.$duration);
        }

        if ($seats !== '—') {
            $list = [];

            foreach (explode(', ', $seats) as $item) {
                [$variant, $count] = array_pad(explode(' ', $item, 2), 2, '');
                $list[] = Text::get('ui.proposals.versions.seats', ['variant' => $names['variants'][$variant] ?? $variant, 'count' => $count]);
            }

            $text[] = implode(', ', $list);
        }

        return $text !== [] ? implode(' · ', $text) : Text::get('ui.proposals.versions.nothing');
    }

    /**
     * @return array{tiers: array<string, string>, formats: array<string, string>, variants: array<string, string>}
     */
    private function names(): array
    {
        if ($this->names !== null) {
            return $this->names;
        }

        /** @var array<string, string> $tiers */
        $tiers = PartnershipTier::query()->pluck('name', 'code')->all();
        /** @var array<string, string> $formats */
        $formats = LedFormat::query()->pluck('name', 'code')->all();
        /** @var array<string, string> $variants */
        $variants = HospitalityVariant::query()->pluck('name', 'code')->all();

        return $this->names = ['tiers' => $tiers, 'formats' => $formats, 'variants' => $variants];
    }

    private static function discountLabel(AppliedDiscount $discount): string
    {
        return self::discountText($discount->type?->value, $discount->isNone() ? null : $discount->value);
    }

    private static function discountText(mixed $type, mixed $value): string
    {
        if (! is_string($type) || ! is_string($value)) {
            return Text::get('ui.proposals.versions.no_discount');
        }

        return DiscountType::tryFrom($type) === DiscountType::Percent
            ? Text::get('ui.proposals.versions.discount_percent', ['value' => Amounts::italian(Amounts::normalize($value))])
            : Text::get('ui.proposals.versions.discount_amount', ['value' => Amounts::italian(Amounts::normalize($value))]);
    }

    private static function amount(mixed $value): string
    {
        return Amounts::normalize(is_string($value) || is_int($value) ? (string) $value : '0');
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private static function period(array $values): string
    {
        return self::date($values['starts_on'] ?? null).' – '.self::date($values['ends_on'] ?? null);
    }

    private static function date(mixed $value): string
    {
        return is_string($value) ? Carbon::parse($value)->format('d/m/Y') : '—';
    }
}
