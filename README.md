# sitetus/laravel-collector

Composer wrapper around the [Sitetus](https://sitetus.com) PHP collector (`sitetus/collector`) for Laravel applications. Same read-only core, same JSON envelope; the service provider does the wiring that the single-file install asks you to do by hand.

The collector is optional: the Sitetus panel works without it; the collector only adds a package inventory and inside-the-server health data, read through signed requests the panel sends.

## Install

```bash
composer require sitetus/laravel-collector
```

Then copy the lines the panel shows for your site into `.env` and deploy:

```dotenv
SITETUS_PROJECT_KEY=…
SITETUS_PUBLIC_KEY=…
SITETUS_ALGO=ed25519
SITETUS_INSTALL_PATH=…
SITETUS_MODULES=…
```

`SITETUS_COLLECTOR_ENABLED=false` switches the route off without removing the package; `SITETUS_EXPIRES_AT` is set when the panel's package carries an expiry.

None of these values is a secret: the public key verifies the panel's signatures, the project key and the install path are identifiers. Press "Verify connection" in the panel afterwards.

## What it does

- Registers the pull route `/sitetus/<install-path>`; every unsigned or invalid request gets a bodyless `404`, exactly as if the route did not exist.
- Writes the scheduler and queue heartbeats itself, so the panel can watch both without any line added to your code.
- Lets you add developer-defined checks to the envelope — numbers and booleans only, never text:

```php
use Sitetus\LaravelCollector\CheckOutcome;
use Sitetus\LaravelCollector\Facades\Sitetus;

Sitetus::check('orders_last_24h', fn () => CheckOutcome::ok(Order::where('created_at', '>=', now()->subDay())->count()));
```

The envelope is byte-for-byte the single-file collector's, except for `collector.name`. Everything the single file will not do, this package will not do either; the list is in the [core README](https://github.com/sitetus/collector-php).

## Requirements

PHP ≥ 8.1, Laravel 10 to 13.

## Development

The core (`sitetus/collector`) is a Composer dependency; its source is the `collector-php` repository.

```bash
composer install
vendor/bin/phpunit
```

Security reports: write to **security@sitetus.com**; please do not open a public issue.

## License

MIT — `LICENSE`.
