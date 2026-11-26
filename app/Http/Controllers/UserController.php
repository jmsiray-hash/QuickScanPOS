<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class UserController extends Controller
{
    public function create(): View
    {
        return view('users.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'max:50', 'unique:users,username'],
            'full_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'min:8'],
            'avatar' => ['bail', 'nullable', 'mimetypes:image/jpeg,image/png,image/gif,image/webp,image/bmp,image/avif', 'max:2048'],
        ], [
            'avatar.mimetypes' => 'Choose a JPEG, PNG, GIF, WebP, BMP, or AVIF image.',
            'avatar.max' => 'The avatar may not be larger than 2 MB.',
        ]);
        $validated['role'] = $request->user()->isAdmin()
            ? $request->validate(['role' => ['required', 'in:cashier,manager,admin']])['role']
            : 'cashier';

        $avatar = $validated['avatar'] ?? null;
        unset($validated['avatar']);

        if ($avatar !== null && ! $avatar->isValid()) {
            return back()
                ->withInput()
                ->withErrors(['avatar' => 'The image could not be uploaded. Please choose it again.']);
        }

        $avatarPath = null;
        try {
            $avatarPath = $avatar === null ? null : $this->storeAvatar($avatar);
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors(['avatar' => 'The avatar could not be saved. Check that public storage is writable, then try again.']);
        }

        if ($avatarPath !== null) {
            $validated['avatar'] = $avatarPath;
        }

        try {
            User::create($validated);
        } catch (Throwable $exception) {
            if ($avatarPath !== null) {
                Storage::disk('public')->delete($avatarPath);
            }

            throw $exception;
        }

        return redirect()
            ->route('users.index')
            ->with('status', 'User created successfully.');
    }

    public function index(): View
    {
        $users = User::query()->get();

        return view('users.index', compact('users'));
    }

    public function show(User $user): View
    {
        return view('users.show', compact('user'));
    }

    public function avatar(User $user): StreamedResponse
    {
        abort_unless(
            $user->avatar !== null && Storage::disk('public')->exists($user->avatar),
            404,
        );

        return Storage::disk('public')->response($user->avatar);
    }

    public function edit(User $user): View
    {
        return view('users.edit', compact('user'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'username' => [
                'required',
                'string',
                'max:50',
                'unique:users,username,'.$user->getKey(),
            ],
            'full_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->getKey()],
            'avatar' => ['bail', 'nullable', 'mimetypes:image/jpeg,image/png,image/gif,image/webp,image/bmp,image/avif', 'max:2048'],
        ], [
            'avatar.mimetypes' => 'Choose a JPEG, PNG, GIF, WebP, BMP, or AVIF image.',
            'avatar.max' => 'The avatar may not be larger than 2 MB.',
        ]);
        if ($request->user()->isAdmin()) {
            $validated['role'] = $request->validate(['role' => ['required', 'in:cashier,manager,admin']])['role'];
        } elseif ($request->exists('role')) {
            abort(403);
        }

        $avatar = $validated['avatar'] ?? null;
        unset($validated['avatar']);

        if ($avatar !== null && ! $avatar->isValid()) {
            $errorCode = $avatar->getError();
            $uploadMessage = match ($errorCode) {
                UPLOAD_ERR_INI_SIZE => 'The server rejected the image because it exceeds the PHP upload limit.',
                UPLOAD_ERR_FORM_SIZE => 'The image exceeds the form upload limit.',
                UPLOAD_ERR_PARTIAL => 'The image was only partially uploaded. Please choose it again.',
                UPLOAD_ERR_NO_FILE => 'No avatar file was received. Please choose an image.',
                UPLOAD_ERR_NO_TMP_DIR => 'The server upload temporary directory is missing.',
                UPLOAD_ERR_CANT_WRITE => 'The server could not write the uploaded image to temporary storage.',
                UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the avatar upload.',
                default => 'The image could not be uploaded. Please choose it again.',
            };

            return back()
                ->withInput()
                ->withErrors(['avatar' => $uploadMessage]);
        }

        $previousAvatar = $user->avatar;
        try {
            $newAvatar = $avatar === null ? null : $this->storeAvatar($avatar);
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors(['avatar' => 'The avatar could not be saved. Check that public storage is writable, then try again.']);
        }

        if ($newAvatar !== null) {
            $validated['avatar'] = $newAvatar;
        }

        try {
            $saved = $user->update($validated);
        } catch (Throwable $exception) {
            if ($newAvatar !== null) {
                Storage::disk('public')->delete($newAvatar);
            }

            throw $exception;
        }

        if (! $saved) {
            if ($newAvatar !== null) {
                Storage::disk('public')->delete($newAvatar);
            }

            throw new RuntimeException('The user changes could not be saved.');
        }

        if ($newAvatar !== null && $previousAvatar !== null) {
            Storage::disk('public')->delete($previousAvatar);
        }

        return redirect()
            ->route('users.index')
            ->with('status', 'User updated successfully.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->shifts()->exists()) {
            return back()->withErrors([
                'user' => 'This account has shift history and cannot be deleted. Keep it for audit history; change its role if its permissions need to be reduced.',
            ]);
        }

        if ($user->avatar !== null) {
            Storage::disk('public')->delete($user->avatar);
        }

        $user->delete();

        return redirect()
            ->route('users.index')
            ->with('status', 'User deleted successfully.');
    }

    private function storeAvatar(UploadedFile $avatar): string
    {
        $path = $avatar->storePublicly('avatars', 'public');

        if ($path === false) {
            throw new RuntimeException('The avatar image could not be stored.');
        }

        return $path;
    }
}
