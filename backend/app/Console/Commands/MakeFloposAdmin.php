<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class MakeFloposAdmin extends Command
{
    protected $signature = 'user:make-admin {email : The email of the user to promote}
                                            {--revoke : Revoke admin access instead}';

    protected $description = 'Grant or revoke Flopos admin access for a user';

    public function handle(): int
    {
        $email = $this->argument('email');
        $revoke = $this->option('revoke');

        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("No user found with email: {$email}");
            return 1;
        }

        $user->update(['is_flopos_admin' => !$revoke]);

        if ($revoke) {
            $this->info("Admin access revoked for {$user->name} ({$user->email})");
        } else {
            $this->info("✓ {$user->name} ({$user->email}) is now a Flopos admin");
            $this->line("  They can log in at https://admin.flopos.com");
        }

        return 0;
    }
}
