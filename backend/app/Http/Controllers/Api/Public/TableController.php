<?php

namespace App\Http\Controllers\Api\Public;

use App\Enums\OrderStatus;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\Public\TableResource;
use App\Models\TableQrCode;
use Illuminate\Http\JsonResponse;

class TableController extends Controller
{
    /**
     * Resolve a scanned QR token into the table + restaurant context the
     * customer ordering screen needs. Bound by the QR token route key.
     */
    public function show(TableQrCode $tableQrCode): JsonResponse
    {
        $tableQrCode->load('table.restaurant.settings');

        abort_unless($tableQrCode->table->restaurant->is_active, 404);

        return ApiResponse::success(
            new TableResource($tableQrCode),
            'Table resolved.',
        );
    }

    /**
     * The table's running bill: every order placed this visit that hasn't been
     * settled yet, plus the combined total. The customer pays this once when
     * they leave — orders are not paid individually.
     */
    public function bill(TableQrCode $tableQrCode): JsonResponse
    {
        $table = $tableQrCode->table()->with('restaurant')->firstOrFail();

        $orders = $table->orders()
            ->whereNotIn('status', [OrderStatus::Completed->value, OrderStatus::Cancelled->value])
            ->with('items')
            ->orderBy('created_at')
            ->get();

        return ApiResponse::success([
            'table' => ['name' => $table->name],
            'currency' => $table->restaurant->currency,
            'bill_requested' => (bool) $table->bill_requested_at,
            'orders' => $orders->map(fn ($order) => [
                'order_number' => $order->order_number,
                'status' => $order->status->value,
                'total' => (float) $order->total,
                'created_at' => $order->created_at?->toIso8601String(),
                'items' => $order->items->map(fn ($item) => [
                    'product_name' => $item->product_name,
                    'quantity' => $item->quantity,
                    'total_price' => (float) $item->total_price,
                ]),
            ]),
            'subtotal' => (float) $orders->sum('subtotal'),
            'tax' => (float) $orders->sum('tax'),
            'service_charge' => (float) $orders->sum('service_charge'),
            'total' => (float) $orders->sum('total'),
        ], 'Bill retrieved.');
    }

    /**
     * Signal staff that the guest is ready to pay and leave.
     */
    public function requestBill(TableQrCode $tableQrCode): JsonResponse
    {
        $table = $tableQrCode->table()->firstOrFail();

        if ($table->bill_requested_at === null) {
            $table->update(['bill_requested_at' => now()]);
        }

        return ApiResponse::success(null, 'A staff member will bring your bill shortly.');
    }

    /**
     * Call a waiter over to the table.
     */
    public function callWaiter(TableQrCode $tableQrCode): JsonResponse
    {
        $table = $tableQrCode->table()->with('restaurant.settings')->firstOrFail();
        $settings = $table->restaurant->settings;

        abort_if(
            $settings && ! $settings->enable_waiter_call,
            422,
            'Calling a waiter is not available right now.',
        );

        if ($table->waiter_called_at === null) {
            $table->update(['waiter_called_at' => now()]);
        }

        return ApiResponse::success(null, 'A waiter is on the way.');
    }
}
