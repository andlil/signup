<?php

namespace Tests\Feature\Events;

use App\Enums\SignupStatus;
use App\Models\Event;
use App\Models\Signup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EventWithoutStartTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_public_page_and_dashboard_show_the_last_day_to_sign_up(): void
    {
        $event = Event::factory()->withoutStart()->published()->create(['title' => 'Semesterplanering', 'signup_deadline' => '2027-03-31 23:59']);

        $this->withSession(['locale' => 'sv'])
            ->get(route('events.public', $event))
            ->assertOk()
            ->assertDontSee('När')
            ->assertSee('31 mars 2027');

        $this->actingAs($event->user)
            ->get(route('dashboard'))
            ->assertSee('Anmälan senast 31 mars 2027');
    }

    public function test_signups_are_accepted_until_the_last_day_to_sign_up(): void
    {
        Mail::fake();
        $event = Event::factory()->withoutStart()->published()->create();
        $form = ['name' => 'Anna', 'email' => 'anna@example.com', 'consent' => '1'];

        $this->post(route('signups.store', $event), $form)->assertSessionHasNoErrors();

        $this->travelTo($event->signup_deadline->addMinute());

        $this->post(route('signups.store', $event), [...$form, 'email' => 'bertil@example.com'])->assertForbidden();
    }

    public function test_participants_can_cancel_until_the_last_day_to_sign_up(): void
    {
        Mail::fake();
        $event = Event::factory()->withoutStart()->published()->create();
        $early = Signup::factory()->for($event)->create();
        $late = Signup::factory()->for($event)->create();

        $this->delete(route('signups.destroy', ['token' => $early->manage_token]))->assertRedirect();
        $this->assertSame(SignupStatus::Cancelled, $early->fresh()->status);

        $this->travelTo($event->signup_deadline->addMinute());

        $this->delete(route('signups.destroy', ['token' => $late->manage_token]))->assertForbidden();
    }

    public function test_signups_are_deleted_counting_from_the_last_day_to_sign_up(): void
    {
        $event = Event::factory()->withoutStart()->published()->create(['signup_deadline' => now()->subDays(31), 'retention_days' => 30]);
        $kept = Event::factory()->withoutStart()->published()->create(['signup_deadline' => now()->subDays(29), 'retention_days' => 30]);
        Signup::factory()->for($event)->create();
        Signup::factory()->for($kept)->create();

        $this->artisan('signup:delete-expired-signups')->assertSuccessful();

        $this->assertSame(0, $event->signups()->count());
        $this->assertSame(1, $kept->signups()->count());
    }
}
