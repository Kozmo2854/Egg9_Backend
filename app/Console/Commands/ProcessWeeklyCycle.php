<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Subscription;
use App\Models\WeeklyStock;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessWeeklyCycle extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'egg9:process-weekly-cycle {--force : Force execution even if not Monday}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process weekly cycle: archive old week, create new week, process subscriptions';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Check if it's Monday or force flag is set
        if (!$this->option('force') && now()->dayOfWeek !== 1) {
            $this->info('This command should only run on Mondays. Use --force to override.');
            return 0;
        }

        $this->info('Starting weekly cycle processing...');

        DB::beginTransaction();

        try {
            // Step 1: Archive previous week
            $this->archivePreviousWeek();

            // Step 2: Create new week
            $newWeek = $this->createNewWeek();

            // Step 3: Process active subscriptions
            $this->processSubscriptions($newWeek);

            DB::commit();

            $this->info('✓ Weekly cycle processing completed successfully!');
            Log::info('Weekly cycle processing completed successfully');

            return 0;
        } catch (\Exception $e) {
            DB::rollBack();

            $this->error('✗ Error processing weekly cycle: ' . $e->getMessage());
            Log::error('Weekly cycle processing failed: ' . $e->getMessage(), [
                'exception' => $e,
            ]);

            return 1;
        }
    }

    /**
     * Archive previous week by marking it as closed
     */
    private function archivePreviousWeek()
    {
        $this->info('Step 1: Archiving previous week...');

        $previousWeeks = WeeklyStock::where('is_ordering_open', true)
            ->where('week_end', '<', now())
            ->get();

        foreach ($previousWeeks as $week) {
            $week->update(['is_ordering_open' => false]);
            $this->line("  - Closed week starting {$week->week_start->format('Y-m-d')}");
        }

        $this->info("  Archived {$previousWeeks->count()} week(s)");
    }

    /**
     * Create new week's stock
     */
    private function createNewWeek()
    {
        $this->info('Step 2: Creating new week...');

        $today = now();
        $weekStart = $today->copy()->startOfWeek(); // Monday
        $weekEnd = $weekStart->copy()->addWeek()->subDay(); // Next Sunday

        // Check if week already exists
        $existingWeek = WeeklyStock::where('week_start', $weekStart)->first();

        if ($existingWeek) {
            $this->line("  Week starting {$weekStart->format('Y-m-d')} already exists");
            return $existingWeek;
        }

        // Get previous week's price or use default
        $previousWeek = WeeklyStock::orderBy('week_start', 'desc')->first();
        $pricePerDozen = $previousWeek ? $previousWeek->price_per_dozen : 5.99;

        $newWeek = WeeklyStock::create([
            'week_start' => $weekStart,
            'week_end' => $weekEnd,
            'available_eggs' => 0, // Admin will set this
            'price_per_dozen' => $pricePerDozen,
            'is_ordering_open' => true,
            'delivery_date' => null,
            'delivery_time' => null,
            'all_orders_delivered' => false,
        ]);

        $this->line("  ✓ Created new week: {$weekStart->format('Y-m-d')} to {$weekEnd->format('Y-m-d')}");
        $this->line("  Price per dozen: \${$pricePerDozen}");

        return $newWeek;
    }

    /**
     * Process all active subscriptions
     */
    private function processSubscriptions(WeeklyStock $newWeek)
    {
        $this->info('Step 3: Processing active subscriptions...');

        $activeSubscriptions = Subscription::where('status', 'active')->get();

        if ($activeSubscriptions->isEmpty()) {
            $this->line('  No active subscriptions to process');
            return;
        }

        $successCount = 0;
        $failureCount = 0;

        foreach ($activeSubscriptions as $subscription) {
            try {
                // Create order for this subscription
                $order = Order::create([
                    'user_id' => $subscription->user_id,
                    'quantity' => $subscription->quantity,
                    'price_per_dozen' => $newWeek->price_per_dozen,
                    'total' => Order::calculateTotal($subscription->quantity, $newWeek->price_per_dozen),
                    'status' => 'pending',
                    'delivery_status' => 'not_delivered',
                    'week_start' => $newWeek->week_start,
                ]);

                // Decrement weeks remaining
                $subscription->weeks_remaining--;

                // Update next delivery date
                $subscription->next_delivery = now()->addWeek()->startOfWeek();

                // If no weeks remaining, mark as completed
                if ($subscription->weeks_remaining <= 0) {
                    $subscription->status = 'completed';
                    $this->line("  ✓ Subscription #{$subscription->id} (User #{$subscription->user_id}) completed");
                } else {
                    $this->line("  ✓ Created order #{$order->id} for subscription #{$subscription->id} (User #{$subscription->user_id})");
                }

                $subscription->save();
                $successCount++;
            } catch (\Exception $e) {
                $failureCount++;
                $this->error("  ✗ Failed to process subscription #{$subscription->id}: {$e->getMessage()}");
                Log::error("Failed to process subscription #{$subscription->id}", [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("  Processed {$successCount} subscription(s) successfully");
        if ($failureCount > 0) {
            $this->warn("  Failed to process {$failureCount} subscription(s)");
        }
    }
}

