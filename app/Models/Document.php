<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A generated document of a group of variants (M09; D-120, D-204, D-205, D-413; DM-04): one row per
 * generation. The current one has superseded_at null; a regeneration replaces it and the old row
 * stays, downloadable by the admin only (D-205). kpi_snapshot and page_model keep what the PDF was
 * made of (D-111, D-204).
 *
 * @property int $id
 * @property int $proposal_group_id
 * @property int $proposal_id
 * @property int $proposal_version_id
 * @property DocumentType $document_type
 * @property string $format
 * @property string $language
 * @property DocumentStatus $status
 * @property int $attempts
 * @property string|null $last_error
 * @property int|null $file_id
 * @property int|null $page_count
 * @property int|null $size_bytes
 * @property array<string, mixed>|null $kpi_snapshot
 * @property array<string, mixed>|null $page_model
 * @property int|null $generated_by
 * @property Carbon|null $generated_at
 * @property Carbon|null $superseded_at
 * @property int|null $superseded_by_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read ProposalGroup $group
 * @property-read StoredFile|null $file
 * @property-read User|null $generatedBy
 */
final class Document extends Model
{
    public const FORMAT_PDF = 'pdf';

    public const LANGUAGE = 'it';

    protected $guarded = ['id', 'current_slot'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'document_type' => DocumentType::class,
            'status' => DocumentStatus::class,
            'attempts' => 'integer',
            'page_count' => 'integer',
            'size_bytes' => 'integer',
            'kpi_snapshot' => 'array',
            'page_model' => 'array',
            'generated_at' => 'datetime',
            'superseded_at' => 'datetime',
        ];
    }

    public function isCurrent(): bool
    {
        return $this->superseded_at === null;
    }

    public function isReady(): bool
    {
        return $this->status === DocumentStatus::Ready && $this->file_id !== null;
    }

    /**
     * @return BelongsTo<ProposalGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(ProposalGroup::class, 'proposal_group_id');
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
    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
