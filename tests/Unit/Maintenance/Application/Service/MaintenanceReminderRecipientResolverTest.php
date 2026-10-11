<?php

declare(strict_types=1);

namespace Tests\Unit\Maintenance\Application\Service;

use Maintenance\Application\Service\MaintenanceReminderRecipientResolver;
use Organization\Application\Port\Inbound\OrganizationMemberNotificationDirectoryPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Class MaintenanceReminderRecipientResolverTest
 *
 * Verifies one owner-authorized batch lookup for reminder recipients.
 *
 * @category Tests
 */
#[CoversClass(MaintenanceReminderRecipientResolver::class)]
final class MaintenanceReminderRecipientResolverTest extends TestCase
{
  #[Test]
  public function testItResolvesMaintenanceAdministratorsInOneOwnerDirectoryCall(): void
  {
    $directory = $this->createMock(OrganizationMemberNotificationDirectoryPort::class);
    $directory->expects(self::once())->method('administratorUserIds')
      ->with('organization-id', 'organization.maintenance.manage')
      ->willReturn(['admin-id', 'wildcard-admin-id']);

    self::assertSame(['admin-id', 'wildcard-admin-id'], new MaintenanceReminderRecipientResolver($directory)->organizationAdministrators('organization-id'));
  }

  #[Test]
  public function testAnEmptyAuthorizedDirectoryProducesNoRecipients(): void
  {
    $directory = $this->createMock(OrganizationMemberNotificationDirectoryPort::class);
    $directory->expects(self::once())->method('administratorUserIds')->willReturn([]);

    self::assertSame([], new MaintenanceReminderRecipientResolver($directory)->organizationAdministrators('organization-id'));
  }
}
