@props(['question', 'name', 'selected' => [], 'disabled' => false])

@php
    $selectable = array_flip($question->selectableDayValues());
    $months = [];

    // Show whole weeks, Monday to Sunday, grouped by the month the week starts in.
    $day = $question->period_start->startOfWeek(\Carbon\CarbonInterface::MONDAY);
    $lastDay = $question->period_end->endOfWeek(\Carbon\CarbonInterface::SUNDAY);

    while ($day->lessThanOrEqualTo($lastDay)) {
        $week = [];
        for ($i = 0; $i < 7; $i++) {
            $week[] = $day;
            $day = $day->addDay();
        }

        $monthDay = collect($week)->first(fn ($weekDay) => isset($selectable[$weekDay->toDateString()])) ?? $week[0];
        $months[$monthDay->format('Y-m')]['label'] ??= $monthDay->isoFormat('MMMM YYYY');
        $months[$monthDay->format('Y-m')]['weeks'][] = $week;
    }

    $weekdayNames = collect(range(0, 6))->map(fn ($offset) => $question->period_start->startOfWeek(\Carbon\CarbonInterface::MONDAY)->addDays($offset)->isoFormat('dd'));
    $selectedCount = count(array_intersect(array_keys($selectable), $selected));
@endphp

<fieldset data-vacation-calendar>
    <legend class="block text-sm font-medium">
        {{ $question->label }} @if ($question->is_required) <span class="text-red-700">*</span> @endif
    </legend>
    <p class="text-sm text-stone-500">
        {{ $question->help_text ?: __('Tick the days you want off.') }}
    </p>
    <p class="mt-1 text-sm font-medium text-teal-800" aria-live="polite">
        <span data-vacation-count>{{ $selectedCount }}</span> {{ __('days selected') }}
    </p>

    <div class="mt-3 space-y-5">
        @foreach ($months as $month)
            <div>
                <p class="mb-1 text-sm font-semibold capitalize">{{ $month['label'] }}</p>
                <table class="w-full table-fixed border-separate border-spacing-1 text-center text-sm">
                    <thead>
                        <tr class="text-xs text-stone-500">
                            <th scope="col" class="w-12 font-normal"><span class="sr-only">{{ __('Week') }}</span></th>
                            @foreach ($weekdayNames as $weekdayName)
                                <th scope="col" class="font-normal capitalize">{{ $weekdayName }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($month['weeks'] as $week)
                            @php $weekHasSelectableDays = collect($week)->contains(fn ($weekDay) => isset($selectable[$weekDay->toDateString()])); @endphp
                            <tr data-vacation-week>
                                <th scope="row" class="font-normal">
                                    @if ($weekHasSelectableDays && ! $disabled)
                                        <button type="button" data-vacation-toggle-week
                                            title="{{ __('Select or clear the whole week') }}"
                                            class="w-full rounded px-1 py-2 text-xs text-stone-600 hover:bg-stone-100">
                                            {{ __('w.') }} {{ $week[0]->isoWeek() }}
                                        </button>
                                    @else
                                        <span class="text-xs text-stone-400">{{ __('w.') }} {{ $week[0]->isoWeek() }}</span>
                                    @endif
                                </th>
                                @foreach ($week as $weekDay)
                                    @php $value = $weekDay->toDateString(); @endphp
                                    <td>
                                        @if (isset($selectable[$value]))
                                            <label @class([
                                                'flex h-10 cursor-pointer items-center justify-center rounded-md border border-stone-200 bg-white select-none hover:border-teal-600',
                                                'has-checked:border-teal-700 has-checked:bg-teal-700 has-checked:font-semibold has-checked:text-white',
                                                'has-focus-visible:ring-2 has-focus-visible:ring-teal-600/40',
                                                'cursor-not-allowed opacity-60' => $disabled,
                                            ]) title="{{ $weekDay->isoFormat('dddd D MMMM') }}">
                                                <input type="checkbox" name="{{ $name }}" value="{{ $value }}" class="sr-only"
                                                    @checked(in_array($value, $selected, true)) @disabled($disabled)
                                                    aria-label="{{ $weekDay->isoFormat('dddd D MMMM YYYY') }}">
                                                {{ $weekDay->day }}
                                            </label>
                                        @else
                                            <span class="flex h-10 items-center justify-center text-stone-300" aria-hidden="true">{{ $weekDay->day }}</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach
    </div>
</fieldset>
