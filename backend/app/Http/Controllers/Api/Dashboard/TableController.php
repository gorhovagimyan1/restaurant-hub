<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Enums\OrderStatus;
use App\Enums\TableStatus;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Concerns\ResolvesRestaurant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\StoreTableRequest;
use App\Http\Resources\Dashboard\TableResource;
use App\Models\Order;
use App\Models\RestaurantTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TableController extends Controller
{
    use ResolvesRestaurant;

    /**
     * All tables for the current restaurant, each with its QR token.
     */
    public function index(Request $request): JsonResponse
    {
        $tables = $this->currentRestaurant($request)
            ->tables()
            ->with('qrCode')
            ->orderBy('id')
            ->get();

        return ApiResponse::success(
            TableResource::collection($tables),
            'Tables retrieved.',
        );
    }

    /**
     * Create a table and generate its QR code in one step.
     */
    public function store(StoreTableRequest $request): JsonResponse
    {
        $restaurant = $this->currentRestaurant($request);
        $data = $request->validated();

        $table = $restaurant->tables()->create([
            'name' => $data['name'],
            'capacity' => $data['capacity'] ?? 4,
            // Set explicitly so the freshly-created instance carries the enum
            // rather than a null (the column otherwise relies on a DB default).
            'status' => TableStatus::Available,
        ]);

        // The QR token auto-generates on create (see TableQrCode::booted).
        $table->qrCode()->create([]);

        return ApiResponse::created(
            new TableResource($table->load('qrCode')),
            'Table created.',
        );
    }

    /**
     * Tables currently asking for attention — a waiter call or a bill request.
     * Polled by the staff boards.
     */
    public function serviceCalls(Request $request): JsonResponse
    {
        $tables = $this->currentRestaurant($request)
            ->tables()
            ->where(fn ($q) => $q->whereNotNull('waiter_called_at')->orWhereNotNull('bill_requested_at'))
            ->orderByRaw('COALESCE(waiter_called_at, bill_requested_at)')
            ->get();

        return ApiResponse::success(
            $tables->map(fn (RestaurantTable $table) => [
                'id' => $table->id,
                'name' => $table->name,
                'waiter_called' => (bool) $table->waiter_called_at,
                'bill_requested' => (bool) $table->bill_requested_at,
                'since' => optional($table->waiter_called_at ?? $table->bill_requested_at)->toIso8601String(),
            ]),
            'Service calls retrieved.',
        );
    }

    /**
     * Acknowledge (clear) a table's waiter call.
     */
    public function acknowledgeCall(Request $request, RestaurantTable $table): JsonResponse
    {
        $restaurant = $this->currentRestaurant($request);
        $this->guardTenant($restaurant, $table);

        $table->update(['waiter_called_at' => null]);

        return ApiResponse::success(null, 'Call acknowledged.');
    }

    /**
     * Settle the table's bill: mark every open order as paid & completed,
     * clear any bill request, and free the table. This is the single payment
     * step taken when the customer leaves — orders are not paid individually.
     */
    public function settle(Request $request, RestaurantTable $table): JsonResponse
    {
        $restaurant = $this->currentRestaurant($request);
        $this->guardTenant($restaurant, $table);

        DB::transaction(function () use ($table) {
            $table->orders()
                ->whereNotIn('status', [OrderStatus::Completed->value, OrderStatus::Cancelled->value])
                ->get()
                ->each(function (Order $order) {
                    $order->update([
                        'status' => OrderStatus::Completed,
                        'completed_at' => $order->completed_at ?? now(),
                    ]);
                });

            // End the dining session for this visit. This is the single point
            // the QR "expires": afterwards the guest's stale session token is
            // rejected and they must scan again to open a fresh session.
            $table->openSession()->first()?->close();

            $table->update([
                'status' => TableStatus::Available,
                'bill_requested_at' => null,
                'waiter_called_at' => null,
            ]);
        });

        return ApiResponse::success(null, 'Table settled and freed.');
    }

    /**
     * Delete a table (and its QR code via cascade).
     */
    public function destroy(Request $request, RestaurantTable $table): JsonResponse
    {
        $restaurant = $this->currentRestaurant($request);
        $this->guardTenant($restaurant, $table);

        $table->delete();

        return ApiResponse::success(null, 'Table deleted.');
    }
}
