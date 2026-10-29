<x-mail::message>
# {{ __('Hi :name!', ['name' => $signup->name]) }}

@if ($byOrganizer)
{{ __('The organizer has cancelled your signup for :event.', ['event' => $event->title]) }}
@else
{{ __('Your signup for :event has been cancelled, as you asked.', ['event' => $event->title]) }}
@endif

@if ($event->acceptsSignups())
{{ __('Changed your mind? You can sign up again on the event page.') }}

<x-mail::button :url="route('events.public', $event)">
{{ __('Go to the event') }}
</x-mail::button>
@endif
</x-mail::message>
