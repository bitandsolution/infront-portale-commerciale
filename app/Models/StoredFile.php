<?php

namespace App\Models;

use App\Domain\Files\Exceptions\StoredFileImmutable;
use App\Enums\FilePurpose;
use App\Enums\FileType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * A file of the portal on the private disk (C10, D-218). Written only by
 * App\Domain\Files\Services\FileStorage, downloaded only from the route "files.download", which
 * checks App\Policies\StoredFilePolicy.
 *
 * Never overwritten (C10, M05 RB-07): a row is inserted once and never changed, by the code nor by
 * SQL (trigger stored_files_before_update). A new logo is a new file: the documents and versions
 * already approved keep pointing to the old one.
 *
 * @property int $id
 * @property string $disk
 * @property string $path
 * @property FilePurpose $purpose
 * @property string $mime_type
 * @property int $size_bytes
 * @property string $sha256
 * @property int|null $width
 * @property int|null $height
 * @property string|null $original_name
 * @property int|null $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User|null $creator
 */
final class StoredFile extends Model
{
    /** Private disk outside the web root (config/filesystems.php, FILES_ROOT). */
    public const DISK = 'files';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purpose' => FilePurpose::class,
            'size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function type(): FileType
    {
        return FileType::fromMimeType($this->mime_type)
            ?? throw new LogicException("Stored file {$this->id} has an unknown MIME type.");
    }

    /**
     * Name proposed to the browser: the original name with the extension of the real type (a
     * "logo.html" stored as PNG downloads as "logo.png"), or "<purpose>-<id>.<ext>".
     * The PDF documents get the name of M09 RB-09 from their slice (F7).
     */
    public function downloadName(): string
    {
        $extension = $this->type()->extension();
        $name = $this->original_name !== null ? pathinfo($this->original_name, PATHINFO_FILENAME) : '';

        return ($name !== '' ? $name : str_replace('_', '-', $this->purpose->value).'-'.$this->id).'.'.$extension;
    }

    /**
     * Inserts the row; a row already saved is never saved again.
     *
     * @param  array<string, mixed>  $options
     */
    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw StoredFileImmutable::make();
        }

        return parent::save($options);
    }
}
