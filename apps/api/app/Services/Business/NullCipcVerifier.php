<?php

namespace App\Services\Business;

use App\Contracts\CipcVerifier;

/**
 * Used when CIPC verification is disabled: never confirms a registration, so a
 * "CIPC verified" sticker is never granted without a real provider configured.
 */
class NullCipcVerifier implements CipcVerifier
{
    public function lookup(string $registrationNumber): CipcResult
    {
        return CipcResult::unavailable('disabled');
    }
}
