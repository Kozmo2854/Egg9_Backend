<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Week;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    protected OrderService $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }
    /**
     * Get all orders for the authenticated user
     */
    public function index(Request $request)
    {
        $orders = $request->user()->orders()
            ->with('week')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'orders' => $orders->map(fn($order) => $this->orderService->formatOrder($order)),
        ]);
    }

    /**
     * Get user's order for the current week (one-time orders only)
     */
    public function getCurrentWeekOrder(Request $request)
    {
        $week = Week::getCurrentWeek();

        if (!$week) {
            return response()->json([
                'order' => null,
            ]);
        }

        $order = Order::where('user_id', $request->user()->id)
            ->where('week_id', $week->id)
            ->whereNull('subscription_id') // Only one-time orders
            ->with('week')
            ->first();

        if (!$order) {
            return response()->json([
                'order' => null,
            ]);
        }

        return response()->json([
            'order' => $this->orderService->formatOrder($order),
        ]);
    }

    /**
     * Get user's subscription order for the current week
     */
    public function getCurrentWeekSubscriptionOrder(Request $request)
    {
        $week = Week::getCurrentWeek();

        if (!$week) {
            return response()->json([
                'order' => null,
            ]);
        }

        $order = Order::where('user_id', $request->user()->id)
            ->where('week_id', $week->id)
            ->whereNotNull('subscription_id') // Only subscription orders
            ->with('week')
            ->first();

        if (!$order) {
            return response()->json([
                'order' => null,
            ]);
        }

        return response()->json([
            'order' => $this->orderService->formatOrder($order),
        ]);
    }

    /**
     * Create a new order for the current week
     */
    public function store(Request $request)
    {
        $request->validate([
            'quantity' => 'required|integer|min:10',
        ]);

        // Validate quantity is multiple of 10
        if ($request->quantity % 10 !== 0) {
            throw ValidationException::withMessages([
                'quantity' => ['Quantity must be a multiple of 10.'],
            ]);
        }

        $week = Week::getCurrentWeek();

        if (!$week) {
            return response()->json([
                'message' => 'No week available for ordering',
            ], 400);
        }

        // Check if ordering is open for this week
        if (!$week->is_ordering_open) {
            return response()->json([
                'message' => 'Ordering is closed for this week',
            ], 400);
        }

        // Check if orders for this week are already delivered
        if ($week->all_orders_delivered) {
            return response()->json([
                'message' => 'Orders for this week have already been delivered. Please wait for the next week.',
            ], 400);
        }

        // Check low season order cap
        $lowSeasonCap = $week->getLowSeasonOrderCap();
        if ($lowSeasonCap !== null && $request->quantity > $lowSeasonCap) {
            return response()->json([
                'message' => 'low_season_cap',
                'description' => "During low season, one-time orders are limited to {$lowSeasonCap} eggs maximum.",
                'maxAllowed' => $lowSeasonCap,
                'requestedQuantity' => $request->quantity,
                'isLowSeason' => true,
            ], 400);
        }

        // Check if user already has a one-time order for this week
        // Note: Users can have both a subscription order AND a one-time order
        $existingOrder = Order::where('user_id', $request->user()->id)
            ->where('week_id', $week->id)
            ->where('status', 'pending')
            ->whereNull('subscription_id') // Only check for one-time orders
            ->first();

        if ($existingOrder) {
            return response()->json([
                'message' => 'You already have a pending order for this week. Please update it instead.',
            ], 400);
        }

        // Check available eggs
        $availableEggs = $week->getAvailableEggsForUser($request->user()->id);
        if ($request->quantity > $availableEggs) {
            return response()->json([
                'message' => 'Insufficient stock available',
                'availableEggs' => $availableEggs,
            ], 400);
        }

        // Create the order
        $total = Order::calculateTotal($request->quantity, $week->price_per_dozen);

        $order = Order::create([
            'user_id' => $request->user()->id,
            'week_id' => $week->id,
            'quantity' => $request->quantity,
            'total' => $total,
            'status' => 'pending',
        ]);

        $order->load('week');

        return response()->json([
            'order' => $this->formatOrder($order),
        ], 201);
    }

    /**
     * Update an existing pending order
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'quantity' => 'required|integer|min:10',
        ]);

        // Validate quantity is multiple of 10
        if ($request->quantity % 10 !== 0) {
            throw ValidationException::withMessages([
                'quantity' => ['Quantity must be a multiple of 10.'],
            ]);
        }

        $order = Order::with('week')->find($id);

        if (!$order) {
            return response()->json([
                'message' => 'Order not found',
            ], 404);
        }

        // Check ownership
        if ($order->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized to update this order',
            ], 403);
        }

        // Check if order can be modified
        if (!$order->canBeModified()) {
            return response()->json([
                'message' => 'Only pending orders can be updated',
            ], 400);
        }

        // Prevent updating subscription-generated orders directly
        if ($order->subscription_id) {
            return response()->json([
                'message' => 'Cannot update subscription orders directly. Please modify your subscription instead.',
            ], 400);
        }

        $week = Week::getCurrentWeek();

        if (!$week || $order->week_id !== $week->id) {
            return response()->json([
                'message' => 'Cannot update order for past or closed weeks',
            ], 400);
        }

        // Check if ordering is open for this week
        if (!$week->is_ordering_open) {
            return response()->json([
                'message' => 'Ordering is closed for this week',
            ], 400);
        }

        // Check low season order cap (only for one-time orders, not subscription orders)
        if (!$order->subscription_id) {
            $lowSeasonCap = $week->getLowSeasonOrderCap();
            if ($lowSeasonCap !== null && $request->quantity > $lowSeasonCap) {
                return response()->json([
                    'message' => 'low_season_cap',
                    'description' => "During low season, one-time orders are limited to {$lowSeasonCap} eggs maximum.",
                    'maxAllowed' => $lowSeasonCap,
                    'requestedQuantity' => $request->quantity,
                    'isLowSeason' => true,
                ], 400);
            }
        }

        // Check available eggs (including user's current order)
        $availableEggs = $week->getAvailableEggsForUser($request->user()->id);
        if ($request->quantity > $availableEggs) {
            return response()->json([
                'message' => 'Insufficient stock available',
                'availableEggs' => $availableEggs,
            ], 400);
        }

        // Update the order (recalculate total with current week's price)
        $total = Order::calculateTotal($request->quantity, $week->price_per_dozen);

        $order->update([
            'quantity' => $request->quantity,
            'total' => $total,
        ]);

        $order->load('week');

        return response()->json([
            'order' => $this->orderService->formatOrder($order),
        ]);
    }

    /**
     * Cancel (delete) a pending order
     */
    public function destroy(Request $request, $id)
    {
        $order = Order::find($id);

        if (!$order) {
            return response()->json([
                'message' => 'Order not found',
            ], 404);
        }

        // Check ownership
        if ($order->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized to delete this order',
            ], 403);
        }

        // Check if order can be modified
        if (!$order->canBeModified()) {
            return response()->json([
                'message' => 'Only pending orders can be cancelled',
            ], 400);
        }

        // Prevent cancelling subscription-generated orders directly
        if ($order->subscription_id) {
            return response()->json([
                'message' => 'Cannot cancel subscription orders directly. Please cancel your subscription instead.',
            ], 400);
        }

        $order->delete();

        return response()->json([
            'message' => 'Order cancelled successfully',
        ]);
    }

    /**
     * Mark order payment as submitted by user
     */
    public function markPaymentSubmitted(Request $request, $id)
    {
        $order = Order::with('week')->find($id);

        if (!$order) {
            return response()->json([
                'message' => 'Order not found',
            ], 404);
        }

        // Check ownership
        if ($order->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized to update this order',
            ], 403);
        }

        // Only allow marking payment for pending or delivered orders (not completed)
        if ($order->status === 'completed') {
            return response()->json([
                'message' => 'Cannot mark payment for completed orders',
            ], 400);
        }

        $order->update(['payment_submitted' => true]);

        return response()->json([
            'message' => 'Payment marked as submitted successfully',
            'order' => $this->formatOrder($order),
        ]);
    }

    /**
     * Confirm pickup by user (for delivered orders)
     * Also checks if order should be marked as completed
     */
    public function confirmPickup(Request $request, $id)
    {
        $order = Order::with('week')->find($id);

        if (!$order) {
            return response()->json([
                'message' => 'Order not found',
            ], 404);
        }

        // Check ownership
        if ($order->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized to update this order',
            ], 403);
        }

        // Only allow confirming delivered orders
        if (!in_array($order->status, ['delivered', 'completed'])) {
            return response()->json([
                'message' => 'Only delivered orders can be confirmed as picked up',
            ], 400);
        }

        $order->update(['picked_up' => true]);
        
        // Check if order is now complete (delivered + paid + picked up)
        $order->refresh();
        $order->checkAndUpdateCompletion();

        return response()->json([
            'message' => 'Pickup confirmed successfully',
            'order' => $this->formatOrder($order),
        ]);
    }

    /**
     * Format order data for API response
     */
}
