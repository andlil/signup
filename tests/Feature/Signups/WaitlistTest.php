<?php

namespace Tests\Feature\Signups;

use App\Enums\SignupStatus;
use App\Livewire\Events\EventEditor;
use App\Mail\SignupPromotedMail;
use App\Models\Event;
use App\Models\Signup;
use App\Services\SignupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class WaitlistTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_group_that_does_not_fit_is_skipped_for_a_smaller_one(): void
    {
        Mail::fake();
        $event = Event::factory()->published()->create(['capacity' => 2]);
        $leaving = Signup::factory()->for($event)->create(['places' => 2]);
        $bigGroup = Signup::factory()->for($event)->waitlisted()->create(['places' => 3]);
        $couple = Signup::factory()->for($event)->waitlisted()->create(['places' => 2]);

        app(SignupService::class)->cancel($leaving);

        $this->assertSame(SignupStatus::Waitlisted, $bigGroup->fresh()->status);
        $this->assertSame(SignupStatus::Confirmed, $couple->fresh()->status);
        Mail::assertQueued(SignupPromotedMail::class, 1);
    }

    public function test_more_places_on_the_event_move_the_waiting_list_up(): void
    {
        Mail::fake();
        $event = Event::factory()->published()->create(['capacity' => 1]);
        Signup::factory()->for($event)->create();
        $waiting = Signup::factory()->for($event)->waitlisted()->create();
        $stillWaiting = Signup::factory()->for($event)->waitlisted()->create();

        Livewire::actingAs($event->user)
            ->test(EventEditor::class, ['event' => $event])
            ->set('capacity', '2')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(SignupStatus::Confirmed, $waiting->fresh()->status);
        $this->assertSame(SignupStatus::Waitlisted, $stillWaiting->fresh()->status);
    }

    public function test_removing_the_limit_confirms_everyone(): void
    {
        Mail::fake();
        $event = Event::factory()->published()->create(['capacity' => 1]);
        Signup::factory()->for($event)->create();
        Signup::factory()->for($event)->waitlisted()->count(3)->create();

        Livewire::actingAs($event->user)
            ->test(EventEditor::class, ['event' => $event])
            ->set('capacity', '')
            ->call('save');

        $this->assertSame(0, $event->signups()->where('status', SignupStatus::Waitlisted)->count());
    }

    public function test_fewer_places_never_removes_confirmed_signups(): void
    {
        Mail::fake();
        $event = Event::factory()->published()->create(['capacity' => 3]);
        Signup::factory()->for($event)->count(3)->create();

        Livewire::actingAs($event->user)
            ->test(EventEditor::class, ['event' => $event])
            ->set('capacity', '1')
            ->call('save');

        $this->assertSame(3, $event->signups()->where('status', SignupStatus::Confirmed)->count());
        $this->assertSame(0, $event->remainingPlaces());
    }
}
