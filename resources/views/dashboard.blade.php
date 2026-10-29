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
                                {{ $event->starts_at->isoFormat('LLL') }}@if ($event->location) · {{ $event->location }}@endif
                                @if ($showOrganizer) · {{ $event->user->name }}@endif
                            </p>
                        </div>
                        <span class="text-sm text-stone-500">{{ trans_choice(':count question|:count questions', $event->questions_count) }}</span>
                        <x-events.status-badge :status="$event->status" />
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</x-layouts.app>
