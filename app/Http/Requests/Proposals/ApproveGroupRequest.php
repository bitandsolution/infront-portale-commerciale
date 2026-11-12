<?php

namespace App\Http\Requests\Proposals;

use App\Domain\Proposals\Data\DiscountInput;
use App\Models\ProposalGroup;
use App\Support\Proposals\DiscountForm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Approval of a group (D-408): the discount of every variant, by proposal id, as type ("percent",
 * "amount" or empty) and value in Italian notation; empty = no discount. The ranges are checked by
 * the approval (Discounts), on the list total calculated by the server.
 */
class ApproveGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('review', ProposalGroup::class) ?? false;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'discounts' => ['nullable', 'array'],
            'discounts.*' => ['array'],
            'discounts.*.type' => ['nullable', 'string', 'in:percent,amount'],
            'discounts.*.value' => ['nullable', 'string', 'max:20'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach (DiscountForm::parse($this->rawDiscounts())['errors'] as $id => $message) {
                    $validator->errors()->add("discounts.{$id}.value", $message);
                }
            },
        ];
    }

    /**
     * @return array<int, DiscountInput>
     */
    public function discounts(): array
    {
        return DiscountForm::parse($this->rawDiscounts())['discounts'];
    }

    /**
     * @return array<mixed>
     */
    private function rawDiscounts(): array
    {
        $raw = $this->input('discounts', []);

        return is_array($raw) ? $raw : [];
    }
}
