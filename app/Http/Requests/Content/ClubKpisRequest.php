<?php

namespace App\Http\Requests\Content;

use App\Domain\Content\Data\KpiInput;
use App\Enums\Ability;
use App\Support\Text;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * KPI and digital of a club in a season (M08 F-05, §9; D-491; F6-01): for every KPI the value, a whole
 * number ≥ 0 typed with or without the separators of the thousands ("1.250.000"), with its source
 * and period, both required with the value. An empty value removes the KPI; its source and period
 * are then ignored.
 */
class ClubKpisRequest extends FormRequest
{
    /** Largest value accepted: 12 digits (999 billions), far above any KPI of D-108. */
    public const MAX_DIGITS = 12;

    public const SOURCE_MAX = 200;

    public const PERIOD_MAX = 100;

    public function authorize(): bool
    {
        return $this->user()?->can(Ability::ContentManage->value) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'season_id' => ['bail', 'required', 'integer', 'exists:seasons,id'],
            'kpis' => ['array'],
            'kpis.*' => ['array'],
            'kpis.*.value' => ['nullable', 'string', 'max:20', function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && trim($value) !== '' && self::parseValue($value) === null) {
                    $fail(Text::get('content.kpis.validation.value_format', ['digits' => (string) self::MAX_DIGITS]));
                }
            }],
            'kpis.*.source' => ['nullable', 'string', 'max:'.self::SOURCE_MAX],
            'kpis.*.period' => ['nullable', 'string', 'max:'.self::PERIOD_MAX],
        ];
    }

    /**
     * @return array<int, Closure>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach ((array) $this->input('kpis', []) as $id => $row) {
                if (! is_array($row) || self::parseValue((string) ($row['value'] ?? '')) === null) {
                    continue;
                }

                foreach (['source', 'period'] as $field) {
                    if (trim((string) ($row[$field] ?? '')) === '') {
                        $validator->errors()->add("kpis.{$id}.{$field}", Text::get('content.kpis.validation.'.$field.'_required'));
                    }
                }
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'kpis.*.value' => Text::get('content.kpis.attributes.value'),
            'kpis.*.source' => Text::get('content.kpis.attributes.source'),
            'kpis.*.period' => Text::get('content.kpis.attributes.period'),
        ];
    }

    /**
     * @return list<KpiInput>
     */
    public function kpiInputs(): array
    {
        $inputs = [];

        foreach ((array) $this->input('kpis', []) as $id => $row) {
            if (! is_numeric($id) || ! is_array($row)) {
                continue;
            }

            $inputs[] = new KpiInput(
                (int) $id,
                self::parseValue((string) ($row['value'] ?? '')),
                is_string($row['source'] ?? null) ? $row['source'] : null,
                is_string($row['period'] ?? null) ? $row['period'] : null,
            );
        }

        return $inputs;
    }

    /**
     * "1.250.000", "1 250 000" or "1250000" → 1250000; null for an empty text or anything else
     * (decimals, signs, letters).
     */
    public static function parseValue(string $text): ?int
    {
        $digits = preg_replace('/[\s.\x{00A0}\x{202F}]/u', '', trim($text));

        if ($digits === null || $digits === '' || preg_match('/^\d{1,'.self::MAX_DIGITS.'}$/', $digits) !== 1) {
            return null;
        }

        return (int) $digits;
    }
}
