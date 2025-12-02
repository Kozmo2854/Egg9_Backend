<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class CronController extends Controller
{
    /**
     * Process weekly cycle (triggered by external cron)
     * 
     * Security: Requires CRON_SECRET token in Authorization header
     */
    public function processWeeklyCycle(Request $request)
    {
        // Verify cron secret token
        $cronSecret = env('CRON_SECRET');
        $authHeader = $request->header('Authorization');
        
        if (!$cronSecret || $authHeader !== "Bearer {$cronSecret}") {
            Log::warning('Unauthorized cron attempt', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        try {
            // Run the weekly cycle command
            Log::info('Processing weekly cycle via cron endpoint');
            
            Artisan::call('egg9:process-weekly-cycle', ['--force' => true]);
            
            $output = Artisan::output();
            
            Log::info('Weekly cycle processed successfully', [
                'output' => $output
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Weekly cycle processed successfully',
                'output' => $output,
                'timestamp' => now()->toDateTimeString(),
            ], 200);
            
        } catch (\Exception $e) {
            Log::error('Weekly cycle processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Weekly cycle processing failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

