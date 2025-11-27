<?php

namespace App\Http\Controllers;

use App\Models\AppSettings;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SettingsController extends Controller
{
    /**
     * Get current app settings
     */
    public function index(): JsonResponse
    {
        $settings = AppSettings::get();
        
        return response()->json([
            'id' => $settings->id,
            'defaultPricePerDozen' => (float) $settings->default_price_per_dozen,
            'paymentSettings' => [
                'bankAccountNumber' => $settings->bank_account_number,
                'recipientName' => $settings->recipient_name,
                'paymentPurpose' => $settings->payment_purpose,
                'paymentCode' => $settings->payment_code,
            ],
            'createdAt' => $settings->created_at->toISOString(),
            'updatedAt' => $settings->updated_at->toISOString(),
        ]);
    }

    /**
     * Update default price per dozen
     */
    public function updatePrice(Request $request): JsonResponse
    {
        $request->validate([
            'price' => 'required|numeric|min:0|max:999.99',
            'apply_to_current_week' => 'boolean',
        ]);

        $settings = AppSettings::get();
        $applyToCurrentWeek = $request->input('apply_to_current_week', true);
        
        $settings->updateDefaultPrice(
            $request->input('price'),
            $applyToCurrentWeek
        );

        return response()->json([
            'message' => 'Default price updated successfully',
            'settings' => [
                'id' => $settings->id,
                'defaultPricePerDozen' => (float) $settings->default_price_per_dozen,
                'createdAt' => $settings->created_at->toISOString(),
                'updatedAt' => $settings->updated_at->toISOString(),
            ],
        ]);
    }
}
