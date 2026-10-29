<x-mail::message>
# {{ __('Hi :name!', ['name' => $user->name]) }}

{{ __('An account has been created for you on :app. You log in without a password: enter your email address and we will send you a login link.', ['app' => config('app.name')]) }}

<x-mail::button :url="$url">
{{ __('Go to login') }}
</x-mail::button>
</x-mail::message>
