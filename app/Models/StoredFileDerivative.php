<?php

namespace App\Models;

use App\Domain\Files\Exceptions\StoredFileImmutable;
use App\Enums\FileDerivativeKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A file derived from a stored file (D-310, D-420): the thumbnail of a slide image in DM-05. One per
 * original and kind; inserted once by App\Domain\Files\Services\FileDerivatives and never changed nor
 * deleted (the model refuses a second save, the triggers refuse every UPDATE and DELETE).
 *
 * @property int $id
 * @property int $original_file_id
 * @property FileDerivativeKind $kind
 * @property int $derivative_file_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read StoredFile $original
 * @property-read StoredFile $derivative
 */
final class StoredFileDerivative extends Model
{
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => FileDerivativeKind::class,
        ];
    }

    /**
     * @return BelongsTo<StoredFile, $this>
     */
    public function original(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'original_file_id');
    }

    /**
     * @return BelongsTo<StoredFile, $this>
     */
    public function derivative(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'derivative_file_id');
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
