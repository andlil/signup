@props(['event'])

@php
    use App\Enums\EventStatus;
    $closedReason = $event->signupsClosedReason();
@endphp

<span {{ $attributes->class('inline-flex flex-wrap items-center gap-1.5') }}>
    @if ($event->status === EventStatus::Draft)
        <span class="inline-block rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-800">{{ __('Draft') }}</span>
    @else
        <span class="inline-block rounded-full bg-teal-50 px-2 py-0.5 text-xs font-medium text-teal-800">{{ __('Published') }}</span>
        @if ($closedReason)
            <span class="inline-block rounded-full bg-stone-100 px-2 py-0.5 text-xs font-medium text-stone-600" title="{{ $closedReason }}">
                {{ __('Signups closed') }}<span class="sr-only">: {{ $closedReason }}</span>
            </span>
        @else
            <span class="inline-block rounded-full bg-sky-50 px-2 py-0.5 text-xs font-medium text-sky-800">{{ __('Signups open') }}</span>
        @endif
    @endif
</span>
