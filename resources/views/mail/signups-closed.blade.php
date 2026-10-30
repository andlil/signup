<x-mail::message>
# {{ __('Signups have closed') }}

{{ __('Signups for :event closed :date. The Excel file with all signups is attached.', ['event' => $event->title, 'date' => $event->signupsCloseAt()->isoFormat('LLL')]) }}

{{ trans_choice(':count confirmed signup|:count confirmed signups', $confirmed) }}@if ($event->capacity !== null), {{ __(':taken of :capacity places are taken.', ['taken' => $event->takenPlaces(), 'capacity' => $event->capacity]) }}@else.@endif
@if ($waitlisted > 0)
{{ trans_choice(':count on the waiting list|:count on the waiting list', $waitlisted) }}.
@endif

<x-mail::button :url="route('events.signups', $event)">
{{ __('See all signups') }}
</x-mail::button>
</x-mail::message>
