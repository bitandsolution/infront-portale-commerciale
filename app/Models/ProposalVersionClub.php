<?php

namespace App\Models;

use App\Domain\Proposals\Exceptions\ProposalImmutable;
use App\Enums\MatchesSource;
use Database\Factories\ProposalVersionClubFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Club row of a version (M06 §5, §10; D-087, D-088, D-198): package, one LED format with its seconds
 * per match, home matches and their source, the list prices applied and the club price. The prices
 * and the matches are always computed by the server (PriceCalculator, MatchCounter; CLAUDE.md §4).
 * Immutable when its version is frozen (model and triggers).
 *
 * @property int $id
 * @property int $proposal_version_id
 * @property int $club_id
 * @property int|null $competition_season_id
 * @property int $sort_order
 * @property int $territory_id
 * @property int $visibility_type_id
 * @property int|null $partnership_tier_id
 * @property int|null $led_format_id
 * @property int|null $led_seconds_per_match
 * @property int|null $home_matches
 * @property MatchesSource|null $matches_source
 * @property int|null $price_list_id
 * @property string|null $led_unit_price
 * @property string|null $led_price
 * @property string|null $partnership_price
 * @property string|null $hospitality_price
 * @property string|null $club_total
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read ProposalVersion $version
 * @property-read Club $club
 * @property-read CompetitionSeason|null $edition
 * @property-read PartnershipTier|null $partnershipTier
 * @property-read LedFormat|null $ledFormat
 * @property-read Collection<int, ProposalVersionClubSeat> $seats
 */
final class ProposalVersionClub extends Model
{
    /** @use HasFactory<ProposalVersionClubFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'led_seconds_per_match' => 'integer',
            'home_matches' => 'integer',
            'matches_source' => MatchesSource::class,
            'led_unit_price' => 'decimal:2',
            'led_price' => 'decimal:2',
            'partnership_price' => 'decimal:2',
            'hospitality_price' => 'decimal:2',
            'club_total' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        self::saving(function (ProposalVersionClub $row): void {
            $row->refuseWhenFrozen($row->proposal_version_id);
        });

        self::deleting(function (ProposalVersionClub $row): void {
            $row->refuseWhenFrozen();
        });
    }

    /**
     * The version the row belongs to before the change, and the one it is moving to.
     */
    private function refuseWhenFrozen(?int $newVersionId = null): void
    {
        $original = $this->getOriginal('proposal_version_id');
        $ids = array_values(array_filter([is_numeric($original) ? (int) $original : null, $newVersionId]));

        if ($ids !== [] && ProposalVersion::anyFrozen(...$ids)) {
            throw ProposalImmutable::version($ids[0]);
        }
    }

    /**
     * @return BelongsTo<ProposalVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(ProposalVersion::class, 'proposal_version_id');
    }

    /**
     * @return BelongsTo<Club, $this>
     */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /**
     * @return BelongsTo<CompetitionSeason, $this>
     */
    public function edition(): BelongsTo
    {
        return $this->belongsTo(CompetitionSeason::class, 'competition_season_id');
    }

    /**
     * @return BelongsTo<PartnershipTier, $this>
     */
    public function partnershipTier(): BelongsTo
    {
        return $this->belongsTo(PartnershipTier::class);
    }

    /**
     * @return BelongsTo<LedFormat, $this>
     */
    public function ledFormat(): BelongsTo
    {
        return $this->belongsTo(LedFormat::class);
    }

    /**
     * @return BelongsTo<PriceList, $this>
     */
    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    /**
     * @return HasMany<ProposalVersionClubSeat, $this>
     */
    public function seats(): HasMany
    {
        return $this->hasMany(ProposalVersionClubSeat::class);
    }
}
