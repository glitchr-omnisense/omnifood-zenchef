<?php

namespace Omnifood\Zenchef;

use Omnifood\Exception\InvalidConfigException;
use Omnifood\Exception\ProviderException;
use Omnifood\Exception\UnauthorizedException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * A client for Zenchef's partner API, configured from the documentation
 * Zenchef sends on request - its base URL and the headers that carry the
 * restaurant's credentials are not public, so nothing is assumed: they are
 * options, "{token}" and "{restaurant_id}" in a header's value standing
 * for the restaurant's token and id.
 *
 * Zenchef's published limits: 100 calls a minute for the calls answered in
 * less than a second, the slower ones one after the other, pages of 250 to
 * 500.
 */
final class Api
{
    public const PLATFORM = 'zenchef';

    private readonly HttpClientInterface $http;

    /** @param array<string, string> $headers */
    public function __construct(
        private readonly ?string $token = null,
        private readonly ?string $restaurantId = null,
        private readonly ?string $baseUri = null,
        private readonly array $headers = [],
        ?HttpClientInterface $http = null,
    ) {
        $this->http = $http ?? HttpClient::create();
    }

    /**
     * @param array<string, scalar|null>       $query
     * @param array<mixed>|object|null $json
     *
     * @return array<mixed>
     */
    public function request(string $method, string $path, array $query = [], array|object|null $json = null): array
    {
        if (!$this->baseUri || !$this->headers) {
            throw new InvalidConfigException('The "zenchef" platform needs: base_uri, headers (from the documentation Zenchef sends on request).');
        }
        $headers = ['Accept' => 'application/json'];
        foreach ($this->headers as $name => $value) {
            $headers[$name] = strtr((string) $value, [
                '{token}' => $this->token ?: throw new InvalidConfigException('The "zenchef" platform needs: token.'),
                '{restaurant_id}' => $this->restaurantId ?: throw new InvalidConfigException('The "zenchef" platform needs: restaurant_id.'),
            ]);
        }
        $options = ['headers' => $headers];
        if ($query = array_filter($query, static fn ($v) => null !== $v && '' !== $v)) {
            $options['query'] = $query;
        }
        if (null !== $json) {
            $options['json'] = $json;
        }
        try {
            $response = $this->http->request($method, rtrim($this->baseUri, '/').'/'.ltrim(strtr($path, ['{restaurant_id}' => rawurlencode((string) $this->restaurantId)]), '/'), $options);
            $status = $response->getStatusCode();
            $content = $response->getContent(false);
        } catch (HttpExceptionInterface $e) {
            throw new ProviderException(self::PLATFORM, 'Zenchef could not be reached: '.$e->getMessage(), null, null, $e);
        }
        $data = '' === $content ? [] : json_decode($content, true);
        if ($status < 400 && null === $data && \JSON_ERROR_NONE !== json_last_error()) {
            // A proxy's or a maintenance page: not an empty answer.
            throw new ProviderException(self::PLATFORM, \sprintf('An answer that is not JSON (HTTP %d).', $status), $status);
        }
        $data = \is_array($data) ? $data : [];
        if ($status >= 400) {
            // The error's shape is not public: its message where there is one, the status otherwise.
            $message = \is_string($data['message'] ?? null) ? $data['message'] : (\is_string($data['error'] ?? null) ? $data['error'] : \sprintf('HTTP %d.', $status));

            throw 401 === $status || 403 === $status ? new UnauthorizedException(self::PLATFORM, $message, $status) : new ProviderException(self::PLATFORM, $message, $status);
        }

        return $data;
    }

    public function restaurantId(): ?string
    {
        return $this->restaurantId;
    }
}
