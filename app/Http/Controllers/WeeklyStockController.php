<?php

namespace App\Http\Controllers;

use App\Models\WeeklyStock;
use Illuminate\Http\Request;

class WeeklyStockController extends Controller
{
    /**
     * Get current week's stock information
     */
    public function getCurrentWeek(Request $request)
    {
        $weeklyStock = WeeklyStock::getCurrentWeek();

        if (!$weeklyStock) {
            return response()->json([
                'message' => 'No weekly stock available for ordering',
            ], 404);
        }

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
     * Calculate available eggs for the current user
     */
    public function getAvailableEggs(Request $request)
    {
        $weeklyStock = WeeklyStock::getCurrentWeek();

        if (!$weeklyStock) {
            return response()->json([
                'message' => 'No weekly stock available for ordering',
            ], 404);
        }

        // Don't pass userId - we want ACTUAL available eggs (minus ALL orders including this user's)
        $availableEggs = $weeklyStock->getAvailableEggsForUser(null);

        return response()->json([
            'availableEggs' => $availableEggs,
        ]);
    }
}

