<?php

namespace Tests\Feature;

use App\Mail\SignupsClosedMail;
use App\Models\Event;
use App\Models\Signup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ClosingExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    public function test_the_organizer_gets_the_signups_once_the_deadline_has_passed(): void
    {
        $event = Event::factory()->published()->create(['signup_deadline' => now()->addHour()]);

        $this->artisan('signup:send-closing-exports')->assertSuccessful();
        Mail::assertNothingQueued();

        $this->travel(2)->hours();
        $this->artisan('signup:send-closing-exports')->assertSuccessful();
        $this->artisan('signup:send-closing-exports')->assertSuccessful();

        Mail::assertQueued(SignupsClosedMail::class, 1);
        Mail::assertQueued(SignupsClosedMail::class, fn (SignupsClosedMail $mail): bool => $mail->hasTo($event->user->email) && $mail->event->is($event));
        $this->assertNotNull($event->fresh()->export_sent_at);
    }

    public function test_signups_close_at_the_start_when_there_is_no_earlier_deadline(): void
    {
        Event::factory()->published()->create(['starts_at' => now()->subMinute(), 'signup_deadline' => null]);
        Event::factory()->published()->create(['starts_at' => now()->subMinute(), 'signup_deadline' => now()->addDay()]);

        $this->artisan('signup:send-closing-exports')->assertSuccessful();

        Mail::assertQueued(SignupsClosedMail::class, 2);
    }

    public function test_drafts_old_events_and_inactive_organizers_get_nothing(): void
    {
        Event::factory()->create(['signup_deadline' => now()->subHour()]);
        Event::factory()->published()->create(['starts_at' => now()->subWeek(), 'signup_deadline' => now()->subWeek()]);
        $inactive = Event::factory()->published()->create(['signup_deadline' => now()->subHour()]);
        $inactive->user->update(['is_active' => false]);

        $this->artisan('signup:send-closing-exports')->assertSuccessful();

        Mail::assertNothingQueued();
    }

    public function test_the_email_has_the_excel_file_attached(): void
    {
        $this->travelTo('2027-02-01 12:00');
        $event = Event::factory()->published()->create(['title' => 'Kräftskiva', 'signup_deadline' => now()->subHour(), 'capacity' => 10]);
        Signup::factory()->for($event)->count(2)->create();
        Signup::factory()->for($event)->waitlisted()->create();

        $mail = (new SignupsClosedMail($event))->locale('sv');

        $mail->assertHasSubject('Anmälan till Kräftskiva har stängt');
        $mail->assertSeeInText('2 bekräftade anmälningar');
        $mail->assertSeeInText('1 på väntelistan');

        $attachment = $mail->attachments()[0];
        $this->assertSame('kraftskiva-2027-02-01.xlsx', $attachment->as);
        $content = $attachment->attachWith(fn () => null, fn ($data) => $data());
        $this->assertStringStartsWith('PK', $content);
    }
}
