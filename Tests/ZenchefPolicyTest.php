<?php

namespace Omnifood\Zenchef\Tests;

use Omnifood\Exception\RateLimitedException;
use Omnifood\Exception\UsageLimitReachedException;
use Omnifood\Registry;
use Omnifood\Zenchef\ZenchefPlatform;
use Omnifood\Zenchef\ZenchefPlatformFactory;
use Omnisense\Contracts\Policy\PolicyExport;
use Omnisense\Contracts\Policy\UsagePolicy;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Zenchef's usage policy, declared as data (contract-only, 100 calls a
 * minute, one at a time), and a 429 to a call of the configured client:
 * sent once, surfaced, the next calls held unsent for the wait.
 */
final class ZenchefPolicyTest extends TestCase
{
    private const OPTIONS = ['token' => 'zc-token', 'restaurant_id' => '12345', 'base_uri' => 'https://zenchef.example/api', 'headers' => ['X-Token' => '{token}']];

    /** @var list<array{string, string}> */
    private array $calls = [];

    /** @var list<MockResponse> */
    private array $answers = [];

    public function testThePolicyIsDeclaredAsData(): void
    {
        $declared = (new PolicyExport())->folders(\dirname(__DIR__))->entries();
        self::assertCount(1, $declared, 'composer.json\'s extra.omnisense.policy names it');
        self::assertSame(['omnifood/zenchef', 'policy.json'], [$declared[0]['package'], $declared[0]['file']]);
        self::assertArrayNotHasKey('error', $declared[0], 'it reads as the schema says');
        $policy = UsagePolicy::fromFile(__DIR__.'/../policy.json');

        self::assertSame('zenchef', $policy->provider);
        self::assertSame('2026-11-10', $policy->provenance->verifiedAt);
        self::assertSame('contract', $policy->rights->commercial->value, 'contract-only');
        self::assertSame(100, $policy->limitsFor('reservations')[0]->max, 'every call');
        self::assertSame(1, $policy->concurrency, 'the slow calls one after the other');
        $registry = new Registry([new ZenchefPlatformFactory($this->http())], ['zenchef' => ['factory' => 'zenchef', 'options' => self::OPTIONS]]);
        self::assertEquals($policy, $registry->policy('zenchef'));
    }

    public function testA429IsSurfacedNeverRetriedAndHoldsTheNextCalls(): void
    {
        $this->answers = [new MockResponse('{"message": "Too many requests"}', ['http_code' => 429, 'response_headers' => ['retry-after' => '60']])];
        $platform = (new ZenchefPlatformFactory($this->http()))->create(self::OPTIONS);
        self::assertInstanceOf(ZenchefPlatform::class, $platform);
        $api = $platform->api();

        try {
            $api->request('POST', '/restaurants/{restaurant_id}/bookings', [], ['nb_guests' => 2]);
            self::fail('a 429');
        } catch (RateLimitedException $e) {
            self::assertSame(60, $e->retryAfter);
            self::assertSame(429, $e->status);
            self::assertSame('zenchef', $e->platform);
        }
        self::assertCount(1, $this->calls, 'sent once, not retried');

        $this->expectException(UsageLimitReachedException::class);
        try {
            $api->request('GET', '/restaurants/{restaurant_id}/bookings');
        } finally {
            self::assertCount(1, $this->calls, 'held, not sent');
        }
    }

    private function http(): MockHttpClient
    {
        return new MockHttpClient(function (string $method, string $url): MockResponse {
            $this->calls[] = [$method, $url];

            return array_shift($this->answers) ?? new MockResponse('{}');
        });
    }
}
