<?php

namespace Tests\Feature\Events;

use App\Enums\QuestionType;
use App\Livewire\Events\FormBuilder;
use App\Models\Event;
use App\Models\Question;
use App\Models\Signup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

class VacationPlanningTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A vacation planning question for Monday 5 July to Sunday 18 July 2027.
     */
    private function vacationQuestion(Event $event, bool $weekdaysOnly = false, bool $required = false): Question
    {
        return Question::factory()->for($event)->create([
            'type' => QuestionType::VacationDays,
            'label' => 'Semester',
            'period_start' => '2027-07-05',
            'period_end' => '2027-07-18',
            'weekdays_only' => $weekdaysOnly,
            'is_required' => $required,
        ]);
    }

    public function test_the_organizer_can_add_a_vacation_planning_question(): void
    {
        $event = Event::factory()->create();

        Livewire::actingAs($event->user)
            ->test(FormBuilder::class, ['event' => $event])
            ->call('add')
            ->set('label', 'Sommarsemester')
            ->set('type', QuestionType::VacationDays->value)
            ->set('periodStart', '2027-06-28')
            ->set('periodEnd', '2027-08-15')
            ->set('weekdaysOnly', true)
            ->call('save')
            ->assertHasNoErrors();

        $question = $event->questions()->sole();
        $this->assertSame('2027-06-28', $question->period_start->toDateString());
        $this->assertSame('2027-08-15', $question->period_end->toDateString());
        $this->assertTrue($question->weekdays_only);
        $this->assertCount(35, $question->selectableDays());
    }

    public function test_the_period_is_validated(): void
    {
        $event = Event::factory()->create();
        $component = Livewire::actingAs($event->user)
            ->test(FormBuilder::class, ['event' => $event])
            ->call('add')
            ->set('label', 'Semester')
            ->set('type', QuestionType::VacationDays->value);

        $component->call('save')->assertHasErrors(['periodStart' => 'required', 'periodEnd' => 'required']);

        $component->set('periodStart', '2027-07-10')->set('periodEnd', '2027-07-01')->call('save')
            ->assertHasErrors(['periodEnd' => 'after_or_equal']);

        $component->set('periodStart', '2027-01-01')->set('periodEnd', '2028-01-02')->call('save')
            ->assertHasErrors(['periodEnd' => [__('The period can be at most :days days.', ['days' => Question::MAX_PERIOD_DAYS])]]);

        $component->set('periodStart', '2027-07-10')->set('periodEnd', '2027-07-11')->set('weekdaysOnly', true)->call('save')
            ->assertHasErrors(['periodEnd' => [__('The period has no weekdays.')]]);

        $this->assertDatabaseEmpty('questions');
    }

    public function test_the_signup_form_shows_a_calendar_of_the_selectable_days(): void
    {
        $event = Event::factory()->published()->create();
        $question = $this->vacationQuestion($event, weekdaysOnly: true);

        $response = $this->get(route('events.public', $event))->assertOk();

        $this->assertSame(10, substr_count($response->getContent(), 'name="answers['.$question->id.'][]"'));
        $response->assertSee('value="2027-07-09"', false)
            ->assertDontSee('value="2027-07-10"', false);
    }

    public function test_participants_pick_days_which_are_stored_in_order(): void
    {
        Mail::fake();
        $event = Event::factory()->published()->create();
        $question = $this->vacationQuestion($event);

        $this->post(route('signups.store', $event), [
            'name' => 'Anna',
            'email' => 'anna@example.com',
            'consent' => '1',
            'answers' => [$question->id => ['2027-07-12', '2027-07-05', '2027-07-06']],
        ])->assertSessionHasNoErrors();

        $this->assertSame(['2027-07-05', '2027-07-06', '2027-07-12'], Signup::query()->sole()->answerTo($question));
    }

    public function test_days_outside_the_period_or_on_hidden_weekends_are_rejected(): void
    {
        $event = Event::factory()->published()->create();
        $question = $this->vacationQuestion($event, weekdaysOnly: true, required: true);
        $form = ['name' => 'Anna', 'email' => 'anna@example.com', 'consent' => '1'];

        $this->post(route('signups.store', $event), $form)->assertSessionHasErrors("answers.{$question->id}");

        $this->post(route('signups.store', $event), [...$form, 'answers' => [$question->id => ['2027-07-10']]])
            ->assertSessionHasErrors("answers.{$question->id}.0");

        $this->post(route('signups.store', $event), [...$form, 'answers' => [$question->id => ['2027-08-01']]])
            ->assertSessionHasErrors("answers.{$question->id}.0");

        $this->assertDatabaseEmpty('signups');
    }

    public function test_picked_days_are_described_compactly(): void
    {
        app()->setLocale('sv');
        $event = Event::factory()->published()->create();
        $weekdays = $this->vacationQuestion($event, weekdaysOnly: true);
        $allDays = $this->vacationQuestion($event);

        $this->assertSame('8–12 juli (3 dagar)', $weekdays->describeDays(['2027-07-08', '2027-07-09', '2027-07-12']));
        $this->assertSame('8–9 juli, 12 juli (3 dagar)', $allDays->describeDays(['2027-07-08', '2027-07-09', '2027-07-12']));
        $this->assertSame('5 juli (1 dag)', $allDays->describeDays(['2027-07-05']));
        $this->assertSame('', $allDays->describeDays([]));
    }

    public function test_the_export_has_a_column_per_day(): void
    {
        $event = Event::factory()->published()->create();
        $question = $this->vacationQuestion($event, weekdaysOnly: true);
        Signup::factory()->for($event)->create(['answers' => [$question->id => ['2027-07-05', '2027-07-16']]]);

        $content = $this->withSession(['locale' => 'sv'])
            ->actingAs($event->user)
            ->get(route('events.signups.export', $event))
            ->streamedContent();

        $path = tempnam(sys_get_temp_dir(), 'export-test-').'.xlsx';
        file_put_contents($path, $content);
        $reader = new Reader;
        $reader->open($path);
        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }
        }
        $reader->close();
        unlink($path);

        $headings = array_slice($rows[0], 4, 11);
        $this->assertSame('Semester (dagar)', $headings[0]);
        $this->assertSame('2027-07-05', $headings[1]);
        $this->assertSame('2027-07-16', $headings[10]);

        $answers = array_slice($rows[1], 4, 11);
        $this->assertSame(2, $answers[0]);
        $this->assertSame('x', $answers[1]);
        $this->assertSame('x', $answers[10]);
        $this->assertEmpty($answers[2]);
    }
}
