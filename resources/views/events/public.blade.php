<x-layouts.app :title="$event->title">
    <article class="mx-auto max-w-2xl">
        @if ($isPreview)
            <div role="status" class="mb-6 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                {{ __('Preview. The event is a draft and is only visible to you.') }}
            </div>
        @endif

        @if ($event->coverImageUrl())
            <img src="{{ $event->coverImageUrl() }}" alt="" class="mb-6 aspect-[3/1] w-full rounded-lg object-cover">
        @endif

        <h1 class="text-3xl font-semibold tracking-tight">{{ $event->title }}</h1>

        <dl class="mt-4 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-[auto_1fr]">
            <dt class="font-medium text-stone-500">{{ __('When') }}</dt>
            <dd>
                {{ $event->starts_at->isoFormat('LLLL') }}
                @if ($event->ends_at)
                    – {{ $event->ends_at->isSameDay($event->starts_at) ? $event->ends_at->isoFormat('LT') : $event->ends_at->isoFormat('LLLL') }}
                @endif
            </dd>
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
                <form class="mt-4 space-y-5" onsubmit="return false">
                    <div>
                        <x-form.label for="name">{{ __('Name') }} <span class="text-red-700">*</span></x-form.label>
                        <x-form.input id="name" name="name" type="text" required autocomplete="name" disabled />
                    </div>
                    <div>
                        <x-form.label for="email">{{ __('Email') }} <span class="text-red-700">*</span></x-form.label>
                        <x-form.input id="email" name="email" type="email" required autocomplete="email" disabled />
                    </div>

                    @foreach ($event->questions as $question)
                        <x-events.question-field :question="$question" disabled />
                    @endforeach

                    <p class="rounded-md bg-stone-100 px-3 py-2 text-sm text-stone-600">{{ __('Signing up opens in the next version.') }}</p>
                </form>
            @endif
        </section>
    </article>
</x-layouts.app>
