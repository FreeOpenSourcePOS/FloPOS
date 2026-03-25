<?php

namespace App\Console\Commands;

use App\Models\AdminUser;
use Illuminate\Console\Command;

class CreateAdminUser extends Command
{
    protected $signature = 'admin:create {name} {email} {password}';

    protected $description = 'Create a Flopos admin user';

    public function handle(): int
    {
        if (config('app.edition') !== 'cloud') {
            $this->error('Admin users only exist on the cloud edition.');
            return Command::FAILURE;
        }

        if (AdminUser::where('email', $this->argument('email'))->exists()) {
            $this->error('An admin user with that email already exists.');
            return Command::FAILURE;
        }

        $admin = AdminUser::create([
            'name'      => $this->argument('name'),
            'email'     => $this->argument('email'),
            'password'  => $this->argument('password'),
            'is_active' => true,
        ]);

        $this->info("Admin user created: {$admin->name} <{$admin->email}> (ID: {$admin->id})");

        return Command::SUCCESS;
    }
}
