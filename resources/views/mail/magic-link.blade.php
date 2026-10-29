<x-mail::message>
# {{ __('Hi :name!', ['name' => $user->name]) }}

{{ __('Click the button below to log in to :app.', ['app' => config('app.name')]) }}

<x-mail::button :url="$url">
{{ __('Log in') }}
</x-mail::button>

{{ __('The link is valid for :minutes minutes and can only be used once.', ['minutes' => $minutes]) }}

{{ __('If you did not ask to log in, you can ignore this email.') }}
</x-mail::message>
