<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Carbon\Carbon;

class CleanUnverifiedUsersCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'app:clean-unverified-users {--hours=24 : The age of unverified accounts to delete in hours}';

    /**
     * The console command description.
     */
    protected $description = 'Delete users who registered but did not verify their email within the specified time limit';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $cutoff = Carbon::now()->subHours($hours);

        $query = User::whereNull('email_verified_at')
            ->where('created_at', '<', $cutoff);

        $count = $query->count();

        if ($count === 0) {
            $this->info("No unverified users older than {$hours} hours found.");
            return self::SUCCESS;
        }

        $this->info("Found {$count} unverified users older than {$hours} hours. Deleting...");
        $query->delete();
        $this->info("Cleanup completed.");

        return self::SUCCESS;
    }
}
