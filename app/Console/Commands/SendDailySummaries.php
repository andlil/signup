<?php

namespace App\Console\Commands;

use App\Enums\EventStatus;
use App\Enums\NotificationMode;
use App\Enums\SignupStatus;
use App\Mail\DailySummaryMail;
use App\Models\Event;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

#[Signature('signup:send-daily-summaries')]
#[Description('Email organizers who asked for it a summary of the signups and cancellations since the last summary')]
class SendDailySummaries extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $until = now();

        $events = Event::query()
            ->with('user')
            ->where('notification_mode', NotificationMode::DailySummary)
            ->where('status', '!=', EventStatus::Draft)
            ->get();

        $summaries = $events
            ->keyBy('id')
            ->map(fn (Event $event): ?array => $this->summarize($event, $until))
            ->filter();

        $events->groupBy('user_id')->each(function (Collection $organizerEvents) use ($summaries): void {
            $organizerSummaries = $organizerEvents
                ->map(fn (Event $event): ?array => $summaries->get($event->getKey()))
                ->filter()
                ->values()
                ->all();

            if ($organizerSummaries !== [] && $organizerEvents->first()->user->is_active) {
                Mail::to($organizerEvents->first()->user)->send(new DailySummaryMail($organizerSummaries));
            }
        });

        Event::query()->whereKey($events->modelKeys())->update(['summary_sent_at' => $until]);

        $this->info("Sent summaries for {$summaries->count()} events.");

        return self::SUCCESS;
    }

    /**
     * Describe what happened with the event since the previous summary, or null if nothing did.
     *
     * @return array{title: string, url: string, new: list<array{name: string, waitlisted: bool}>, cancelled: list<string>, taken: int, capacity: ?int, waitlisted: int}|null
     */
    private function summarize(Event $event, Carbon $until): ?array
    {
        $since = $event->summary_sent_at ?? $event->published_at ?? $event->created_at;

        $new = $event->signups()
            ->where('created_at', '>', $since)
            ->where('created_at', '<=', $until)
            ->orderBy('id')
            ->get(['name', 'status']);

        $cancelled = $event->signups()
            ->where('status', SignupStatus::Cancelled)
            ->where('cancelled_at', '>', $since)
            ->where('cancelled_at', '<=', $until)
            ->orderBy('cancelled_at')
            ->pluck('name');

        if ($new->isEmpty() && $cancelled->isEmpty()) {
            return null;
        }

        return [
            'title' => $event->title,
            'url' => route('events.signups', $event),
            'new' => $new->map(fn ($signup): array => [
                'name' => $signup->name,
                'waitlisted' => $signup->status === SignupStatus::Waitlisted,
            ])->all(),
            'cancelled' => $cancelled->all(),
            'taken' => $event->takenPlaces(),
            'capacity' => $event->capacity,
            'waitlisted' => $event->signups()->where('status', SignupStatus::Waitlisted)->count(),
        ];
    }
}
