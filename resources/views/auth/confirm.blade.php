<x-layouts.app :title="__('Log in')">
    <div class="mx-auto max-w-sm text-center">
        <h1 class="text-2xl font-semibold tracking-tight">{{ __('Welcome, :name!', ['name' => $user->name]) }}</h1>
        <p class="mt-2 text-sm text-stone-600">{{ __('Press the button to finish logging in.') }}</p>

        <form method="POST" action="{{ route('login.consume', ['token' => $token]) }}" class="mt-6">
            @csrf
            <button type="submit" class="w-full rounded-md bg-teal-700 px-4 py-2 font-medium text-white hover:bg-teal-800 focus:ring-2 focus:ring-teal-600/40 focus:outline-none">
                {{ __('Log in') }}
            </button>
        </form>
    </div>
</x-layouts.app>
