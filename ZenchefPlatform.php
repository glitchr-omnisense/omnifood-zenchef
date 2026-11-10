<?php

namespace Omnifood\Zenchef;

use Omnifood\Channel;
use Omnifood\Exception\NotSupportedException;
use Omnifood\Model\Capabilities;
use Omnifood\Model\Notification;
use Omnifood\Model\Reservation;
use Omnifood\Model\ReservationStatus;
use Omnifood\NotifiableInterface;
use Omnifood\ReservationsInterface;
use Omnisense\Contracts\Policy\DeclaresUsagePolicyInterface;
use Omnisense\Contracts\Policy\UsagePolicy;

/**
 * A restaurant's reservations at Zenchef - not yet: Zenchef's partner API
 * is documented on request only (https://help.zenchef.com/hc/en-gb/articles/27690768125597-Zenchef-API).
 * Its help centre says what it offers - reservations read, created,
 * updated, their status changed; availability and opening hours; guests;
 * one webhook per restaurant, on a reservation made or updated - but not
 * its paths, fields, statuses nor how a webhook is signed. Every call is
 * refused (NotSupportedException) rather than guessed; api() is the
 * client to write them with once the documentation is in hand.
 */
final class ZenchefPlatform implements ReservationsInterface, NotifiableInterface, DeclaresUsagePolicyInterface
{
    private static ?UsagePolicy $policy = null;

    public const DOCUMENTATION = 'https://help.zenchef.com/hc/en-gb/articles/27690768125597-Zenchef-API';

    public function __construct(private readonly Api $api)
    {
    }

    /** Its usage policy, as the package declares it (policy.json, read once). */
    public function usagePolicy(): UsagePolicy
    {
        return self::$policy ??= UsagePolicy::fromFile(__DIR__.'/policy.json');
    }

    public function getName(): string
    {
        return 'zenchef';
    }

    public function getChannel(): Channel
    {
        return Channel::ZENCHEF;
    }

    public function capabilities(): Capabilities
    {
        return new Capabilities();
    }

    /** The client, configured with the restaurant's credentials, for the calls Zenchef's documentation describes. */
    public function api(): Api
    {
        return $this->api;
    }

    public function reservations(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        throw $this->undocumented('list reservations');
    }

    public function reservation(string $ref): Reservation
    {
        throw $this->undocumented('read a reservation');
    }

    public function createReservation(Reservation $reservation): Reservation
    {
        throw $this->undocumented('create a reservation');
    }

    public function updateReservation(Reservation $reservation): Reservation
    {
        throw $this->undocumented('update a reservation');
    }

    public function cancelReservation(string $ref, ?string $reason = null): void
    {
        throw $this->undocumented('cancel a reservation');
    }

    public function setStatus(string $ref, ReservationStatus $status): void
    {
        throw $this->undocumented('set a reservation\'s status');
    }

    public function pushAvailability(array $slots): void
    {
        throw NotSupportedException::operation($this->getName(), 'take availability pushed (Zenchef\'s help centre lists reading availability, not writing it)');
    }

    public function notify(string $body, array $headers): Notification
    {
        throw $this->undocumented('read a webhook (its payload and signature are not public)');
    }

    private function undocumented(string $operation): NotSupportedException
    {
        return NotSupportedException::operation($this->getName(), $operation.' through a public API: Zenchef sends its documentation on request ('.self::DOCUMENTATION.')');
    }
}
