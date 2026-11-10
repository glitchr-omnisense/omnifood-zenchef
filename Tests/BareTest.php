<?php

namespace Omnifood\Zenchef\Tests;

use Omnifood\Zenchef\ZenchefPlatformFactory;
use Omnifood\Testing\BareTestCase;

/** omnifood/zenchef's classes load with omnisense/omnifood, omnisense/contracts and Symfony's HTTP client contracts alone. */
final class BareTest extends BareTestCase
{
    protected static function entryClass(): string
    {
        return ZenchefPlatformFactory::class;
    }

    protected static function requiredPackages(): array
    {
        return ['symfony/http-client-contracts', 'symfony/deprecation-contracts', 'psr/container', 'symfony/polyfill-mbstring'];
    }
}
