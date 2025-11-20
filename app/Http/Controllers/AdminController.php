<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Subscription;
use App\Models\WeeklyStock;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    /**
     * Get all orders with user names
     */
    public function getAllOrders(Request $request)
    {
        $orders = Order::with('user')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'orders' => $orders->map(function ($order) {
                return [
                    'id' => $order->id,
                    'userId' => $order->user_id,
                    'userName' => $order->user->name,
                    'userEmail' => $order->user->email,
                    'quantity' => $order->quantity,
                    'pricePerDozen' => (float) $order->price_per_dozen,
                    'total' => (float) $order->total,
                    'status' => $order->status,
                    'deliveryStatus' => $order->delivery_status,
                    'weekStart' => $order->week_start->toISOString(),
                    'createdAt' => $order->created_at->toISOString(),
                    'updatedAt' => $order->updated_at->toISOString(),
                ];
            }),
        ]);
    }

    /**
     * Get all subscriptions with user names
     */
    public function getAllSubscriptions(Request $request)
    {
        $subscriptions = Subscription::with('user')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'subscriptions' => $subscriptions->map(function ($subscription) {
                return [
                    'id' => $subscription->id,
                    'userId' => $subscription->user_id,
                    'userName' => $subscription->user->name,
                    'userEmail' => $subscription->user->email,
                    'quantity' => $subscription->quantity,
                    'frequency' => $subscription->frequency,
                    'period' => $subscription->period,
                    'weeksRemaining' => $subscription->weeks_remaining,
                    'status' => $subscription->status,
                    'nextDelivery' => $subscription->next_delivery ? $subscription->next_delivery->toISOString() : null,
                    'createdAt' => $subscription->created_at->toISOString(),
                    'updatedAt' => $subscription->updated_at->toISOString(),
                ];
            }),
        ]);
    }

    /**
     * Update the available eggs for current week
     */
    public function updateWeeklyStock(Request $request)
    {
        $request->validate([
            'availableEggs' => 'required|integer|min:0',
        ]);

        $weeklyStock = WeeklyStock::getCurrentWeek();

        if (!$weeklyStock) {
            return response()->json([
                'message' => 'No weekly stock available',
            ], 404);
        }

        $weeklyStock->update([
            'available_eggs' => $request->availableEggs,
        ]);

        return response()->json([
            'weeklyStock' => [
                'id' => $weeklyStock->id,
                'weekStart' => $weeklyStock->week_start->toISOString(),
                'weekEnd' => $weeklyStock->week_end->toISOString(),
                'availableEggs' => $weeklyStock->available_eggs,
                'pricePerDozen' => (float) $weeklyStock->price_per_dozen,
                'isOrderingOpen' => $weeklyStock->is_ordering_open,
                'deliveryDate' => $weeklyStock->delivery_date ? $weeklyStock->delivery_date->toISOString() : null,
                'deliveryTime' => $weeklyStock->delivery_time,
                'allOrdersDelivered' => $weeklyStock->all_orders_delivered,
            ],
        ]);
    }

    /**
     * Update delivery date and time for current week
     */
    public function updateDeliveryInfo(Request $request)
    {
        $request->validate([
            'deliveryDate' => 'required|date',
            'deliveryTime' => 'required|string',
        ]);

        $weeklyStock = WeeklyStock::getCurrentWeek();

        if (!$weeklyStock) {
            return response()->json([
                'message' => 'No weekly stock available',
            ], 404);
        }

        $weeklyStock->update([
            'delivery_date' => $request->deliveryDate,
            'delivery_time' => $request->deliveryTime,
        ]);

        return response()->json([
            'weeklyStock' => [
                'id' => $weeklyStock->id,
                'weekStart' => $weeklyStock->week_start->toISOString(),
                'weekEnd' => $weeklyStock->week_end->toISOString(),
                'availableEggs' => $weeklyStock->available_eggs,
                'pricePerDozen' => (float) $weeklyStock->price_per_dozen,
                'isOrderingOpen' => $weeklyStock->is_ordering_open,
                'deliveryDate' => $weeklyStock->delivery_date->toISOString(),
                'deliveryTime' => $weeklyStock->delivery_time,
                'allOrdersDelivered' => $weeklyStock->all_orders_delivered,
            ],
        ]);
    }

    /**
     * Mark all orders for current week as delivered
     */
    public function markAllOrdersDelivered(Request $request)
    {
        $weeklyStock = WeeklyStock::getCurrentWeek();

        if (!$weeklyStock) {
            return response()->json([
                'message' => 'No weekly stock available',
            ], 404);
        }

        // Mark all orders for this week as delivered
        $updatedCount = Order::where('week_start', $weeklyStock->week_start)
            ->update([
                'delivery_status' => 'delivered',
            ]);

        // Update weekly stock flag
        $weeklyStock->update([
            'all_orders_delivered' => true,
        ]);

        return response()->json([
            'message' => "Successfully marked {$updatedCount} orders as delivered",
            'updatedCount' => $updatedCount,
        ]);
    }

    /**
     * Approve an order
     */
    public function approveOrder(Request $request, $id)
    {
        $order = Order::find($id);

        if (!$order) {
            return response()->json([
                'message' => 'Order not found',
            ], 404);
        }

        if ($order->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending orders can be approved',
            ], 400);
        }

        $order->update([
            'status' => 'approved',
        ]);

        return response()->json([
            'order' => [
                'id' => $order->id,
                'userId' => $order->user_id,
                'quantity' => $order->quantity,
                'pricePerDozen' => (float) $order->price_per_dozen,
                'total' => (float) $order->total,
                'status' => $order->status,
                'deliveryStatus' => $order->delivery_status,
                'weekStart' => $order->week_start->toISOString(),
                'createdAt' => $order->created_at->toISOString(),
                'updatedAt' => $order->updated_at->toISOString(),
            ],
        ]);
    }

    /**
     * Decline an order
     */
    public function declineOrder(Request $request, $id)
    {
        $order = Order::find($id);

        if (!$order) {
            return response()->json([
                'message' => 'Order not found',
            ], 404);
        }

        if ($order->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending orders can be declined',
            ], 400);
        }

        $order->update([
            'status' => 'declined',
        ]);

        return response()->json([
            'order' => [
                'id' => $order->id,
                'userId' => $order->user_id,
                'quantity' => $order->quantity,
                'pricePerDozen' => (float) $order->price_per_dozen,
                'total' => (float) $order->total,
                'status' => $order->status,
                'deliveryStatus' => $order->delivery_status,
                'weekStart' => $order->week_start->toISOString(),
                'createdAt' => $order->created_at->toISOString(),
                'updatedAt' => $order->updated_at->toISOString(),
            ],
        ]);
    }
}

