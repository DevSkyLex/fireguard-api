<?php

declare(strict_types=1);

namespace Tests\Unit\Maintenance\Application\UseCase\Query\Schedule\ListMaintenanceSchedules;

use Maintenance\Application\Contract\Schedule\MaintenanceSchedulePage;
use Maintenance\Application\Port\Outbound\Schedule\MaintenanceScheduleRepositoryPort;
use Maintenance\Application\UseCase\Query\Schedule\ListMaintenanceSchedules\{
  ListMaintenanceSchedulesHandler,
  ListMaintenanceSchedulesQuery,
  ListMaintenanceSchedulesResult
};
use Maintenance\Domain\Exception\{MaintenanceAccessDeniedException, MaintenanceNotFoundException};
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ListMaintenanceSchedulesHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListMaintenanceSchedulesHandler::class)]
final class ListMaintenanceSchedulesHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string USER_ID = 'user-1';

  #[Test]
  public function testInvokeReturnsThePageWhenAuthorized(): void
  {
    $page = new MaintenanceSchedulePage([], 1, 30, 0);

    $schedules = $this->createMock(MaintenanceScheduleRepositoryPort::class);
    $schedules->expects(self::once())->method('list')->willReturn($page);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    $handler = new ListMaintenanceSchedulesHandler($schedules, $authorization);

    $result = $handler->__invoke(new ListMaintenanceSchedulesQuery(self::USER_ID, self::ORG_ID));

    self::assertInstanceOf(ListMaintenanceSchedulesResult::class, $result);
    self::assertSame($page, $result->page);
  }

  #[Test]
  public function testInvokeThrowsWhenPermissionIsMissing(): void
  {
    $schedules = $this->createMock(MaintenanceScheduleRepositoryPort::class);
    $schedules->expects(self::never())->method('list');

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::MISSING_PERMISSION);

    $handler = new ListMaintenanceSchedulesHandler($schedules, $authorization);

    $this->expectException(MaintenanceAccessDeniedException::class);

    $handler->__invoke(new ListMaintenanceSchedulesQuery(self::USER_ID, self::ORG_ID));
  }

  #[Test]
  public function testInvokeThrowsNotFoundWhenTheCallerIsOutsideTheOrganization(): void
  {
    $schedules = $this->createMock(MaintenanceScheduleRepositoryPort::class);
    $schedules->expects(self::never())->method('list');

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::OUTSIDE_SCOPE);

    $handler = new ListMaintenanceSchedulesHandler($schedules, $authorization);

    $this->expectException(MaintenanceNotFoundException::class);

    $handler->__invoke(new ListMaintenanceSchedulesQuery(self::USER_ID, self::ORG_ID));
  }
}
