<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class BootstrapAdmin extends Command
{
    protected $signature = 'admin:bootstrap';

    protected $description = 'Create the first administrator account securely';

    public function handle(): int
    {
        if (User::query()->where('role', 'admin')->exists()) {
            $this->error('An administrator already exists. Use Company Settings or Users to manage admin accounts.');

            return self::FAILURE;
        }

        $email = strtolower(trim((string) $this->ask('Admin Gmail / email')));
        $existingUser = User::query()->where('email', $email)->first();
        $fullName = trim((string) $this->ask('Admin full name', $existingUser?->full_name ?? 'Administrator'));
        if ($existingUser !== null) {
            $this->warn('This existing staff account will become the first administrator and its password will be replaced.');
        }

        $password = $this->secret('Admin password (at least 12 characters)');
        $passwordConfirmation = $this->secret('Confirm admin password');
        $validated = Validator::make([
            'full_name' => $fullName,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $passwordConfirmation,
        ], [
            'full_name' => ['required', 'string', 'max:100'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($existingUser?->getKey()),
            ],
            'password' => ['required', 'string', 'confirmed', Password::min(12)],
        ])->validate();

        DB::transaction(function () use ($validated, $existingUser): void {
            if (User::query()->where('role', 'admin')->exists()) {
                throw new \RuntimeException('An administrator was created while setup was in progress. No account was changed.');
            }

            if ($existingUser !== null) {
                $existingUser->forceFill([
                    'full_name' => $validated['full_name'],
                    'password' => $validated['password'],
                    'role' => 'admin',
                ])->save();

                return;
            }

            $username = Str::of(Str::before($validated['email'], '@'))
                ->replaceMatches('/[^a-zA-Z0-9_]/', '_')
                ->limit(50, '')
                ->value();
            $baseUsername = $username !== '' ? $username : 'admin';
            $suffix = 1;

            while (User::query()->where('username', $username)->exists()) {
                $tail = '_'.$suffix++;
                $username = Str::limit($baseUsername, 50 - strlen($tail), '').$tail;
            }

            User::create([
                'username' => $username,
                'full_name' => $validated['full_name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'role' => 'admin',
            ]);
        });

        $this->info('Administrator account created. Sign in using Admin login; the password was not displayed or stored in plaintext.');

        return self::SUCCESS;
    }
}
