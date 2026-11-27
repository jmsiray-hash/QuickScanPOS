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
        $this->ensureLocalSetup($request);

        if (User::query()->where('role', 'admin')->exists()) {
            return to_route('login')->with('status', 'An administrator already exists. Sign in with your admin account.');
        }

        return view('auth.admin-setup');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureLocalSetup($request);

        if (User::query()->where('role', 'admin')->exists()) {
            return to_route('login')->with('status', 'An administrator already exists. Sign in with your admin account.');
        }

        $email = strtolower(trim((string) $request->input('email')));
        $existingUser = User::query()->where('email', $email)->first();
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
            if (User::query()->where('role', 'admin')->exists()) {
                abort(409, 'An administrator account has already been created.');
            }

            if ($existingUser !== null) {
                $existingUser->forceFill([
                    'full_name' => $validated['full_name'],
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

    private function ensureLocalSetup(Request $request): void
    {
        abort_unless(
            app()->environment(['local', 'testing']) && in_array($request->ip(), ['127.0.0.1', '::1'], true),
            404,
        );
    }
}
