<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Enums\OrderStatus;
use App\Enums\RestaurantStatus;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\RestaurantResource;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class PlatformOverviewController extends Controller
{
    /**
     * A platform-wide snapshot for the super-admin dashboard: how many
     * restaurants/users exist, today's order volume and takings across every
     * tenant, and the most recent restaurant signups.
     */
    public function index(): JsonResponse
    {
        [$start, $end] = $this->todayRange();

        return ApiResponse::success([
            'restaurants' => $this->restaurants(),
            'users' => $this->users(),
            'orders' => $this->orders($start, $end),
            'recent_restaurants' => RestaurantResource::collection(
                Restaurant::query()
                    ->with('users')
                    ->withCount(['users', 'orders'])
                    ->latest()
                    ->limit(5)
                    ->get(),
            ),
        ], 'Platform overview retrieved.');
    }

    /**
     * Today in the application timezone, as UTC instants for comparison against
     * the stored timestamps.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function todayRange(): array
    {
        $dayStart = Carbon::now(config('app.timezone'))->startOfDay();

        return [
            $dayStart->copy()->utc(),
            $dayStart->copy()->addDay()->utc(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function restaurants(): array
    {
        $byStatus = Restaurant::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            'total' => (int) $byStatus->sum(),
            'active' => (int) $byStatus->get(RestaurantStatus::Active->value, 0),
            'pending' => (int) $byStatus->get(RestaurantStatus::Pending->value, 0),
            'suspended' => (int) $byStatus->get(RestaurantStatus::Suspended->value, 0),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function users(): array
    {
        return [
            'total' => User::query()->count(),
            'active' => User::query()->where('is_active', true)->count(),
        ];
    }

    /**
     * Platform-wide order volume and takings for today.
     *
     * @return array<string, float|int>
     */
    private function orders(Carbon $start, Carbon $end): array
    {
        $completed = Order::query()
            ->where('status', OrderStatus::Completed->value)
            ->whereBetween('completed_at', [$start, $end]);

        return [
            'today' => Order::query()->whereBetween('created_at', [$start, $end])->count(),
            'completed_today' => (clone $completed)->count(),
            'revenue_today' => (float) $completed->sum('total'),
            'total' => Order::query()->count(),
        ];
    }
}
