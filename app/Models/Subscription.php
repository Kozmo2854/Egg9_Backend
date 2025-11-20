<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'quantity',
        'frequency',
        'period',
        'weeks_remaining',
        'status',
        'next_delivery',
    ];

    protected $casts = [
        'next_delivery' => 'date',
    ];

    /**
     * Get the user that owns the subscription
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Calculate the total price for the subscription
     * Formula: (quantity / 10) * price_per_dozen * period
     */
    public static function calculateTotal(int $quantity, float $pricePerDozen, int $period): float
    {
        return ($quantity / 10) * $pricePerDozen * $period;
    }

    /**
     * Process this subscription (create an order for the week)
     */
    public function process(): ?Order
    {
        if ($this->status !== 'active') {
            return null;
        }

        $currentWeek = WeeklyStock::getCurrentWeek();
        if (!$currentWeek) {
            return null;
        }

        // Create an order for this subscription
        $order = Order::create([
            'user_id' => $this->user_id,
            'quantity' => $this->quantity,
            'price_per_dozen' => $currentWeek->price_per_dozen,
            'total' => Order::calculateTotal($this->quantity, $currentWeek->price_per_dozen),
            'status' => 'pending',
            'delivery_status' => 'not_delivered',
            'week_start' => $currentWeek->week_start,
        ]);

        // Decrement weeks remaining
        $this->weeks_remaining--;

        // Update next delivery date
        $this->next_delivery = now()->addWeek()->startOfWeek();

        // If no weeks remaining, mark as completed
        if ($this->weeks_remaining <= 0) {
            $this->status = 'completed';
        }

        $this->save();

        return $order;
    }
}

