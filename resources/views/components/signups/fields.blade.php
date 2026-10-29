@props(['event', 'signup' => null])

{{-- The signup form fields, shared by the event page and the participant's own page. --}}

<div>
    <x-form.label for="name">{{ __('Name') }} <span class="text-red-700">*</span></x-form.label>
    <x-form.input id="name" name="name" type="text" :value="old('name', $signup?->name)" required maxlength="255" autocomplete="name" />
    <x-form.error for="name" />
</div>

<div>
    <x-form.label for="email">{{ __('Email') }} <span class="text-red-700">*</span></x-form.label>
    <x-form.input id="email" name="email" type="email" :value="old('email', $signup?->email)" required maxlength="255" autocomplete="email" />
    <p class="mt-1 text-sm text-stone-500">{{ __('We send a confirmation with a link for changing or cancelling your signup.') }}</p>
    <x-form.error for="email" />
</div>

@foreach ($event->questions as $question)
    <x-events.question-field :question="$question" :value="$signup?->answerTo($question)" />
@endforeach
