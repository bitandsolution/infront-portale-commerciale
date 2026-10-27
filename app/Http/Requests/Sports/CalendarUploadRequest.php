<?php

namespace App\Http\Requests\Sports;

use App\Domain\Files\Rules\AcceptableFile;
use App\Enums\FilePurpose;
use App\Http\Requests\Sports\Concerns\HasSportsAttributes;
use App\Models\CalendarImport;
use App\Models\CompetitionSeason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * Calendar file uploaded for a competition of the season (FL-02 step 1): an .xlsx without macros,
 * checked from the content (AcceptableFile, D-218) and again by FileStorage when it is stored.
 */
class CalendarUploadRequest extends FormRequest
{
    use HasSportsAttributes;

    public function authorize(): bool
    {
        return $this->user()?->can('create', CalendarImport::class) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'competition_season_id' => ['required', 'integer', Rule::exists(CompetitionSeason::class, 'id')],
            'file' => ['bail', 'required', 'file', new AcceptableFile(FilePurpose::CalendarImport)],
        ];
    }

    public function edition(): CompetitionSeason
    {
        return CompetitionSeason::query()->findOrFail($this->integer('competition_season_id'));
    }

    public function calendarFile(): UploadedFile
    {
        /** @var UploadedFile $file */
        $file = $this->file('file');

        return $file;
    }
}
