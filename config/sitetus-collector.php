<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sitetus Collector (Composer wrapper)
    |--------------------------------------------------------------------------
    |
    | Values come from the panel's install package. None of them is a secret:
    | the public key verifies the panel's signatures and the project key is
    | an identifier. The route is only registered when both are filled.
    |
    */

    'enabled' => env('SITETUS_COLLECTOR_ENABLED', true),

    'project_key' => env('SITETUS_PROJECT_KEY', ''),

    'public_key' => env('SITETUS_PUBLIC_KEY', ''),

    'algo' => env('SITETUS_ALGO', 'ed25519'),

    'expires_at' => env('SITETUS_EXPIRES_AT'),

    'install_path' => env('SITETUS_INSTALL_PATH', ''),

    // Opt-in read-only modules: files, archives, permissions,
    // bot_traffic. Comma-separated; empty = none. The panel's install screen
    // shows the value that matches the modules chosen there. 0.5.1: opt-in
    // means opt-in — the default enables nothing.
    'modules' => env('SITETUS_MODULES', ''),

];
