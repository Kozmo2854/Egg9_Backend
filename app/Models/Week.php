<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Week extends Model
{
    use HasFactory;

    protected $table = 'weeks';

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
        'price_per_dozen' => 'decimal:2',
        'is_ordering_open' => 'boolean',
        'all_orders_delivered' => 'boolean',
    ];

    /**
     * Get the orders for this week
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get the current week (based on dates only)
     */
    public static function getCurrentWeek(): ?self
    {
        $today = now()->startOfDay();
        return self::where('week_start', '<=', $today)
            ->where('week_end', '>=', $today)
            ->first();
    }

    /**
     * Calculate available eggs for a specific user
     * Takes into account all pending orders (subscription orders are already included in orders table)
     */
    public function getAvailableEggsForUser(?int $userId = null): int
    {
        $available = $this->available_eggs;

        // Subtract all pending orders for this week (except the user's own order)
        // Note: We only count pending orders, not completed ones
        // Subscription orders are already in the orders table, so no need to subtract subscriptions separately
        $committedOrders = Order::where('week_id', $this->id)
            ->where('status', 'pending')
            ->when($userId, function ($query) use ($userId) {
                return $query->where('user_id', '!=', $userId);
            })
            ->sum('quantity');

        $available -= $committedOrders;

        // Add back the user's existing pending order if they have one
        if ($userId) {
            $userOrder = Order::where('user_id', $userId)
                ->where('week_id', $this->id)
                ->where('status', 'pending')
                ->first();

            if ($userOrder) {
                $available += $userOrder->quantity;
            }
        }

        return max(0, $available);
    }
}

