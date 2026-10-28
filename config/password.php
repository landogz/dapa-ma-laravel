<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Password policy
    |--------------------------------------------------------------------------
    |
    | Complexity, expiration, reuse history, and temporary-password force change.
    | New / change / reset / admin-set passwords must satisfy these rules.
    | Login "current password" fields are not validated against complexity.
    |
    */

    'min_length' => 8,

    'require_mixed_case' => true,

    'require_number' => true,

    'require_symbol' => true,

    'uncompromised' => true,

    'uncompromised_threshold' => 0,

    'expire_days' => 90,

    'history_count' => 5,

    'force_change_on_admin_set' => true,

    'hint' => 'Password must include at least 8 characters, upper and lower case, a number, a symbol.',

];
