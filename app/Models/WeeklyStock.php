<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WeeklyStock extends Model
{
    use HasFactory;

    protected $fillable = [
        'week_start',
        'week_end',
        'available_eggs',
        'price_per_dozen',
        'is_ordering_open',
        'delivery_date',
        'delivery_time',
        'all_orders_delivered',
    ];

    protected $casts = [
        'week_start' => 'date',
        'week_end' => 'date',
        'delivery_date' => 'datetime',
        'is_ordering_open' => 'boolean',
        'all_orders_delivered' => 'boolean',
    ];

    /**
     * Get the current week's stock
     */
    public static function getCurrentWeek()
    {
        $today = now()->startOfDay();
        return self::where('week_start', '<=', $today)
            ->where('week_end', '>=', $today)
            ->where('is_ordering_open', true)
            ->first();
    }

    /**
     * Calculate available eggs for a specific user
     * Takes into account all pending orders and active subscriptions
     */
    public function getAvailableEggsForUser(?int $userId = null): int
    {
        $available = $this->available_eggs;

        // Subtract all pending orders for this week (except the user's own order)
        $pendingOrders = Order::where('week_start', $this->week_start)
            ->where('status', 'pending')
            ->when($userId, function ($query) use ($userId) {
                return $query->where('user_id', '!=', $userId);
            })
            ->sum('quantity');

        $available -= $pendingOrders;

        // Subtract all active subscriptions (except the user's)
        $activeSubscriptions = Subscription::where('status', 'active')
            ->when($userId, function ($query) use ($userId) {
                return $query->where('user_id', '!=', $userId);
            })
            ->sum('quantity');

        $available -= $activeSubscriptions;

        // Add back the user's existing order if they have one
        if ($userId) {
            $userOrder = Order::where('user_id', $userId)
                ->where('week_start', $this->week_start)
                ->where('status', 'pending')
                ->first();

            if ($userOrder) {
                $available += $userOrder->quantity;
            }
        }

        return max(0, $available);
    }
}

