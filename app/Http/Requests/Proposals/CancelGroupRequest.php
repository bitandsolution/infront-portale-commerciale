<?php

namespace App\Http\Requests\Proposals;

use App\Domain\Settings\Services\Settings;
use App\Models\ProposalGroup;
use App\Support\Text;
use Illuminate\Foundation\Http\FormRequest;

/**
 * "Annulla il gruppo" (M07 F-07, FL-05; D-095, D-447; F5-05): the reason, required, at least
 * forced_reason_min_length characters. Only the admin (ProposalGroupPolicy::cancel).
 */
class CancelGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('cancel', ProposalGroup::class) ?? false;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'cancel_reason' => ['required', 'string', 'min:'.$this->minLength(), 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $message = Text::get('proposals.errors.cancel_reason_short', ['min' => (string) $this->minLength()]);

        return [
            'cancel_reason.required' => $message,
            'cancel_reason.min' => $message,
        ];
    }

    public function reason(): string
    {
        return $this->string('cancel_reason')->trim()->toString();
    }

    protected function prepareForValidation(): void
    {
        $reason = $this->input('cancel_reason');

        if (is_string($reason)) {
            $this->merge(['cancel_reason' => trim($reason)]);
        }
    }

    private function minLength(): int
    {
        return app(Settings::class)->forcedReasonMinLength();
    }
}
