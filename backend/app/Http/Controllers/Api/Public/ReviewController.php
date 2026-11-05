<?php

namespace App\Http\Controllers\Api\Public;

use App\Enums\OrderItemStatus;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Concerns\ResolvesDiningSession;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreReviewRequest;
use App\Models\OrderItem;
use App\Models\TableQrCode;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReviewController extends Controller
{
    use ResolvesDiningSession;

    /**
     * Dishes from this visit that were served and not rated yet. Works for a
     * little while after the session closes, so a guest who has already paid
     * can still be asked.
     */
    public function pending(Request $request, TableQrCode $tableQrCode): JsonResponse
    {
        $table = $tableQrCode->table()->firstOrFail();
        $session = $this->requireReviewableSession($table, $request->query('session_token'));

        $items = OrderItem::query()
            ->whereHas('order', fn ($q) => $q->where('dining_session_id', $session->id))
            ->where('status', OrderItemStatus::Served->value)
            ->whereNotNull('product_id')
            ->whereDoesntHave('review')
            ->orderBy('id')
            ->get();

        return ApiResponse::success([
            'items' => $items->map(fn (OrderItem $item) => [
                'id' => $item->id,
                'product_name' => $item->product_name,
                'quantity' => $item->quantity,
            ])->values(),
        ], 'Dishes to rate retrieved.');
    }

    /**
     * Rate dishes from this visit.
     *
     * Only lines the guest's own session was actually served can be rated, and
     * each line only once — so ratings come from real diners, not drive-by
     * visitors, without needing an account.
     */
    public function store(StoreReviewRequest $request, TableQrCode $tableQrCode): JsonResponse
    {
        $table = $tableQrCode->table()->with('restaurant')->firstOrFail();
        $session = $this->requireReviewableSession($table, $request->validated('session_token'));

        $submitted = collect($request->validated('reviews'))->keyBy('order_item_id');

        $items = OrderItem::query()
            ->whereIn('id', $submitted->keys())
            ->whereHas('order', fn ($q) => $q->where('dining_session_id', $session->id))
            ->with('review')
            ->get();

        abort_unless($items->count() === $submitted->count(), 422, 'One of the dishes could not be found.');

        foreach ($items as $item) {
            abort_unless(
                $item->status === OrderItemStatus::Served,
                422,
                "You can rate \"{$item->product_name}\" once it has been served.",
            );
            abort_if($item->review !== null, 422, "You have already rated \"{$item->product_name}\".");
            abort_if($item->product_id === null, 422, "\"{$item->product_name}\" can no longer be rated.");
        }

        try {
            DB::transaction(function () use ($items, $submitted, $table, $session) {
                foreach ($items as $item) {
                    $input = $submitted[$item->id];

                    $item->review()->create([
                        'restaurant_id' => $table->restaurant_id,
                        'product_id' => $item->product_id,
                        'dining_session_id' => $session->id,
                        'rating' => $input['rating'],
                        'comment' => filled($input['comment'] ?? null) ? trim($input['comment']) : null,
                    ]);
                }
            });
        } catch (UniqueConstraintViolationException) {
            // Two taps at once: the unique key held, the second one loses.
            abort(422, 'You have already rated one of these dishes.');
        }

        return ApiResponse::created(null, 'Thank you for your feedback!');
    }
}
