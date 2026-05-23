<?php

declare(strict_types=1);

/**
 * Used by the bridge's exception renderers to populate the
 * `error_type` field of JSON error responses. The exception's
 * original detailed message remains in `message` for backwards
 * compatibility — only the headline is localized.
 *
 * Hosts override via `vendor:publish --tag=access-lang` and keep
 * the same keys.
 */
return [
    'invalid_input' => 'Invalid input',
    'not_found' => 'Not found',
    'authorization_failed' => 'Forbidden',
];
