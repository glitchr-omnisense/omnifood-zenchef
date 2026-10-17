# omnifood/zenchef

Zenchef for [omniscient/omnifood](https://github.com/glitchr-studio/omnifood) - its place, not yet its
calls: **Zenchef's partner API is documented on request only.** Its help centre
(https://help.zenchef.com/hc/en-gb/articles/27690768125597-Zenchef-API, read on 2026-10-04) lists what
it offers - reservations read, created, updated and their status changed; availability and opening
hours read; guests; one webhook per restaurant on a reservation made or updated - but neither its
address, nor its paths, fields, statuses, nor how a webhook is signed.

> **Not verified against the live API (non vérifié en réel)**, and nothing guessed: every
> reservation call throws `NotSupportedException` until the documentation is in hand.

```php
use Omnifood\Zenchef\ZenchefPlatformFactory;
use Symfony\Component\HttpClient\HttpClient;

$zenchef = (new ZenchefPlatformFactory(HttpClient::create()))->create([
    'token' => getenv('ZENCHEF_TOKEN') ?: null,                  // Settings > Partners: "Zenchef token"
    'restaurant_id' => getenv('ZENCHEF_RESTAURANT_ID') ?: null,  // Settings > Partners
    'base_uri' => getenv('ZENCHEF_BASE_URI') ?: null,            // from Zenchef's documentation
    'headers' => [],                                             // from it too: ['<header>' => '{token}', '<header>' => '{restaurant_id}']
]);
```

Plain PHP, no framework needed: the factory takes any `HttpClientInterface` - the application's, a
`MockHttpClient` in a test - and makes its own when given none. In a Symfony application, the same
options under `omnifood.platforms` ([the bundle](https://github.com/glitchr-studio/omnifood/blob/1.x/docs/symfony.md)):

```yaml
omnifood:
    platforms:
        zenchef:
            factory: zenchef
            options:
                token: '%env(default::ZENCHEF_TOKEN)%'                  # Settings > Partners: "Zenchef token"
                restaurant_id: '%env(default::ZENCHEF_RESTAURANT_ID)%'  # Settings > Partners
                base_uri: ~                                             # from Zenchef's documentation
                headers: {}                                             # from it too: {"<header>": "{token}", "<header>": "{restaurant_id}"}
```

## What it does

- Holds the restaurant's credentials and a client, `api()`, that sends the configured headers
  (`{token}` and `{restaurant_id}` replaced) to the configured address - the calls to write once
  Zenchef's documentation says them.
- `capabilities()`.

## Left in NotSupportedException

Everything of `ReservationsInterface` and `NotifiableInterface` - `reservations()`, `reservation()`,
`createReservation()`, `updateReservation()`, `cancelReservation()`, `setStatus()`, `notify()` - for
want of a public documentation; `pushAvailability()` because Zenchef's help centre lists reading
availability, not writing it.

Zenchef's published rules, for when it is written: 100 calls a minute for the calls answered in
less than a second, the slower ones one after the other; pages of 250 to 500; after a first full
import, only what changed since the last call.

## What it takes

- The restaurant on Zenchef's **Grow** subscription (or a special agreement).
- The restaurant asks **help@zenchef.com** (or api-tech-help@zenchef.com) for the **API
  documentation** and, if wanted, a **demo restaurant** on the pre-production environment, giving
  its `restaurantId` and the use.
- Its **token** and **restaurant id**: Zenchef, Settings > Partners.
- To become an official partner: https://www.zenchef.com/integrations.

## Responsibility

This package is an independent, unofficial client: it is not affiliated with, endorsed by or
sponsored by Zenchef. It reaches their service on behalf of whoever runs it, and it does not
redistribute their data beyond the short excerpts recorded as test fixtures. Its MIT licence covers
this package's code only and grants no right to the service or to its data.

Whoever installs and runs this package is responsible for complying with Zenchef's terms
([zenchef.com/terms-conditions](https://www.zenchef.com/terms-conditions)), for obtaining whatever
they require (an account, a key, a licence or subscription, an attribution or a citation, a rate
limit to respect), and for any use made of what it returns. The authors publish it free of charge,
make no commercial use of it, and accept no liability for how it is used (see [LICENSE](LICENSE)).

License: MIT since 2026-10-09; earlier versions remain published under LGPL-3.0-or-later.
