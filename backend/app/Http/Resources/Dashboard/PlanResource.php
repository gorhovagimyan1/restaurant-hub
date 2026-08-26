<?php

namespace App\Http\Resources\Dashboard;

use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A plan as the checkout screen renders it.
 *
 * @mixin Plan
 */
class PlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'currency' => $this->currency,
            'monthly_price' => (float) $this->monthly_price,
            'yearly_price' => (float) $this->yearly_price,
            // What a year works out to per month, and what that saves — the
            // two numbers the yearly option has to justify itself with.
            'yearly_monthly_equivalent' => round((float) $this->yearly_price / 12, 2),
            'yearly_saving_percent' => $this->yearlySavingPercent(),
            'features' => $this->features ?? [],
        ];
    }
}
