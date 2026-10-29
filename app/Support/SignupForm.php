<?php

namespace App\Support;

use App\Enums\QuestionType;
use App\Enums\SignupStatus;
use App\Models\Event;
use App\Models\Question;
use App\Models\Signup;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Validates a signup form submission against the event's questions.
 */
class SignupForm
{
    public function __construct(private Event $event, private ?Signup $signup = null) {}

    /**
     * Validate the request and return the signup's name, email and normalized answers.
     *
     * @return array{name: string, email: string, answers: array<int, string|int|list<string>>}
     */
    public function validate(Request $request): array
    {
        // Compare emails in lower case, since not every database ignores case.
        if (is_string($request->input('email'))) {
            $request->merge(['email' => mb_strtolower(trim($request->input('email')))]);
        }

        $validated = $request->validate($this->rules(), $this->messages(), $this->attributes());

        return [
            'name' => trim($validated['name']),
            'email' => mb_strtolower(trim($validated['email'])),
            'answers' => $this->normalizeAnswers($validated['answers'] ?? []),
        ];
    }

    /**
     * Get the validation rules for the fixed fields and every question.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique(Signup::class)
                    ->where('event_id', $this->event->id)
                    ->whereNot('status', SignupStatus::Cancelled->value)
                    ->ignore($this->signup?->id),
            ],
            'answers' => ['array'],
        ];

        if ($this->signup === null) {
            $rules['consent'] = ['accepted'];
        }

        foreach ($this->event->questions as $question) {
            $rules = [...$rules, ...$this->rulesFor($question)];
        }

        return $rules;
    }

    /**
     * Get custom error messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => __('This email address is already signed up. Use the link in your confirmation email to change your signup.'),
        ];
    }

    /**
     * Get readable names for the fields, used in error messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [];

        foreach ($this->event->questions as $question) {
            $attributes[$question->fieldName()] = $question->label;
            $attributes[$question->fieldName().'.*'] = $question->label;
        }

        return $attributes;
    }

    /**
     * Get the number of places a signup with these answers takes.
     *
     * @param  array<int, mixed>  $answers
     */
    public function placesFor(array $answers): int
    {
        $questionId = $this->event->capacity_question_id;

        return $questionId === null ? 1 : max(1, (int) ($answers[$questionId] ?? 1));
    }

    /**
     * @return array<string, mixed>
     */
    private function rulesFor(Question $question): array
    {
        $field = $question->fieldName();
        $presence = $question->is_required ? 'required' : 'nullable';

        return match ($question->type) {
            QuestionType::ShortText => [$field => [$presence, 'string', 'max:1000']],
            QuestionType::LongText => [$field => [$presence, 'string', 'max:5000']],
            QuestionType::SingleChoice, QuestionType::Dropdown => [$field => [$presence, 'string', Rule::in($question->options)]],
            QuestionType::Checkboxes => [
                $field => [$question->is_required ? 'required' : 'nullable', 'array', ...($question->is_required ? ['min:1'] : [])],
                $field.'.*' => ['string', 'distinct', Rule::in($question->options)],
            ],
            QuestionType::Date => [$field => [$presence, 'date_format:Y-m-d']],
            QuestionType::Number => [$field => array_filter([
                $presence,
                'integer',
                $question->min_value !== null ? 'min:'.$question->min_value : null,
                $question->max_value !== null ? 'max:'.$question->max_value : null,
                ...$this->capacityRules($question),
            ])],
        };
    }

    /**
     * A signup cannot ask for more places than the event has.
     *
     * @return list<string>
     */
    private function capacityRules(Question $question): array
    {
        if ($question->id !== $this->event->capacity_question_id || $this->event->capacity === null) {
            return [];
        }

        return ['max:'.$this->event->capacity];
    }

    /**
     * Keep answers to known questions only, with numbers as integers and empty answers removed.
     *
     * @param  array<int|string, mixed>  $answers
     * @return array<int, string|int|list<string>>
     */
    private function normalizeAnswers(array $answers): array
    {
        $normalized = [];

        foreach ($this->event->questions as $question) {
            $answer = $answers[$question->id] ?? null;

            if ($answer === null || $answer === '' || $answer === []) {
                continue;
            }

            $normalized[$question->id] = match ($question->type) {
                QuestionType::Number => (int) $answer,
                QuestionType::Checkboxes => array_values(array_intersect($question->options, $answer)),
                default => trim((string) $answer),
            };
        }

        return $normalized;
    }
}
