<x-layouts.app :title="__('Invalid link')">
    <div class="mx-auto max-w-sm text-center">
        <h1 class="text-2xl font-semibold tracking-tight">{{ __('The link no longer works') }}</h1>
        <p class="mt-2 text-sm text-stone-600">
            {{ __('Login links are valid for :minutes minutes and can only be used once.', ['minutes' => \App\Models\LoginToken::LIFETIME_MINUTES]) }}
        </p>
        <a href="{{ route('login') }}" class="mt-6 inline-block rounded-md bg-teal-700 px-4 py-2 font-medium text-white hover:bg-teal-800">
            {{ __('Request a new link') }}
        </a>
    </div>
</x-layouts.app>
