<x-mail::message>
# {{ __('Signups since yesterday') }}

@foreach ($summaries as $summary)
## {{ $summary['title'] }}

@if ($summary['new'] !== [])
**{{ trans_choice(':count new signup|:count new signups', count($summary['new'])) }}:**
@foreach ($summary['new'] as $signup)
- {{ $signup['name'] }}@if ($signup['waitlisted']) ({{ __('waiting list') }})@endif

@endforeach
@endif

@if ($summary['cancelled'] !== [])
**{{ trans_choice(':count cancellation|:count cancellations', count($summary['cancelled'])) }}:**
@foreach ($summary['cancelled'] as $name)
- {{ $name }}
@endforeach

@endif
@if ($summary['capacity'] !== null)
{{ __(':taken of :capacity places are taken.', ['taken' => $summary['taken'], 'capacity' => $summary['capacity']]) }}
@else
{{ trans_choice(':count place taken|:count places taken', $summary['taken']) }}.
@endif
@if ($summary['waitlisted'] > 0)
{{ trans_choice(':count on the waiting list|:count on the waiting list', $summary['waitlisted']) }}.
@endif

<x-mail::button :url="$summary['url']">
{{ __('See all signups') }}
</x-mail::button>
@endforeach
</x-mail::message>
