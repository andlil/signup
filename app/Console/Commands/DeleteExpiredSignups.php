<?php

namespace App\Console\Commands;

use App\Models\Event;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('signup:delete-expired-signups')]
#[Description('Delete the signups of events that ended more than their retention period ago')]
class DeleteExpiredSignups extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $deleted = 0;

        Event::query()
            ->whereRaw('coalesce(ends_at, starts_at) < ?', [now()])
            ->whereHas('signups')
            ->lazyById()
            ->each(function (Event $event) use (&$deleted): void {
                if ($event->signupsExpireAt()->isFuture()) {
                    return;
                }

                $deleted += $event->signups()->delete();
            });

        $this->info("Deleted {$deleted} expired signups.");

        return self::SUCCESS;
    }
}
