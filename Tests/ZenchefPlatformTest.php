<?php

namespace Omnifood\Zenchef\Tests;

use Omnifood\Exception\InvalidConfigException;
use Omnifood\Exception\NotSupportedException;
use Omnifood\Exception\ProviderException;
use Omnifood\Exception\UnauthorizedException;
use Omnifood\Model\Guest;
use Omnifood\Model\Reservation;
use Omnifood\Model\ReservationStatus;
use Omnifood\Model\Slot;
use Omnifood\Zenchef\ZenchefPlatform;
use Omnifood\Zenchef\ZenchefPlatformFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ZenchefPlatformTest extends TestCase
{
    /** @var list<array{string, string, array<string, string>}> */
    private array $calls = [];

    private function platform(array $options = [], int $status = 200): ZenchefPlatform
    {
        $http = new MockHttpClient(function (string $method, string $url, array $options) use ($status): MockResponse {
            $headers = [];
            foreach ($options['headers'] as $header) {
                [$name, $value] = explode(': ', $header, 2);
                $headers[strtolower($name)] = $value;
            }
            $this->calls[] = [$method, $url, $headers];

            return new MockResponse($status >= 400 ? '{"message":"Invalid token"}' : '{"data":[]}', ['http_code' => $status]);
        });

        return (new ZenchefPlatformFactory($http))->create($options + ['token' => 'zc-token', 'restaurant_id' => '12345']);
    }

    public function testEveryReservationCallIsRefusedRatherThanGuessed(): void
    {
        $platform = $this->platform();
        $reservation = new Reservation(new \DateTimeImmutable('+1 day'), 2, new Guest('Aiko'));
        foreach ([
            fn () => $platform->reservations(new \DateTimeImmutable(), new \DateTimeImmutable('+1 week')),
            fn () => $platform->reservation('1'),
            fn () => $platform->createReservation($reservation),
            fn () => $platform->updateReservation($reservation->with(reference: '1')),
            fn () => $platform->cancelReservation('1'),
            fn () => $platform->setStatus('1', ReservationStatus::SEATED),
            fn () => $platform->pushAvailability([new Slot(new \DateTimeImmutable(), 4)]),
            fn () => $platform->notify('{}', []),
        ] as $i => $call) {
            try {
                $call();
                self::fail("Call $i refused.");
            } catch (NotSupportedException $e) {
                self::assertStringStartsWith('The "zenchef" platform does not ', $e->getMessage());
            }
        }
        self::assertStringContainsString(ZenchefPlatform::DOCUMENTATION, $e->getMessage());
        self::assertSame([], $this->calls, 'nothing sent');
        self::assertSame('zenchef', $platform->getName());
        self::assertFalse($platform->capabilities()->availabilityPush);
    }

    public function testTheClientCarriesTheCredentialsWhereZenchefsDocumentationSays(): void
    {
        $platform = $this->platform(['base_uri' => 'https://zenchef.example/api', 'headers' => ['X-Token' => '{token}', 'X-Restaurant' => '{restaurant_id}']]);

        self::assertSame(['data' => []], $platform->api()->request('GET', '/restaurants/{restaurant_id}/things', ['page' => 1]));
        [$method, $url, $headers] = $this->calls[0];
        self::assertSame(['GET', 'https://zenchef.example/api/restaurants/12345/things?page=1'], [$method, $url]);
        self::assertSame(['zc-token', '12345'], [$headers['x-token'], $headers['x-restaurant']]);
    }

    public function testNoAddressNoCall(): void
    {
        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('The "zenchef" platform needs: base_uri, headers');
        $this->platform()->api()->request('GET', '/anything');
    }

    /** A 2xx whose body is not JSON (a proxy's page) is not an empty answer. */
    public function testAnAnswerThatIsNotJsonIsAnError(): void
    {
        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('[zenchef] An answer that is not JSON (HTTP 200).');
        (new \Omnifood\Zenchef\ZenchefPlatformFactory(new MockHttpClient(new MockResponse('<html><body>502 Bad Gateway</body></html>', ['http_code' => 200, 'response_headers' => ['Content-Type: text/html']]))))->create(['token' => 'zc-token', 'restaurant_id' => '12345', 'base_uri' => 'https://zenchef.example', 'headers' => ['X-Token' => '{token}']])->api()->request('GET', '/x');
    }

    public function testErrorsAreMapped(): void
    {
        try {
            $this->platform(['base_uri' => 'https://zenchef.example', 'headers' => ['X-Token' => '{token}']], 401)->api()->request('GET', '/x');
            self::fail('Refused.');
        } catch (UnauthorizedException $e) {
            self::assertSame('[zenchef] Invalid token', $e->getMessage());
        }
        $this->expectException(ProviderException::class);
        $this->platform(['base_uri' => 'https://zenchef.example', 'headers' => ['X-Token' => '{token}']], 500)->api()->request('GET', '/x');
    }
}
