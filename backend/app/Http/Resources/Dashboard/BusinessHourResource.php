<?php

namespace App\Http\Resources\Dashboard;

use App\Models\BusinessHour;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A single weekday's opening hours.
 *
 * @mixin BusinessHour
 */
class BusinessHourResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'day_of_week' => $this->day_of_week->value,
            'day_label' => $this->day_of_week->label(),
            'is_closed' => $this->is_closed,
            // Stored as TIME (HH:MM:SS); expose the HH:MM the inputs use.
            'open_time' => $this->formatTime($this->open_time),
            'close_time' => $this->formatTime($this->close_time),
        ];
    }

    private function formatTime(?string $time): ?string
    {
        return $time ? substr($time, 0, 5) : null;
    }
}
