<?php

namespace App\Models;

use Database\Factories\StadiumFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Stadium (M02 F-07): name, city, capacity. Shared stadiums are normal (more clubs on the same
 * stadium): the inventory is per club. Deactivated, never deleted (C4, RB-08).
 *
 * @property int $id
 * @property string $name
 * @property string $city
 * @property int|null $capacity
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class Stadium extends Model
{
    /** @use HasFactory<StadiumFactory> */
    use HasFactory;

    protected $table = 'stadiums';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Clubs that play at home in this stadium.
     *
     * @return HasMany<Club, $this>
     */
    public function clubs(): HasMany
    {
        return $this->hasMany(Club::class, 'home_stadium_id');
    }
}
