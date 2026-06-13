<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class CreateUserCommand extends Command
{
    protected $signature = 'credflow:create-user
                            {--name= : Full name of the user}
                            {--email= : E-mail address (login)}
                            {--password= : Password (min 8 chars)}';

    protected $description = 'Create a new user account (registration is closed, use this command instead).';

    public function handle(): int
    {
        $name = $this->option('name') ?? text(
            label: 'Full name',
            required: true,
        );

        $email = $this->option('email') ?? text(
            label: 'E-mail',
            required: true,
            validate: fn (string $v) => filter_var($v, FILTER_VALIDATE_EMAIL) ? null : 'Enter a valid e-mail.',
        );

        $rawPassword = $this->option('password') ?? password(
            label: 'Password',
            required: true,
            validate: fn (string $v) => strlen($v) >= 8 ? null : 'Password must be at least 8 characters.',
        );

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $rawPassword],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'unique:users,email'],
                'password' => ['required', 'min:8'],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return Command::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'email' => strtolower($email),
            'password' => Hash::make($rawPassword),
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();

        $this->info('User created successfully.');
        $this->table(['ID', 'Name', 'E-mail'], [[$user->id, $user->name, $user->email]]);
        $this->line('');
        $this->line('Next steps for this user:');
        $this->line('  1. Log in and go to <comment>/agente</comment> to configure the AI agent');
        $this->line('  2. Go to <comment>/agente/regras-operacionais</comment> to configure banks and products');
        $this->line('  3. Go to <comment>/agente/follow-up</comment> to configure follow-up intervals');
        $this->line('  4. Go to <comment>/whatsapp</comment> to connect a WhatsApp instance');

        return Command::SUCCESS;
    }
}
