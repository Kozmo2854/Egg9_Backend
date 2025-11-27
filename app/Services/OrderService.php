<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * Service class for order-related business logic
 */
class OrderService
{
    /**
     * Format order data for API response
     *
     * @param Order $order
     * @return array
     */
    public function formatOrder(Order $order): array
    {
        return [
            'id' => $order->id,
            'userId' => $order->user_id,
            'subscriptionId' => $order->subscription_id,
            'weekId' => $order->week_id,
            'quantity' => $order->quantity,
            'total' => (float) $order->total,
            'status' => $order->status,
            'isPaid' => $order->is_paid,
            'paymentSubmitted' => $order->payment_submitted,
            'pickedUp' => $order->picked_up,
            'weekStart' => $order->week->week_start->toISOString(),
            'createdAt' => $order->created_at->toISOString(),
            'updatedAt' => $order->updated_at->toISOString(),
        ];
    }

    /**
     * Validate order ownership
     *
     * @param Order $order
     * @param User $user
     * @return JsonResponse|null Returns error response if validation fails, null if passes
     */
    public function validateOwnership(Order $order, User $user): ?JsonResponse
    {
        if ($order->user_id !== $user->id) {
            return response()->json([
                'message' => 'Unauthorized to access this order',
            ], 403);
        }

        return null;
    }

    /**
     * Validate order can be modified
     *
     * @param Order $order
     * @return JsonResponse|null Returns error response if validation fails, null if passes
     */
    public function validateCanBeModified(Order $order): ?JsonResponse
    {
        if (!$order->canBeModified()) {
            return response()->json([
                'message' => 'Only pending and unpaid orders can be modified',
            ], 400);
        }

        return null;
    }

    /**
     * Validate order is not subscription-generated
     *
     * @param Order $order
     * @param string $action Action being performed (e.g., 'update', 'cancel')
     * @return JsonResponse|null Returns error response if validation fails, null if passes
     */
    public function validateNotSubscriptionOrder(Order $order, string $action = 'modify'): ?JsonResponse
    {
        if ($order->subscription_id) {
            return response()->json([
                'message' => "Cannot {$action} subscription orders directly. Please modify your subscription instead.",
            ], 400);
        }

        return null;
    }

    /**
     * Get orders needing payment submission for a user
     *
     * @param User $user
     * @param int $weekId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getUnpaidOrdersForWeek(User $user, int $weekId)
    {
        return Order::where('user_id', $user->id)
            ->where('week_id', $weekId)
            ->where('is_paid', false)
            ->where('payment_submitted', false)
            ->get();
    }

    /**
     * Get delivered orders needing pickup confirmation
     *
     * @param User $user
     * @param int $weekId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getOrdersNeedingPickup(User $user, int $weekId)
    {
        return Order::where('user_id', $user->id)
            ->where('week_id', $weekId)
            ->whereIn('status', ['delivered', 'completed'])
            ->where('picked_up', false)
            ->get();
    }

    /**
     * Calculate order total based on quantity and price per dozen
     *
     * @param int $quantity
     * @param float $pricePerDozen
     * @return float
     */
    public function calculateTotal(int $quantity, float $pricePerDozen): float
    {
        return ($quantity / 10) * $pricePerDozen;
    }
}

