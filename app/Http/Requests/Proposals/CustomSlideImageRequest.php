<?php

namespace App\Http\Requests\Proposals;

use App\Domain\Content\Services\CustomSlideContent;
use App\Domain\Files\Rules\AcceptableFile;
use App\Enums\FilePurpose;
use App\Models\ProposalGroup;
use App\Models\User;
use App\Support\Text;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * Upload of an image of a slide composed with blocks (D-430; DM-07): JPEG or PNG up to 5 MB, checked
 * on the content like FileStorage does (AcceptableFile); the key of its block, or none for the image
 * of the layout "image_text". The permission on the group is ProposalGroupPolicy::editDocument.
 */
class CustomSlideImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $group = $this->route('group');

        return $group instanceof ProposalGroup && ($this->user()?->can('editDocument', $group) ?? false);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'image' => ['required', 'file', new AcceptableFile(FilePurpose::CustomSlideImage)],
            'block' => ['nullable', 'string', 'regex:'.CustomSlideContent::KEY_PATTERN],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['image.required' => Text::get('proposals.custom_slides.validation.image_required')];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['image' => 'immagine'];
    }

    public function uploadedImage(): UploadedFile
    {
        /** @var UploadedFile $file */
        $file = $this->file('image');

        return $file;
    }

    public function blockKey(): ?string
    {
        $key = $this->string('block')->toString();

        return $key !== '' ? $key : null;
    }

    public function actor(): User
    {
        /** @var User $user */
        $user = $this->user();

        return $user;
    }
}
