<?php

namespace App\Http\Requests\Proposals;

use App\Domain\Settings\Services\Settings;
use App\Models\ProposalGroup;
use App\Support\Text;

/**
 * "Forza e approva" of a group (M07 F-05, RB-06; M04 F-10; D-064, D-070, D-459; F5-03): the discounts
 * of ApproveGroupRequest and the reason of the forcing, required, at least forced_reason_min_length
 * characters (M04 §9). Only the admin (ProposalGroupPolicy::force).
 */
class ForceGroupRequest extends ApproveGroupRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('force', ProposalGroup::class) ?? false;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'forced_reason' => ['required', 'string', 'min:'.$this->minLength(), 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $message = Text::get('proposals.errors.forced_reason_short', ['min' => (string) $this->minLength()]);

        return [
            'forced_reason.required' => $message,
            'forced_reason.min' => $message,
        ];
    }

    public function forcedReason(): string
    {
        return $this->string('forced_reason')->trim()->toString();
    }

    protected function prepareForValidation(): void
    {
        $reason = $this->input('forced_reason');

        if (is_string($reason)) {
            $this->merge(['forced_reason' => trim($reason)]);
        }
    }

    private function minLength(): int
    {
        return app(Settings::class)->forcedReasonMinLength();
    }
}
