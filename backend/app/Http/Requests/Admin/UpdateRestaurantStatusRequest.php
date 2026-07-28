<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\RestaurantStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a super-admin changing a restaurant's platform status.
 */
class UpdateRestaurantStatusRequest extends FormRequest
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
            'status' => ['required', 'string', Rule::in(RestaurantStatus::values())],
        ];
    }
}
