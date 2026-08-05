<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * New-sale notification sent to the vendor when one of their orders is paid.
 */
class NewSaleMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'You made a sale — order '.$this->order->reference,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.new-sale',
            with: ['order' => $this->order],
        );
    }
}
