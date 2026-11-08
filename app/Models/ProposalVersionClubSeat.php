<?php

namespace App\Models;

use App\Domain\Proposals\Exceptions\ProposalImmutable;
use Database\Factories\ProposalVersionClubSeatFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Hospitality seats of a club row (M06 §5; D-055, D-147): one variant, 1 to 6 seats (the total of
 * at most hospitality_max_seats per club is a rule of the code, D-293), seat price and price.
 * Immutable when the version of its row is frozen (model and triggers).
 *
 * @property int $id
 * @property int $proposal_version_club_id
 * @property int $hospitality_variant_id
 * @property int $seats
 * @property string|null $seat_price
 * @property string|null $price
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read ProposalVersionClub $clubRow
 * @property-read HospitalityVariant $variant
 */
final class ProposalVersionClubSeat extends Model
{
    /** @use HasFactory<ProposalVersionClubSeatFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'seats' => 'integer',
            'seat_price' => 'decimal:2',
            'price' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        self::saving(function (ProposalVersionClubSeat $seat): void {
            $seat->refuseWhenFrozen($seat->proposal_version_club_id);
        });

        self::deleting(function (ProposalVersionClubSeat $seat): void {
            $seat->refuseWhenFrozen();
        });
    }

    private function refuseWhenFrozen(?int $newRowId = null): void
    {
        $original = $this->getOriginal('proposal_version_club_id');
        $rows = array_values(array_filter([is_numeric($original) ? (int) $original : null, $newRowId]));

        if ($rows === []) {
            return;
        }

        $versions = ProposalVersionClub::query()->whereKey($rows)->pluck('proposal_version_id')->map(fn (mixed $id): int => (int) $id)->all();

        if ($versions !== [] && ProposalVersion::anyFrozen(...$versions)) {
            throw ProposalImmutable::version($versions[0]);
        }
    }

    /**
     * @return BelongsTo<ProposalVersionClub, $this>
     */
    public function clubRow(): BelongsTo
    {
        return $this->belongsTo(ProposalVersionClub::class, 'proposal_version_club_id');
    }

    /**
     * @return BelongsTo<HospitalityVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(HospitalityVariant::class, 'hospitality_variant_id');
    }
}
