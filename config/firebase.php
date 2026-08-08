<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Firebase Cloud Messaging (HTTP v1)
    |--------------------------------------------------------------------------
    |
    | Service-account credentials for OAuth 2.0. Set in .env — never commit
    | real values. FIREBASE_PRIVATE_KEY may use literal \n for newlines.
    |
    */

    'project_id' => env('FIREBASE_PROJECT_ID'),

    'client_email' => env('FIREBASE_CLIENT_EMAIL'),

    'private_key' => env('FIREBASE_PRIVATE_KEY')
        ? str_replace('\\n', "\n", env('FIREBASE_PRIVATE_KEY'))
        : null,

    /*
    | Bounded timeout (seconds) for OAuth token + FCM send HTTP calls.
    | Prevents indefinite hangs; does not change redirect/flash on timeout.
    */
    'timeout' => (int) env('FIREBASE_REQUEST_TIMEOUT', 10),

];
