<?php

namespace App\Domain\Files\Services;

use App\Domain\Files\Exceptions\InvalidFile;
use App\Enums\FileDerivativeKind;
use App\Models\StoredFile;
use App\Models\StoredFileDerivative;
use GdImage;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use LogicException;

/**
 * Files derived from a stored file (D-310, D-420): made once, at the first request, and reused
 * afterwards; the original is never touched.
 *
 * - Thumbnail (DM-05): the image scaled to fit 480×270 with its proportions, on a white background
 *   (a transparent PNG would turn black in a JPEG), as a JPEG stored through FileStorage (checked
 *   and re-encoded like every image) with the purpose "thumbnail".
 *
 * Two requests for the same missing thumbnail: the second insert hits the unique key (original,
 * kind), its transaction is rolled back (FileStorage deletes its file) and the first thumbnail is
 * returned.
 */
final class FileDerivatives
{
    private const JPEG_QUALITY = 80;

    public function __construct(private readonly FileStorage $storage) {}

    public function thumbnail(StoredFile $original): StoredFile
    {
        return $this->derive($original, FileDerivativeKind::Thumbnail);
    }

    public function derive(StoredFile $original, FileDerivativeKind $kind): StoredFile
    {
        $existing = self::existing($original, $kind);

        if ($existing !== null) {
            return $existing;
        }

        if (! $original->type()->isImage()) {
            throw new LogicException("Only images have derived files, not {$original->type()->value}.");
        }

        $contents = Storage::disk($original->disk)->get($original->path);

        if ($contents === null) {
            throw InvalidFile::unreadable();
        }

        $jpeg = $this->scaled($contents, $kind->box());

        try {
            return DB::transaction(function () use ($original, $kind, $jpeg): StoredFile {
                $file = $this->storage->storeContents($jpeg, $kind->purpose(), null, $kind->value.'-'.$original->id.'.jpg');

                StoredFileDerivative::query()->create([
                    'original_file_id' => $original->id,
                    'kind' => $kind,
                    'derivative_file_id' => $file->id,
                ]);

                return $file;
            });
        } catch (UniqueConstraintViolationException $exception) {
            return self::existing($original, $kind) ?? throw $exception;
        }
    }

    private static function existing(StoredFile $original, FileDerivativeKind $kind): ?StoredFile
    {
        return StoredFileDerivative::query()
            ->where('original_file_id', $original->id)
            ->where('kind', $kind->value)
            ->first()
            ?->derivative;
    }

    /**
     * @param  array{int, int}  $box
     */
    private function scaled(string $contents, array $box): string
    {
        $image = @imagecreatefromstring($contents);

        if (! $image instanceof GdImage) {
            throw InvalidFile::unreadable();
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $ratio = min($box[0] / $width, $box[1] / $height, 1);
        /** @var int<1, max> $targetWidth */
        $targetWidth = max(1, (int) round($width * $ratio));
        /** @var int<1, max> $targetHeight */
        $targetHeight = max(1, (int) round($height * $ratio));

        $target = imagecreatetruecolor($targetWidth, $targetHeight);

        if (! $target instanceof GdImage) {
            throw InvalidFile::unreadable();
        }

        $white = (int) imagecolorallocate($target, 255, 255, 255);
        imagefill($target, 0, 0, $white);
        imagecopyresampled($target, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        ob_start();

        try {
            $written = imagejpeg($target, null, self::JPEG_QUALITY);
        } finally {
            $output = (string) ob_get_clean();
        }

        if (! $written || $output === '') {
            throw InvalidFile::unreadable();
        }

        return $output;
    }
}
