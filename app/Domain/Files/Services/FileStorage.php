<?php

namespace App\Domain\Files\Services;

use App\Domain\Files\Exceptions\InvalidFile;
use App\Domain\Files\Exceptions\StoredFileImmutable;
use App\Enums\FilePurpose;
use App\Models\StoredFile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LogicException;

/**
 * The only way a file enters the portal (C10, D-218). Called by the domain Actions of the slices
 * (client logo in F3, club logo and calendar import in F1, slides in F6, documents in F7) inside
 * their own transaction, together with the row that points to the file.
 *
 * 1. checks the content against the rules of the purpose (FileInspector): an invalid file throws
 *    InvalidFile with the Italian message for the user;
 * 2. re-encodes the images (ImageReencoder);
 * 3. writes the file on the private disk at "<purpose>/<yyyy>/<mm>/<ulid>.<ext>": the name never
 *    comes from the user, the extension comes from the real type, an existing path is never
 *    overwritten;
 * 4. inserts the stored_files row with MIME type, size, SHA-256 and pixels of the stored file.
 *
 * If the transaction is rolled back, the file just written is deleted. A file left behind anyway
 * (process killed in between) is removed by files:prune-orphans.
 *
 * No audit event of its own: the upload is part of the event of the entity that uses the file
 * (e.g. the client update with the old and new logo_file_id; M10 §4.1).
 */
final class FileStorage
{
    public function __construct(
        private readonly FileInspector $inspector,
        private readonly ImageReencoder $reencoder,
    ) {}

    public function storeUpload(UploadedFile $file, FilePurpose $purpose, ?User $author): StoredFile
    {
        $contents = $file->isValid() ? $file->get() : false;

        if ($contents === false) {
            throw InvalidFile::unreadable();
        }

        return $this->storeContents($contents, $purpose, $author, $file->getClientOriginalName());
    }

    /**
     * Stores a content produced on the server (generated PDF, server commands). A null author is
     * the system.
     */
    public function storeContents(string $contents, FilePurpose $purpose, ?User $author, ?string $originalName = null): StoredFile
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Files are stored inside the transaction of the action that uses them.');
        }

        $inspected = $this->inspector->inspect($contents, $purpose);
        $type = $inspected->type;

        if ($type->isImage() && $inspected->width !== null && $inspected->height !== null) {
            $contents = $this->reencoder->reencode($contents, $type, $inspected->width, $inspected->height);
        }

        $disk = Storage::disk(StoredFile::DISK);
        $path = $purpose->value.'/'.now()->format('Y/m').'/'.strtolower((string) Str::ulid()).'.'.$type->extension();

        if ($disk->exists($path)) {
            throw StoredFileImmutable::pathTaken($path);
        }

        $disk->put($path, $contents);
        DB::afterRollBack(fn () => $disk->delete($path));

        $storedFile = new StoredFile([
            'disk' => StoredFile::DISK,
            'path' => $path,
            'purpose' => $purpose,
            'mime_type' => $type->mimeType(),
            'size_bytes' => strlen($contents),
            'sha256' => hash('sha256', $contents),
            'width' => $inspected->width,
            'height' => $inspected->height,
            'original_name' => self::cleanName($originalName),
            'created_by' => $author?->id,
        ]);
        $storedFile->save();

        return $storedFile;
    }

    /**
     * Name of the file as chosen by the user, kept only for display and downloads: no folders,
     * no control characters, at most 255 characters.
     */
    public static function cleanName(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }

        $name = basename(str_replace('\\', '/', $name));
        $name = (string) preg_replace('/[\x00-\x1F\x7F]/u', '', $name);
        $name = trim(mb_substr($name, 0, 255), " .\t");

        return $name !== '' ? $name : null;
    }
}
