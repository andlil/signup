<x-mail::message>
# {{ __('Hi :name!', ['name' => $signup->name]) }}

@if ($signup->status === \App\Enums\SignupStatus::Confirmed)
{{ __('Thank you for signing up for :event. Your place is confirmed.', ['event' => $event->title]) }}
@else
{{ __('Thank you for signing up for :event. The event is full, so you are number :position on the waiting list. We will email you if you get a place.', ['event' => $event->title, 'position' => $signup->waitlistPosition()]) }}
@endif

@include('mail.partials.signup-summary')

{{ __('Use the button to change or cancel your signup. Keep this email: the link is personal and is how you reach your signup.') }}

<x-mail::button :url="$signup->manageUrl()">
{{ __('Change or cancel') }}
</x-mail::button>
</x-mail::message>
