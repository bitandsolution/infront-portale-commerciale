<?php

namespace App\Http\Requests\Proposals;

use App\Domain\Content\Services\CustomSlideContent;
use App\Domain\Proposals\Data\CustomSlideInput;
use App\Enums\CustomBlockType;
use App\Enums\CustomSlideLayout;
use App\Models\ProposalCustomSlide;
use App\Models\ProposalGroup;
use App\Models\ProposalGroupFile;
use App\Models\SlideSection;
use App\Models\User;
use App\Support\Text;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Content of a slide composed with blocks (D-399, D-429, D-430; DM-07), sent by the editor: title,
 * layout, section, image of the layout "image_text" and the blocks, with the limits of
 * CustomSlideContent. The images must be among those uploaded for the group. The permission on the
 * group is ProposalGroupPolicy::editDocument; the statuses are checked by the Action.
 */
class CustomSlideRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:'.CustomSlideContent::MAX_SLIDE_TITLE_LENGTH],
            'layout' => ['required', 'string', Rule::enum(CustomSlideLayout::class)],
            'section' => ['required', 'string', Rule::in(SlideSection::CUSTOM_SLIDE_SECTIONS)],
            'image_file_id' => ['nullable', 'integer', 'min:1'],
            'blocks' => ['present', 'array', 'max:'.CustomSlideContent::MAX_BLOCKS],
            'blocks.*' => ['array'],
            'blocks.*.key' => ['required', 'string', 'regex:'.CustomSlideContent::KEY_PATTERN, 'distinct'],
            'blocks.*.type' => ['required', 'string', Rule::enum(CustomBlockType::class)],
            'blocks.*.column' => ['nullable', 'string', Rule::in([CustomSlideContent::LEFT, CustomSlideContent::RIGHT])],
            'blocks.*.text' => ['nullable', 'string', 'max:'.CustomSlideContent::MAX_TEXT_LENGTH],
            'blocks.*.file_id' => ['nullable', 'integer', 'min:1'],
            'blocks.*.rows' => ['nullable', 'array', 'min:1', 'max:'.CustomSlideContent::MAX_TABLE_ROWS],
            'blocks.*.rows.*' => ['array', 'min:1', 'max:'.CustomSlideContent::MAX_TABLE_COLUMNS],
            'blocks.*.rows.*.*' => ['nullable', 'string', 'max:'.CustomSlideContent::MAX_CELL_LENGTH],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => Text::get('proposals.custom_slides.validation.title_required'),
            'blocks.max' => Text::get('proposals.custom_slides.validation.too_many_blocks', ['max' => (string) CustomSlideContent::MAX_BLOCKS]),
            'blocks.*.rows.max' => Text::get('proposals.custom_slides.validation.table_rows', ['max' => (string) CustomSlideContent::MAX_TABLE_ROWS]),
            'blocks.*.rows.*.max' => Text::get('proposals.custom_slides.validation.table_columns', ['max' => (string) CustomSlideContent::MAX_TABLE_COLUMNS]),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'titolo della slide',
            'layout' => 'schema',
            'section' => 'posizione nel documento',
            'blocks' => 'blocchi',
            'blocks.*.text' => 'testo',
            'blocks.*.rows.*.*' => 'cella della tabella',
        ];
    }

    /**
     * D-430: at most 2 images per slide; the images only among those of the group.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $input = $this->customSlideInput();

            if (CustomSlideContent::imageCount($input->layout, $input->blocks) > CustomSlideContent::MAX_IMAGES) {
                $validator->errors()->add('blocks', Text::get('proposals.custom_slides.validation.too_many_images', ['max' => (string) CustomSlideContent::MAX_IMAGES]));
            }

            $slide = $this->route('customSlide');
            $groupId = $slide instanceof ProposalCustomSlide ? $slide->proposal_group_id : 0;
            $ids = array_values(array_filter([
                $input->layout === CustomSlideLayout::ImageText ? $input->imageFileId : null,
                ...array_map(fn (array $block): mixed => $block['file_id'] ?? null, $input->blocks),
            ], is_int(...)));
            $known = ProposalGroupFile::query()->where('proposal_group_id', $groupId)->whereIn('stored_file_id', $ids)->pluck('stored_file_id')->all();

            foreach ($ids as $id) {
                if (! in_array($id, $known, true)) {
                    $validator->errors()->add('blocks', Text::get('proposals.custom_slides.validation.image_unknown'));
                    break;
                }
            }
        }];
    }

    public function customSlideInput(): CustomSlideInput
    {
        $blocks = $this->input('blocks', []);
        $imageFileId = $this->input('image_file_id');

        return new CustomSlideInput(
            title: $this->string('title')->trim()->toString(),
            layout: CustomSlideLayout::tryFrom($this->string('layout')->toString()) ?? CustomSlideLayout::OneColumn,
            sectionCode: $this->string('section')->toString(),
            imageFileId: is_numeric($imageFileId) ? (int) $imageFileId : null,
            blocks: CustomSlideContent::normalize(is_array($blocks) ? $blocks : []),
        );
    }

    public function actor(): User
    {
        /** @var User $user */
        $user = $this->user();

        return $user;
    }
}
