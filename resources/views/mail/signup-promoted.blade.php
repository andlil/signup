<x-mail::message>
# {{ __('Good news, :name!', ['name' => $signup->name]) }}

{{ __('A place has become free at :event and you have moved up from the waiting list. Your place is now confirmed.', ['event' => $event->title]) }}

@include('mail.partials.signup-summary')

{{ __('If you can no longer come, please cancel so someone else gets the place.') }}

<x-mail::button :url="$signup->manageUrl()">
{{ __('Change or cancel') }}
</x-mail::button>
</x-mail::message>
