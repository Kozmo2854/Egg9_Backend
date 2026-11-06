<?php

// TEST EMAIL BLAST — REMOVE AFTER QA

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Week;
use App\Notifications\DeliveryScheduledNotification;
use App\Notifications\OrderDeliveredNotification;
use App\Notifications\PaymentReminderNotification;
use App\Notifications\PickupReminderNotification;
use App\Notifications\StockAvailableNotification;
use App\Notifications\SubscriptionTrimmedNotification;
use App\Notifications\WeekSkippedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * TEMPORARY QA endpoint: emails every notification template to a SINGLE hard-coded
 * recipient so the real templates (footers, buttons, signed links) can be eyeballed
 * in a real inbox. Secret-gated with CRON_SECRET, identical to CronController.
 *
 * SAFETY: This NEVER fans out. It sends only to jovanzeka97@gmail.com. There is no
 * loop over users and no Notification::send to a collection.
 *
 * REMOVE AFTER QA (delete this file + the matching route line in routes/api.php).
 */
class TestEmailBlastController extends Controller
{
    /**
     * The one and only recipient. Hard-coded on purpose.
     */
    private const RECIPIENT_EMAIL = 'jovanzeka97@gmail.com';

    public function sendAll(Request $request)
    {
        // --- Secret gate (identical to CronController) ---
        $cronSecret = env('CRON_SECRET');
        $authHeader = $request->header('Authorization');

        if (! $cronSecret || $authHeader !== "Bearer {$cronSecret}") {
            Log::warning('Unauthorized test email blast attempt', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        // --- Resolve the single recipient ---
        $jovan = User::where('email', self::RECIPIENT_EMAIL)->first();

        if (! $jovan) {
            abort(404, 'recipient not found');
        }

        // --- Representative test data ---
        // Prefer a real, persisted Week so FK (orders.week_id) + display fields are authentic.
        $week = Week::getCurrentWeek() ?? Week::query()->latest('week_start')->first();

        if (! $week) {
            abort(422, 'no week available to build test data');
        }

        $weekStart = $week->week_start; // Carbon (cast as date)

        // --- Throwaway test order so the signed "Confirm pickup" button works end-to-end ---
        // Must be owned by Jovan, status 'delivered', picked_up = false (see
        // OrderController::confirmPickupFromEmail()).
        $testOrderId = DB::table('orders')->insertGetId([
            'user_id' => $jovan->id,
            'subscription_id' => null,
            'week_id' => $week->id,
            'quantity' => 6,
            'total' => 360,
            'status' => 'delivered',
            'is_paid' => false,
            'payment_submitted' => false,
            'payment_submitted_at' => null,
            'picked_up' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sent = [];

        // Send to Jovan ONLY, via the real notification classes, mail channel only.
        $jovan->notify((new StockAvailableNotification($week))->onlyVia('mail'));
        $sent[] = 'StockAvailable';

        $jovan->notify((new DeliveryScheduledNotification($week))->onlyVia('mail'));
        $sent[] = 'DeliveryScheduled';

        $jovan->notify((new OrderDeliveredNotification($week, 12, 720))->onlyVia('mail'));
        $sent[] = 'OrderDelivered';

        $jovan->notify((new PaymentReminderNotification(12, 720, $weekStart))->onlyVia('mail'));
        $sent[] = 'PaymentReminder';

        $jovan->notify((new SubscriptionTrimmedNotification(12, 6))->onlyVia('mail'));
        $sent[] = 'SubscriptionTrimmed';

        $jovan->notify((new PickupReminderNotification(6, $weekStart, $testOrderId))->onlyVia('mail'));
        $sent[] = 'PickupReminder';

        // WeekSkipped: all three variants.
        $jovan->notify((new WeekSkippedNotification(WeekSkippedNotification::VARIANT_SUBSCRIPTION, 3))->onlyVia('mail'));
        $sent[] = 'WeekSkipped[subscription]';

        $jovan->notify((new WeekSkippedNotification(WeekSkippedNotification::VARIANT_ORDER_CANCELLED))->onlyVia('mail'));
        $sent[] = 'WeekSkipped[order_cancelled]';

        $jovan->notify((new WeekSkippedNotification(WeekSkippedNotification::VARIANT_NONE))->onlyVia('mail'));
        $sent[] = 'WeekSkipped[none]';

        Log::info('Test email blast dispatched', [
            'recipient' => $jovan->email,
            'test_order_id' => $testOrderId,
            'sent' => $sent,
        ]);

        return response()->json([
            'recipient' => $jovan->email,
            'test_order_id' => $testOrderId,
            'sent' => $sent,
        ]);
    }
}
