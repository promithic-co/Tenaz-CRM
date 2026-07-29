<?php

namespace App\Console\Commands;

use App\Actions\Fortify\CreateNewUser;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class CreateUserCommand extends Command
{
    protected $signature = 'credflow:create-user
                            {--name= : Full name of the user}
                            {--company= : Company name}
                            {--email= : E-mail address (login)}';

    protected $description = 'Create a company and its owner account.';

    public function handle(): int
    {
        $name = $this->option('name') ?? text(
            label: 'Full name',
            required: true,
        );

        $companyName = $this->option('company') ?? text(
            label: 'Company name',
            required: true,
        );

        $email = $this->option('email') ?? text(
            label: 'E-mail',
            required: true,
            validate: fn (string $v) => filter_var($v, FILTER_VALIDATE_EMAIL) ? null : 'Enter a valid e-mail.',
        );

        $rawPassword = password(
            label: 'Password',
            required: true,
        );

        try {
            $user = app(CreateNewUser::class)->create([
                'name' => $name,
                'company_name' => $companyName,
                'email' => $email,
                'password' => $rawPassword,
                'password_confirmation' => $rawPassword,
            ]);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $errors) {
                foreach ($errors as $error) {
                    $this->error($error);
                }
            }

            if ($exception->errors() === []) {
                $this->error($exception->getMessage());
            }

            return Command::FAILURE;
        }

        $tenant = $user->tenants()->firstOrFail();

        $this->info('Company owner created successfully.');
        $this->table(
            ['User ID', 'Name', 'E-mail', 'Company ID', 'Company', 'Role'],
            [[
                $user->id,
                $user->name,
                $user->email,
                $tenant->id,
                $tenant->name,
                $tenant->pivot->role,
            ]],
        );
        $this->line('');
        $this->line('Next steps for this user:');
        $this->line('  1. Log in and go to <comment>/agente</comment> to configure the AI agent');
        $this->line('  2. Go to <comment>/agente/regras-operacionais</comment> to configure banks and products');
        $this->line('  3. Go to <comment>/agente/follow-up</comment> to configure follow-up intervals');
        $this->line('  4. Go to <comment>/whatsapp</comment> to connect a WhatsApp instance');

        return Command::SUCCESS;
    }
}
