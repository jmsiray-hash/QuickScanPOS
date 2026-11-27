<?php

namespace App\Http\Controllers;

use App\Models\StoreSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class StoreSettingController extends Controller
{
    public function edit(Request $request): View
    {
        abort_unless($request->user()->isAdmin(), 403);

        return view('settings.store', [
            'settings' => StoreSetting::current(),
            'adminEmail' => $request->user()->email,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        if ($request->has('admin_email')) {
            $request->merge([
                'admin_email' => strtolower(trim((string) $request->input('admin_email'))),
            ]);
        }

        $validated = $request->validate([
            'store_name' => ['required', 'string', 'max:150'],
            'store_address' => ['required', 'string', 'max:1000'],
            'admin_email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:254',
                Rule::unique('users', 'email')->ignore($request->user()->getKey()),
            ],
            'current_password' => [
                Rule::requiredIf(fn (): bool => ($request->filled('admin_email')
                    && $request->input('admin_email') !== strtolower((string) $request->user()->email))
                    || $request->filled('new_password')),
                'current_password',
            ],
            'new_password' => ['nullable', 'string', 'confirmed', Password::min(12)],
        ]);

        $user = $request->user();
        $emailChanged = isset($validated['admin_email'])
            && $validated['admin_email'] !== strtolower((string) $user->email);
        $passwordChanged = isset($validated['new_password']);

        DB::transaction(function () use ($validated, $user, $emailChanged, $passwordChanged): void {
            StoreSetting::current()->update([
                'store_name' => $validated['store_name'],
                'store_address' => $validated['store_address'],
            ]);

            if ($emailChanged) {
                $user->forceFill([
                    'email' => $validated['admin_email'],
                    'email_verified_at' => null,
                ])->save();
            }

            if ($passwordChanged) {
                $user->forceFill(['password' => $validated['new_password']])->save();
            }
        });

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();

            return to_route('settings.store.edit')
                ->with('status', 'Settings saved. Verify the new admin email using the link we sent.');
        }

        return to_route('settings.store.edit')->with(
            'status',
            $passwordChanged ? 'Settings saved. Your admin sign-in password has been changed.' : 'Company details saved.',
        );
    }
}
