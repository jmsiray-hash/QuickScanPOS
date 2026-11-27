<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class InitialAdminController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if (! $this->setupAllowed()) {
            return to_route('login')->with('status', 'Admin setup is disabled. Sign in with your admin account.');
        }

        return view('auth.admin-setup', [
            'replacingAdmin' => User::query()->where('role', 'admin')->exists()
                || ((bool) config('app.allow_admin_setup')
                    && User::query()->where('username', 'admin_juan')->exists()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (! $this->setupAllowed()) {
            return to_route('login')->with('status', 'Admin setup is disabled. Sign in with your admin account.');
        }

        $email = strtolower(trim((string) $request->input('email')));
        $request->merge(['email' => $email]);
        $existingAdmin = User::query()->where('role', 'admin')->orderBy('id')->first();
        $existingUser = $existingAdmin
            ?? (config('app.allow_admin_setup')
                ? User::query()->where('username', 'admin_juan')->first()
                : null)
            ?? User::query()->where('email', $email)->first();
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:100'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($existingUser?->getKey()),
            ],
            'password' => ['required', 'string', 'confirmed', Password::min(12)],
        ]);

        DB::transaction(function () use ($validated, $existingUser): void {
            $existingAdmin = User::query()->where('role', 'admin')->orderBy('id')->lockForUpdate()->first();
            if ($existingAdmin !== null) {
                abort_unless((bool) config('app.allow_admin_setup'), 404);
                $target = $existingAdmin;
            } else {
                $target = $existingUser === null
                    ? null
                    : User::query()->lockForUpdate()->find($existingUser->getKey());
            }

            if ($target !== null) {
                $target->forceFill([
                    'full_name' => $validated['full_name'],
                    'email' => strtolower($validated['email']),
                    'password' => $validated['password'],
                    'role' => 'admin',
                ])->save();

                return;
            }

            $baseUsername = Str::of(Str::before($validated['email'], '@'))
                ->replaceMatches('/[^a-zA-Z0-9_]/', '_')
                ->limit(50, '')
                ->value();
            $baseUsername = $baseUsername !== '' ? $baseUsername : 'admin';
            $username = $baseUsername;
            $suffix = 1;

            while (User::query()->where('username', $username)->exists()) {
                $tail = '_'.$suffix++;
                $username = Str::limit($baseUsername, 50 - strlen($tail), '').$tail;
            }

            User::create([
                'username' => $username,
                'full_name' => $validated['full_name'],
                'email' => strtolower($validated['email']),
                'password' => $validated['password'],
                'role' => 'admin',
            ]);
        });

        return to_route('login')->with('status', 'Administrator created. Sign in with Admin login and change this password after setup.');
    }

    private function setupAllowed(): bool
    {
        return ! User::query()->where('role', 'admin')->exists()
            || (bool) config('app.allow_admin_setup');
    }
}
