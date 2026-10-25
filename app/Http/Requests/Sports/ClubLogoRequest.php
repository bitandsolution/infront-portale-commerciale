<?php

namespace App\Http\Requests\Sports;

use App\Domain\Files\Rules\AcceptableFile;
use App\Enums\FilePurpose;
use App\Http\Requests\Sports\Concerns\HasSportsAttributes;
use App\Models\Club;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * New logo of a club (D-218, D-288): PNG or JPEG up to 2 MB, at most 5000 px per side, checked from
 * the content (AcceptableFile) and again by FileStorage when it is stored.
 */
class ClubLogoRequest extends FormRequest
{
    use HasSportsAttributes;

    public function authorize(): bool
    {
        $club = $this->route('club');

        return $club instanceof Club && ($this->user()?->can('update', $club) ?? false);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'logo' => ['bail', 'required', 'file', new AcceptableFile(FilePurpose::ClubLogo)],
        ];
    }

    public function logo(): UploadedFile
    {
        /** @var UploadedFile $file */
        $file = $this->file('logo');

        return $file;
    }
}
