<?php

namespace App\Services\Payments;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * PayFast (SA) payment provider (Shop P2) — redirect to their hosted page, with
 * the ITN as the authoritative confirmation. Signature is an MD5 over the
 * URL-encoded field string (+ optional passphrase); the ITN is additionally
 * validated by posting the raw data back to PayFast.
 */
class PayFastDriver implements PaymentGateway
{
    /**
     * @param  array{merchant_id: ?string, merchant_key: ?string, passphrase: ?string, sandbox: bool, timeout: int}  $config
     */
    public function __construct(private array $config) {}

    public function enabled(): bool
    {
        return ! empty($this->config['merchant_id']) && ! empty($this->config['merchant_key']);
    }

    public function checkout(Order $order): array
    {
        $front = config('payments.frontend_url');
        $api = config('payments.api_url');

        $fields = array_filter([
            'merchant_id' => (string) $this->config['merchant_id'],
            'merchant_key' => (string) $this->config['merchant_key'],
            'return_url' => "{$front}/shop/checkout/success?order={$order->ulid}",
            'cancel_url' => "{$front}/shop/checkout/cancel?order={$order->ulid}",
            'notify_url' => "{$api}/api/v1/shop/payfast/itn",
            'm_payment_id' => $order->reference,
            'amount' => number_format($order->total_cents / 100, 2, '.', ''),
            'item_name' => Str::limit($order->itemName(), 90, ''),
            'email_address' => $order->buyer?->user?->email,
        ], fn ($v) => $v !== null && $v !== '');

        $fields['signature'] = $this->signature($fields);

        return [
            'process_url' => $this->host().'/eng/process',
            'fields' => $fields,
        ];
    }

    public function handleWebhook(Request $request): ?Order
    {
        $data = $request->request->all();

        $given = $data['signature'] ?? null;
        if (! is_string($given) || ! hash_equals($this->signatureFrom($data), $given)) {
            return null;
        }

        if (! $this->validatePostback($data)) {
            return null;
        }

        if (($data['payment_status'] ?? null) !== 'COMPLETE') {
            return null;
        }

        $order = Order::query()->where('reference', $data['m_payment_id'] ?? '')->first();
        if ($order === null) {
            return null;
        }

        // Amount must match to the cent.
        if ((int) round(((float) ($data['amount_gross'] ?? 0)) * 100) !== (int) $order->total_cents) {
            return null;
        }

        $order->pf_payment_id = $data['pf_payment_id'] ?? null;

        return $order;
    }

    private function host(): string
    {
        return $this->config['sandbox']
            ? 'https://sandbox.payfast.co.za'
            : 'https://www.payfast.co.za';
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function signature(array $fields): string
    {
        return $this->build($fields);
    }

    /**
     * Recompute the signature from posted ITN data (in received order).
     *
     * @param  array<string, mixed>  $data
     */
    private function signatureFrom(array $data): string
    {
        unset($data['signature']);

        return $this->build($data);
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function build(array $fields): string
    {
        $pairs = [];
        foreach ($fields as $key => $value) {
            if ($key === 'signature' || $value === null || $value === '') {
                continue;
            }
            $pairs[] = $key.'='.urlencode(trim((string) $value));
        }

        $str = implode('&', $pairs);

        if (! empty($this->config['passphrase'])) {
            $str .= '&passphrase='.urlencode(trim((string) $this->config['passphrase']));
        }

        return md5($str);
    }

    /**
     * Post the raw ITN back to PayFast; a genuine notification returns "VALID".
     *
     * @param  array<string, mixed>  $data
     */
    private function validatePostback(array $data): bool
    {
        try {
            $response = Http::asForm()
                ->timeout($this->config['timeout'] ?? 15)
                ->post($this->host().'/eng/query/validate', $data);

            return trim($response->body()) === 'VALID';
        } catch (\Throwable) {
            return false;
        }
    }
}
