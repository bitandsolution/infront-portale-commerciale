<?php

namespace App\Models;

use Database\Factories\TerritoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Territory of the sale (M03 F-02; D-021, D-030): ITALIA active, ESTERO foreseen and not active.
 * From the system data only (D-332): no page changes it (D-331).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class Territory extends Model
{
    /** @use HasFactory<TerritoryFactory> */
    use HasFactory;

    protected $table = 'territories';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
