@php
    $money = fn (int $cents) => $order->currency.' '.number_format($cents / 100, 2);
@endphp
<x-mail::message>
# Thanks for your purchase

Order **{{ $order->reference }}** — {{ optional($order->paid_at)->format('j M Y') }}

<x-mail::table>
| Item | Amount |
| :--- | -----: |
@foreach ($order->items as $item)
| {{ $item->title }} | {{ $money($item->unit_cents) }} |
@endforeach
</x-mail::table>

@if ($order->discount_cents > 0)
Discount: **−{{ $money($order->discount_cents) }}**
@endif

**Total paid: {{ $money($order->total_cents) }}**

@if ($order->vat_cents > 0)
Includes VAT ({{ number_format($order->vat_rate_bp / 100, 0) }}%): {{ $money($order->vat_cents) }}
@if ($order->store && $order->store->vat_number)
VAT number: {{ $order->store->vat_number }}
@endif
@endif

Sold by **{{ optional($order->store)->name ?? 'SBH' }}**.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
