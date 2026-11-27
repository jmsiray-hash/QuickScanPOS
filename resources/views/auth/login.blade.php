<x-guest-layout>
    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <div class="block mt-4">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" name="remember">
                <span class="ms-2 text-sm text-gray-600">{{ __('Remember me') }}</span>
            </label>
        </div>

        <div class="mt-4">
            @if (Route::has('password.request'))
                <a class="login-forgot-link" href="{{ route('password.request') }}">
                    {{ __('Forgot your password?') }}
                </a>
            @endif

            <p class="login-choice-label">Choose how to sign in</p>
            <div class="login-choice-actions">
                <button class="login-choice-button login-choice-cashier" type="submit" name="login_as" value="cashier">
                    <span aria-hidden="true">▣</span>
                    <span><strong>Log in as Cashier</strong><small>Open the cashier register</small></span>
                </button>
                <button class="login-choice-button login-choice-admin" type="submit" name="login_as" value="admin">
                    <span aria-hidden="true">⚙</span>
                    <span><strong>Log in as Admin</strong><small>Open the management dashboard</small></span>
                </button>
            </div>
            <x-input-error :messages="$errors->get('login_as')" class="mt-2" />
        </div>
    </form>

    <style>
        .login-choice-label { color:#334155; font-size:.88rem; font-weight:700; margin:18px 0 8px; }
        .login-choice-actions { display:grid; gap:9px; grid-template-columns:repeat(2,minmax(0,1fr)); }
        .login-choice-button { align-items:center; border:1px solid #cbd5e1; border-radius:8px; cursor:pointer; display:flex; font:inherit; gap:10px; min-height:64px; padding:10px 12px; text-align:left; transition:border-color .15s,background .15s; }
        .login-choice-button>span:first-child { align-items:center; background:#fff; border-radius:7px; display:flex; flex:none; height:34px; justify-content:center; width:34px; }
        .login-choice-button>span:last-child { display:grid; gap:2px; }
        .login-choice-button strong { font-size:.82rem; }
        .login-choice-button small { font-size:.68rem; font-weight:400; }
        .login-choice-cashier { background:#ecfdf5; border-color:#a7e4cc; color:#145c48; }
        .login-choice-cashier:hover { background:#d9f7e9; border-color:#42b88c; }
        .login-choice-admin { background:#eff6ff; border-color:#bfdbfe; color:#1e4777; }
        .login-choice-admin:hover { background:#e1efff; border-color:#70a9e8; }
        .login-forgot-link { color:#475569; display:block; font-size:.82rem; margin-top:12px; text-align:right; text-decoration:underline; }
        @media(max-width:420px) { .login-choice-actions { grid-template-columns:1fr; } }
    </style>
</x-guest-layout>
