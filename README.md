# glitchr/omnibank

One contract for bank access - accounts, balances, transactions, transfers - over statement
files, a bank's own API or an aggregator: the Omnitrade of banking.

```php
$gateway = $registry->get('treasury');

$result = $gateway->connect($connection, 'https://app.example/bank/back');
$connection = $result->connection;          // keep it: the provider's user, its tokens
if ($result->url) { return redirect($result->url); }   // the provider's consent page

foreach ($gateway->accounts($connection) as $account) {
    $gateway->balances($connection, $account);                                  // list<Balance>
    $gateway->transactions($connection, $account, new \DateTimeImmutable('-30 days'));   // list<BankTransaction>
}
$gateway->transfer($connection, $transfer);                     // a TransferResult (a pain.001 file, for files)
$gateway->notify($request->getContent(), $request->headers->all());   // a webhook, checked and read
```

This package holds the contract (`GatewayInterface`, `GatewayFactory`, `Registry`), the models,
the requests and the Symfony bundle. Each provider is a package of its own:

| Package | Provider |
|---|---|
| `omnibank/files` | Statement files: CAMT.053, OFX, CSV read; transfers written as pain.001.001.03. No network |
| `omnibank/qonto` | Qonto: accounts, balances, transactions with an API key (unverified) |
| `omnibank/powens` | Powens: bank aggregation through its Webview, CONNECTION_SYNCED webhooks (unverified) |
| `omnibank/bridge` | Bridge API v3: bank aggregation through Connect sessions, item webhooks (unverified) |

## The models

- `Money` - an amount in minor units, **signed**, and its currency: `Money::of(-1250, 'EUR')`,
  `isNegative()`, `decimal()`, `Money::fromDecimal('1234.56', 'EUR')` (read digit by digit).
- `Account` - `id` (the provider's), `iban`, `bic`, `name`, `currency`, `type`, `raw`.
- `Balance` - `amount`, `at`, `type` (`booked`, `available`, `expected`, or the provider's word).
- `BankTransaction` - `id` (stable: the bank's reference, or a fingerprint of the line), `bookedOn`,
  `valueOn`, `amount` (**negative when money leaves the account**), `label`, `counterpartyName`,
  `counterpartyIban`, `reference`, `raw`.
- `Connection` - what the application keeps for one access and hands back on every call: the
  provider's `state` and the `consent` (`Consent`: a `ConsentStatus` - ACTIVE, NEEDS_RENEWAL,
  REVOKED, NONE - and `expiresAt`). `withState()`, `withConsent()`, `toArray()`/`fromArray()` for
  JSON. It holds tokens: store it as you store secrets.
- `ConnectResult` - `url` (the page to send the user to, null when there is nothing to do) and
  the `connection` to keep.
- `Transfer` / `TransferResult` - a SEPA credit transfer, and what became of it (`id`, `status`,
  and the `file`/`fileName` when it is a file to hand the bank).
- `Notification` - a webhook read: `type`, `connectionId`, `consent`, `raw`.

What every provider answers with is kept whole in `$raw`: normalization loses nothing.

## Errors

`RequestNotSupportedException` (the provider does not do that: `supports()` says so beforehand),
`InvalidConfigException` (an option missing), `ProviderException` (the provider refused),
`InvalidNotificationException` (a webhook whose signature does not hold) and
`UnavailableException` - the provider could not be reached or failed (network, timeout, 5xx, 429).
**An `UnavailableException` is never "no data"**: nothing is known, try again later.

## Symfony

`Omnibank\Bridge\Symfony\OmnibankBundle`: every `omnibank/*` provider installed registered,
`Omnibank\Registry` autowired, and each configured gateway injectable by its name.

```yaml
omnibank:
    gateways:
        statements: { factory: files, options: { debtor_name: 'Nakaya SAS', debtor_bic: AGRIFRPP882 } }
        treasury:   { factory: qonto, options: { login: '%env(QONTO_LOGIN)%', secret_key: '%env(QONTO_SECRET_KEY)%' } }
        banks:      { factory: powens, options: { domain: '%env(POWENS_DOMAIN)%', client_id: '%env(POWENS_CLIENT_ID)%', client_secret: '%env(POWENS_CLIENT_SECRET)%', webhook_secret: '%env(POWENS_WEBHOOK_SECRET)%' } }
```

```php
public function __construct(GatewayInterface $treasury) {}
```

An application's own `GatewayFactoryInterface` is registered too (autoconfigured).

## Docker: every provider with your sandbox keys

`docker/` runs this package with every `omnibank/*` provider installed - from GitHub, or from the
checkouts beside this one when `OMNIBANK_PLUGINS=../..` is set - and a console that exercises
them with the keys in `docker/.env` (copy `.env.dist`). Connections are kept between runs.

```sh
cd docker && cp .env.dist .env
docker compose run --rm omnibank gateways
docker compose run --rm omnibank connect powens                       # prints the Webview to open
docker compose run --rm omnibank accounts powens
docker compose run --rm omnibank transactions qonto --since 2026-09-01
docker compose run --rm -v ~/Downloads:/statements omnibank transactions files --path /statements/releve.xml
docker compose run --rm omnibank test                                 # every package's tests
```

License: LGPL-3.0-or-later.
