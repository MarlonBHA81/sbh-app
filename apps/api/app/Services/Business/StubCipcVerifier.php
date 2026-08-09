<?php

namespace App\Services\Business;

use App\Contracts\CipcVerifier;

/**
 * A deterministic verifier for local dev and demos (no external calls): any
 * well-formed SA registration number (YYYY/NNNNNN/NN) is treated as verified,
 * anything else as not found. NOT for production — enable the http driver with
 * a real provider there.
 */
class StubCipcVerifier implements CipcVerifier
{
    public function lookup(string $registrationNumber): CipcResult
    {
        $normalised = trim($registrationNumber);

        if (preg_match('/^\d{4}\/\d{6}\/\d{2}$/', $normalised) !== 1) {
            return CipcResult::notFound();
        }

        return CipcResult::verified(
            registeredName: 'Registered Company '.$normalised,
            companyStatus: 'In Business',
        );
    }
}
