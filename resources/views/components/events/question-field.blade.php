@props(['question', 'value' => null, 'disabled' => false])

@php
    use App\Enums\QuestionType;
    $id = 'question-'.$question->id;
    $name = 'answers['.$question->id.']';
    $current = old('answers.'.$question->id, $value);
@endphp

<div>
    @if (in_array($question->type, [QuestionType::SingleChoice, QuestionType::Checkboxes], true))
        <fieldset>
            <legend class="block text-sm font-medium">
                {{ $question->label }} @if ($question->is_required) <span class="text-red-700">*</span> @endif
            </legend>
            @if ($question->help_text) <p class="text-sm text-stone-500">{{ $question->help_text }}</p> @endif
            <div class="mt-2 space-y-2">
                @foreach ($question->options as $option)
                    <label class="flex items-center gap-2 text-sm">
                        @if ($question->type === QuestionType::SingleChoice)
                            <input type="radio" name="{{ $name }}" value="{{ $option }}" @checked($current === $option)
                                @required($question->is_required) @disabled($disabled) class="border-stone-300">
                        @else
                            <input type="checkbox" name="{{ $name }}[]" value="{{ $option }}" @checked(in_array($option, (array) $current, true))
                                @disabled($disabled) class="rounded border-stone-300">
                        @endif
                        {{ $option }}
                    </label>
                @endforeach
            </div>
        </fieldset>
    @else
        <x-form.label :for="$id">
            {{ $question->label }} @if ($question->is_required) <span class="text-red-700">*</span> @endif
        </x-form.label>
        @if ($question->help_text) <p class="text-sm text-stone-500">{{ $question->help_text }}</p> @endif

        @switch($question->type)
            @case(QuestionType::LongText)
                <x-form.textarea :id="$id" :name="$name" rows="4" :required="$question->is_required" :disabled="$disabled">{{ $current }}</x-form.textarea>
                @break
            @case(QuestionType::Dropdown)
                <x-form.select :id="$id" :name="$name" :required="$question->is_required" :disabled="$disabled">
                    <option value="">{{ __('Choose…') }}</option>
                    @foreach ($question->options as $option)
                        <option value="{{ $option }}" @selected($current === $option)>{{ $option }}</option>
                    @endforeach
                </x-form.select>
                @break
            @case(QuestionType::Date)
                <x-form.input :id="$id" :name="$name" type="date" :value="$current" :required="$question->is_required" :disabled="$disabled" class="max-w-xs" />
                @break
            @case(QuestionType::Number)
                <x-form.input :id="$id" :name="$name" type="number" :value="$current" :min="$question->min_value" :max="$question->max_value"
                    :required="$question->is_required" :disabled="$disabled" class="max-w-40" />
                @break
            @default
                <x-form.input :id="$id" :name="$name" type="text" :value="$current" maxlength="1000" :required="$question->is_required" :disabled="$disabled" />
        @endswitch
    @endif

    <x-form.error :for="'answers.'.$question->id" />
</div>
