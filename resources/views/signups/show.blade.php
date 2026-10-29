@php use App\Enums\SignupStatus; @endphp

<x-layouts.app :title="$event->title">
    <div class="mx-auto max-w-2xl">
        @switch(session('signup_status'))
            @case('confirmed')
                <div role="status" class="mb-6 rounded-md border border-teal-200 bg-teal-50 px-4 py-3 text-teal-900">
                    <p class="font-medium">{{ __('Thank you, you are signed up!') }}</p>
                    <p class="mt-1 text-sm">{{ __('We have emailed you a confirmation. Save this page: its address is your personal link to the signup.') }}</p>
                </div>
                @break
            @case('waitlisted')
                <div role="status" class="mb-6 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-amber-900">
                    <p class="font-medium">{{ __('You are on the waiting list.') }}</p>
                    <p class="mt-1 text-sm">{{ __('We will email you if a place becomes free. Save this page: its address is your personal link to the signup.') }}</p>
                </div>
                @break
            @case('updated')
                <div role="status" class="mb-6 rounded-md border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-900">{{ __('Your changes have been saved.') }}</div>
                @break
            @case('cancelled')
                <div role="status" class="mb-6 rounded-md border border-stone-200 bg-stone-100 px-4 py-3 text-sm text-stone-800">{{ __('Your signup has been cancelled.') }}</div>
                @break
        @endswitch

        <p class="text-sm text-stone-500">{{ __('Your signup for') }}</p>
        <h1 class="text-2xl font-semibold tracking-tight">
            <a href="{{ route('events.public', $event) }}" class="hover:underline">{{ $event->title }}</a>
        </h1>
        <p class="mt-1 text-sm text-stone-600">
            {{ collect([$event->starts_at?->isoFormat('LLLL'), $event->location])->filter()->implode(' · ') }}
        </p>

        <div class="mt-6 rounded-lg border border-stone-200 bg-white p-5">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="font-semibold">{{ $signup->name }}</h2>
                <x-signups.status-badge :status="$signup->status" />
            </div>

            @if ($signup->status === SignupStatus::Waitlisted)
                <p class="mt-2 text-sm text-stone-600">{{ __('You are number :position on the waiting list.', ['position' => $signup->waitlistPosition()]) }}</p>
            @elseif ($signup->status === SignupStatus::Confirmed && $signup->places > 1)
                <p class="mt-2 text-sm text-stone-600">{{ trans_choice('The signup takes :count place.|The signup takes :count places.', $signup->places) }}</p>
            @endif

            @if ($signup->canBeChanged())
                <form method="POST" action="{{ route('signups.update', ['token' => $signup->manage_token]) }}" class="mt-5 space-y-5 border-t border-stone-100 pt-5">
                    @csrf
                    @method('PUT')

                    <x-signups.fields :event="$event" :signup="$signup" />

                    <x-button>{{ __('Save changes') }}</x-button>
                </form>
            @elseif ($signup->status !== SignupStatus::Cancelled)
                <dl class="mt-5 space-y-3 border-t border-stone-100 pt-5 text-sm">
                    <div><dt class="font-medium">{{ __('Email') }}</dt><dd>{{ $signup->email }}</dd></div>
                    @foreach ($event->questions as $question)
                        <div><dt class="font-medium">{{ $question->label }}</dt><dd>{{ $signup->displayAnswerTo($question) ?: '–' }}</dd></div>
                    @endforeach
                </dl>
                <p class="mt-4 text-sm text-stone-500">{{ __('Signups for the event are closed, so the answers can no longer be changed.') }}</p>
            @endif
        </div>

        @if ($signup->canBeCancelled())
            <form method="POST" action="{{ route('signups.destroy', ['token' => $signup->manage_token]) }}" class="mt-6"
                onsubmit="return confirm(@js(__('Cancel your signup? Your place goes to the next person on the waiting list.')))">
                @csrf
                @method('DELETE')
                <x-button variant="danger">{{ __('Cancel my signup') }}</x-button>
            </form>
        @elseif ($signup->status === SignupStatus::Cancelled && $event->acceptsSignups())
            <a href="{{ route('events.public', $event) }}" class="mt-6 inline-block text-sm text-teal-700 hover:underline">{{ __('Sign up again') }}</a>
        @endif
    </div>
</x-layouts.app>
