<?php

namespace App\Models;

use App\Enums\SlideConditionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Inclusion condition of a slide (M08 §4.2; D-202): one row per value, with a stable code.
 *
 * @property int $id
 * @property int $slide_id
 * @property SlideConditionType $condition_type
 * @property string $value
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class SlideCondition extends Model
{
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'condition_type' => SlideConditionType::class,
        ];
    }

    /**
     * @return BelongsTo<Slide, $this>
     */
    public function slide(): BelongsTo
    {
        return $this->belongsTo(Slide::class);
    }
}
