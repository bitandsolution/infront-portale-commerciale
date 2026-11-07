<?php

namespace App\Models;

use App\Models\Concerns\HasOwner;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Client of the proposals (M05 F-01; D-074, D-196): one shared registry, each client owned by one
 * agent (D-076, D-082, D-358). The agent sees and changes only his own clients (RB-01): queries go
 * through visibleTo() (HasOwner) and ClientPolicy. Never deleted (C4, RB-06): the admin deactivates
 * it, or merges it into another client (F3-03, D-359), after which it stays read-only.
 *
 * @property int $id
 * @property int $owner_id
 * @property string $company_name
 * @property string $company_name_normalized
 * @property string|null $vat_number
 * @property string $email
 * @property int $logo_file_id
 * @property int $commodity_sector_id
 * @property string|null $sector_free_text
 * @property string|null $contact_name
 * @property bool $is_active
 * @property int|null $merged_into_id
 * @property int $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $owner
 * @property-read CommoditySector $sector
 * @property-read StoredFile $logo
 * @property-read Client|null $mergedInto
 */
final class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory;

    use HasOwner;

    protected $guarded = ['id'];

    /**
     * Defaults of the columns, also before the first save (audit of a new client).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<CommoditySector, $this>
     */
    public function sector(): BelongsTo
    {
        return $this->belongsTo(CommoditySector::class, 'commodity_sector_id');
    }

    /**
     * @return BelongsTo<StoredFile, $this>
     */
    public function logo(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'logo_file_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Merged into another client (D-359): read-only for everyone, never reactivated. */
    public function isMerged(): bool
    {
        return $this->merged_into_id !== null;
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'merged_into_id');
    }
}
