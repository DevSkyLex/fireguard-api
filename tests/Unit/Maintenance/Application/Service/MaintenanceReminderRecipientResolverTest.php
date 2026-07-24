<?php

declare(strict_types=1);

namespace Tests\Unit\Maintenance\Application\Service;

use DateTimeImmutable;
use Maintenance\Application\Service\MaintenanceReminderRecipientResolver;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Port\Outbound\OrganizationMemberRepositoryPort;
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test MaintenanceReminderRecipientResolverTest.
 *
 * @category Service Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MaintenanceReminderRecipientResolver::class)]
final class MaintenanceReminderRecipientResolverTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  #[Test]
  public function testOrganizationAdministratorsReturnsActiveMembersGrantingMaintenanceManage(): void
  {
    $admin = $this->member('member-admin', 'user-admin', true);
    $inactiveAdmin = $this->member('member-inactive', 'user-inactive', false);
    $regularMember = $this->member('member-regular', 'user-regular', true);
    $wildcardAdmin = $this->member('member-wildcard', 'user-wildcard', true);

    $members = $this->createStub(OrganizationMemberRepositoryPort::class);
    $members->method('findByOrganizationId')->willReturn([$admin, $inactiveAdmin, $regularMember, $wildcardAdmin]);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('getUserPermissions')->willReturnMap([
      ['user-admin', self::ORG_ID, ['organization.maintenance.manage']],
      ['user-inactive', self::ORG_ID, ['organization.maintenance.manage']],
      ['user-regular', self::ORG_ID, ['organization.maintenance.read']],
      ['user-wildcard', self::ORG_ID, ['organization.*']],
    ]);

    $resolver = new MaintenanceReminderRecipientResolver($members, $authorization);

    self::assertSame(
      ['user-admin', 'user-wildcard'],
      $resolver->organizationAdministrators(self::ORG_ID),
    );
  }

  #[Test]
  public function testOrganizationAdministratorsReturnsEmptyListWhenNoneQualify(): void
  {
    $members = $this->createStub(OrganizationMemberRepositoryPort::class);
    $members->method('findByOrganizationId')->willReturn([$this->member('member-1', 'user-1', true)]);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('getUserPermissions')->willReturn(['organization.maintenance.read']);

    $resolver = new MaintenanceReminderRecipientResolver($members, $authorization);

    self::assertSame([], $resolver->organizationAdministrators(self::ORG_ID));
  }

  private function member(string $memberId, string $userId, bool $isActive): OrganizationMember
  {
    return OrganizationMember::reconstitute(
      OrganizationMemberId::fromString($this->uuidFor($memberId)),
      OrganizationId::fromString(self::ORG_ID),
      $userId,
      $isActive,
      new DateTimeImmutable(),
    );
  }

  private function uuidFor(string $seed): string
  {
    return match ($seed) {
      'member-admin' => '550e8400-e29b-41d4-a716-446655440011',
      'member-inactive' => '550e8400-e29b-41d4-a716-446655440012',
      'member-regular' => '550e8400-e29b-41d4-a716-446655440013',
      'member-wildcard' => '550e8400-e29b-41d4-a716-446655440014',
      default => '550e8400-e29b-41d4-a716-446655440015',
    };
  }
}
