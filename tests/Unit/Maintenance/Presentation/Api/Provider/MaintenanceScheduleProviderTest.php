<?php

declare(strict_types=1);

namespace Tests\Unit\Maintenance\Presentation\Api\Provider;

use ApiPlatform\Metadata\{Get, GetCollection};
use ApiPlatform\State\Pagination\TraversablePaginator;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Maintenance\Application\Contract\Schedule\{MaintenanceSchedulePage, MaintenanceScheduleView};
use Maintenance\Application\UseCase\Query\Schedule\GetMaintenanceSchedule\{GetMaintenanceScheduleQuery, GetMaintenanceScheduleResult};
use Maintenance\Application\UseCase\Query\Schedule\ListMaintenanceSchedules\{ListMaintenanceSchedulesQuery, ListMaintenanceSchedulesResult};
use Maintenance\Presentation\Api\Dto\Output\MaintenanceScheduleOutput;
use Maintenance\Presentation\Api\Factory\MaintenanceScheduleOutputFactory;
use Maintenance\Presentation\Api\Provider\MaintenanceScheduleProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Test MaintenanceScheduleProviderTest.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MaintenanceScheduleProvider::class)]
final class MaintenanceScheduleProviderTest extends TestCase
{
  private const string USER_ID = '550e8400-e29b-41d4-a716-446655441300';

  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string SCHEDULE_ID = 'schedule-1';

  #[Test]
  public function testProvideReturnsTheScheduleForAnItemRequest(): void
  {
    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static fn (GetMaintenanceScheduleQuery $query): bool => self::USER_ID === $query->userId
        && self::SCHEDULE_ID === $query->scheduleId))
      ->willReturn(new GetMaintenanceScheduleResult($this->view()));

    $provider = new MaintenanceScheduleProvider($queryBus, new MaintenanceScheduleOutputFactory(), $this->security(), new RequestStack());

    $output = $provider->provide(new Get(), ['id' => self::SCHEDULE_ID]);

    self::assertInstanceOf(MaintenanceScheduleOutput::class, $output);
    self::assertSame(self::SCHEDULE_ID, $output->id);
  }

  #[Test]
  public function testProvideRequiresTheOrganizationFilterForACollectionRequest(): void
  {
    $requestStack = new RequestStack();
    $requestStack->push(Request::create('/api/maintenance/schedules'));

    $provider = new MaintenanceScheduleProvider(
      $this->createStub(QueryBusPort::class),
      new MaintenanceScheduleOutputFactory(),
      $this->security(),
      $requestStack,
    );

    $this->expectException(BadRequestHttpException::class);

    $provider->provide(new GetCollection(), []);
  }

  #[Test]
  public function testProvideListsSchedulesForACollectionRequest(): void
  {
    $requestStack = new RequestStack();
    $requestStack->push(Request::create('/api/maintenance/schedules?organization=' . self::ORG_ID));

    $page = new MaintenanceSchedulePage([$this->view()], 1, 30, 1);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static fn (ListMaintenanceSchedulesQuery $query): bool => self::ORG_ID === $query->organizationId))
      ->willReturn(new ListMaintenanceSchedulesResult($page));

    $provider = new MaintenanceScheduleProvider($queryBus, new MaintenanceScheduleOutputFactory(), $this->security(), $requestStack);

    $result = $provider->provide(new GetCollection(), []);

    self::assertInstanceOf(TraversablePaginator::class, $result);
  }

  private function security(): Security
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(new SecurityUser(
      id: self::USER_ID,
      email: 'user@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    ));

    return $security;
  }

  private function view(): MaintenanceScheduleView
  {
    return new MaintenanceScheduleView(
      id: self::SCHEDULE_ID,
      organizationId: self::ORG_ID,
      equipmentId: '550e8400-e29b-41d4-a716-446655440002',
      facilityId: null,
      equipmentType: 'fire_extinguisher',
      intervalOverride: null,
      lastInspectionClosedAt: new DateTimeImmutable('2026-01-01'),
      nextDueAt: new DateTimeImmutable('2026-04-01'),
      dueStatus: 'up_to_date',
      lastRemindedAt: null,
      remindedFor: null,
      createdAt: new DateTimeImmutable('2026-01-01'),
      updatedAt: new DateTimeImmutable('2026-01-01'),
    );
  }
}
