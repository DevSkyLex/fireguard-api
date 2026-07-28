<?php

declare(strict_types=1);

namespace Tests\Unit\Calendar\Presentation\Api\Provider\Event;

use ApiPlatform\Metadata\Get;
use Auth\Infrastructure\Security\User\SecurityUser;
use Calendar\Application\UseCase\Query\Event\GetCalendarEvent\{GetCalendarEventQuery, GetCalendarEventResult};
use Calendar\Domain\Exception\CalendarEventNotFoundException;
use Calendar\Presentation\Api\Factory\CalendarEventOutputFactory;
use Calendar\Presentation\Api\Provider\Event\GetCalendarEventProvider;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};

/**
 * Test GetCalendarEventProviderTest.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetCalendarEventProvider::class)]
final class GetCalendarEventProviderTest extends TestCase
{
  // #region Constants
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a01';

  private const string EVENT_ID = 'event-1';

  private const string USER_ID = 'user-id';
  // #endregion

  // #region Methods
  #[Test]
  public function testProvideReturnsTheMappedEvent(): void
  {
    $startsAt = new DateTimeImmutable('2026-04-01T09:00:00+00:00');
    $endsAt = new DateTimeImmutable('2026-04-01T11:00:00+00:00');
    $createdAt = new DateTimeImmutable('2026-03-01T09:00:00+00:00');
    $updatedAt = new DateTimeImmutable('2026-03-02T09:00:00+00:00');

    $captured = null;
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willReturnCallback(function (GetCalendarEventQuery $query) use (
        &$captured,
        $startsAt,
        $endsAt,
        $createdAt,
        $updatedAt,
      ): GetCalendarEventResult {
        $captured = $query;

        return new GetCalendarEventResult(
          id: self::EVENT_ID,
          organizationId: self::ORGANIZATION_ID,
          title: 'Annual fire drill',
          description: 'Full building evacuation.',
          startsAt: $startsAt,
          endsAt: $endsAt,
          allDay: false,
          facilityId: 'facility-1',
          createdByMemberId: self::USER_ID,
          createdAt: $createdAt,
          updatedAt: $updatedAt,
        );
      });

    $provider = new GetCalendarEventProvider(
      $queryBus,
      $this->authenticatedSecurity(),
      new CalendarEventOutputFactory(),
    );

    $output = $provider->provide(new Get(), [
      'organizationId' => self::ORGANIZATION_ID,
      'eventId' => self::EVENT_ID,
    ]);

    self::assertInstanceOf(GetCalendarEventQuery::class, $captured);
    self::assertSame(self::USER_ID, $captured->userId);
    self::assertSame(self::ORGANIZATION_ID, $captured->organizationId);
    self::assertSame(self::EVENT_ID, $captured->eventId);

    self::assertSame(self::EVENT_ID, $output->id);
    self::assertSame('Annual fire drill', $output->title);
    self::assertSame($startsAt->format('c'), $output->startsAt);
    self::assertSame($endsAt->format('c'), $output->endsAt);
    self::assertFalse($output->allDay);
    self::assertSame('facility-1', $output->facilityId);
  }

  #[Test]
  public function testProvideRequiresAuthentication(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $provider = new GetCalendarEventProvider(
      $this->createStub(QueryBusPort::class),
      $security,
      new CalendarEventOutputFactory(),
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new Get(), [
      'organizationId' => self::ORGANIZATION_ID,
      'eventId' => self::EVENT_ID,
    ]);
  }

  #[Test]
  public function testProvideRejectsABlankEventId(): void
  {
    $provider = new GetCalendarEventProvider(
      $this->createStub(QueryBusPort::class),
      $this->authenticatedSecurity(),
      new CalendarEventOutputFactory(),
    );

    $this->expectException(BadRequestHttpException::class);

    $provider->provide(new Get(), [
      'organizationId' => self::ORGANIZATION_ID,
      'eventId' => '',
    ]);
  }

  #[Test]
  public function testProvideMapsDomainExceptions(): void
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(CalendarEventNotFoundException::withId(self::EVENT_ID));

    $provider = new GetCalendarEventProvider(
      $queryBus,
      $this->authenticatedSecurity(),
      new CalendarEventOutputFactory(),
    );

    $this->expectException(NotFoundHttpException::class);

    $provider->provide(new Get(), [
      'organizationId' => self::ORGANIZATION_ID,
      'eventId' => self::EVENT_ID,
    ]);
  }
  // #endregion

  // #region Helpers
  private function authenticatedSecurity(): Security
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(
      new SecurityUser(self::USER_ID, 'user@example.com', 'password', ['ROLE_USER'], [], true),
    );

    return $security;
  }
  // #endregion
}
