<?php

namespace App\Http\Requests\Dashboard;

use App\Enums\DayOfWeek;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a bulk replacement of a restaurant's weekly opening hours.
 */
class UpdateBusinessHoursRequest extends FormRequest
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
            'hours' => ['required', 'array', 'min:1', 'max:7'],
            'hours.*.day_of_week' => ['required', 'integer', Rule::in(DayOfWeek::values()), 'distinct'],
            'hours.*.is_closed' => ['required', 'boolean'],
            'hours.*.open_time' => ['nullable', 'date_format:H:i'],
            'hours.*.close_time' => ['nullable', 'date_format:H:i'],
        ];
    }

    /**
     * An open day must carry both an open and a close time.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach ((array) $this->input('hours', []) as $index => $hour) {
                if (($hour['is_closed'] ?? false)) {
                    continue;
                }

                foreach (['open_time', 'close_time'] as $field) {
                    if (empty($hour[$field])) {
                        $validator->errors()->add(
                            "hours.{$index}.{$field}",
                            'An open day needs both an opening and closing time.',
                        );
                    }
                }
            }
        });
    }
}
