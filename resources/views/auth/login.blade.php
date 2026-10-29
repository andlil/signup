<x-layouts.app :title="__('Log in')">
    <div class="mx-auto max-w-sm">
        <h1 class="text-2xl font-semibold tracking-tight">{{ __('Log in') }}</h1>
        <p class="mt-2 text-sm text-stone-600">
            {{ __('Enter your email address and we will send you a login link. No password needed.') }}
        </p>

        <form method="POST" action="{{ route('login.send') }}" class="mt-6 space-y-4">
            @csrf
            <div>
                <label for="email" class="block text-sm font-medium">{{ __('Email') }}</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email"
                    class="mt-1 block w-full rounded-md border border-stone-300 bg-white px-3 py-2 shadow-sm focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20 focus:outline-none">
                @error('email')
                    <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="w-full rounded-md bg-teal-700 px-4 py-2 font-medium text-white hover:bg-teal-800 focus:ring-2 focus:ring-teal-600/40 focus:outline-none">
                {{ __('Send login link') }}
            </button>
        </form>
    </div>
</x-layouts.app>
