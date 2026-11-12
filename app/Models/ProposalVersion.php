<?php

namespace App\Models;

use App\Domain\Content\Data\DocumentSelection;
use App\Domain\Proposals\Exceptions\ProposalImmutable;
use App\Enums\DiscountType;
use App\Enums\DocumentMode;
use Database\Factories\ProposalVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Version of a proposal (D-096, D-198; progettazione-tecnica §5.3.4): the working version (number
 * null, editable: the agent's draft, the proposal under review, the admin's change of an approved one)
 * or a frozen version (number 1, 2…, frozen_at set), immutable: prices, matches and campaign KPIs
 * frozen at the approval (D-041, D-111) are its rows. The discount is only on the total, here, never
 * on the club rows (D-103). Freezing, discount and forcing are written by F5.
 *
 * Immutability: the model refuses to change or delete a frozen version (ProposalImmutable);
 * the triggers of the database refuse it for every writer.
 *
 * @property int $id
 * @property int $proposal_id
 * @property int|null $number
 * @property int|null $working_slot
 * @property Carbon|null $frozen_at
 * @property int|null $frozen_by
 * @property string|null $change_note
 * @property Carbon|null $starts_on
 * @property Carbon|null $ends_on
 * @property string|null $client_name
 * @property int|null $client_logo_file_id
 * @property int|null $commodity_sector_id
 * @property string|null $sector_free_text
 * @property string $list_total
 * @property DiscountType|null $discount_type
 * @property string|null $discount_value
 * @property string|null $discount_amount
 * @property string|null $final_total
 * @property Carbon|null $campaign_starts_on
 * @property int|null $campaign_months
 * @property int|null $total_matches
 * @property int|null $total_spots
 * @property int|null $top_matches
 * @property DocumentMode|null $document_mode
 * @property list<int>|null $excluded_slide_ids
 * @property bool $is_forced
 * @property string|null $forced_reason
 * @property bool $is_legacy
 * @property array<string, mixed>|null $snapshot
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Proposal $proposal
 */
final class ProposalVersion extends Model
{
    /** @use HasFactory<ProposalVersionFactory> */
    use HasFactory;

    protected $guarded = ['id', 'working_slot'];

    /**
     * Defaults of the columns, also before the first save.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'list_total' => '0.00',
        'is_forced' => false,
        'is_legacy' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'working_slot' => 'integer',
            'frozen_at' => 'datetime',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'list_total' => 'decimal:2',
            'discount_type' => DiscountType::class,
            'discount_value' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'final_total' => 'decimal:2',
            'campaign_starts_on' => 'date',
            'campaign_months' => 'integer',
            'total_matches' => 'integer',
            'total_spots' => 'integer',
            'top_matches' => 'integer',
            'document_mode' => DocumentMode::class,
            'excluded_slide_ids' => 'array',
            'is_forced' => 'boolean',
            'is_legacy' => 'boolean',
            'snapshot' => 'array',
        ];
    }

    protected static function booted(): void
    {
        // Freezing a working version is an update of a version not yet frozen: allowed.
        self::updating(function (ProposalVersion $version): void {
            if ($version->getOriginal('frozen_at') !== null) {
                throw ProposalImmutable::version($version->id);
            }
        });

        self::deleting(function (ProposalVersion $version): void {
            if ($version->getOriginal('frozen_at') !== null) {
                throw ProposalImmutable::version($version->id);
            }
        });
    }

    /**
     * The slides chosen for the document, frozen with the version at the approval (D-419); complete
     * without exclusions for the versions frozen before DM-05.
     */
    public function documentSelection(): DocumentSelection
    {
        return DocumentSelection::fromColumns($this->document_mode, $this->excluded_slide_ids);
    }

    public function isFrozen(): bool
    {
        return $this->frozen_at !== null;
    }

    /**
     * Whether any of these versions is frozen (read from the database, not from loaded models).
     */
    public static function anyFrozen(int ...$ids): bool
    {
        return self::query()->whereKey($ids)->whereNotNull('frozen_at')->exists();
    }

    /**
     * @return BelongsTo<Proposal, $this>
     */
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class);
    }

    /**
     * @return HasMany<ProposalVersionClub, $this>
     */
    public function clubs(): HasMany
    {
        return $this->hasMany(ProposalVersionClub::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function frozenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'frozen_by');
    }
}
