<?php

namespace App\Http\Requests\Proposals;

use App\Domain\Proposals\Data\DiscountInput;
use App\Domain\Settings\Services\Settings;
use App\Models\Proposal;
use App\Models\User;
use App\Support\Proposals\DiscountForm;
use App\Support\Text;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * "Salva la versione n+1" of an approved variant (M07 FL-04; D-467; F5-04): lock version of the page,
 * the discount of the version (type "percent" / "amount" or empty, value in Italian notation, as in
 * the detail of a group) and the reason of a forcing, when the admin forces an increase beyond the
 * residual (at least forced_reason_min_length characters, D-465). Only the admin
 * (ProposalPolicy::revise); SaveNewVersion checks the rest.
 */
class SaveVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $proposal = $this->route('proposal');

        return $proposal instanceof Proposal && ($this->user()?->can('revise', $proposal) ?? false);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'lock_version' => ['required', 'integer', 'min:0'],
            'discount_type' => ['nullable', 'string', 'in:percent,amount'],
            'discount_value' => ['nullable', 'string', 'max:20'],
            'forced_reason' => ['nullable', 'string', 'min:'.$this->minLength(), 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['forced_reason.min' => Text::get('proposals.errors.forced_reason_short', ['min' => (string) $this->minLength()])];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->parsed()['error'] !== null) {
                $validator->errors()->add('discount_value', (string) $this->parsed()['error']);
            }
        });
    }

    public function lockVersion(): int
    {
        return $this->integer('lock_version');
    }

    public function discount(): DiscountInput
    {
        return $this->parsed()['discount'];
    }

    public function forcedReason(): ?string
    {
        $reason = $this->string('forced_reason')->trim()->toString();

        return $reason !== '' ? $reason : null;
    }

    public function actor(): User
    {
        /** @var User $user */
        $user = $this->user();

        return $user;
    }

    protected function prepareForValidation(): void
    {
        $reason = $this->input('forced_reason');

        if (is_string($reason)) {
            $this->merge(['forced_reason' => trim($reason) !== '' ? trim($reason) : null]);
        }
    }

    /**
     * @return array{discount: DiscountInput, error: string|null}
     */
    private function parsed(): array
    {
        $parsed = DiscountForm::parse([0 => ['type' => $this->input('discount_type'), 'value' => $this->input('discount_value')]]);

        return ['discount' => $parsed['discounts'][0] ?? new DiscountInput, 'error' => $parsed['errors'][0] ?? null];
    }

    private function minLength(): int
    {
        return app(Settings::class)->forcedReasonMinLength();
    }
}
