<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\TenantRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $input = [
            ...$input,
            'name' => trim($input['name'] ?? ''),
            'company_name' => trim($input['company_name'] ?? ''),
            'email' => strtolower(trim($input['email'] ?? '')),
        ];

        Validator::make($input, [
            ...$this->profileRules(),
            'company_name' => ['required', 'string', 'max:255'],
            'password' => $this->passwordRules(),
        ])->validate();

        return DB::transaction(function () use ($input): User {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
            ]);

            $tenant = Tenant::create(['name' => $input['company_name']]);
            $user->tenants()->attach($tenant->id, ['role' => TenantRole::Owner->value]);

            return $user;
        });
    }
}
