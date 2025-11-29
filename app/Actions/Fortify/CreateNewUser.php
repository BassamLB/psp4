<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'registration_code' => ['required', 'string', 'exists:users,registration_code'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => $this->passwordRules(),
        ])->validate();

        // Find the user by registration code
        $user = User::where('registration_code', $input['registration_code'])->first();

        // Check if the user already has a password (already registered)
        if ($user->password) {
            throw ValidationException::withMessages([
                'registration_code' => ['هذا الرمز تم استخدامه بالفعل.'],
            ]);
        }

        // Verify email matches the invited email
        if ($user->email !== $input['email']) {
            throw ValidationException::withMessages([
                'email' => ['البريد الإلكتروني لا يتطابق مع رمز التسجيل.'],
            ]);
        }

        // Update the user with registration details
        $user->update([
            'password' => $input['password'],
            'is_active' => true,
            'is_allowed' => false, // Require admin approval
        ]);

        return $user;
    }
}
