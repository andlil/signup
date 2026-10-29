<?php

namespace App\Services;

use App\Enums\NotificationMode;
use App\Enums\SignupStatus;
use App\Mail\OrganizerSignupMail;
use App\Mail\SignupCancelledMail;
use App\Mail\SignupPromotedMail;
use App\Mail\SignupReceivedMail;
use App\Models\Event;
use App\Models\Signup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Registers, changes and cancels signups while keeping the number of places and the waiting list correct.
 *
 * Every change locks the event row, so two people cannot take the last place at the same time.
 */
class SignupService
{
    /**
     * Register a new signup, confirming it if it fits and waitlisting it otherwise.
     *
     * @param  array{name: string, email: string, answers: array<int, mixed>}  $data
     */
    public function register(Event $event, array $data, int $places, string $locale): Signup
    {
        $signup = DB::transaction(function () use ($event, $data, $places, $locale): Signup {
            $event = $this->lock($event);

            $signup = $event->signups()->make([...$data, 'places' => $places, 'locale' => $locale]);
            $signup->consented_at = now();

            if ($this->fits($event, $places)) {
                $signup->status = SignupStatus::Confirmed;
                $signup->confirmed_at = now();
            } else {
                $signup->status = SignupStatus::Waitlisted;
            }

            $signup->save();

            return $signup;
        });

        Mail::to($signup)->send((new SignupReceivedMail($signup))->afterCommit());
        $this->notifyOrganizer($signup, cancelled: false);

        return $signup;
    }

    /**
     * Change a signup's answers.
     *
     * A confirmed signup that asks for more places than are free is rejected
     * rather than losing its place; freed places go to the waiting list.
     *
     * @param  array{name: string, email: string, answers: array<int, mixed>}  $data
     *
     * @throws ValidationException
     */
    public function update(Signup $signup, array $data, int $places): Signup
    {
        DB::transaction(function () use ($signup, $data, $places): void {
            $event = $this->lock($signup->event);
            $signup->refresh();

            if ($signup->status === SignupStatus::Confirmed && ! $this->fits($event, $places - $signup->places)) {
                throw ValidationException::withMessages([
                    'answers.'.$event->capacity_question_id => __('There are only :count free places.', [
                        'count' => $event->remainingPlaces() + $signup->places,
                    ]),
                ]);
            }

            $signup->fill([...$data, 'places' => $places])->save();

            $this->promoteWaitlisted($event);
        });

        return $signup->refresh();
    }

    /**
     * Cancel a signup and give its places to the waiting list.
     */
    public function cancel(Signup $signup, bool $byOrganizer = false): void
    {
        DB::transaction(function () use ($signup): void {
            $event = $this->lock($signup->event);

            $signup->forceFill([
                'status' => SignupStatus::Cancelled,
                'cancelled_at' => now(),
            ])->save();

            $this->promoteWaitlisted($event);
        });

        Mail::to($signup)->send((new SignupCancelledMail($signup, $byOrganizer))->afterCommit());

        if (! $byOrganizer) {
            $this->notifyOrganizer($signup, cancelled: true);
        }
    }

    /**
     * Confirm waitlisted signups, in signup order, for as long as they fit.
     *
     * A signup that is too large for the free places is skipped, so smaller
     * signups further down the list can still get in.
     */
    public function promoteWaitlist(Event $event): void
    {
        DB::transaction(fn () => $this->promoteWaitlisted($this->lock($event)));
    }

    private function promoteWaitlisted(Event $event): void
    {
        $waitlisted = $event->signups()
            ->where('status', SignupStatus::Waitlisted)
            ->orderBy('id')
            ->get();

        foreach ($waitlisted as $signup) {
            if (! $this->fits($event, $signup->places)) {
                continue;
            }

            $signup->forceFill([
                'status' => SignupStatus::Confirmed,
                'confirmed_at' => now(),
            ])->save();

            Mail::to($signup)->send((new SignupPromotedMail($signup))->afterCommit());
        }
    }

    /**
     * Determine whether the event has room for the given number of extra places.
     */
    private function fits(Event $event, int $places): bool
    {
        return $event->capacity === null || $event->takenPlaces() + $places <= $event->capacity;
    }

    /**
     * Reload the event with a row lock that is held until the transaction ends.
     */
    private function lock(Event $event): Event
    {
        return Event::query()->lockForUpdate()->findOrFail($event->id);
    }

    private function notifyOrganizer(Signup $signup, bool $cancelled): void
    {
        $event = $signup->event;

        if ($event->notification_mode === NotificationMode::EachSignup) {
            Mail::to($event->user)->send((new OrganizerSignupMail($signup, $cancelled))->afterCommit());
        }
    }
}
