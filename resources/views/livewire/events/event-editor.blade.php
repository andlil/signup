<div>
    @if ($event)
        <x-events.header :event="$event" active="details" />
    @else
        <a href="{{ route('dashboard') }}" class="text-sm text-stone-600 hover:text-stone-900">← {{ __('My events') }}</a>
        <h1 class="mt-2 mb-6 text-2xl font-semibold tracking-tight">{{ __('New event') }}</h1>
    @endif

    @if ($status)
        <div role="status" class="mb-6 rounded-md border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-900">{{ $status }}</div>
    @endif

    <form wire:submit="save" class="space-y-6">
        <section class="space-y-4 rounded-lg border border-stone-200 bg-white p-5">
            <h2 class="font-semibold">{{ __('About the event') }}</h2>

            <div>
                <x-form.label for="title">{{ __('Title') }}</x-form.label>
                <x-form.input id="title" type="text" wire:model="title" required maxlength="255" />
                <x-form.error for="title" />
            </div>

            <div>
                <x-form.label for="description">{{ __('Description') }}</x-form.label>
                <x-form.markdown-editor id="description" property="description" />
                <x-form.error for="description" />
            </div>
        </section>

        <section class="grid gap-4 rounded-lg border border-stone-200 bg-white p-5 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <h2 class="font-semibold">{{ __('When and where') }}</h2>
                <p class="mt-1 text-sm text-stone-600">{{ __('Enter when the event starts, a last day to sign up, or both. For a poll without a fixed date, a last day to sign up is enough.') }}</p>
            </div>

            <div>
                <x-form.label for="startsAt">{{ __('Starts') }}</x-form.label>
                <x-form.input id="startsAt" type="datetime-local" wire:model="startsAt" />
                <x-form.error for="startsAt" />
            </div>

            <div>
                <x-form.label for="endsAt">{{ __('Ends') }} <span class="font-normal text-stone-500">({{ __('optional') }})</span></x-form.label>
                <x-form.input id="endsAt" type="datetime-local" wire:model="endsAt" />
                <x-form.error for="endsAt" />
            </div>

            <div class="sm:col-span-2">
                <x-form.label for="location">{{ __('Location') }} <span class="font-normal text-stone-500">({{ __('optional') }})</span></x-form.label>
                <x-form.input id="location" type="text" wire:model="location" maxlength="255" />
                <x-form.error for="location" />
            </div>
        </section>

        <section class="grid gap-4 rounded-lg border border-stone-200 bg-white p-5 sm:grid-cols-2">
            <h2 class="font-semibold sm:col-span-2">{{ __('Signups') }}</h2>

            <div>
                <x-form.label for="signupDeadline">{{ __('Last day to sign up') }}</x-form.label>
                <x-form.input id="signupDeadline" type="datetime-local" wire:model="signupDeadline" />
                <p class="mt-1 text-sm text-stone-500">{{ __('The form closes automatically after this.') }}</p>
                <x-form.error for="signupDeadline" />
            </div>

            <div>
                <x-form.label for="capacity">{{ __('Number of places') }} <span class="font-normal text-stone-500">({{ __('optional') }})</span></x-form.label>
                <x-form.input id="capacity" type="number" min="1" wire:model="capacity" />
                <p class="mt-1 text-sm text-stone-500">
                    @if ($event?->capacityQuestion)
                        {{ __('Places are counted from the answer to “:question”. Signups that do not fit go on the waiting list.', ['question' => $event->capacityQuestion->label]) }}
                    @else
                        {{ __('Every signup takes one place. To count people instead, mark a number question in the form as the number of places. Signups that do not fit go on the waiting list.') }}
                    @endif
                </p>
                <x-form.error for="capacity" />
            </div>

            <div>
                <x-form.label for="notificationMode">{{ __('Notify me') }}</x-form.label>
                <x-form.select id="notificationMode" wire:model="notificationMode">
                    @foreach ($notificationModes as $mode)
                        <option value="{{ $mode->value }}">{{ $mode->label() }}</option>
                    @endforeach
                </x-form.select>
                <x-form.error for="notificationMode" />
            </div>

            <div>
                <x-form.label for="retentionDays">{{ __('Delete signups after') }}</x-form.label>
                <div class="flex items-center gap-2">
                    <x-form.input id="retentionDays" type="number" min="1" max="3650" wire:model="retentionDays" class="max-w-28" />
                    <span class="mt-1 text-sm text-stone-600">{{ __('days after the event') }}</span>
                </div>
                <p class="mt-1 text-sm text-stone-500">{{ __('Personal data is deleted automatically to comply with GDPR.') }}</p>
                <x-form.error for="retentionDays" />
            </div>
        </section>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <x-button wire:loading.attr="disabled" wire:target="save">
                {{ $event ? __('Save changes') : __('Create event') }}
            </x-button>

            @if ($event)
                @can('delete', $event)
                    <x-button type="button" variant="danger" wire:click="delete"
                        wire:confirm="{{ __('Delete the event? This cannot be undone.') }}">
                        {{ __('Delete event') }}
                    </x-button>
                @endcan
            @endif
        </div>
    </form>
</div>
