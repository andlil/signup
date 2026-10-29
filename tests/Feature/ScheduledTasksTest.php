<?php

namespace Tests\Feature;

use App\Enums\NotificationMode;
use App\Mail\DailySummaryMail;
use App\Models\Event;
use App\Models\LoginToken;
use App\Models\Signup;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ScheduledTasksTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_maintenance_commands_are_scheduled(): void
    {
        $commands = collect(app(Schedule::class)->events())->map(fn ($event): string => $event->command);

        foreach (['signup:send-daily-summaries', 'signup:delete-expired-signups', 'model:prune'] as $command) {
            $this->assertTrue($commands->contains(fn (string $scheduled): bool => str_contains($scheduled, $command)), "{$command} is not scheduled");
        }
    }

    public function test_signups_are_deleted_once_the_retention_period_after_the_event_has_passed(): void
    {
        $expired = Event::factory()->published()->create(['starts_at' => now()->subDays(40), 'ends_at' => now()->subDays(31), 'retention_days' => 30]);
        $recent = Event::factory()->published()->create(['starts_at' => now()->subDays(20), 'ends_at' => null, 'retention_days' => 30]);
        $upcoming = Event::factory()->published()->create();
        Signup::factory()->for($expired)->count(2)->create();
        Signup::factory()->for($expired)->cancelled()->create();
        Signup::factory()->for($recent)->create();
        Signup::factory()->for($upcoming)->create();

        $this->artisan('signup:delete-expired-signups')
            ->expectsOutput('Deleted 3 expired signups.')
            ->assertSuccessful();

        $this->assertSame(0, $expired->signups()->count());
        $this->assertSame(1, $recent->signups()->count());
        $this->assertSame(1, $upcoming->signups()->count());
        $this->assertModelExists($expired);
    }

    public function test_expired_login_tokens_are_pruned(): void
    {
        $user = User::factory()->create();
        $old = $user->loginTokens()->create(['token_hash' => LoginToken::hash('old'), 'expires_at' => now()->subDays(2)]);
        $fresh = $user->loginTokens()->create(['token_hash' => LoginToken::hash('fresh'), 'expires_at' => now()->addMinutes(5)]);

        $this->artisan('model:prune', ['--model' => [LoginToken::class]])->assertSuccessful();

        $this->assertModelMissing($old);
        $this->assertModelExists($fresh);
    }

    public function test_organizers_get_one_summary_of_the_signups_since_the_last_one(): void
    {
        Mail::fake();
        $organizer = User::factory()->create();
        $first = Event::factory()->for($organizer)->published()->create(['title' => 'Första', 'notification_mode' => NotificationMode::DailySummary, 'capacity' => 10]);
        $second = Event::factory()->for($organizer)->published()->create(['title' => 'Andra', 'notification_mode' => NotificationMode::DailySummary]);
        $quiet = Event::factory()->for($organizer)->published()->create(['title' => 'Tyst', 'notification_mode' => NotificationMode::DailySummary]);
        $perSignup = Event::factory()->published()->create(['notification_mode' => NotificationMode::EachSignup]);

        $this->travel(1)->hour();
        Signup::factory()->for($first)->create(['name' => 'Anna', 'places' => 2]);
        Signup::factory()->for($first)->waitlisted()->create(['name' => 'Bertil']);
        Signup::factory()->for($second)->create(['name' => 'Cecilia'])->forceFill(['status' => 'cancelled', 'cancelled_at' => now()])->save();
        Signup::factory()->for($perSignup)->create();

        $this->travel(1)->hour();
        $this->artisan('signup:send-daily-summaries')->assertSuccessful();

        Mail::assertQueuedCount(1);
        Mail::assertQueued(DailySummaryMail::class, function (DailySummaryMail $mail) use ($organizer): bool {
            $summaries = array_column($mail->summaries, null, 'title');

            return $mail->hasTo($organizer->email)
                && array_keys($summaries) === ['Första', 'Andra']
                && $summaries['Andra']['cancelled'] === ['Cecilia']
                && $summaries['Första']['new'] === [['name' => 'Anna', 'waitlisted' => false], ['name' => 'Bertil', 'waitlisted' => true]]
                && $summaries['Första']['taken'] === 2
                && $summaries['Första']['waitlisted'] === 1;
        });

        $this->assertNotNull($quiet->fresh()->summary_sent_at);

        $this->travel(1)->day();
        $this->artisan('signup:send-daily-summaries')->assertSuccessful();
        Mail::assertQueuedCount(1);
    }

    public function test_the_daily_summary_lists_new_signups_and_cancellations(): void
    {
        $mail = (new DailySummaryMail([[
            'title' => 'Kräftskiva',
            'url' => 'https://signup.test/events/1/signups',
            'new' => [['name' => 'Anna', 'waitlisted' => false], ['name' => 'Bertil', 'waitlisted' => true]],
            'cancelled' => ['Cecilia'],
            'taken' => 4,
            'capacity' => 4,
            'waitlisted' => 1,
        ]]))->locale('sv');

        $mail->assertSeeInText('Kräftskiva');
        $mail->assertSeeInText('2 nya anmälningar');
        $mail->assertSeeInText('Bertil (väntelista)');
        $mail->assertSeeInText('1 avbokning');
        $mail->assertSeeInText('Cecilia');
        $mail->assertSeeInText('4 av 4 platser är tagna.');
    }

    public function test_signups_close_when_the_event_starts(): void
    {
        $event = Event::factory()->published()->create(['starts_at' => now()->subMinute()]);

        $this->get(route('events.public', $event))->assertSee(__('Signups for this event are closed.'));
        $this->post(route('signups.store', $event), ['name' => 'A', 'email' => 'a@example.com', 'consent' => '1'])->assertForbidden();
    }
}
