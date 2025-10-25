<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ClearInvalidStripeAccounts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stripe:clear-invalid-accounts {--dry-run : Show what would be cleared without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear invalid Stripe account IDs from users table';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        
        $this->info('Scanning for users with Stripe account IDs...');
        
        $usersWithStripeAccounts = User::whereNotNull('stripe_account_id')->get();
        
        if ($usersWithStripeAccounts->isEmpty()) {
            $this->info('No users found with Stripe account IDs.');
            return 0;
        }
        
        $this->info("Found {$usersWithStripeAccounts->count()} users with Stripe account IDs.");
        
        $clearedCount = 0;
        
        foreach ($usersWithStripeAccounts as $user) {
            $this->line("Checking user {$user->id} ({$user->email}) with account: {$user->stripe_account_id}");
            
            if ($dryRun) {
                $this->warn("  [DRY RUN] Would clear Stripe account for user {$user->id}");
                $clearedCount++;
            } else {
                $user->update([
                    'stripe_account_id' => null,
                    'stripe_onboarding_completed' => false
                ]);
                $this->info("  ✓ Cleared Stripe account for user {$user->id}");
                $clearedCount++;
            }
        }
        
        if ($dryRun) {
            $this->warn("DRY RUN: Would have cleared {$clearedCount} Stripe accounts.");
            $this->info('Run without --dry-run to actually clear the accounts.');
        } else {
            $this->info("Successfully cleared {$clearedCount} Stripe accounts.");
        }
        
        return 0;
    }
}