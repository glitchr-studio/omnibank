<?php

/**
 * One gateway per provider, its options from the environment (.env): a
 * gateway is configured only when every key under "needs" is set.
 *
 * @return array<string, array{factory: string, needs: list<string>, options: array<string, mixed>}>
 */
$env = static fn (string $key, mixed $default = null): mixed => (false !== ($v = getenv($key)) && '' !== $v) ? $v : $default;

return [
    'files' => ['factory' => 'files', 'needs' => [], 'options' => ['debtor_name' => $env('FILES_DEBTOR_NAME'), 'debtor_bic' => $env('FILES_DEBTOR_BIC')]],
    'qonto' => ['factory' => 'qonto', 'needs' => ['QONTO_LOGIN', 'QONTO_SECRET_KEY'], 'options' => ['login' => $env('QONTO_LOGIN'), 'secret_key' => $env('QONTO_SECRET_KEY'), 'sandbox' => '1' === $env('QONTO_SANDBOX', '0'), 'staging_token' => $env('QONTO_STAGING_TOKEN')]],
    'powens' => ['factory' => 'powens', 'needs' => ['POWENS_DOMAIN', 'POWENS_CLIENT_ID', 'POWENS_CLIENT_SECRET'], 'options' => ['domain' => $env('POWENS_DOMAIN'), 'client_id' => $env('POWENS_CLIENT_ID'), 'client_secret' => $env('POWENS_CLIENT_SECRET'), 'webhook_secret' => $env('POWENS_WEBHOOK_SECRET'), 'webhook_url' => $env('POWENS_WEBHOOK_URL'), 'language' => $env('POWENS_LANGUAGE'), 'redirect_uri' => $env('HARNESS_RETURN_URL')]],
    'bridge' => ['factory' => 'bridge', 'needs' => ['BRIDGE_CLIENT_ID', 'BRIDGE_CLIENT_SECRET'], 'options' => ['client_id' => $env('BRIDGE_CLIENT_ID'), 'client_secret' => $env('BRIDGE_CLIENT_SECRET'), 'version' => $env('BRIDGE_VERSION'), 'webhook_secret' => $env('BRIDGE_WEBHOOK_SECRET'), 'callback_url' => $env('HARNESS_RETURN_URL')]],
];
