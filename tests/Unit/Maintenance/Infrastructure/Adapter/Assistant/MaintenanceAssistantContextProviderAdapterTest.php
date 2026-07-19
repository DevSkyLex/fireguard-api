<?php

declare(strict_types=1);

namespace Tests\Unit\Maintenance\Infrastructure\Adapter\Assistant;

use Assistant\Application\Contract\Context\{AssistantContextBudget, AssistantContextScope};
use DateTimeImmutable;
use Maintenance\Application\Contract\Schedule\{MaintenanceSchedulePage, MaintenanceScheduleView};
use Maintenance\Application\Port\Outbound\Schedule\MaintenanceScheduleRepositoryPort;
use Maintenance\Infrastructure\Adapter\Assistant\MaintenanceAssistantContextProviderAdapter;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function mb_strpos;

/**
 * Test MaintenanceAssistantContextProviderAdapterTest.
 *
 * @category Adapter Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MaintenanceAssistantContextProviderAdapter::class)]
final class MaintenanceAssistantContextProviderAdapterTest extends TestCase
{
  private const string ORG_ID = '018f0b68-6758-7a12-8a1d-3f0d97f65001';

  private const string USER_ID = 'user-1';

  #[Test]
  public function testSupportsDelegatesToTheMaintenanceReadPermission(): void
  {
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with(self::USER_ID, self::ORG_ID, 'organization.maintenance.read')
      ->willReturn(true);

    $adapter = new MaintenanceAssistantContextProviderAdapter($authorization, $this->createStub(MaintenanceScheduleRepositoryPort::class));

    self::assertTrue($adapter->supports(self::ORG_ID, new AssistantContextScope(self::USER_ID, 'thread-1')));
  }

  #[Test]
  public function testProvideReturnsAnEmptyFragmentWhenNothingIsDue(): void
  {
    $schedules = $this->createStub(MaintenanceScheduleRepositoryPort::class);
    $schedules->method('list')->willReturn(new MaintenanceSchedulePage([], 1, 5, 0));

    $adapter = new MaintenanceAssistantContextProviderAdapter($this->createStub(OrganizationAuthorizationPort::class), $schedules);

    $fragment = $adapter->provide(self::ORG_ID, new AssistantContextScope(self::USER_ID, 'thread-1'), new AssistantContextBudget(4000));

    self::assertTrue($fragment->isEmpty());
  }

  #[Test]
  public function testProvideRendersOverdueAndDueSoonSchedulesSoonestFirst(): void
  {
    $overdueItem = $this->scheduleView('extinguisher', 'overdue', new DateTimeImmutable('2026-01-05T00:00:00+00:00'));
    $dueSoonItem = $this->scheduleView('smoke_detector', 'due_soon', new DateTimeImmutable('2026-02-01T00:00:00+00:00'));

    $schedules = $this->createStub(MaintenanceScheduleRepositoryPort::class);
    $schedules->method('list')->willReturnCallback(
      static function (string $organizationId, ?string $facilityId, ?string $equipmentType, ?string $dueStatus, ?DateTimeImmutable $dueBefore, int $page, int $itemsPerPage) use ($overdueItem, $dueSoonItem): MaintenanceSchedulePage {
        return match ($dueStatus) {
          'overdue' => new MaintenanceSchedulePage([$overdueItem], 1, $itemsPerPage, 1),
          'due_soon' => new MaintenanceSchedulePage([$dueSoonItem], 1, $itemsPerPage, 1),
          default => new MaintenanceSchedulePage([], 1, $itemsPerPage, 0),
        };
      },
    );

    $adapter = new MaintenanceAssistantContextProviderAdapter($this->createStub(OrganizationAuthorizationPort::class), $schedules);

    $fragment = $adapter->provide(self::ORG_ID, new AssistantContextScope(self::USER_ID, 'thread-1'), new AssistantContextBudget(4000));

    self::assertFalse($fragment->isEmpty());
    self::assertSame('maintenance.upcoming_due_dates', $fragment->sourceKey);
    self::assertStringContainsString('1 overdue, 1 due soon', $fragment->text);

    $overduePosition = mb_strpos($fragment->text, 'extinguisher');
    $dueSoonPosition = mb_strpos($fragment->text, 'smoke_detector');
    self::assertNotFalse($overduePosition);
    self::assertNotFalse($dueSoonPosition);
    self::assertLessThan($dueSoonPosition, $overduePosition, 'The earlier due date must be listed first regardless of due-status bucket.');
  }

  #[Test]
  public function testProvideDegradesToAnEmptyFragmentWhenTheRepositoryThrows(): void
  {
    $schedules = $this->createStub(MaintenanceScheduleRepositoryPort::class);
    $schedules->method('list')->willThrowException(new RuntimeException('boom'));

    $adapter = new MaintenanceAssistantContextProviderAdapter($this->createStub(OrganizationAuthorizationPort::class), $schedules);

    $fragment = $adapter->provide(self::ORG_ID, new AssistantContextScope(self::USER_ID, 'thread-1'), new AssistantContextBudget(4000));

    self::assertTrue($fragment->isEmpty());
  }

  private function scheduleView(string $equipmentType, string $dueStatus, ?DateTimeImmutable $nextDueAt): MaintenanceScheduleView
  {
    return new MaintenanceScheduleView(
      id: 'schedule-1',
      organizationId: self::ORG_ID,
      equipmentId: 'equipment-1',
      facilityId: null,
      equipmentType: $equipmentType,
      intervalOverride: null,
      lastInspectionClosedAt: null,
      nextDueAt: $nextDueAt,
      dueStatus: $dueStatus,
      lastRemindedAt: null,
      remindedFor: null,
      createdAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    );
  }
}
