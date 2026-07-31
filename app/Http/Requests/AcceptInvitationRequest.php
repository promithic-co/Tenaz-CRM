<?php

namespace App\Http\Requests;

use App\Concerns\PasswordValidationRules;
use App\Models\TenantInvitation;
use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AcceptInvitationRequest extends FormRequest
{
    use PasswordValidationRules;

    public function authorize(): bool
    {
        $existingUser = $this->existingInvitedUser();

        return $existingUser === null || $this->user()?->is($existingUser) === true;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        $existingUser = $this->existingInvitedUser();

        return [
            'name' => $existingUser
                ? ['nullable', 'string', 'max:255']
                : ['required', 'string', 'max:255'],
            'password' => $existingUser
                ? [
                    'required',
                    'string',
                    function (string $attribute, mixed $value, Closure $fail) use ($existingUser): void {
                        if (! Hash::check((string) $value, $existingUser->password)) {
                            $fail('A senha atual está incorreta.');
                        }
                    },
                ]
                : $this->passwordRules(),
        ];
    }

    private function existingInvitedUser(): ?User
    {
        $token = (string) $this->route('token');

        if ($token === '') {
            return null;
        }

        $invitation = TenantInvitation::query()
            ->where('token', TenantInvitation::hashToken($token))
            ->first();

        if (! $invitation || ! $invitation->isPending()) {
            return null;
        }

        return User::query()
            ->whereRaw('LOWER(email) = ?', [Str::lower($invitation->email)])
            ->first();
    }
}
