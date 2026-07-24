<?php

declare(strict_types=1);

namespace Tests\Unit\Calendar\Application\UseCase\Query\Feed\GetCalendarFeed;

use Calendar\Application\Port\Outbound\Event\CalendarEventRepositoryPort;
use Calendar\Application\Port\Outbound\Feed\{InspectionCalendarFeedPort, InterventionCalendarFeedPort, MaintenanceCalendarFeedPort};
use Calendar\Application\Service\CalendarFeedAggregator;
use Calendar\Application\UseCase\Query\Feed\GetCalendarFeed\{GetCalendarFeedHandler, GetCalendarFeedQuery, GetCalendarFeedResult};
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Domain\Exception\OrganizationAccessDeniedException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\LoggerPort;

/**
 * Test GetCalendarFeedHandlerTest.
 *
 * Exercises the handler against a real {@see CalendarFeedAggregator} (final,
 * so it is a real collaborator here rather than a mock) composed of empty
 * stub ports — the handler's own responsibility is the permission check and
 * the `from`/`to` parsing + bounded-range validation.
 *
 * @category UseCase Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetCalendarFeedHandler::class)]
final class GetCalendarFeedHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a01';

  private const string USER_ID = 'user-1';

  #[Test]
  public function itAssertsPermissionAndReturnsTheAggregatedFeed(): void
  {
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('assertGrantedPermissions')
      ->with(self::USER_ID, self::ORGANIZATION_ID, ['organization.events.read']);

    $handler = new GetCalendarFeedHandler($authorization, $this->emptyAggregator());

    $result = $handler->__invoke(new GetCalendarFeedQuery(
      userId: self::USER_ID,
      organizationId: self::ORGANIZATION_ID,
      from: '2026-08-01T00:00:00+00:00',
      to: '2026-08-31T23:59:59+00:00',
    ));

    self::assertInstanceOf(GetCalendarFeedResult::class, $result);
    self::assertSame([], $result->items);
  }

  #[Test]
  public function itThrowsWhenThePermissionIsMissing(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('assertGrantedPermissions')
      ->willThrowException(new OrganizationAccessDeniedException('Missing permission.'));

    $handler = new GetCalendarFeedHandler($authorization, $this->emptyAggregator());

    $this->expectException(OrganizationAccessDeniedException::class);

    $handler->__invoke(new GetCalendarFeedQuery(
      userId: self::USER_ID,
      organizationId: self::ORGANIZATION_ID,
      from: '2026-08-01T00:00:00+00:00',
      to: '2026-08-31T23:59:59+00:00',
    ));
  }

  #[Test]
  public function itThrowsOnAMalformedDatetime(): void
  {
    $handler = new GetCalendarFeedHandler($this->createStub(OrganizationAuthorizationPort::class), $this->emptyAggregator());

    $this->expectException(InvalidArgumentException::class);

    $handler->__invoke(new GetCalendarFeedQuery(
      userId: self::USER_ID,
      organizationId: self::ORGANIZATION_ID,
      from: 'not-a-date',
      to: '2026-08-31T23:59:59+00:00',
    ));
  }

  #[Test]
  public function itThrowsOnAnInvertedRange(): void
  {
    $handler = new GetCalendarFeedHandler($this->createStub(OrganizationAuthorizationPort::class), $this->emptyAggregator());

    $this->expectException(InvalidArgumentException::class);

    $handler->__invoke(new GetCalendarFeedQuery(
      userId: self::USER_ID,
      organizationId: self::ORGANIZATION_ID,
      from: '2026-08-31T23:59:59+00:00',
      to: '2026-08-01T00:00:00+00:00',
    ));
  }

  #[Test]
  public function itThrowsWhenTheRangeExceedsThreeHundredSixtySixDays(): void
  {
    $handler = new GetCalendarFeedHandler($this->createStub(OrganizationAuthorizationPort::class), $this->emptyAggregator());

    $this->expectException(InvalidArgumentException::class);

    $handler->__invoke(new GetCalendarFeedQuery(
      userId: self::USER_ID,
      organizationId: self::ORGANIZATION_ID,
      from: '2025-01-01T00:00:00+00:00',
      to: '2026-12-31T23:59:59+00:00',
    ));
  }

  private function emptyAggregator(): CalendarFeedAggregator
  {
    $events = $this->createStub(CalendarEventRepositoryPort::class);
    $events->method('listBetween')->willReturn([]);

    $inspections = $this->createStub(InspectionCalendarFeedPort::class);
    $inspections->method('findBetween')->willReturn([]);

    $interventions = $this->createStub(InterventionCalendarFeedPort::class);
    $interventions->method('findBetween')->willReturn([]);

    $maintenance = $this->createStub(MaintenanceCalendarFeedPort::class);
    $maintenance->method('findBetween')->willReturn([]);

    return new CalendarFeedAggregator(
      $events,
      $inspections,
      $interventions,
      $maintenance,
      $this->createStub(LoggerPort::class),
    );
  }
}
