<?php

namespace App\Mail;

use App\Models\Signup;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrganizerSignupMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(public Signup $signup, public bool $cancelled = false) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $replace = ['name' => $this->signup->name, 'event' => $this->signup->event->title];

        return new Envelope(
            subject: $this->cancelled
                ? __('Cancellation: :name, :event', $replace)
                : __('New signup: :name, :event', $replace),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.organizer-signup',
            with: ['event' => $this->signup->event->loadMissing('questions')],
        );
    }
}
