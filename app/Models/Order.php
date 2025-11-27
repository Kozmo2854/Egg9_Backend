<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'subscription_id',
        'week_id',
        'quantity',
        'total',
        'status',
        'is_paid',
        'payment_submitted',
        'picked_up',
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'is_paid' => 'boolean',
        'payment_submitted' => 'boolean',
        'picked_up' => 'boolean',
    ];

    /**
     * Get the user that owns the order
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the week this order belongs to
     */
    public function week()
    {
        return $this->belongsTo(Week::class);
    }

    /**
     * Get the subscription that created this order (if any)
     */
    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Calculate the total price
     * Formula: (quantity / 10) * price_per_dozen
     */
    public static function calculateTotal(int $quantity, float $pricePerDozen): float
    {
        return ($quantity / 10) * $pricePerDozen;
    }

    /**
     * Check if the order can be updated or cancelled
     */
    public function canBeModified(): bool
    {
        // Orders can only be modified if they are pending AND not paid
        return $this->status === 'pending' && !$this->is_paid;
    }

    /**
     * Check if order should be marked as completed and update if necessary
     * Order is completed when: delivered + paid + picked up
     */
    public function checkAndUpdateCompletion(): void
    {
        if ($this->status === 'delivered' && $this->is_paid && $this->picked_up) {
            $this->update(['status' => 'completed']);
        }
    }
}

