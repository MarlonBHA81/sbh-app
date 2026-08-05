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
 * VAT receipt sent to the buyer once an order is paid. Prices are inclusive of
 * VAT; the receipt shows the VAT portion and the store's VAT number when set.
 */
class OrderReceiptMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your receipt — order '.$this->order->reference,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.order-receipt',
            with: ['order' => $this->order],
        );
    }
}
