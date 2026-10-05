# The Docker harness

`docker/` runs this package with every `omnibank/*` provider installed - from GitHub (branch 1.x),
or from the checkouts beside this one when `OMNIBANK_PLUGINS=../..` is set in `docker/.env` - and
a console that exercises them with the keys in `docker/.env`. Connections (the provider's user,
its tokens) are kept between runs in the `harness` volume.

```sh
cd docker && cp .env.dist .env       # your sandbox keys, when you have them
docker compose run --rm omnibank gateways
```

| Command | |
|---|---|
| `gateways` | the providers installed and configured, what each does, whether it is connected |
| `connect <gateway>` | the page to send the user to (`--return-url`, `--set key=value`, `--renew`, `--forget`); the connection kept |
| `accounts <gateway>` | the accounts the connection reaches (JSON) |
| `balances <gateway>` | an account's balances (`--account`) |
| `transactions <gateway>` | an account's booked transactions, oldest first (`--account`, `--since`, `--until`) |
| `bare` | plain PHP: the registry built by hand, a statement read, Qonto asked, what PHP loaded |
| `test` | every package's tests |

`accounts`, `balances` and `transactions` take `--path` for the files gateway: a statement file
(CAMT.053, OFX, CSV) mounted in the container.

```sh
docker compose run --rm omnibank connect powens                       # prints the Webview to open
docker compose run --rm omnibank accounts powens
docker compose run --rm omnibank transactions qonto --since 2026-09-01
docker compose run --rm -v ~/Downloads:/statements omnibank transactions files --path /statements/releve.xml
```

## Bare: no bundle, no container

The console above is a `symfony/console` application over a registry built by hand; `bare` is
less still - one PHP script, `docker/harness/bin/bare`, that requires the autoloader and nothing
else. It builds the `Registry` from the provider packages installed, asks each gateway that can
be built what it does, reads the CAMT.053 statement kept in `docker/harness/recorded/` through
`omnibank/files`, asks Qonto for the organization's accounts, the first one's balances and its
transactions of September 2026 - the real API when `QONTO_LOGIN` and `QONTO_SECRET_KEY` are set,
or with `--recorded` the answers kept in `docker/harness/recorded/` - then lists what PHP loaded
and exits 1 if a class of a framework is among it (`Symfony\Component\DependencyInjection`,
`Config`, `HttpKernel`, `HttpFoundation`, a bundle, Doctrine, Twig):

```
$ docker compose run --rm omnibank bare --recorded
Omnibank in bare PHP: the registry built by hand, no bundle, no container.

  files      connect accounts balances transactions transfer
  qonto      connect accounts balances transactions

omnibank/files, the CAMT.053 statement kept in recorded/ (no network):
  Compte courant, FR7630006000011234567890189 (AGRIFRPP882), EUR - 1 account
  balances: opening 10000.00, booked 5926.60, available 5926.60
  6 transactions, the first on 2026-09-02: 1250.00 EUR, Camille Durand, Facture F-2026-042

omnibank/qonto, from the answers kept in recorded/, September 2026:
  Compte principal, FR5116958000011234567890142 (QNTOFRP1XXX), EUR - 2 accounts
  balances: booked 18432.17, available 18102.17
  3 transactions, the first on 2026-09-02: 1250.00 EUR, Camille Durand, Facture F-2026-042

Loaded from Symfony: Symfony\Component\HttpClient, Symfony\Contracts\HttpClient, Symfony\Contracts\Service
Classes of a framework (DependencyInjection, Config, HttpKernel, HttpFoundation, a bundle, Doctrine, Twig): none
```

The answers in `recorded/` were not taken from a bank: they are omnibank/qonto's fixtures, written
from Qonto's published documentation, and omnibank/files' sample statement - the organization,
the IBANs, the names and the amounts are made up. `bare --recorded --json` prints the same whole,
every class and file loaded, without a call: `Tests/BareTest.php` runs it in a process of its own
and checks the list.

The image is `php:8.4-cli-alpine` with Composer; the harness's packages live in the `harness`
volume of the `omnibank` project.
