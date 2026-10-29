# omnifood/zenchef

Zenchef for [omnisense/omnifood](https://github.com/glitchr-omnisense/omnifood) - its place, not
yet its calls. Zenchef's partner API is documented on request only: its help centre
(https://help.zenchef.com/hc/en-gb/articles/27690768125597-Zenchef-API, read on 2026-10-04) lists
what it offers - reservations read, created, updated and their status changed; availability and
opening hours read; guests; one webhook per restaurant on a reservation made or updated - but
neither its address, nor its paths, fields, statuses, nor how a webhook is signed. Until its
documentation is public, this package holds the restaurant's credentials and a configured client
(`api()`), and refuses every reservation call rather than guess it.

## Installation

```sh
composer require omnifood/zenchef
```

Requires PHP 8.2, `omnisense/omnifood` (1.x) and `symfony/http-client` (6.4, 7 or 8). In a Symfony
application with `Omnifood\Bridge\Symfony\OmnifoodBundle`, the factory is registered as soon as
the package is installed.

## Credentials

1. The restaurant is on Zenchef's **Grow** subscription (or a special agreement).
2. It writes to **help@zenchef.com** (or api-tech-help@zenchef.com) for the API documentation and
   a demo restaurant on the pre-production environment, giving its `restaurantId` and the use.
3. Its **token** and **restaurant id**: Zenchef, Settings > Partners.

The documentation gives the API's address and the headers that carry the token and the id: they
are the `base_uri` and `headers` options.

## Options

| Option | Type | Default | Required | |
|---|---|---|---|---|
| `token` | string | - | for `api()->request()` | Settings > Partners: "Zenchef token" |
| `restaurant_id` | string | - | for `api()->request()` | Settings > Partners |
| `base_uri` | string | - | for `api()->request()` | from Zenchef's documentation |
| `headers` | map of strings | `{}` | for `api()->request()` | from it too: `{"<header>": "{token}", "<header>": "{restaurant_id}"}` |

In a header's value, `{token}` and `{restaurant_id}` stand for the two options; in a path,
`{restaurant_id}` stands for the id, URL-encoded.

## Capabilities

| Interface | Supported |
|---|---|
| `ReservationsInterface` | every method refused (`NotSupportedException`) |
| `NotifiableInterface` | `notify()` refused: the payload and the signature are not public |
| Orders, menu, store, tokens | no |

`capabilities()` is the default one (nothing declared). The message of each refusal names the
documentation's page: `The "zenchef" platform does not list reservations through a public API:
Zenchef sends its documentation on request (https://help.zenchef.com/...).`
`pushAvailability()` is refused for another reason: Zenchef's help centre lists reading
availability, not writing it.

## Behaviour

`api()` is the client: `request(string $method, string $path, array $query = [], array|object|null
$json = null): array`. It sends `Accept: application/json` and the configured headers, the query
without its `null` and `''` values, the body as JSON, to `base_uri` + the path, and answers the
decoded JSON (`[]` for an empty or non-JSON body).

- No `base_uri` or no `headers`: `InvalidConfigException` (`The "zenchef" platform needs:
  base_uri, headers (from the documentation Zenchef sends on request).`), before any call.
- A header that names `{token}` or `{restaurant_id}` with that option empty:
  `InvalidConfigException` naming it. (Both placeholders are resolved for every header, so both
  options are needed as soon as any header is configured.)

Zenchef's published limits: 100 calls a minute for the calls answered in less than a second, the
slower ones one after the other, pages of 250 to 500, and only what changed since the last call
after the first import. The client does not enforce them.

## Example

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Omnifood\Zenchef\ZenchefPlatformFactory;
use Symfony\Component\HttpClient\HttpClient;

$zenchef = (new ZenchefPlatformFactory(HttpClient::create()))->create([
    'token' => getenv('ZENCHEF_TOKEN') ?: null,
    'restaurant_id' => getenv('ZENCHEF_RESTAURANT_ID') ?: null,
    'base_uri' => getenv('ZENCHEF_BASE_URI') ?: null,           // from Zenchef's documentation
    'headers' => ['Header-From-The-Docs' => '{token}'],          // likewise
]);

$data = $zenchef->api()->request('GET', '/path/from/the/documentation', ['page' => 1]);
```

```yaml
# config/packages/omnifood.yaml
omnifood:
    platforms:
        zenchef:
            factory: zenchef
            options:
                token: '%env(default::ZENCHEF_TOKEN)%'
                restaurant_id: '%env(default::ZENCHEF_RESTAURANT_ID)%'
                base_uri: '%env(default::ZENCHEF_BASE_URI)%'
                headers: { 'Header-From-The-Docs': '{token}' }
```

In a Symfony application, `PlatformInterface $zenchef` injects the platform; `->api()` is on
`ZenchefPlatform`, not on an interface.

## Errors

- 401 and 403: `UnauthorizedException`; any other status of 400 or more: `ProviderException`,
  the message from `message` or `error` (the error's shape is not public), else `HTTP <status>.`;
  no provider code.
- A network failure: `ProviderException` `Zenchef could not be reached: ...`.
- A 2xx answer whose body is not JSON (a proxy's or a maintenance page): `ProviderException`
  `An answer that is not JSON (HTTP 200).`, with the status.
- Every reservation call and `notify()`: `NotSupportedException`.

No failure carries the token: a network failure's message has it masked as `***` (the key
parameters of a URL too), its previous is a copy of the HTTP client's exception (the same class and
code), and no trace of the chain keeps its arguments, where the request's options stood ([No secret
in a
failure](https://github.com/glitchr-omnisense/omnifood/blob/1.x/docs/errors.md#no-secret-in-a-failure)).

## Verified, and not

`Tests/ZenchefPlatformTest.php` checks that every reservation call is refused rather than
guessed, with nothing sent; that the client carries the credentials where the configured headers
say (`{token}`, `{restaurant_id}` in headers and path); that no address means no call; and the
error mapping. Green in the family's harness on 2026-10-26. Zenchef's API was never called: its
documentation is not public.

## Responsibility

This package is an independent, unofficial client: it is not affiliated with, endorsed by or
sponsored by Zenchef. Whoever runs it complies with Zenchef's terms
([zenchef.com/terms-conditions](https://www.zenchef.com/terms-conditions)); see the README's
Responsibility section.
