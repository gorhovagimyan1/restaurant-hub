<?php

namespace App\Http\Controllers\Api\Public;

use App\Enums\DiningSessionStatus;
use App\Enums\OrderStatus;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Concerns\ResolvesDiningSession;
use App\Http\Controllers\Controller;
use App\Http\Resources\Public\TableResource;
use App\Models\TableQrCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TableController extends Controller
{
    use ResolvesDiningSession;

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
     * Open (or re-join) the table's dining session for this visit.
     *
     * Called right after a QR scan. There is at most one open session per
     * table, so a second guest scanning the same table joins the existing
     * session — everyone shares one running bill. The returned session_token
     * is what authorizes ordering / bill / service calls until staff settle.
     */
    public function openSession(TableQrCode $tableQrCode): JsonResponse
    {
        $table = $tableQrCode->table()->with('restaurant')->firstOrFail();

        abort_unless($table->restaurant->is_active, 404);

        $session = DB::transaction(function () use ($table) {
            $existing = $table->openSession()->lockForUpdate()->first();

            if ($existing) {
                $existing->touchActivity();

                return $existing;
            }

            return $table->diningSessions()->create([
                'status' => DiningSessionStatus::Open,
            ]);
        });

        return ApiResponse::success([
            'session_token' => $session->session_token,
        ], 'Dining session started.');
    }

    /**
     * The table's running bill: every order placed this visit that hasn't been
     * settled yet, plus the combined total. The customer pays this once when
     * they leave — orders are not paid individually.
     */
    public function bill(Request $request, TableQrCode $tableQrCode): JsonResponse
    {
        $table = $tableQrCode->table()->with('restaurant')->firstOrFail();
        $session = $this->requireOpenSession($table, $request->query('session_token'));

        $orders = $session->orders()
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
     * Live progress of every order placed this visit, for the guest's own
     * "where is my food" screen.
     *
     * Deliberately separate from the bill: this one keeps cancelled orders (a
     * guest needs to know something was rejected) and carries per-item status,
     * because the kitchen advances dishes individually. It is polled, so it
     * stays lean — no restaurant or product payload.
     */
    public function orders(Request $request, TableQrCode $tableQrCode): JsonResponse
    {
        $table = $tableQrCode->table()->with('restaurant')->firstOrFail();
        $session = $this->requireOpenSession($table, $request->query('session_token'));

        $orders = $session->orders()
            ->with('items')
            ->latest('created_at')
            ->get();

        return ApiResponse::success([
            'currency' => $table->restaurant->currency,
            'orders' => $orders->map(fn ($order) => [
                'order_number' => $order->order_number,
                'status' => $order->status->value,
                'is_final' => $order->status->isFinal(),
                'total' => (float) $order->total,
                'created_at' => $order->created_at?->toIso8601String(),
                'items' => $order->items->map(fn ($item) => [
                    'product_name' => $item->product_name,
                    'quantity' => $item->quantity,
                    'status' => $item->status->value,
                    'total_price' => (float) $item->total_price,
                ]),
            ]),
            // Lets the client stop polling once nothing can change again.
            'has_active' => $orders->contains(fn ($order) => ! $order->status->isFinal()),
        ], 'Orders retrieved.');
    }

    /**
     * Signal staff that the guest is ready to pay and leave.
     */
    public function requestBill(Request $request, TableQrCode $tableQrCode): JsonResponse
    {
        $table = $tableQrCode->table()->with('restaurant.settings')->firstOrFail();
        $this->requireOpenSession($table, $request->input('session_token'));

        $settings = $table->restaurant->settings;

        abort_if(
            $settings && ! $settings->enable_bill_request,
            422,
            'Requesting the bill is not available right now.',
        );

        if ($table->bill_requested_at === null) {
            $table->update(['bill_requested_at' => now()]);
        }

        return ApiResponse::success(null, 'A staff member will bring your bill shortly.');
    }

    /**
     * Call a waiter over to the table.
     */
    public function callWaiter(Request $request, TableQrCode $tableQrCode): JsonResponse
    {
        $table = $tableQrCode->table()->with('restaurant.settings')->firstOrFail();
        $this->requireOpenSession($table, $request->input('session_token'));

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
