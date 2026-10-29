<?php

namespace Tests\Feature\Signups;

use App\Enums\QuestionType;
use App\Models\Event;
use App\Models\Question;
use App\Models\Signup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

class SignupExportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Read every row of the downloaded spreadsheet as plain values.
     *
     * @return list<list<mixed>>
     */
    private function readRows(string $content): array
    {
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

        return $rows;
    }

    public function test_the_organizer_can_download_the_signups_as_excel(): void
    {
        $this->travelTo('2027-05-01 12:00');
        $event = Event::factory()->published()->create(['title' => 'Kräftskiva 2027', 'capacity' => 10]);
        $people = Question::factory()->for($event)->number(1)->create(['label' => 'Antal personer', 'position' => 1]);
        $event->capacityQuestion()->associate($people)->save();
        $food = Question::factory()->for($event)->choice(['Kött', 'Fisk'], QuestionType::Checkboxes)->create(['label' => 'Mat', 'position' => 2]);
        $nights = Question::factory()->for($event)->number()->create(['label' => 'Nätter', 'position' => 3]);
        $arrival = Question::factory()->for($event)->create(['type' => QuestionType::Date, 'label' => 'Ankomst', 'position' => 4]);

        Signup::factory()->for($event)->waitlisted()->create(['name' => 'Väntande', 'email' => 'w@example.com', 'answers' => []]);
        Signup::factory()->for($event)->create([
            'name' => 'Anna',
            'email' => 'anna@example.com',
            'places' => 3,
            'answers' => [$people->id => 3, $food->id => ['Kött', 'Fisk'], $nights->id => 2, $arrival->id => '2027-08-20'],
        ]);
        Signup::factory()->for($event)->cancelled()->create(['name' => 'Avbokad']);

        $response = $this->withSession(['locale' => 'sv'])
            ->actingAs($event->user)
            ->get(route('events.signups.export', $event))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertDownload('kraftskiva-2027-2027-05-01.xlsx');

        $rows = $this->readRows($response->streamedContent());

        $this->assertSame(['Namn', 'E-post', 'Status', 'Platser', 'Mat', 'Nätter', 'Ankomst', 'Anmäld'], $rows[0]);
        $this->assertCount(3, $rows);

        [$name, $email, $status, $places, $foodAnswer, $nightsAnswer, $arrivalAnswer, $signedUp] = $rows[1];
        $this->assertSame(['Anna', 'anna@example.com', 'Bekräftad', 3, 'Kött, Fisk', 2], [$name, $email, $status, $places, $foodAnswer, $nightsAnswer]);
        $this->assertSame('2027-08-20', $arrivalAnswer->format('Y-m-d'));
        $this->assertSame('2027-05-01 12:00', $signedUp->format('Y-m-d H:i'));

        $this->assertSame(['Väntande', 'w@example.com', 'På väntelistan', 1], array_slice($rows[2], 0, 4));
        $this->assertEmpty($rows[2][4] ?? null);
    }

    public function test_answers_that_look_like_formulas_are_exported_as_text(): void
    {
        $event = Event::factory()->published()->create();
        $question = Question::factory()->for($event)->create();
        Signup::factory()->for($event)->create(['name' => '=1+1', 'answers' => [$question->id => '=HYPERLINK("http://evil.example")']]);

        $rows = $this->readRows($this->actingAs($event->user)->get(route('events.signups.export', $event))->streamedContent());

        $this->assertSame('=1+1', $rows[1][0]);
        $this->assertSame('=HYPERLINK("http://evil.example")', $rows[1][4]);
    }

    public function test_the_main_admin_can_export_any_event(): void
    {
        $event = Event::factory()->published()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('events.signups.export', $event))
            ->assertOk();
    }

    public function test_other_organizers_cannot_export_the_signups(): void
    {
        $event = Event::factory()->published()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('events.signups.export', $event))
            ->assertForbidden();
    }
}
