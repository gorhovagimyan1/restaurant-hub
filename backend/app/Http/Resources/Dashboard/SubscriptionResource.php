<?php

namespace App\Http\Resources\Dashboard;

use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The current billing state, as the dashboard needs it: enough to render the
 * countdown banner, the billing panel and the checkout screen.
 *
 * @mixin Subscription
 */
class SubscriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'interval' => $this->interval?->value,
            'on_trial' => $this->onTrial(),
            'has_access' => $this->hasAccess(),
            'days_remaining' => $this->daysRemaining(),
            'access_ends_at' => $this->accessEndsAt()?->toIso8601String(),
            'trial_ends_at' => $this->trial_ends_at?->toIso8601String(),
            'current_period_end' => $this->current_period_end?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            // Null means nothing has ever been paid for — which is how the
            // client tells "trial ran out" from "subscription lapsed".
            'plan' => $this->plan ? new PlanResource($this->plan) : null,
        ];
    }
}
