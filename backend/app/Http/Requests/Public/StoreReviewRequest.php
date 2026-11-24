<?php

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A guest rating one or more dishes they were served this visit.
 */
class StoreReviewRequest extends FormRequest
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
            'session_token' => ['required', 'uuid'],
            'reviews' => ['required', 'array', 'min:1', 'max:30'],
            'reviews.*.order_item_id' => ['required', 'integer', 'distinct'],
            'reviews.*.rating' => ['required', 'integer', 'between:1,5'],
            'reviews.*.comment' => ['nullable', 'string', 'max:500'],
        ];
    }
}
