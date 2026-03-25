<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class MakeFloposAdmin extends Command
{
    protected $signature = 'user:make-admin';

    protected $description = 'Deprecated — use admin:create instead';

    public function handle(): int
    {
        $this->error('This command is deprecated. Admin users are now a separate entity.');
        $this->line('Use: php artisan admin:create {name} {email} {password}');
        return Command::FAILURE;
    }
}
