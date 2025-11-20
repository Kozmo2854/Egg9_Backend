<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'quantity',
        'price_per_dozen',
        'total',
        'status',
        'delivery_status',
        'week_start',
    ];

    protected $casts = [
        'week_start' => 'date',
    ];

    /**
     * Get the user that owns the order
     */
    public function user()
    {
        return $this->belongsTo(User::class);
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
        return $this->status === 'pending';
    }
}

