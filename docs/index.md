# Zenchef with Omnifood

Zenchef's partner API is documented on request: until its documentation is public, this package
holds the restaurant's credentials and a configured client, and refuses every reservation call.

## Getting the documentation

1. The restaurant is on Zenchef's **Grow** subscription (or a special agreement).
2. It writes to **help@zenchef.com** (or api-tech-help@zenchef.com) for the API documentation and a
   demo restaurant on the pre-production environment, giving its `restaurantId` and the use.
3. Its token and restaurant id: Zenchef, Settings > Partners.

## Configuration

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

The factory takes any `HttpClientInterface` (the application's, a `MockHttpClient` in a test) and
makes its own when given none; several platforms go in a `Registry`
([the core's installation](https://github.com/glitchr-omnisense/omnifood/blob/1.x/docs/installation.md)).
In a Symfony application, the same options in `config/packages/omnifood.yaml`:

```yaml
omnifood:
    platforms:
        zenchef:
            factory: zenchef
            options:
                token: '%env(default::ZENCHEF_TOKEN)%'
                restaurant_id: '%env(default::ZENCHEF_RESTAURANT_ID)%'
                base_uri: '%env(default::ZENCHEF_BASE_URI)%'     # from the documentation
                headers: { 'Header-From-The-Docs': '{token}' }   # likewise
```

## Calling it

```php
$data = $zenchef->api()->request('GET', '/path/from/the/documentation', ['page' => 1]);
```

Zenchef's rules: 100 calls a minute for the fast ones, the slow ones one after the other, pages of
250 to 500, and only what changed since the last call after the first import.
