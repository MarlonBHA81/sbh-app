@php
    $money = fn (int $cents) => $order->currency.' '.number_format($cents / 100, 2);
@endphp
<x-mail::message>
# You made a sale 🎉

Order **{{ $order->reference }}** was just paid.

<x-mail::table>
| Item | Amount |
| :--- | -----: |
@foreach ($order->items as $item)
| {{ $item->title }} | {{ $money($item->unit_cents) }} |
@endforeach
</x-mail::table>

**Total: {{ $money($order->total_cents) }}**
@if ($order->vat_cents > 0)
(includes {{ $money($order->vat_cents) }} VAT)
@endif

Your payout after the platform fee is **{{ $money($order->vendor_amount_cents ?? 0) }}**.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
