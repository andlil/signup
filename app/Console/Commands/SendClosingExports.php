<?php

namespace App\Console\Commands;

use App\Enums\EventStatus;
use App\Mail\SignupsClosedMail;
use App\Models\Event;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

#[Signature('signup:send-closing-exports')]
#[Description('Email organizers the Excel file of signups once signups to their event have closed')]
class SendClosingExports extends Command
{
    /**
     * Events that closed longer ago than this get no file, so turning the feature on does not email old events.
     */
    private const int MAX_AGE_DAYS = 2;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $sent = 0;

        Event::query()
            ->with('user')
            ->whereNull('export_sent_at')
            ->where('status', '!=', EventStatus::Draft)
            ->where(fn ($query) => $query
                ->whereBetween('signup_deadline', [now()->subDays(self::MAX_AGE_DAYS), now()])
                ->orWhereBetween('starts_at', [now()->subDays(self::MAX_AGE_DAYS), now()]))
            ->get()
            ->each(function (Event $event) use (&$sent): void {
                if ($event->signupsCloseAt()?->isFuture() ?? true) {
                    return;
                }

                $claimed = Event::query()
                    ->whereKey($event->id)
                    ->whereNull('export_sent_at')
                    ->update(['export_sent_at' => now()]);

                if ($claimed === 1 && $event->user->is_active) {
                    Mail::to($event->user)->send(new SignupsClosedMail($event));
                    $sent++;
                }
            });

        $this->info("Sent the signups of {$sent} events.");

        return self::SUCCESS;
    }
}
