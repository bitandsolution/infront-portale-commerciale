<?php

namespace App\Http\Requests\Proposals;

use App\Enums\DocumentMode;
use App\Models\ProposalGroup;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Slides chosen for the document of a group (D-397, D-418; DM-05): the mode and the excluded slides
 * (ids; those not in the composition are ignored by SaveDocumentSlides). The permission on the group
 * is ProposalGroupPolicy::editDocument; the statuses are checked by the Action.
 */
class DocumentSlidesRequest extends FormRequest
{
    /** More than the slides a library can reasonably offer to one document. */
    private const MAX_EXCLUDED = 200;

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
            'mode' => ['required', 'string', Rule::enum(DocumentMode::class)],
            'excluded_slide_ids' => ['present', 'array', 'max:'.self::MAX_EXCLUDED],
            'excluded_slide_ids.*' => ['integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['mode' => 'contenuto del documento', 'excluded_slide_ids' => 'slide escluse'];
    }

    public function mode(): DocumentMode
    {
        return DocumentMode::from($this->string('mode')->toString());
    }

    /**
     * @return list<int>
     */
    public function excludedSlideIds(): array
    {
        $ids = $this->input('excluded_slide_ids', []);

        return array_values(array_map(intval(...), is_array($ids) ? array_filter($ids, is_numeric(...)) : []));
    }

    public function actor(): User
    {
        /** @var User $user */
        $user = $this->user();

        return $user;
    }
}
