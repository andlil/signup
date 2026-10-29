<x-layouts.app :title="$event->title">
    <article class="mx-auto max-w-2xl">
        @if ($isPreview)
            <div role="status" class="mb-6 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                {{ __('Preview. The event is a draft and is only visible to you.') }}
            </div>
        @endif

        <h1 class="text-3xl font-semibold tracking-tight">{{ $event->title }}</h1>

        <dl class="mt-4 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-[auto_1fr]">
            @if ($event->starts_at)
                <dt class="font-medium text-stone-500">{{ __('When') }}</dt>
                <dd>
                    {{ $event->starts_at->isoFormat('LLLL') }}
                    @if ($event->ends_at)
                        – {{ $event->ends_at->isSameDay($event->starts_at) ? $event->ends_at->isoFormat('LT') : $event->ends_at->isoFormat('LLLL') }}
                    @endif
                </dd>
            @endif
            @if ($event->location)
                <dt class="font-medium text-stone-500">{{ __('Where') }}</dt>
                <dd>{{ $event->location }}</dd>
            @endif
            @if ($event->signup_deadline)
                <dt class="font-medium text-stone-500">{{ __('Sign up by') }}</dt>
                <dd>{{ $event->signup_deadline->isoFormat('LLL') }}</dd>
            @endif
        </dl>

        @if ($event->description)
            <div class="prose-content mt-6">{{ $event->descriptionHtml() }}</div>
        @endif

        <section class="mt-8 rounded-lg border border-stone-200 bg-white p-5" aria-labelledby="signup-heading">
            <h2 id="signup-heading" class="text-lg font-semibold">{{ __('Sign up') }}</h2>

            @if (! $isPreview && ! $event->acceptsSignups())
                <p class="mt-2 text-stone-600">{{ __('Signups for this event are closed.') }}</p>
            @else
                @if ($remainingPlaces !== null)
                    <p @class([
                        'mt-2 rounded-md px-3 py-2 text-sm',
                        'bg-teal-50 text-teal-900' => $remainingPlaces > 0,
                        'bg-amber-50 text-amber-900' => $remainingPlaces === 0,
                    ])>
                        @if ($remainingPlaces > 0)
                            {{ trans_choice(':count place left of :capacity.|:count places left of :capacity.', $remainingPlaces, ['capacity' => $event->capacity]) }}
                        @else
                            {{ __('The event is full. If you sign up, you go on the waiting list and get an email if a place becomes free.') }}
                        @endif
                    </p>
                @endif

                <form method="POST" action="{{ route('signups.store', $event) }}" class="mt-4 space-y-5">
                    @csrf

                    <x-signups.fields :event="$event" />

                    <div class="hidden" aria-hidden="true">
                        <label for="website">Website</label>
                        <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
                    </div>

                    <div>
                        <label class="flex items-start gap-2 text-sm">
                            <input type="checkbox" name="consent" value="1" required @checked(old('consent')) class="mt-0.5 rounded border-stone-300">
                            <span>{{ __('I agree that my answers are stored so the organizer can manage the event. They are deleted :days days after the event.', ['days' => $event->retention_days]) }} <span class="text-red-700">*</span></span>
                        </label>
                        <x-form.error for="consent" />
                    </div>

                    @if ($isPreview)
                        <p class="rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-900">{{ __('Signing up opens when the event is published.') }}</p>
                    @else
                        <x-button>{{ $remainingPlaces === 0 ? __('Join the waiting list') : __('Sign up') }}</x-button>
                    @endif
                </form>
            @endif
        </section>
    </article>
</x-layouts.app>
