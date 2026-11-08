<?php

namespace App\Mail;

use App\Domain\Identity\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WorkspaceInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invitation $invitation,
        public string $token,
        public string $workspaceName,
        public string $inviterName,
        public string $roleLabel,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Invitation à rejoindre '.$this->workspaceName,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.workspace-invitation',
            with: [
                'url' => route('invitations.show', $this->token),
                'expiresAt' => $this->invitation->expires_at->format('d/m/Y H:i'),
            ],
        );
    }
}
