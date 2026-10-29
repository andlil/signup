@props(['event', 'active'])

@php
    use App\Enums\EventStatus;
    $tabs = [
        'details' => ['route' => 'events.edit', 'label' => __('Details')],
        'form' => ['route' => 'events.form', 'label' => __('Form')],
    ];
@endphp

<div class="mb-6">
    <a href="{{ route('dashboard') }}" class="text-sm text-stone-600 hover:text-stone-900">← {{ __('My events') }}</a>

    <div class="mt-2 flex flex-wrap items-center gap-3">
        <h1 class="text-2xl font-semibold tracking-tight">{{ $event->title }}</h1>
        <x-events.status-badge :status="$event->status" />
    </div>

    <div class="mt-4 rounded-lg border border-stone-200 bg-white p-4">
        @if ($event->status === EventStatus::Draft)
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-stone-600">
                    {{ __('The event is a draft and only you can see it. When you publish it, the page becomes public and the form can no longer be changed.') }}
                </p>
                <div class="flex gap-2">
                    <a href="{{ route('events.public', $event) }}" target="_blank" class="inline-flex items-center rounded-md border border-stone-300 bg-white px-4 py-2 text-sm font-medium text-stone-800 hover:bg-stone-50">
                        {{ __('Preview') }}
                    </a>
                    <form method="POST" action="{{ route('events.publish', $event) }}"
                        onsubmit="return confirm(@js(__('Publish the event? The form questions cannot be changed afterwards.')))">
                        @csrf
                        <x-button>{{ __('Publish') }}</x-button>
                    </form>
                </div>
            </div>
        @else
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <label for="public-link" class="block text-sm font-medium">{{ __('Signup link') }}</label>
                    <div class="mt-1 flex gap-2">
                        <input id="public-link" type="text" readonly value="{{ route('events.public', $event) }}" onclick="this.select()"
                            class="block w-full min-w-0 rounded-md border border-stone-300 bg-stone-50 px-3 py-2 font-mono text-sm">
                        <button type="button" x-data="{ copied: false }"
                            x-on:click="navigator.clipboard.writeText(@js(route('events.public', $event))); copied = true; setTimeout(() => copied = false, 2000)"
                            class="shrink-0 rounded-md border border-stone-300 bg-white px-3 py-2 text-sm font-medium hover:bg-stone-50">
                            <span x-show="! copied">{{ __('Copy') }}</span>
                            <span x-show="copied" x-cloak>{{ __('Copied!') }}</span>
                        </button>
                    </div>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('events.public', $event) }}" target="_blank" class="inline-flex items-center rounded-md border border-stone-300 bg-white px-4 py-2 text-sm font-medium text-stone-800 hover:bg-stone-50">
                        {{ __('Open page') }}
                    </a>
                    @if ($event->status === EventStatus::Published)
                        <form method="POST" action="{{ route('events.close', $event) }}">
                            @csrf
                            <x-button variant="secondary">{{ __('Close signups') }}</x-button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('events.reopen', $event) }}">
                            @csrf
                            <x-button variant="secondary">{{ __('Reopen signups') }}</x-button>
                        </form>
                    @endif
                </div>
            </div>
        @endif
    </div>

    <nav class="mt-6 flex gap-6 border-b border-stone-200 text-sm" aria-label="{{ __('Event sections') }}">
        @foreach ($tabs as $key => $tab)
            <a href="{{ route($tab['route'], $event) }}" @class([
                '-mb-px border-b-2 px-1 pb-3 font-medium',
                'border-teal-700 text-teal-800' => $active === $key,
                'border-transparent text-stone-600 hover:text-stone-900' => $active !== $key,
            ]) @if ($active === $key) aria-current="page" @endif>{{ $tab['label'] }}</a>
        @endforeach
    </nav>
</div>
