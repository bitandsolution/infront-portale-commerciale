<?php

namespace App\Http\Requests\Sports;

use App\Domain\Sports\Data\StadiumData;
use App\Http\Requests\Sports\Concerns\HasSportsAttributes;
use App\Models\Stadium;
use Illuminate\Foundation\Http\FormRequest;

/**
 * New stadium or change of a stadium (M02 F-07): name, city, optional capacity, active.
 */
class StadiumRequest extends FormRequest
{
    use HasSportsAttributes;

    public function authorize(): bool
    {
        $stadium = $this->route('stadium');

        return $stadium instanceof Stadium
            ? $this->user()?->can('update', $stadium) ?? false
            : $this->user()?->can('create', Stadium::class) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'city' => ['required', 'string', 'max:100'],
            'capacity' => ['bail', 'nullable', 'integer', 'between:1,200000'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function stadiumData(): StadiumData
    {
        $capacity = $this->input('capacity');

        return new StadiumData(
            name: $this->string('name')->trim()->toString(),
            city: $this->string('city')->trim()->toString(),
            capacity: $capacity === null || $capacity === '' ? null : $this->integer('capacity'),
            isActive: $this->boolean('is_active'),
        );
    }
}
