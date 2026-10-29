<div>
    <x-events.header :event="$event" active="form" />

    @if (! $canEditQuestions)
        <div class="mb-6 rounded-md border border-stone-200 bg-stone-100 px-4 py-3 text-sm text-stone-700">
            {{ __('The event has been published, so the form is locked and cannot be changed.') }}
        </div>
    @endif

    <section class="rounded-lg border border-stone-200 bg-white">
        <div class="border-b border-stone-100 px-5 py-4">
            <h2 class="font-semibold">{{ __('Signup form') }}</h2>
            <p class="mt-1 text-sm text-stone-600">{{ __('Name and email are always asked for. Add your own questions below.') }}</p>
        </div>

        <ol class="divide-y divide-stone-100">
            @foreach ([__('Name'), __('Email')] as $fixedLabel)
                <li class="flex items-center gap-3 px-5 py-3 text-stone-500">
                    <span class="flex-1">{{ $fixedLabel }} <span class="text-red-700">*</span></span>
                    <span class="text-xs">{{ __('Always included') }}</span>
                </li>
            @endforeach

            @foreach ($questions as $question)
                <li wire:key="question-{{ $question->id }}" class="flex flex-wrap items-center gap-3 px-5 py-3">
                    <div class="min-w-0 flex-1">
                        <p class="font-medium">
                            {{ $question->label }}
                            @if ($question->is_required) <span class="text-red-700">*</span> @endif
                        </p>
                        <p class="text-sm text-stone-500">
                            {{ $question->type->label() }}
                            @if ($question->options) · {{ implode(', ', $question->options) }} @endif
                            @if ($question->type->hasPeriod())
                                · {{ $question->period_start->isoFormat('D MMM YYYY') }} – {{ $question->period_end->isoFormat('D MMM YYYY') }}@if ($question->weekdays_only), {{ __('weekdays only') }}@endif
                            @endif
                            @if ($event->capacity_question_id === $question->id) · <span class="font-medium text-teal-800">{{ __('Counts as places') }}</span> @endif
                        </p>
                    </div>

                    @if ($canEditQuestions)
                        <div class="flex items-center gap-1 text-sm">
                            <button type="button" wire:click="move({{ $question->id }}, 'up')" @disabled($loop->first)
                                class="rounded px-2 py-1 text-stone-600 hover:bg-stone-100 disabled:opacity-30" aria-label="{{ __('Move up') }}">↑</button>
                            <button type="button" wire:click="move({{ $question->id }}, 'down')" @disabled($loop->last)
                                class="rounded px-2 py-1 text-stone-600 hover:bg-stone-100 disabled:opacity-30" aria-label="{{ __('Move down') }}">↓</button>
                            <button type="button" wire:click="edit({{ $question->id }})" class="rounded px-2 py-1 text-stone-700 hover:bg-stone-100">{{ __('Edit') }}</button>
                            <button type="button" wire:click="delete({{ $question->id }})" wire:confirm="{{ __('Delete the question?') }}"
                                class="rounded px-2 py-1 text-red-700 hover:bg-red-50">{{ __('Delete') }}</button>
                        </div>
                    @endif
                </li>
            @endforeach
        </ol>

        @if ($canEditQuestions && ! $editing)
            <div class="border-t border-stone-100 px-5 py-4">
                <x-button type="button" wire:click="add">{{ __('Add question') }}</x-button>
            </div>
        @endif
    </section>

    @if ($editing)
        <section class="mt-6 rounded-lg border border-teal-200 bg-white p-5" wire:key="editor">
            <h2 class="font-semibold">{{ $editingId ? __('Edit question') : __('New question') }}</h2>

            <form wire:submit="save" class="mt-4 grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <x-form.label for="label">{{ __('Question') }}</x-form.label>
                    <x-form.input id="label" type="text" wire:model="label" required maxlength="255" autofocus />
                    <x-form.error for="label" />
                </div>

                <div>
                    <x-form.label for="type">{{ __('Answer type') }}</x-form.label>
                    <x-form.select id="type" wire:model.live="type">
                        @foreach ($questionTypes as $questionType)
                            <option value="{{ $questionType->value }}">{{ $questionType->label() }}</option>
                        @endforeach
                    </x-form.select>
                    <x-form.error for="type" />
                </div>

                <div class="flex items-end">
                    <label class="flex items-center gap-2 pb-2 text-sm">
                        <input type="checkbox" wire:model="isRequired" @disabled($isCapacityField && $selectedType?->hasRange()) class="rounded border-stone-300">
                        {{ __('Required') }}
                    </label>
                </div>

                <div class="sm:col-span-2">
                    <x-form.label for="helpText">{{ __('Help text') }} <span class="font-normal text-stone-500">({{ __('optional') }})</span></x-form.label>
                    <x-form.input id="helpText" type="text" wire:model="helpText" maxlength="1000" />
                    <x-form.error for="helpText" />
                </div>

                @if ($selectedType?->hasOptions())
                    <div class="sm:col-span-2">
                        <x-form.label for="optionsText">{{ __('Options') }}</x-form.label>
                        <p class="text-sm text-stone-500">{{ __('One option per line.') }}</p>
                        <x-form.textarea id="optionsText" wire:model="optionsText" rows="5" />
                        <x-form.error for="optionsText" />
                    </div>
                @endif

                @if ($selectedType?->hasPeriod())
                    <div>
                        <x-form.label for="periodStart">{{ __('First day') }}</x-form.label>
                        <x-form.input id="periodStart" type="date" wire:model="periodStart" />
                        <x-form.error for="periodStart" />
                    </div>

                    <div>
                        <x-form.label for="periodEnd">{{ __('Last day') }}</x-form.label>
                        <x-form.input id="periodEnd" type="date" wire:model="periodEnd" />
                        <x-form.error for="periodEnd" />
                    </div>

                    <label class="flex items-start gap-2 text-sm sm:col-span-2">
                        <input type="checkbox" wire:model="weekdaysOnly" class="mt-0.5 rounded border-stone-300">
                        <span>
                            <span class="font-medium">{{ __('Show weekdays only') }}</span><br>
                            <span class="text-stone-600">{{ __('Participants see every day of the period and tick the days they want off. With weekdays only, Saturdays and Sundays are left out.') }}</span>
                        </span>
                    </label>
                @endif

                @if ($selectedType?->hasRange())
                    <div>
                        <x-form.label for="minValue">{{ __('Lowest allowed') }} <span class="font-normal text-stone-500">({{ __('optional') }})</span></x-form.label>
                        <x-form.input id="minValue" type="number" wire:model="minValue" />
                        <x-form.error for="minValue" />
                    </div>

                    <div>
                        <x-form.label for="maxValue">{{ __('Highest allowed') }} <span class="font-normal text-stone-500">({{ __('optional') }})</span></x-form.label>
                        <x-form.input id="maxValue" type="number" wire:model="maxValue" />
                        <x-form.error for="maxValue" />
                    </div>

                    <label class="flex items-start gap-2 text-sm sm:col-span-2">
                        <input type="checkbox" wire:model.live="isCapacityField" class="mt-0.5 rounded border-stone-300">
                        <span>
                            <span class="font-medium">{{ __('Count places with this answer') }}</span><br>
                            <span class="text-stone-600">{{ __('For example “Number of people”: a signup for 4 people then takes 4 places. The question becomes required with at least 1.') }}</span>
                        </span>
                    </label>
                @endif

                <div class="flex gap-2 sm:col-span-2">
                    <x-button>{{ $editingId ? __('Save question') : __('Add question') }}</x-button>
                    <x-button type="button" variant="secondary" wire:click="cancel">{{ __('Cancel') }}</x-button>
                </div>
            </form>
        </section>
    @endif
</div>
