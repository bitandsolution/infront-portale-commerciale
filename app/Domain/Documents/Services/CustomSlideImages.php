<?php

namespace App\Domain\Documents\Services;

use App\Domain\Content\Data\CustomSlide;
use App\Models\StoredFile;
use Illuminate\Support\Facades\Storage;

/**
 * The images of a slide composed with blocks (DM-07) for CustomBlocksLayout: the file on the private
 * disk with its pixels for the PDF and the preview, the pixels alone for the warnings of the step
 * "Documento". A file missing on the disk is left out, so the layout skips its block.
 */
final class CustomSlideImages
{
    /**
     * @return array<int, array{path: string, width: int, height: int}>
     */
    public function files(CustomSlide $slide): array
    {
        $images = [];

        foreach ($this->stored($slide) as $file) {
            $path = Storage::disk($file->disk)->path($file->path);

            if (is_file($path)) {
                $images[$file->id] = ['path' => $path, 'width' => (int) $file->width, 'height' => (int) $file->height];
            }
        }

        return $images;
    }

    /**
     * @return array<int, array{int, int}>
     */
    public function sizes(CustomSlide $slide): array
    {
        $sizes = [];

        foreach ($this->stored($slide) as $file) {
            $sizes[$file->id] = [(int) $file->width, (int) $file->height];
        }

        return $sizes;
    }

    /**
     * @return list<StoredFile>
     */
    private function stored(CustomSlide $slide): array
    {
        $ids = $slide->imageFileIds();

        return $ids === [] ? [] : array_values(StoredFile::query()->whereKey($ids)->get()->all());
    }
}
