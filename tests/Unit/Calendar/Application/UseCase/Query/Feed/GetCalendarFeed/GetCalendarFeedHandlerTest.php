<?php

declare(strict_types=1);

namespace Tests\Unit\Calendar\Application\UseCase\Query\Feed\GetCalendarFeed;

use Calendar\Application\Port\Outbound\Event\CalendarEventRepositoryPort;
use Calendar\Application\Port\Outbound\Feed\{InspectionCalendarFeedPort, InterventionCalendarFeedPort, MaintenanceCalendarFeedPort};
use Calendar\Application\Service\CalendarFeedAggregator;
use Calendar\Application\UseCase\Query\Feed\GetCalendarFeed\{GetCalendarFeedHandler, GetCalendarFeedQuery, GetCalendarFeedResult};
use DateTimeImmutable;
use Organization\Application\Contract\Workforce\OrganizationWorkforceContext;
use Organization\Application\Port\Inbound\{OrganizationAuthorizationPort, OrganizationWorkforceDirectoryPort};
use Organization\Domain\Exception\OrganizationAccessDeniedException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\LoggerPort;
use Shared\Domain\Exception\InvalidValueException;

use function array_map;

use const DATE_ATOM;

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

    $handler = new GetCalendarFeedHandler($authorization, $this->emptyAggregator(), $this->createStub(OrganizationWorkforceDirectoryPort::class));

    $result = $handler->__invoke(new GetCalendarFeedQuery(
      userId: self::USER_ID,
      organizationId: self::ORGANIZATION_ID,
      from: '2026-08-01T00:00:00+00:00',
      to: '2026-08-31T23:59:59+00:00',
    ));

    self::assertInstanceOf(GetCalendarFeedResult::class, $result);
    self::assertSame([], $result->items);
    self::assertTrue($result->complete);
    self::assertCount(1, $result->sources);
    self::assertSame('calendar_event', $result->sources[0]->sourceKey);
  }

  #[Test]
  public function itDoesNotQueryOrDescribeSourcesWithoutTheirReadPermission(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturnCallback(static fn (string $user, string $organization, string $permission): bool => 'organization.maintenance.read' === $permission);
    $inspections = $this->createMock(InspectionCalendarFeedPort::class);
    $inspections->expects(self::never())->method('findBetween');
    $interventions = $this->createMock(InterventionCalendarFeedPort::class);
    $interventions->expects(self::never())->method('findBetween');
    $maintenance = $this->createMock(MaintenanceCalendarFeedPort::class);
    $maintenance->expects(self::once())->method('findBetween')->willReturn([]);
    $aggregator = new CalendarFeedAggregator($this->createStub(CalendarEventRepositoryPort::class), $inspections, $interventions, $maintenance, $this->createStub(LoggerPort::class));
    $result = (new GetCalendarFeedHandler($authorization, $aggregator, $this->createStub(OrganizationWorkforceDirectoryPort::class)))(new GetCalendarFeedQuery(self::USER_ID, self::ORGANIZATION_ID, '2026-08-01T00:00:00Z', '2026-08-31T00:00:00Z'));
    self::assertSame(['calendar_event', 'maintenance'], array_map(static fn ($source): string => $source->sourceKey, $result->sources));
    self::assertTrue($result->complete);
  }

  #[Test]
  public function itThrowsWhenThePermissionIsMissing(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('assertGrantedPermissions')
      ->willThrowException(new OrganizationAccessDeniedException('Missing permission.'));

    $handler = new GetCalendarFeedHandler($authorization, $this->emptyAggregator(), $this->createStub(OrganizationWorkforceDirectoryPort::class));

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
    $handler = new GetCalendarFeedHandler($this->createStub(OrganizationAuthorizationPort::class), $this->emptyAggregator(), $this->createStub(OrganizationWorkforceDirectoryPort::class));

    $this->expectException(InvalidValueException::class);

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
    $handler = new GetCalendarFeedHandler($this->createStub(OrganizationAuthorizationPort::class), $this->emptyAggregator(), $this->createStub(OrganizationWorkforceDirectoryPort::class));

    $this->expectException(InvalidValueException::class);

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
    $handler = new GetCalendarFeedHandler($this->createStub(OrganizationAuthorizationPort::class), $this->emptyAggregator(), $this->createStub(OrganizationWorkforceDirectoryPort::class));

    $this->expectException(InvalidValueException::class);

    $handler->__invoke(new GetCalendarFeedQuery(
      userId: self::USER_ID,
      organizationId: self::ORGANIZATION_ID,
      from: '2025-01-01T00:00:00+00:00',
      to: '2026-12-31T23:59:59+00:00',
    ));
  }

  #[Test]
  public function itAcceptsAFractionalSecondsOffset(): void
  {
    $handler = new GetCalendarFeedHandler(
      $this->createStub(OrganizationAuthorizationPort::class),
      $this->emptyAggregator(),
      $this->createStub(OrganizationWorkforceDirectoryPort::class),
    );

    $result = $handler->__invoke(new GetCalendarFeedQuery(
      userId: self::USER_ID,
      organizationId: self::ORGANIZATION_ID,
      from: '2026-08-01T00:00:00.5+00:00',
      to: '2026-08-31T23:59:59.123456Z',
    ));

    self::assertSame('2026-08-01T00:00:00.500000+00:00', $result->from->format('Y-m-d\\TH:i:s.uP'));
    self::assertSame('2026-08-31T23:59:59.123456+00:00', $result->to->format('Y-m-d\\TH:i:s.uP'));
  }

  #[Test]
  public function itPassesUtcMonthBoundsToPersistenceAndCarriesTheOrganizationTimezone(): void
  {
    $events = $this->createMock(CalendarEventRepositoryPort::class);
    $events->expects(self::once())->method('listBetween')->with(
      self::ORGANIZATION_ID,
      self::callback(static fn (DateTimeImmutable $from): bool => '2026-03-28T23:00:00+00:00' === $from->format(DATE_ATOM)),
      self::callback(static fn (DateTimeImmutable $to): bool => '2026-03-29T22:00:00+00:00' === $to->format(DATE_ATOM)),
      self::anything(),
    )->willReturn([]);
    $organizations = $this->createMock(OrganizationWorkforceDirectoryPort::class);
    $organizations->expects(self::once())->method('context')->with(self::ORGANIZATION_ID)
      ->willReturn(new OrganizationWorkforceContext('Europe/Paris', 'monday'));
    $aggregator = new CalendarFeedAggregator(
      $events,
      $this->createStub(InspectionCalendarFeedPort::class),
      $this->createStub(InterventionCalendarFeedPort::class),
      $this->createStub(MaintenanceCalendarFeedPort::class),
      $this->createStub(LoggerPort::class),
    );

    $result = (new GetCalendarFeedHandler($this->createStub(OrganizationAuthorizationPort::class), $aggregator, $organizations))(
      new GetCalendarFeedQuery(self::USER_ID, self::ORGANIZATION_ID, '2026-03-29T00:00:00+01:00', '2026-03-30T00:00:00+02:00'),
    );

    self::assertSame('Europe/Paris', $result->timezone);
    self::assertSame('UTC', $result->from->getTimezone()->getName());
    self::assertSame('UTC', $result->to->getTimezone()->getName());
  }

  #[Test]
  public function itRejectsAShapeValidDatetimeThatIsNotARealCalendarDate(): void
  {
    $handler = new GetCalendarFeedHandler(
      $this->createStub(OrganizationAuthorizationPort::class),
      $this->emptyAggregator(),
      $this->createStub(OrganizationWorkforceDirectoryPort::class),
    );

    $this->expectException(InvalidValueException::class);
    $this->expectExceptionMessage('Invalid "from" datetime filter.');

    // Matches the shape regex, but 30 February is not a date: the handler
    // has to catch it through DateTimeImmutable's warning list.
    $handler->__invoke(new GetCalendarFeedQuery(
      userId: self::USER_ID,
      organizationId: self::ORGANIZATION_ID,
      from: '2026-02-30T00:00:00+00:00',
      to: '2026-03-31T23:59:59+00:00',
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
