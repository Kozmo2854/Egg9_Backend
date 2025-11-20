<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\WeeklyStock;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    /**
     * Get all orders for the authenticated user
     */
    public function index(Request $request)
    {
        $orders = $request->user()->orders()
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'orders' => $orders->map(function ($order) {
                return [
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
                ];
            }),
        ]);
    }

    /**
     * Get user's order for the current week
     */
    public function getCurrentWeekOrder(Request $request)
    {
        $weeklyStock = WeeklyStock::getCurrentWeek();

        if (!$weeklyStock) {
            return response()->json([
                'order' => null,
            ]);
        }

        $order = Order::where('user_id', $request->user()->id)
            ->where('week_start', $weeklyStock->week_start)
            ->first();

        if (!$order) {
            return response()->json([
                'order' => null,
            ]);
        }

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

        $weeklyStock = WeeklyStock::getCurrentWeek();

        if (!$weeklyStock) {
            return response()->json([
                'message' => 'No weekly stock available for ordering',
            ], 400);
        }

        // Check if user already has an order for this week
        $existingOrder = Order::where('user_id', $request->user()->id)
            ->where('week_start', $weeklyStock->week_start)
            ->where('status', 'pending')
            ->first();

        if ($existingOrder) {
            return response()->json([
                'message' => 'You already have a pending order for this week. Please update it instead.',
            ], 400);
        }

        // Check available eggs
        $availableEggs = $weeklyStock->getAvailableEggsForUser($request->user()->id);
        if ($request->quantity > $availableEggs) {
            return response()->json([
                'message' => 'Insufficient stock available',
                'availableEggs' => $availableEggs,
            ], 400);
        }

        // Create the order
        $total = Order::calculateTotal($request->quantity, $weeklyStock->price_per_dozen);

        $order = Order::create([
            'user_id' => $request->user()->id,
            'quantity' => $request->quantity,
            'price_per_dozen' => $weeklyStock->price_per_dozen,
            'total' => $total,
            'status' => 'pending',
            'delivery_status' => 'not_delivered',
            'week_start' => $weeklyStock->week_start,
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

        $order = Order::find($id);

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

        $weeklyStock = WeeklyStock::getCurrentWeek();

        if (!$weeklyStock || $order->week_start->notEqualTo($weeklyStock->week_start)) {
            return response()->json([
                'message' => 'Cannot update order for past or closed weeks',
            ], 400);
        }

        // Check available eggs (including user's current order)
        $availableEggs = $weeklyStock->getAvailableEggsForUser($request->user()->id);
        if ($request->quantity > $availableEggs) {
            return response()->json([
                'message' => 'Insufficient stock available',
                'availableEggs' => $availableEggs,
            ], 400);
        }

        // Update the order
        $total = Order::calculateTotal($request->quantity, $order->price_per_dozen);

        $order->update([
            'quantity' => $request->quantity,
            'total' => $total,
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

        $order->delete();

        return response()->json([
            'message' => 'Order cancelled successfully',
        ]);
    }
}

