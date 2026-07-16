<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\BusinessHour;
use App\Models\Restaurant;
use App\Models\SpecialHour;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Resolves whether a restaurant is open at a given moment.
 *
 * Precedence for a date: a special-hours override for that date wins over the
 * recurring weekly business hours. A missing schedule means closed. Spans that
 * cross midnight (e.g. 18:00–02:00) are honoured, including the tail that
 * spills into the following day.
 */
class OpeningHours
{
    public function __construct(private readonly Restaurant $restaurant) {}

    /**
     * A serialisable open/closed snapshot for the given moment.
     *
     * @return array<string, mixed>
     */
    public function statusAt(CarbonInterface $at): array
    {
        $timezone = $this->restaurant->timezone ?: config('app.timezone');
        $now = $at->copy()->setTimezone($timezone);

        $today = $now->copy()->startOfDay();
        $yesterday = $today->copy()->subDay();

        $specials = $this->specialsFor($today, $yesterday);
        $business = $this->businessByDay();

        $open = false;
        $closesAt = null;

        // Check today (normal + today's overnight span) and yesterday (whose
        // overnight span may reach into today).
        foreach ([$today, $yesterday] as $date) {
            $schedule = $this->scheduleForDate($date, $specials, $business);

            if (! $this->isWorkingDay($schedule)) {
                continue;
            }

            $start = $date->copy()->setTimeFromTimeString($schedule['open_time']);
            $end = $date->copy()->setTimeFromTimeString($schedule['close_time']);

            if ($end->lessThanOrEqualTo($start)) {
                $end->addDay(); // crosses midnight
            }

            if ($now->greaterThanOrEqualTo($start) && $now->lessThan($end)) {
                $open = true;
                $closesAt = $end;
                break;
            }
        }

        $todaySchedule = $this->scheduleForDate($today, $specials, $business);
        $todayWorking = $this->isWorkingDay($todaySchedule);

        return [
            'open' => $open,
            'now' => $now->toIso8601String(),
            'timezone' => $timezone,
            'closes_at' => $open ? $closesAt->format('H:i') : null,
            'today' => [
                'date' => $today->toDateString(),
                'is_closed' => ! $todayWorking,
                'open_time' => $todayWorking ? $this->hm($todaySchedule['open_time']) : null,
                'close_time' => $todayWorking ? $this->hm($todaySchedule['close_time']) : null,
                'label' => $todaySchedule['label'] ?? null,
                'is_special' => $todaySchedule['is_special'] ?? false,
            ],
        ];
    }

    public function isOpenAt(CarbonInterface $at): bool
    {
        return $this->statusAt($at)['open'];
    }

    /**
     * Special-hour overrides for the two relevant dates, keyed by Y-m-d.
     *
     * @return Collection<string, SpecialHour>
     */
    private function specialsFor(CarbonInterface $today, CarbonInterface $yesterday): Collection
    {
        return $this->restaurant->specialHours()
            ->whereIn('date', [$yesterday->toDateString(), $today->toDateString()])
            ->get()
            ->keyBy(fn (SpecialHour $special) => $special->date->toDateString());
    }

    /**
     * Weekly business hours keyed by ISO weekday (1–7).
     *
     * @return Collection<int, BusinessHour>
     */
    private function businessByDay(): Collection
    {
        $hours = $this->restaurant->relationLoaded('businessHours')
            ? $this->restaurant->businessHours
            : $this->restaurant->businessHours()->get();

        return $hours->keyBy(fn (BusinessHour $hour) => $hour->day_of_week->value);
    }

    /**
     * The effective schedule for a date: special override, else weekday hours,
     * else null (no schedule → closed).
     *
     * @param  Collection<string, SpecialHour>  $specials
     * @param  Collection<int, BusinessHour>  $business
     * @return array<string, mixed>|null
     */
    private function scheduleForDate(CarbonInterface $date, Collection $specials, Collection $business): ?array
    {
        if ($special = $specials->get($date->toDateString())) {
            return [
                'is_closed' => $special->is_closed,
                'open_time' => $special->open_time,
                'close_time' => $special->close_time,
                'label' => $special->label,
                'is_special' => true,
            ];
        }

        if ($hour = $business->get($date->dayOfWeekIso)) {
            return [
                'is_closed' => $hour->is_closed,
                'open_time' => $hour->open_time,
                'close_time' => $hour->close_time,
                'label' => null,
                'is_special' => false,
            ];
        }

        return null;
    }

    /**
     * A day the restaurant is actually open: not closed and carrying both times.
     *
     * @param  array<string, mixed>|null  $schedule
     */
    private function isWorkingDay(?array $schedule): bool
    {
        return $schedule !== null
            && ! $schedule['is_closed']
            && ! empty($schedule['open_time'])
            && ! empty($schedule['close_time']);
    }

    /**
     * Trim a stored TIME (HH:MM:SS) to the HH:MM used everywhere else.
     */
    private function hm(?string $time): ?string
    {
        return $time ? substr($time, 0, 5) : null;
    }
}
