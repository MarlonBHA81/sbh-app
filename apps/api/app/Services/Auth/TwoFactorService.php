<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Str;
use PragmaRX\Google2FAQRCode\Google2FA;
use PragmaRX\Google2FAQRCode\QRCode\Chillerlan;

/**
 * Member-facing TOTP two-factor: secret generation, QR provisioning, code
 * verification and single-use recovery codes. Stateless — the secret and
 * recovery codes live (encrypted) on the User; this class only does the crypto.
 */
class TwoFactorService
{
    private Google2FA $engine;

    public function __construct()
    {
        // Render QR codes with the chillerlan SVG backend (no imagick/GD
        // dependency); returns a ready `data:image/svg+xml;base64,…` URI.
        $this->engine = new Google2FA(new Chillerlan);
    }

    /** A fresh base32 TOTP secret for a new enrolment. */
    public function generateSecret(): string
    {
        return $this->engine->generateSecretKey();
    }

    /**
     * An inline QR data URI (SVG) the client renders for the user to scan into
     * their authenticator app. The account label is the user's email.
     */
    public function qrCodeDataUri(User $user, string $secret): string
    {
        return $this->engine->getQRCodeInline(
            (string) config('app.name', 'SBH Community'),
            $user->email,
            $secret,
        );
    }

    /**
     * Verify a 6-digit code against a secret, allowing ±1 time step (~30s) of
     * clock skew — the usual tolerance so a code near a window boundary works.
     */
    public function verify(string $secret, string $code): bool
    {
        $code = trim($code);

        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        return (bool) $this->engine->verifyKey($secret, $code, 1);
    }

    /**
     * Generate a fresh batch of single-use recovery codes (shown once, stored
     * encrypted). Format: two 5-char groups, e.g. "A1B2C-3D4E5".
     *
     * @return list<string>
     */
    public function generateRecoveryCodes(int $count = 8): array
    {
        return collect(range(1, $count))
            ->map(fn () => Str::upper(Str::random(5)).'-'.Str::upper(Str::random(5)))
            ->values()
            ->all();
    }

    /**
     * Consume a recovery code. If it matches one of the stored codes, returns
     * the remaining codes (so the caller can persist the shortened list);
     * returns null when nothing matched.
     *
     * @param  list<string>  $codes
     * @return list<string>|null
     */
    public function consumeRecoveryCode(array $codes, string $code): ?array
    {
        $code = trim($code);

        $remaining = array_values(array_filter(
            $codes,
            fn (string $stored) => ! hash_equals($stored, $code),
        ));

        return count($remaining) === count($codes) ? null : $remaining;
    }
}
