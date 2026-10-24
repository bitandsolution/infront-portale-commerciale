<?php

namespace App\Enums;

use App\Support\Text;

/**
 * Kinds of file the portal stores (C10, D-218), recognised from the content, never from the
 * extension. The MIME type and the extension of a stored file always come from here.
 */
enum FileType: string
{
    case Png = 'png';
    case Jpeg = 'jpeg';
    case Xlsx = 'xlsx';
    case Pdf = 'pdf';

    public function mimeType(): string
    {
        return match ($this) {
            self::Png => 'image/png',
            self::Jpeg => 'image/jpeg',
            self::Xlsx => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            self::Pdf => 'application/pdf',
        };
    }

    /**
     * Extension of the file on the private disk and in the download name.
     */
    public function extension(): string
    {
        return match ($this) {
            self::Png => 'png',
            self::Jpeg => 'jpg',
            self::Xlsx => 'xlsx',
            self::Pdf => 'pdf',
        };
    }

    /**
     * Images are always re-encoded (D-218).
     */
    public function isImage(): bool
    {
        return $this === self::Png || $this === self::Jpeg;
    }

    /**
     * Shown in the browser (logo and slide previews, PDF viewer) or only saved (Excel).
     */
    public function disposition(): string
    {
        return $this === self::Xlsx ? 'attachment' : 'inline';
    }

    public static function fromMimeType(string $mimeType): ?self
    {
        foreach (self::cases() as $type) {
            if ($type->mimeType() === $mimeType) {
                return $type;
            }
        }

        return null;
    }

    /**
     * Italian name for the messages (lang/it/files.php).
     */
    public function label(): string
    {
        return Text::get('files.types.'.$this->value);
    }
}
