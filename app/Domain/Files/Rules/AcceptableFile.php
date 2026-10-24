<?php

namespace App\Domain\Files\Rules;

use App\Domain\Files\Exceptions\InvalidFile;
use App\Domain\Files\Services\FileInspector;
use App\Enums\FilePurpose;
use App\Support\Text;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Validation rule for the upload fields of the forms (logos, slides, Excel calendars): the same
 * checks as FileStorage (D-218), so the user sees the error under the field before the Action runs.
 * The Action stores the file with FileStorage, which checks it again.
 */
final class AcceptableFile implements ValidationRule
{
    public function __construct(private readonly FilePurpose $purpose) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $contents = $value instanceof UploadedFile && $value->isValid() ? $value->get() : false;

        if ($contents === false) {
            $fail(Text::get('files.errors.unreadable'));

            return;
        }

        try {
            app(FileInspector::class)->inspect($contents, $this->purpose);
        } catch (InvalidFile $invalid) {
            $fail($invalid->getMessage());
        }
    }
}
