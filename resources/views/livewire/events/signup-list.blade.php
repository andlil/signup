@php use App\Enums\SignupStatus; @endphp

<div>
    <x-events.header :event="$event" active="signups" />

    @if ($status)
        <div role="status" class="mb-6 rounded-md border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-900">{{ $status }}</div>
    @endif

    <dl class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-lg border border-stone-200 bg-white p-4">
            <dt class="text-sm text-stone-600">{{ __('Places taken') }}</dt>
            <dd class="mt-1 text-2xl font-semibold">
                {{ (int) ($counts[SignupStatus::Confirmed->value]->places ?? 0) }}@if ($event->capacity !== null)<span class="text-base font-normal text-stone-500"> / {{ $event->capacity }}</span>@endif
            </dd>
        </div>
        <div class="rounded-lg border border-stone-200 bg-white p-4">
            <dt class="text-sm text-stone-600">{{ __('Confirmed signups') }}</dt>
            <dd class="mt-1 text-2xl font-semibold">{{ $counts[SignupStatus::Confirmed->value]->signups ?? 0 }}</dd>
        </div>
        <div class="rounded-lg border border-stone-200 bg-white p-4">
            <dt class="text-sm text-stone-600">{{ __('On the waiting list') }}</dt>
            <dd class="mt-1 text-2xl font-semibold">{{ $counts[SignupStatus::Waitlisted->value]->signups ?? 0 }}</dd>
        </div>
    </dl>

    <div class="mt-6 flex flex-wrap items-center gap-2">
        <div class="flex flex-wrap gap-2" role="tablist">
        @foreach ($statuses as $statusOption)
            <button type="button" role="tab" wire:click="$set('show', '{{ $statusOption->value }}')"
                aria-selected="{{ $currentStatus === $statusOption ? 'true' : 'false' }}"
                @class([
                    'rounded-full px-3 py-1 text-sm font-medium',
                    'bg-stone-800 text-white' => $currentStatus === $statusOption,
                    'bg-white text-stone-700 ring-1 ring-stone-200 hover:bg-stone-50' => $currentStatus !== $statusOption,
                ])>
                {{ $statusOption->groupLabel() }} ({{ $counts[$statusOption->value]->signups ?? 0 }})
            </button>
        @endforeach
        </div>

        <a href="{{ route('events.signups.export', $event) }}" class="ml-auto inline-flex items-center rounded-md border border-stone-300 bg-white px-3 py-1.5 text-sm font-medium text-stone-800 hover:bg-stone-50">
            {{ __('Download Excel') }}
        </a>
    </div>

    <p class="mt-3 text-sm text-stone-500">
        {{ __('The signups are deleted automatically on :date, :days days after the event.', ['date' => $event->signupsExpireAt()->isoFormat('LL'), 'days' => $event->retention_days]) }}
    </p>

    @if ($signups->isEmpty())
        <div class="mt-4 rounded-lg border border-dashed border-stone-300 bg-white px-6 py-10 text-center text-sm text-stone-600">
            {{ __('No signups here yet.') }}
        </div>
    @else
        <div class="mt-4 overflow-x-auto rounded-lg border border-stone-200 bg-white">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-stone-200 bg-stone-50 text-stone-600">
                    <tr>
                        @if ($currentStatus === SignupStatus::Waitlisted)
                            <th scope="col" class="px-4 py-3 font-medium">#</th>
                        @endif
                        <th scope="col" class="px-4 py-3 font-medium">{{ __('Name') }}</th>
                        <th scope="col" class="px-4 py-3 font-medium">{{ __('Email') }}</th>
                        <th scope="col" class="px-4 py-3 font-medium">{{ __('Places') }}</th>
                        @foreach ($questions as $question)
                            @if ($question->id !== $event->capacity_question_id)
                                <th scope="col" class="px-4 py-3 font-medium">{{ $question->label }}</th>
                            @endif
                        @endforeach
                        <th scope="col" class="px-4 py-3 font-medium">{{ __('Signed up') }}</th>
                        <th scope="col" class="px-4 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @foreach ($signups as $signup)
                        <tr wire:key="signup-{{ $signup->id }}" class="align-top">
                            @if ($currentStatus === SignupStatus::Waitlisted)
                                <td class="px-4 py-3 text-stone-500">{{ $loop->iteration }}</td>
                            @endif
                            <td class="px-4 py-3 font-medium">{{ $signup->name }}</td>
                            <td class="px-4 py-3"><a href="mailto:{{ $signup->email }}" class="hover:underline">{{ $signup->email }}</a></td>
                            <td class="px-4 py-3">{{ $signup->places }}</td>
                            @foreach ($questions as $question)
                                @if ($question->id !== $event->capacity_question_id)
                                    <td class="max-w-xs px-4 py-3 break-words whitespace-pre-line">{{ $signup->displayAnswerTo($question) }}</td>
                                @endif
                            @endforeach
                            <td class="px-4 py-3 whitespace-nowrap text-stone-600">{{ $signup->created_at->isoFormat('lll') }}</td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                @if ($signup->status === SignupStatus::Cancelled)
                                    <button type="button" wire:click="delete({{ $signup->id }})"
                                        wire:confirm="{{ __('Delete the signup for :name permanently?', ['name' => $signup->name]) }}"
                                        class="text-red-700 hover:underline">{{ __('Delete') }}</button>
                                @else
                                    <button type="button" wire:click="cancel({{ $signup->id }})"
                                        wire:confirm="{{ __('Cancel the signup for :name? They will get an email about it.', ['name' => $signup->name]) }}"
                                        class="text-red-700 hover:underline">{{ __('Cancel signup') }}</button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
