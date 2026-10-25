<?php

namespace App\Models;

use Database\Factories\DisciplineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Sport discipline (M02 F-01, D-016). In the 1.0 only football (CALCIO), created by the system data
 * seeder; there is no page to manage disciplines (D-321).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class Discipline extends Model
{
    /** @use HasFactory<DisciplineFactory> */
    use HasFactory;

    /** Code of football, the only discipline of the 1.0 (D-016). */
    public const FOOTBALL = 'CALCIO';

    protected $guarded = ['id'];

    /**
     * Discipline used by the back office when none is chosen: the first active one. With a single
     * discipline (1.0) the forms do not show the field.
     */
    public static function default(): self
    {
        return self::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->firstOrFail();
    }

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
     * @return HasMany<Season, $this>
     */
    public function seasons(): HasMany
    {
        return $this->hasMany(Season::class);
    }

    /**
     * @return HasMany<Competition, $this>
     */
    public function competitions(): HasMany
    {
        return $this->hasMany(Competition::class);
    }
}
