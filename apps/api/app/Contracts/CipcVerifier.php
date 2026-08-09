<?php

namespace App\Contracts;

use App\Services\Business\CipcResult;

/**
 * Looks up a South African company registration number against CIPC (or an
 * aggregator). Implementations are resolved from config: a Null verifier when
 * disabled, a stub for dev/demo, and an HTTP driver for a real provider.
 */
interface CipcVerifier
{
    public function lookup(string $registrationNumber): CipcResult;
}
