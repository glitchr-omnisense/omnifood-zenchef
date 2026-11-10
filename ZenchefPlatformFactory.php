<?php

namespace Omnifood\Zenchef;

use Omnifood\Config;
use Omnifood\PlatformFactory;
use Omnifood\PlatformInterface;

/**
 * Zenchef: the restaurant's credentials, and a client ready for the partner
 * API once Zenchef has sent its documentation:
 *
 *   options:
 *     token: '%env(default::ZENCHEF_TOKEN)%'                 # Settings > Partners in Zenchef: "Zenchef token"
 *     restaurant_id: '%env(default::ZENCHEF_RESTAURANT_ID)%' # Settings > Partners: the restaurant's id
 *     base_uri: ~                                            # from Zenchef's documentation (not public)
 *     headers: {}                                            # from it too: {"<header>": "{token}", "<header>": "{restaurant_id}"}
 */
final class ZenchefPlatformFactory extends PlatformFactory
{
    protected function populate(Config $config): void
    {
        $config->defaults([
            'omnifood.factory_name' => 'zenchef',
            'omnifood.required_options' => [],
            'token' => null,
            'restaurant_id' => null,
            'base_uri' => null,
            'headers' => [],
        ]);
    }

    protected function build(Config $config): PlatformInterface
    {
        return new ZenchefPlatform(new Api(
            $config['token'] ? (string) $config['token'] : null,
            $config['restaurant_id'] ? (string) $config['restaurant_id'] : null,
            $config['base_uri'] ? (string) $config['base_uri'] : null,
            array_map('strval', (array) $config['headers']),
            $this->http,
            $config->backoff(),
        ));
    }
}
