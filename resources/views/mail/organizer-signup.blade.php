<x-mail::message>
@if ($cancelled)
# {{ __(':name has cancelled', ['name' => $signup->name]) }}
@else
# {{ __('New signup from :name', ['name' => $signup->name]) }}
@endif

@if (! $cancelled && $signup->status === \App\Enums\SignupStatus::Waitlisted)
{{ __('The event is full, so the signup was put on the waiting list.') }}
@endif

@include('mail.partials.signup-summary')

@if ($event->capacity !== null)
{{ __(':taken of :capacity places are taken.', ['taken' => $event->takenPlaces(), 'capacity' => $event->capacity]) }}
@endif

<x-mail::button :url="route('events.signups', $event)">
{{ __('See all signups') }}
</x-mail::button>
</x-mail::message>
