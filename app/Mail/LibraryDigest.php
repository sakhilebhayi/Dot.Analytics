<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Support\Facades\URL;

/** The library's email to someone who asked for it. Plain, no tracking, and one click turns it off. */
class LibraryDigest extends Mailable
{
    public string $stopUrl;

    /** @param  array<string, mixed>  $digest */
    public function __construct(public User $user, public array $digest, public string $frequency)
    {
        $this->stopUrl = URL::signedRoute('me.digest.stop', ['user' => $user->id]);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: ($this->frequency === 'daily' ? 'Your daily' : 'Your weekly').' business library digest');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.library-digest', text: 'emails.library-digest-text');
    }

    public function headers(): Headers
    {
        return new Headers(text: ['List-Unsubscribe' => '<'.$this->stopUrl.'>', 'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click']);
    }
}
