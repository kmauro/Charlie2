<?php

namespace App\Domains\Audits\Mail;

use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AuditRetentionWarningMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public int $recordsCount,
        public DateTimeInterface $cutoff,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Aviso de limpieza del registro de auditoría',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.audits.retention-warning',
        );
    }
}
