<?php

namespace Tests\Feature\Events;

use App\Enums\QuestionType;
use App\Livewire\Events\FormBuilder;
use App\Models\Event;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FormBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_owner_can_add_a_text_question(): void
    {
        $event = Event::factory()->create();

        Livewire::actingAs($event->user)
            ->test(FormBuilder::class, ['event' => $event])
            ->call('add')
            ->set('label', 'Allergier?')
            ->set('type', QuestionType::LongText->value)
            ->set('helpText', 'Skriv "inga" om du inte har några.')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('editing', false)
            ->assertSee('Allergier?');

        $question = $event->questions()->sole();
        $this->assertSame(QuestionType::LongText, $question->type);
        $this->assertFalse($question->is_required);
        $this->assertNull($question->options);
        $this->assertSame(1, $question->position);
    }

    public function test_choice_questions_need_options_which_are_trimmed_and_deduplicated(): void
    {
        $event = Event::factory()->create();

        $component = Livewire::actingAs($event->user)
            ->test(FormBuilder::class, ['event' => $event])
            ->call('add')
            ->set('label', 'Vilken mat?')
            ->set('type', QuestionType::Checkboxes->value)
            ->set('optionsText', "  \n")
            ->call('save')
            ->assertHasErrors('optionsText');

        $component
            ->set('optionsText', " Kött \nFisk\n\nVegetariskt\nFisk")
            ->set('isRequired', true)
            ->call('save')
            ->assertHasNoErrors();

        $question = $event->questions()->sole();
        $this->assertSame(['Kött', 'Fisk', 'Vegetariskt'], $question->options);
        $this->assertTrue($question->is_required);
    }

    public function test_options_are_dropped_when_the_type_has_none(): void
    {
        $event = Event::factory()->create();

        Livewire::actingAs($event->user)
            ->test(FormBuilder::class, ['event' => $event])
            ->call('add')
            ->set('label', 'Ankomstdatum')
            ->set('type', QuestionType::Date->value)
            ->set('optionsText', 'Ignoreras')
            ->set('minValue', '3')
            ->call('save')
            ->assertHasNoErrors();

        $question = $event->questions()->sole();
        $this->assertNull($question->options);
        $this->assertNull($question->min_value);
    }

    public function test_number_questions_validate_their_range(): void
    {
        $event = Event::factory()->create();

        Livewire::actingAs($event->user)
            ->test(FormBuilder::class, ['event' => $event])
            ->call('add')
            ->set('label', 'Antal nätter')
            ->set('type', QuestionType::Number->value)
            ->set('minValue', '5')
            ->set('maxValue', '2')
            ->call('save')
            ->assertHasErrors(['maxValue' => 'gte']);
    }

    public function test_a_number_question_can_count_places(): void
    {
        $event = Event::factory()->create();

        Livewire::actingAs($event->user)
            ->test(FormBuilder::class, ['event' => $event])
            ->call('add')
            ->set('label', 'Antal personer')
            ->set('type', QuestionType::Number->value)
            ->set('maxValue', '8')
            ->set('isCapacityField', true)
            ->call('save')
            ->assertHasNoErrors();

        $question = $event->questions()->sole();
        $this->assertSame($question->id, $event->fresh()->capacity_question_id);
        $this->assertTrue($question->is_required);
        $this->assertSame(1, $question->min_value);
        $this->assertSame(8, $question->max_value);
    }

    public function test_a_capacity_question_cannot_allow_zero(): void
    {
        $event = Event::factory()->create();

        Livewire::actingAs($event->user)
            ->test(FormBuilder::class, ['event' => $event])
            ->call('add')
            ->set('label', 'Antal personer')
            ->set('type', QuestionType::Number->value)
            ->set('minValue', '0')
            ->set('isCapacityField', true)
            ->call('save')
            ->assertHasErrors(['minValue' => 'min']);
    }

    public function test_unmarking_the_capacity_question_counts_signups_again(): void
    {
        $event = Event::factory()->create();
        $question = Question::factory()->for($event)->number(1)->create(['is_required' => true]);
        $event->capacityQuestion()->associate($question)->save();

        Livewire::actingAs($event->user)
            ->test(FormBuilder::class, ['event' => $event])
            ->call('edit', $question->id)
            ->assertSet('isCapacityField', true)
            ->set('isCapacityField', false)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNull($event->fresh()->capacity_question_id);
    }

    public function test_questions_can_be_edited_reordered_and_deleted(): void
    {
        $event = Event::factory()->create();
        $first = Question::factory()->for($event)->create(['label' => 'Första', 'position' => 1]);
        $second = Question::factory()->for($event)->create(['label' => 'Andra', 'position' => 2]);

        $component = Livewire::actingAs($event->user)
            ->test(FormBuilder::class, ['event' => $event])
            ->call('edit', $first->id)
            ->assertSet('label', 'Första')
            ->set('label', 'Första, ändrad')
            ->call('save')
            ->call('move', $second->id, 'up');

        $this->assertSame(['Andra', 'Första, ändrad'], $event->questions()->pluck('label')->all());

        $component->call('move', $second->id, 'up');
        $this->assertSame(['Andra', 'Första, ändrad'], $event->questions()->pluck('label')->all());

        $component->call('delete', $second->id);
        $this->assertModelMissing($second);
    }

    public function test_questions_of_another_event_cannot_be_touched(): void
    {
        $event = Event::factory()->create();
        $otherQuestion = Question::factory()->create();

        Livewire::actingAs($event->user)
            ->test(FormBuilder::class, ['event' => $event])
            ->call('delete', $otherQuestion->id)
            ->assertNotFound();

        $this->assertModelExists($otherQuestion);
    }

    public function test_the_form_is_locked_once_the_event_is_published(): void
    {
        $event = Event::factory()->published()->create();
        $question = Question::factory()->for($event)->create();

        $component = Livewire::actingAs($event->user)
            ->test(FormBuilder::class, ['event' => $event])
            ->assertOk()
            ->assertSee(__('The event has been published, so the form is locked and cannot be changed.'))
            ->assertDontSee(__('Add question'));

        $component->call('add')->assertForbidden();

        Livewire::actingAs($event->user)
            ->test(FormBuilder::class, ['event' => $event])
            ->call('delete', $question->id)
            ->assertForbidden();

        $this->assertModelExists($question);
    }

    public function test_the_form_locks_even_if_the_event_is_published_while_the_builder_is_open(): void
    {
        $event = Event::factory()->create();
        $component = Livewire::actingAs($event->user)->test(FormBuilder::class, ['event' => $event]);

        Event::query()->whereKey($event->id)->first()->publish();

        $component->call('add')->assertForbidden();
    }

    public function test_the_main_admin_can_edit_questions_of_any_draft(): void
    {
        $event = Event::factory()->create();

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(FormBuilder::class, ['event' => $event])
            ->call('add')
            ->set('label', 'Från admin')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(1, $event->questions()->count());
    }

    public function test_another_organizer_cannot_open_the_form_builder(): void
    {
        $event = Event::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('events.form', $event))
            ->assertForbidden();
    }
}
