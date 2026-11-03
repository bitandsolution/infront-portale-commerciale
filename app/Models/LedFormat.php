<?php

namespace App\Models;

use Database\Factories\LedFormatFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * LED format (M03 F-03; D-051): JUMBO and DOMINATION consume the same seconds on FILA_1 and FILA_2,
 * FILA_1 and FILA_2 consume their own pool (M04 RB-02). Code and pools from the system data
 * (D-332); name, description, order and active changed by the admin from the Values page (D-331).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property int $visibility_type_id
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read VisibilityType $visibilityType
 * @property-read Collection<int, InventoryPool> $pools
 */
final class LedFormat extends Model
{
    /** @use HasFactory<LedFormatFactory> */
    use HasFactory;

    protected $table = 'led_formats';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<VisibilityType, $this>
     */
    public function visibilityType(): BelongsTo
    {
        return $this->belongsTo(VisibilityType::class);
    }

    /**
     * Pools consumed by the format, each by the same seconds (D-051).
     *
     * @return BelongsToMany<InventoryPool, $this>
     */
    public function pools(): BelongsToMany
    {
        return $this->belongsToMany(InventoryPool::class, 'led_format_pools')->withTimestamps();
    }
}
