<?php

namespace App\Mail;

use App\Models\TenantInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TenantInvitationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public TenantInvitation $invitation,
        public string $plainToken,
    ) {}

    public function envelope(): Envelope
    {
        $appName = (string) config('app.name');
        $tenantName = (string) $this->invitation->tenant?->name;

        return new Envelope(
            subject: "Convite para entrar em {$tenantName} no {$appName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.tenant-invitation',
            with: [
                'acceptUrl' => route('invitations.show', $this->plainToken),
                'tenantName' => $this->invitation->tenant?->name,
                'roleLabel' => $this->invitation->role->label(),
                'inviterName' => $this->invitation->invitedBy?->name,
                'expiresAt' => $this->invitation->expires_at,
            ],
        );
    }

    /**
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
