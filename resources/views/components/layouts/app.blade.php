@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-stone-50 text-stone-900 antialiased">
    <header class="border-b border-stone-200 bg-white">
        <div class="mx-auto flex max-w-5xl flex-wrap items-center gap-x-6 gap-y-2 px-4 py-3">
            <a href="{{ auth()->check() ? route('dashboard') : route('login') }}" class="text-lg font-semibold tracking-tight">
                {{ config('app.name') }}
            </a>

            @auth
                <nav class="flex gap-4 text-sm">
                    <a href="{{ route('dashboard') }}" @class(['font-medium text-teal-700' => request()->routeIs('dashboard'), 'text-stone-600 hover:text-stone-900' => ! request()->routeIs('dashboard')])>
                        {{ __('My events') }}
                    </a>
                    @can('manage-users')
                        <a href="{{ route('admin.users') }}" @class(['font-medium text-teal-700' => request()->routeIs('admin.users'), 'text-stone-600 hover:text-stone-900' => ! request()->routeIs('admin.users')])>
                            {{ __('Accounts') }}
                        </a>
                    @endcan
                </nav>
            @endauth

            <div class="ml-auto flex items-center gap-4 text-sm">
                <form method="POST" action="{{ route('locale.update') }}" class="flex gap-1">
                    @csrf
                    @foreach (config('app.supported_locales') as $code => $language)
                        <button type="submit" name="locale" value="{{ $code }}" lang="{{ $code }}"
                            @class(['rounded px-2 py-1', 'bg-stone-200 font-medium' => app()->getLocale() === $code, 'text-stone-500 hover:text-stone-900' => app()->getLocale() !== $code])
                            @if (app()->getLocale() === $code) aria-current="true" @endif>
                            {{ $language }}
                        </button>
                    @endforeach
                </form>

                @auth
                    <span class="hidden text-stone-500 sm:inline">{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-stone-600 hover:text-stone-900">{{ __('Log out') }}</button>
                    </form>
                @endauth
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-5xl px-4 py-8">
        @if (session('status'))
            <div role="status" class="mb-6 rounded-md border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-900">
                {{ session('status') }}
            </div>
        @endif

        @if (session('error'))
            <div role="alert" class="mb-6 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
                {{ session('error') }}
            </div>
        @endif

        {{ $slot }}
    </main>
</body>
</html>
