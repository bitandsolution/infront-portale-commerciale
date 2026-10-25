<?php

namespace App\Models;

use Database\Factories\ClubFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Club (M02 F-05; D-185, D-188). Infront clubs (is_infront) are the ones that can be sold (RB-07);
 * the others exist as opponents. is_top_match_opponent marks the guests that make a top match
 * (RB-06, D-253, D-257). No competition on the club: the category is the seasonal enrollment.
 * Deactivated, never deleted (C4, RB-08, M02-CA7).
 *
 * @property int $id
 * @property int $discipline_id
 * @property string $code
 * @property string $name
 * @property string $short_name
 * @property int|null $home_stadium_id
 * @property int|null $logo_file_id
 * @property string|null $primary_color
 * @property string|null $secondary_color
 * @property bool $is_infront
 * @property bool $is_top_match_opponent
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Stadium|null $homeStadium
 * @property-read StoredFile|null $logo
 */
final class Club extends Model
{
    /** @use HasFactory<ClubFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_infront' => 'boolean',
            'is_top_match_opponent' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Discipline, $this>
     */
    public function discipline(): BelongsTo
    {
        return $this->belongsTo(Discipline::class);
    }

    /**
     * @return BelongsTo<Stadium, $this>
     */
    public function homeStadium(): BelongsTo
    {
        return $this->belongsTo(Stadium::class, 'home_stadium_id');
    }

    /**
     * @return BelongsTo<StoredFile, $this>
     */
    public function logo(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'logo_file_id');
    }

    /**
     * @return HasMany<ClubAlias, $this>
     */
    public function aliases(): HasMany
    {
        return $this->hasMany(ClubAlias::class);
    }

    /**
     * @return HasMany<ClubEnrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(ClubEnrollment::class);
    }
}
