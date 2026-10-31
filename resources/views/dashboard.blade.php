<x-layouts.app :title="__('My events')">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold tracking-tight">{{ $showOrganizer ? __('All events') : __('My events') }}</h1>
        <a href="{{ route('events.create') }}" class="inline-flex items-center rounded-md bg-teal-700 px-4 py-2 text-sm font-medium text-white hover:bg-teal-800">
            {{ __('New event') }}
        </a>
    </div>

    @if ($events->isEmpty())
        <div class="mt-6 rounded-lg border border-dashed border-stone-300 bg-white px-6 py-12 text-center">
            <p class="font-medium">{{ __('You have no events yet.') }}</p>
            <p class="mt-1 text-sm text-stone-600">{{ __('Create an event, build its signup form and share the link.') }}</p>
        </div>
    @else
        <ul class="mt-6 divide-y divide-stone-100 overflow-hidden rounded-lg border border-stone-200 bg-white">
            @foreach ($events as $event)
                <li>
                    <a href="{{ route('events.edit', $event) }}" class="flex flex-wrap items-center gap-x-4 gap-y-1 px-4 py-4 hover:bg-stone-50">
                        <div class="min-w-0 flex-1">
                            <p class="font-medium">{{ $event->title }}</p>
                            <p class="mt-0.5 text-sm text-stone-600">
                                {{ $event->starts_at ? $event->starts_at->isoFormat('LLL') : __('Sign up by :date', ['date' => $event->signup_deadline->isoFormat('LLL')]) }}@if ($event->location) · {{ $event->location }}@endif
                                @if ($showOrganizer) · {{ $event->user->name }}@endif
                            </p>
                        </div>
                        <span class="text-sm text-stone-600">
                            @if ($event->capacity !== null)
                                {{ __(':taken of :capacity places', ['taken' => (int) $event->confirmed_places, 'capacity' => $event->capacity]) }}
                            @else
                                {{ trans_choice(':count place taken|:count places taken', (int) $event->confirmed_places) }}
                            @endif
                            @if ($event->waitlisted_count > 0)
                                · {{ trans_choice(':count on the waiting list|:count on the waiting list', $event->waitlisted_count) }}
                            @endif
                        </span>
                        <x-events.badges :event="$event" />
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</x-layouts.app>
