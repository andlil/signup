<?php

namespace App\Mail;

use App\Enums\SignupStatus;
use App\Models\Event;
use App\Support\SignupExport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SignupsClosedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(public Event $event) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Signups for :event have closed', ['event' => $this->event->title]),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.signups-closed',
            with: [
                'confirmed' => $this->event->signups()->where('status', SignupStatus::Confirmed)->count(),
                'waitlisted' => $this->event->signups()->where('status', SignupStatus::Waitlisted)->count(),
            ],
        );
    }

    /**
     * Attach the signups as an Excel file, built when the email is sent so it is up to date.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $export = new SignupExport($this->event);

        return [
            Attachment::fromData(function () use ($export): string {
                $path = tempnam(sys_get_temp_dir(), 'signup-export-');

                try {
                    $export->writeTo($path);

                    return file_get_contents($path);
                } finally {
                    unlink($path);
                }
            }, $export->fileName())->withMime('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
        ];
    }
}
