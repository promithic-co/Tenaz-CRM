<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class MakeSuperAdminCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:super-admin {email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Promote a user to platform super-admin by email';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error("No user found with email {$this->argument('email')}");

            return self::FAILURE;
        }

        $user->is_super_admin = true;
        $user->save();

        $this->info("{$user->email} is now a super-admin.");

        return self::SUCCESS;
    }
}
