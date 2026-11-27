<x-guest-layout>
    <div class="mb-4 text-sm text-gray-600">
        Create the first administrator for this local QuickScanPOS installation. This page is disabled once an admin account exists.
    </div>

    <form method="POST" action="{{ route('admin.setup.store') }}">
        @csrf

        <div>
            <x-input-label for="full_name" value="Admin name" />
            <x-text-input id="full_name" class="block mt-1 w-full" type="text" name="full_name" :value="old('full_name')" required autofocus maxlength="100" autocomplete="name" />
            <x-input-error :messages="$errors->get('full_name')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="email" value="Admin Gmail / email" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email', 'Admin@gmail.com')" required maxlength="255" autocomplete="email" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password" value="Admin password (minimum 12 characters)" />
            <x-text-input id="password" class="block mt-1 w-full" type="password" name="password" required minlength="12" autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password_confirmation" value="Confirm admin password" />
            <x-text-input id="password_confirmation" class="block mt-1 w-full" type="password" name="password_confirmation" required minlength="12" autocomplete="new-password" />
        </div>

        <div class="mt-6 flex items-center justify-between">
            <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md" href="{{ route('login') }}">Back to login</a>
            <x-primary-button>Create Admin Account</x-primary-button>
        </div>
    </form>
</x-guest-layout>
