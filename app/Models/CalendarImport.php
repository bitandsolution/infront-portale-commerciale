<?php

namespace App\Models;

use App\Domain\Sports\Data\CalendarPreview;
use App\Enums\CalendarImportStatus;
use Database\Factories\CalendarImportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Import of a calendar file into an edition (M02 F-09, F-10, FL-02; D-325). The preview is kept
 * between the requests of the flow and rebuilt from the file after every correction; at the
 * confirmation the matches are inserted and updated in one transaction.
 *
 * @property int $id
 * @property int $competition_season_id
 * @property int $file_id
 * @property int $uploaded_by
 * @property CalendarImportStatus $status
 * @property array<string, int>|null $column_mapping
 * @property array<string, mixed>|null $preview
 * @property int $inserted_count
 * @property int $updated_count
 * @property int $unchanged_count
 * @property int $discarded_count
 * @property Carbon|null $confirmed_at
 * @property int|null $confirmed_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read CompetitionSeason $competitionSeason
 * @property-read StoredFile $file
 * @property-read User $uploader
 * @property-read User|null $confirmer
 */
final class CalendarImport extends Model
{
    /** @use HasFactory<CalendarImportFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * @var array<string, string>
     */
    protected $attributes = [
        'status' => 'preview',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CalendarImportStatus::class,
            'column_mapping' => 'array',
            'preview' => 'array',
            'confirmed_at' => 'datetime',
        ];
    }

    public function isOpen(): bool
    {
        return $this->status === CalendarImportStatus::Preview;
    }

    /**
     * Preview stored with the import, or an empty one (discarded import).
     */
    public function previewData(): CalendarPreview
    {
        return CalendarPreview::fromArray($this->preview ?? []);
    }

    /**
     * Column mapping as stored: field => index of the column in the file.
     *
     * @return array<string, int>
     */
    public function mapping(): array
    {
        return $this->column_mapping ?? [];
    }

    /**
     * @return BelongsTo<CompetitionSeason, $this>
     */
    public function competitionSeason(): BelongsTo
    {
        return $this->belongsTo(CompetitionSeason::class);
    }

    /**
     * @return BelongsTo<StoredFile, $this>
     */
    public function file(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'file_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}
