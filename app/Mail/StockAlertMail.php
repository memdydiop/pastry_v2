<?php

namespace App\Mail;

use App\Models\Ingredient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StockAlertMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Ingredient $ingredient,
        public float $currentStock,
        public string $triggeredBy,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Alerte Stock Critique : '.$this->ingredient->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.stock-alert',
            with: [
                'ingredient' => $this->ingredient,
                'currentStock' => $this->currentStock,
                'triggeredBy' => $this->triggeredBy,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
