<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSettings extends Model
{
    protected $table = 'app_settings';

    protected $fillable = [
        'default_price_per_dozen',
        'max_subscription_eggs',
        'max_per_subscription',
        'bank_account_number',
        'recipient_name',
        'payment_purpose',
        'payment_code',
    ];

    protected $casts = [
        'default_price_per_dozen' => 'decimal:2',
        'max_subscription_eggs' => 'integer',
        'max_per_subscription' => 'integer',
    ];

    /**
     * Get the singleton instance of app settings
     */
    public static function get(): self
    {
        return self::firstOrCreate([], [
            'default_price_per_dozen' => 5.99,
            'max_subscription_eggs' => 120,
            'max_per_subscription' => 30,
        ]);
    }

    /**
     * Get remaining subscription capacity
     * Returns how many more eggs can be subscribed
     */
    public function getRemainingSubscriptionCapacity(): int
    {
        $currentTotal = Subscription::where('status', 'active')->sum('quantity');
        return max(0, $this->max_subscription_eggs - $currentTotal);
    }

    /**
     * Get total eggs currently committed to subscriptions
     */
    public function getTotalSubscriptionEggs(): int
    {
        return Subscription::where('status', 'active')->sum('quantity');
    }

    /**
     * Update the default price and optionally apply to current/future weeks
     */
    public function updateDefaultPrice(float $price, bool $applyToCurrentWeek = true): void
    {
        $this->default_price_per_dozen = $price;
        $this->save();

        // Apply only to current week and all future weeks (not past weeks)
        if ($applyToCurrentWeek) {
            $today = now()->startOfDay();
            $affectedWeeks = Week::where('week_end', '>=', $today)->get();
            
            foreach ($affectedWeeks as $week) {
                $week->update(['price_per_dozen' => $price]);
                
                // Recalculate totals for all pending orders in this week
                $pendingOrders = Order::where('week_id', $week->id)
                    ->where('status', 'pending')
                    ->get();
                
                foreach ($pendingOrders as $order) {
                    $newTotal = Order::calculateTotal($order->quantity, $price);
                    $order->update(['total' => $newTotal]);
                }
            }
        }
    }
}
