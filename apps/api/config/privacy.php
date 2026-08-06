<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Privacy policy version
    |--------------------------------------------------------------------------
    |
    | Stamped onto every server-side consent record so a choice is provably tied
    | to the policy the user saw. Bump this whenever the cookie/privacy policy
    | materially changes so returning users are re-prompted and re-recorded.
    |
    */

    'policy_version' => env('PRIVACY_POLICY_VERSION', '2026-08-01'),

];
