<?php

namespace App\Models;

use App\Enums\LedPhotoKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Configuration of the simulation of the logo on the LED for a club and a kind of photo, or the
 * generic one of Infront without club (D-398, D-427; DM-06): the photo of the field, the areas of the
 * LED (four points each, pixels of the photo, from the top left clockwise), the height of the logo
 * in % of the LED and the pixels between two logos. Saved only by SaveLedMockupSetting (audit).
 *
 * @property int $id
 * @property int|null $club_id
 * @property int $club_slot
 * @property LedPhotoKind $photo_kind
 * @property int $photo_file_id
 * @property list<list<array{int, int}>> $areas
 * @property int $logo_height_percent
 * @property int $spacing
 * @property int|null $updated_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Club|null $club
 * @property-read StoredFile $photo
 */
final class LedMockupSetting extends Model
{
    /** Defaults of a new configuration: like the logos of the old system on rows of about 40 px. */
    public const DEFAULT_LOGO_HEIGHT_PERCENT = 85;

    public const DEFAULT_SPACING = 30;

    protected $guarded = ['id', 'club_slot'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'photo_kind' => LedPhotoKind::class,
            'areas' => 'array',
            'logo_height_percent' => 'integer',
            'spacing' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Club, $this>
     */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /**
     * @return BelongsTo<StoredFile, $this>
     */
    public function photo(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'photo_file_id');
    }
}
