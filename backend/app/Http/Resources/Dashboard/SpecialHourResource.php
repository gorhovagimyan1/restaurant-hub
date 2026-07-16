<?php

namespace App\Http\Resources\Dashboard;

use App\Models\SpecialHour;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A single date-specific opening-hour override (holiday / special day).
 *
 * @mixin SpecialHour
 */
class SpecialHourResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->date->format('Y-m-d'),
            'is_closed' => $this->is_closed,
            // Stored as TIME (HH:MM:SS); expose the HH:MM the inputs use.
            'open_time' => $this->formatTime($this->open_time),
            'close_time' => $this->formatTime($this->close_time),
            'label' => $this->label,
        ];
    }

    private function formatTime(?string $time): ?string
    {
        return $time ? substr($time, 0, 5) : null;
    }
}
