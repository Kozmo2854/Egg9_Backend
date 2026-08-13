<?php

namespace App\Console\Commands;

use App\Models\AppSettings;
use App\Models\Week;
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
    protected $signature = 'egg9:process-weekly-cycle {--force : Force execution (testing mode: moves all weeks to past, then creates new week)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process weekly cycle: archive old week, create new week, process subscriptions. Use --force for testing.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Check if it's Monday or force flag is set
        if (! $this->option('force') && now()->dayOfWeek !== 1) {
            $this->info('This command should only run on Mondays. Use --force to override.');

            return 0;
        }

        $this->info('Starting weekly cycle processing...');

        DB::beginTransaction();

        try {
            // If force flag is set, move all existing weeks to the past for testing
            if ($this->option('force')) {
                $this->moveWeeksToPast();
            }

            // Step 1: Archive previous week
            $this->archivePreviousWeek();

            // Step 2: Create new week
            $newWeek = $this->createNewWeek();

            // Note: Subscriptions are now processed when admin sets stock
            // This allows admin to see preview and control the process
            $this->info('Step 3: Subscriptions will be processed when admin sets stock');

            DB::commit();

            $this->info('✓ Weekly cycle processing completed successfully!');
            Log::info('Weekly cycle processing completed successfully');

            return 0;
        } catch (\Exception $e) {
            DB::rollBack();

            $this->error('✗ Error processing weekly cycle: '.$e->getMessage());
            Log::error('Weekly cycle processing failed: '.$e->getMessage(), [
                'exception' => $e,
            ]);

            return 1;
        }
    }

    /**
     * Move all existing weeks to the past (for testing with --force flag)
     * This shifts week dates to past weeks so a new current week can be created
     */
    private function moveWeeksToPast()
    {
        $this->warn('🧪 TESTING MODE: Moving all existing weeks to the past...');

        $allWeeks = Week::all();

        if ($allWeeks->isEmpty()) {
            $this->line('  No weeks to move');

            return;
        }

        // Move each week back by the number of weeks needed to clear the current week slot
        $currentWeekStart = now()->startOfWeek();

        foreach ($allWeeks as $index => $week) {
            // Skip already-past weeks so we don't re-date them onto an occupied week_start slot (unique-constraint collision, e.g. after a skipped week)
            if ($week->week_end->isPast()) {
                continue;
            }

            // Calculate how many weeks to shift back (at least 1 week before current)
            $weeksToShift = $index + 1;
            $newWeekStart = $currentWeekStart->copy()->subWeeks($weeksToShift);
            $newWeekEnd = $newWeekStart->copy()->addDays(6);

            $oldStart = $week->week_start->format('Y-m-d');

            $week->update([
                'week_start' => $newWeekStart,
                'week_end' => $newWeekEnd,
                'is_ordering_open' => false,
            ]);

            $this->line("  - Moved week {$oldStart} → {$newWeekStart->format('Y-m-d')}");
        }

        $this->info("  ✓ Moved {$allWeeks->count()} week(s) to the past");
    }

    /**
     * Archive previous week by marking it as closed
     */
    private function archivePreviousWeek()
    {
        $this->info('Step 1: Archiving previous week...');

        $previousWeeks = Week::where('is_ordering_open', true)
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
        $existingWeek = Week::where('week_start', $weekStart)->first();

        if ($existingWeek) {
            $this->line("  Week starting {$weekStart->format('Y-m-d')} already exists");

            return $existingWeek;
        }

        // Get default price from app settings
        $settings = AppSettings::get();
        $pricePerDozen = $settings->default_price_per_dozen;

        $newWeek = Week::create([
            'week_start' => $weekStart,
            'week_end' => $weekEnd,
            'available_eggs' => 0, // Admin will set this
            'price_per_dozen' => $pricePerDozen,
            'is_ordering_open' => false, // Will open when admin sets stock
            'delivery_date' => null,
            'delivery_time' => null,
            'all_orders_delivered' => false,
        ]);

        $this->line("  ✓ Created new week: {$weekStart->format('Y-m-d')} to {$weekEnd->format('Y-m-d')}");
        $this->line("  Price per dozen: \${$pricePerDozen}");

        return $newWeek;
    }
}
