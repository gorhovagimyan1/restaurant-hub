<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a bulk replacement of a restaurant's special days (holidays and
 * one-off overrides). An empty list clears them all.
 */
class UpdateSpecialHoursRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'special_hours' => ['present', 'array', 'max:366'],
            'special_hours.*.date' => ['required', 'date_format:Y-m-d', 'distinct'],
            'special_hours.*.is_closed' => ['required', 'boolean'],
            'special_hours.*.open_time' => ['nullable', 'date_format:H:i'],
            'special_hours.*.close_time' => ['nullable', 'date_format:H:i'],
            'special_hours.*.label' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * An open special day must carry both an open and a close time.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach ((array) $this->input('special_hours', []) as $index => $day) {
                if (($day['is_closed'] ?? false)) {
                    continue;
                }

                foreach (['open_time', 'close_time'] as $field) {
                    if (empty($day[$field])) {
                        $validator->errors()->add(
                            "special_hours.{$index}.{$field}",
                            'An open day needs both an opening and closing time.',
                        );
                    }
                }
            }
        });
    }
}
