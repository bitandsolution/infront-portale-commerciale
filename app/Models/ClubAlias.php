<?php

namespace App\Models;

use App\Enums\AliasSource;
use App\Support\Sports\NameNormalizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Another name of a club, used to recognize it in the calendar files (F-11, FL-02). The normalized
 * form is unique in the discipline: an alias never points to two clubs (D-185).
 *
 * @property int $id
 * @property int $club_id
 * @property int $discipline_id
 * @property string $alias
 * @property string $alias_normalized
 * @property AliasSource $source
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Club $club
 */
final class ClubAlias extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        self::saving(function (self $alias): void {
            $alias->alias_normalized = NameNormalizer::normalize($alias->alias);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => AliasSource::class,
        ];
    }

    /**
     * @return BelongsTo<Club, $this>
     */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }
}
