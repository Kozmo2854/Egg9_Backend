<?php

namespace App\Http\Controllers;

use App\Models\AppSettings;
use App\Models\Order;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Week;
use App\Services\PushNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function __construct(
        private PushNotificationService $pushService
    ) {}
    /**
     * Get all orders with user names
     */
    public function getAllOrders(Request $request)
    {
        $orders = Order::with(['user', 'week'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'orders' => $orders->map(function ($order) {
                return [
                    'id' => $order->id,
                    'userId' => $order->user_id,
                    'subscriptionId' => $order->subscription_id,
                    'weekId' => $order->week_id,
                    'userName' => $order->user->name,
                    'userEmail' => $order->user->email,
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
            }),
        ]);
    }

    /**
     * Get all active subscriptions with user names
     */
    public function getAllSubscriptions(Request $request)
    {
        $subscriptions = Subscription::with('user')
            ->where('status', 'active')
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
     * Get all users with their order and subscription counts
     */
    public function getAllUsers(Request $request)
    {
        $users = User::orderBy('created_at', 'desc')->get();

        return response()->json([
            'users' => $users->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phoneNumber' => $user->phone_number,
                    'role' => $user->role,
                    'createdAt' => $user->created_at->toISOString(),
                ];
            }),
        ]);
    }

    /**
     * Get detailed information about a specific user including orders and subscriptions
     */
    public function getUserDetails(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'message' => 'User not found',
            ], 404);
        }

        // Get all orders for this user with week information
        $orders = Order::where('user_id', $id)
            ->with('week')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'subscriptionId' => $order->subscription_id,
                    'isSubscription' => $order->subscription_id !== null,
                    'weekId' => $order->week_id,
                    'quantity' => $order->quantity,
                    'total' => (float) $order->total,
                    'status' => $order->status,
                    'isPaid' => $order->is_paid,
                    'paymentSubmitted' => $order->payment_submitted,
                    'pickedUp' => $order->picked_up,
                    'weekStart' => $order->week->week_start->toISOString(),
                    'weekEnd' => $order->week->week_end->toISOString(),
                    'createdAt' => $order->created_at->toISOString(),
                ];
            });

        // Calculate total eggs bought (completed orders only)
        $totalEggsBought = Order::where('user_id', $id)
            ->where('status', 'completed')
            ->sum('quantity');

        // Get all subscriptions for this user
        $subscriptions = Subscription::where('user_id', $id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($subscription) {
                return [
                    'id' => $subscription->id,
                    'quantity' => $subscription->quantity,
                    'frequency' => $subscription->frequency,
                    'period' => $subscription->period,
                    'weeksRemaining' => $subscription->weeks_remaining,
                    'status' => $subscription->status,
                    'nextDelivery' => $subscription->next_delivery ? $subscription->next_delivery->toISOString() : null,
                    'createdAt' => $subscription->created_at->toISOString(),
                    'updatedAt' => $subscription->updated_at->toISOString(),
                ];
            });

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phoneNumber' => $user->phone_number,
                'role' => $user->role,
                'createdAt' => $user->created_at->toISOString(),
            ],
            'orders' => $orders,
            'subscriptions' => $subscriptions,
            'totalEggsBought' => $totalEggsBought,
        ]);
    }

    /**
     * Confirm payment for an order (sets is_paid to true)
     * Also checks if order should be marked as completed
     */
    public function confirmPayment(Request $request, $id)
    {
        $order = Order::with('week')->find($id);

        if (!$order) {
            return response()->json([
                'message' => 'Order not found',
            ], 404);
        }

        $order->update(['is_paid' => true]);
        
        // Check if order is now complete (delivered + paid + picked up)
        $order->refresh();
        $order->checkAndUpdateCompletion();

        return response()->json([
            'message' => 'Payment confirmed successfully',
            'order' => [
                'id' => $order->id,
                'userId' => $order->user_id,
                'subscriptionId' => $order->subscription_id,
                'userName' => $order->user->name,
                'userEmail' => $order->user->email,
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
            ],
        ]);
    }

    /**
     * Mark all pending orders for current week as delivered (status → delivered)
     * and closes ordering. Subscription lifecycle is managed by ProcessWeeklyCycle cron job.
     */
    public function markAllOrdersDelivered(Request $request)
    {
        $week = Week::getCurrentWeek();

        if (!$week) {
            return response()->json([
                'message' => 'No week available',
            ], 404);
        }

        DB::beginTransaction();

        try {
            // Mark all pending orders for this week as delivered (regardless of payment status)
            $updatedCount = Order::where('week_id', $week->id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'delivered',
                ]);

            // Check each delivered order to see if it should be marked as completed
            // (delivered + paid + picked up)
            $deliveredOrders = Order::where('week_id', $week->id)
                ->where('status', 'delivered')
                ->get();

            foreach ($deliveredOrders as $order) {
                $order->checkAndUpdateCompletion();
            }

            // Note: weeks_remaining is now managed by the ProcessWeeklyCycle cron job
            // We no longer decrement here to avoid double-decrementing

            // Close ordering for this week and mark orders as delivered
            $week->update([
                'all_orders_delivered' => true,
                'is_ordering_open' => false,
            ]);

            DB::commit();

            // Notify users that their orders have been delivered
            $this->pushService->notifyOrderDelivered($week);

            return response()->json([
                'message' => "Successfully marked {$updatedCount} orders as delivered",
                'updatedCount' => $updatedCount,
        ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to mark orders as delivered: ' . $e->getMessage(),
            ], 500);
    }
    }

    /**
     * Get payment settings
     */
    public function getPaymentSettings(Request $request)
    {
        $settings = AppSettings::get();

        return response()->json([
            'paymentSettings' => [
                'bankAccountNumber' => $settings->bank_account_number,
                'recipientName' => $settings->recipient_name,
                'paymentPurpose' => $settings->payment_purpose,
                'paymentCode' => $settings->payment_code,
            ],
        ]);
    }

    /**
     * Update payment settings
     */
    public function updatePaymentSettings(Request $request)
    {
        $validated = $request->validate([
            'bank_account_number' => 'required|string|max:30',
            'recipient_name' => 'required|string|max:100',
            'payment_purpose' => 'required|string|max:150',
            'payment_code' => 'required|string|max:10',
        ]);

        $settings = AppSettings::get();
        $settings->update([
            'bank_account_number' => $validated['bank_account_number'],
            'recipient_name' => $validated['recipient_name'],
            'payment_purpose' => $validated['payment_purpose'],
            'payment_code' => $validated['payment_code'],
        ]);

        return response()->json([
            'message' => 'Payment settings updated successfully',
            'paymentSettings' => [
                'bankAccountNumber' => $settings->bank_account_number,
                'recipientName' => $settings->recipient_name,
                'paymentPurpose' => $settings->payment_purpose,
                'paymentCode' => $settings->payment_code,
            ],
        ]);
    }

    /**
     * Test push notification (Debug endpoint)
     * Sends a test notification to all registered push tokens
     */
    public function testPushNotification(Request $request)
    {
        $tokens = \App\Models\PushToken::pluck('token')->toArray();
        
        \Illuminate\Support\Facades\Log::info('Test notification requested', [
            'token_count' => count($tokens),
            'tokens' => $tokens,
        ]);
        
        if (empty($tokens)) {
            return response()->json([
                'message' => 'No push tokens registered',
                'tokens' => [],
            ], 400);
        }
        
        // Send directly using HTTP client (bypass job queue for debugging)
        $messages = array_map(fn($token) => [
            'to' => $token,
            'title' => '🧪 Test Notification',
            'body' => 'This is a test notification from Egg9!',
            'sound' => 'default',
            'data' => ['type' => 'test'],
        ], $tokens);
        
        try {
            $response = \Illuminate\Support\Facades\Http::post('https://exp.host/--/api/v2/push/send', $messages);
            
            \Illuminate\Support\Facades\Log::info('Test notification response', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            
            return response()->json([
                'message' => 'Test notification sent',
                'token_count' => count($tokens),
                'expo_response' => $response->json(),
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Test notification failed', [
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'message' => 'Failed to send test notification',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

}

