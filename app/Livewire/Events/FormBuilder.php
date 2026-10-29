<?php

namespace App\Livewire\Events;

use App\Enums\QuestionType;
use App\Models\Event;
use App\Models\Question;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.layouts.app')]
class FormBuilder extends Component
{
    #[Locked]
    public Event $event;

    /**
     * Whether the question editor is open.
     */
    public bool $editing = false;

    /**
     * The question being edited, or null when adding a new one.
     */
    #[Locked]
    public ?int $editingId = null;

    public string $type = QuestionType::ShortText->value;

    public string $label = '';

    public string $helpText = '';

    public bool $isRequired = false;

    /**
     * The answer options, one per line.
     */
    public string $optionsText = '';

    public string $minValue = '';

    public string $maxValue = '';

    public bool $isCapacityField = false;

    public string $periodStart = '';

    public string $periodEnd = '';

    public bool $weekdaysOnly = false;

    /**
     * Only people who can edit the event may see its form builder.
     */
    public function mount(Event $event): void
    {
        Gate::authorize('update', $event);

        $this->event = $event;
    }

    /**
     * Open an empty editor for a new question.
     */
    public function add(): void
    {
        $this->authorizeEditing();

        $this->resetEditor();
        $this->editing = true;
    }

    /**
     * Open the editor for an existing question.
     */
    public function edit(int $questionId): void
    {
        $this->authorizeEditing();

        $question = $this->findQuestion($questionId);

        $this->resetEditor();
        $this->editing = true;
        $this->editingId = $question->id;
        $this->type = $question->type->value;
        $this->label = $question->label;
        $this->helpText = $question->help_text ?? '';
        $this->isRequired = $question->is_required;
        $this->optionsText = implode("\n", $question->options ?? []);
        $this->minValue = (string) ($question->min_value ?? '');
        $this->maxValue = (string) ($question->max_value ?? '');
        $this->isCapacityField = $this->event->capacity_question_id === $question->id;
        $this->periodStart = $question->period_start?->toDateString() ?? '';
        $this->periodEnd = $question->period_end?->toDateString() ?? '';
        $this->weekdaysOnly = $question->weekdays_only;
    }

    /**
     * Close the editor without saving.
     */
    public function cancel(): void
    {
        $this->resetEditor();
    }

    /**
     * Save the question in the editor.
     */
    public function save(): void
    {
        $this->authorizeEditing();

        $type = QuestionType::tryFrom($this->type);
        $isCapacityField = $type === QuestionType::Number && $this->isCapacityField;

        $validated = $this->withValidator(function (Validator $validator) use ($type): void {
            $validator->after(function (Validator $validator) use ($type): void {
                if ($type?->hasOptions() && $this->options()->isEmpty()) {
                    $validator->errors()->add('optionsText', __('Add at least one option.'));
                }

                if ($this->options()->count() > 50) {
                    $validator->errors()->add('optionsText', __('A question can have at most 50 options.'));
                }

                if ($this->options()->contains(fn (string $option): bool => mb_strlen($option) > 255)) {
                    $validator->errors()->add('optionsText', __('Each option can be at most 255 characters.'));
                }

                if ($type?->hasPeriod() && ! $validator->errors()->hasAny(['periodStart', 'periodEnd'])) {
                    $start = CarbonImmutable::parse($this->periodStart);
                    $end = CarbonImmutable::parse($this->periodEnd);

                    if ($start->diffInDays($end) + 1 > Question::MAX_PERIOD_DAYS) {
                        $validator->errors()->add('periodEnd', __('The period can be at most :days days.', ['days' => Question::MAX_PERIOD_DAYS]));
                    } elseif ((new Question(['period_start' => $start, 'period_end' => $end, 'weekdays_only' => $this->weekdaysOnly]))->selectableDays()->isEmpty()) {
                        $validator->errors()->add('periodEnd', __('The period has no weekdays.'));
                    }
                }
            });
        })->validate([
            'type' => ['required', Rule::enum(QuestionType::class)],
            'label' => ['required', 'string', 'max:255'],
            'helpText' => ['nullable', 'string', 'max:1000'],
            'isRequired' => ['boolean'],
            'optionsText' => ['nullable', 'string', 'max:20000'],
            'minValue' => ['nullable', 'integer', 'min:'.($isCapacityField ? 1 : -1000000), 'max:1000000'],
            'maxValue' => ['nullable', 'integer', 'max:1000000', ...($this->minValue !== '' ? ['gte:minValue'] : [])],
            'periodStart' => [...($type?->hasPeriod() ? ['required'] : ['nullable']), 'date_format:Y-m-d'],
            'periodEnd' => [...($type?->hasPeriod() ? ['required', 'after_or_equal:periodStart'] : ['nullable']), 'date_format:Y-m-d'],
            'weekdaysOnly' => ['boolean'],
        ]);

        $attributes = [
            'type' => $type,
            'label' => $validated['label'],
            'help_text' => $validated['helpText'] ?: null,
            'is_required' => $isCapacityField || $validated['isRequired'],
            'options' => $type->hasOptions() ? $this->options()->all() : null,
            'min_value' => $type->hasRange() ? $this->nullableInteger($validated['minValue']) ?? ($isCapacityField ? 1 : null) : null,
            'max_value' => $type->hasRange() ? $this->nullableInteger($validated['maxValue']) : null,
            'period_start' => $type->hasPeriod() ? $validated['periodStart'] : null,
            'period_end' => $type->hasPeriod() ? $validated['periodEnd'] : null,
            'weekdays_only' => $type->hasPeriod() && $validated['weekdaysOnly'],
        ];

        DB::transaction(function () use ($attributes, $isCapacityField): void {
            $question = $this->editingId === null
                ? $this->event->questions()->create([
                    ...$attributes,
                    'position' => ($this->event->questions()->max('position') ?? 0) + 1,
                ])
                : tap($this->findQuestion($this->editingId))->update($attributes);

            if ($isCapacityField) {
                $this->event->capacityQuestion()->associate($question)->save();
            } elseif ($this->event->capacity_question_id === $question->id) {
                $this->event->capacityQuestion()->dissociate()->save();
            }
        });

        $this->resetEditor();
    }

    /**
     * Delete a question.
     */
    public function delete(int $questionId): void
    {
        $this->authorizeEditing();

        $this->findQuestion($questionId)->delete();
        $this->event->refresh();

        if ($this->editingId === $questionId) {
            $this->resetEditor();
        }
    }

    /**
     * Move a question one step up or down in the form.
     */
    public function move(int $questionId, string $direction): void
    {
        $this->authorizeEditing();

        $questions = $this->event->questions()->get()->values();
        $index = $questions->search(fn (Question $question): bool => $question->id === $questionId);
        $swapIndex = $direction === 'up' ? $index - 1 : $index + 1;

        if ($index === false || ! $questions->has($swapIndex)) {
            return;
        }

        $current = $questions[$index];
        $other = $questions[$swapIndex];

        DB::transaction(function () use ($current, $other): void {
            [$currentPosition, $otherPosition] = [$current->position, $other->position];

            $current->update(['position' => $otherPosition]);
            $other->update(['position' => $currentPosition]);
        });
    }

    /**
     * Questions can only change while the event is a draft.
     */
    private function authorizeEditing(): void
    {
        $this->event->refresh();

        Gate::authorize('editQuestions', $this->event);
    }

    /**
     * Find one of this event's questions.
     */
    private function findQuestion(int $questionId): Question
    {
        return $this->event->questions()->findOrFail($questionId);
    }

    /**
     * Parse the options text into a list of unique, non-empty options.
     *
     * @return Collection<int, string>
     */
    private function options(): Collection
    {
        return collect(preg_split('/\R/', $this->optionsText))
            ->map(fn (string $option): string => trim($option))
            ->filter(fn (string $option): bool => $option !== '')
            ->unique()
            ->values();
    }

    private function nullableInteger(?string $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    private function resetEditor(): void
    {
        $this->resetValidation();
        $this->reset('editing', 'editingId', 'type', 'label', 'helpText', 'isRequired', 'optionsText', 'minValue', 'maxValue', 'isCapacityField', 'periodStart', 'periodEnd', 'weekdaysOnly');
    }

    public function render(): View
    {
        return view('livewire.events.form-builder', [
            'questions' => $this->event->questions()->get(),
            'questionTypes' => QuestionType::cases(),
            'selectedType' => QuestionType::tryFrom($this->type),
            'canEditQuestions' => $this->event->hasEditableQuestions(),
        ])->title(__('Form').' · '.$this->event->title);
    }
}
