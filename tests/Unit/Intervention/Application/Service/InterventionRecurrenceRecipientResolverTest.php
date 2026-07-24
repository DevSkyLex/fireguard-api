<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Application\Service;

use DateTimeImmutable;
use Intervention\Application\Service\InterventionRecurrenceRecipientResolver;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Port\Outbound\OrganizationMemberRepositoryPort;
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test InterventionRecurrenceRecipientResolver.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InterventionRecurrenceRecipientResolver::class)]
final class InterventionRecurrenceRecipientResolverTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  #[Test]
  public function testOrganizationAdministratorsReturnsActiveMembersGrantingThePlanPermission(): void
  {
    $admin = $this->member('550e8400-e29b-41d4-a716-446655440011', 'user-admin', true);
    $inactiveAdmin = $this->member('550e8400-e29b-41d4-a716-446655440012', 'user-inactive', false);
    $regularMember = $this->member('550e8400-e29b-41d4-a716-446655440013', 'user-regular', true);
    $wildcardAdmin = $this->member('550e8400-e29b-41d4-a716-446655440014', 'user-wildcard', true);

    $members = $this->createStub(OrganizationMemberRepositoryPort::class);
    $members->method('findByOrganizationId')->willReturn([$admin, $inactiveAdmin, $regularMember, $wildcardAdmin]);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('getUserPermissions')->willReturnMap([
      ['user-admin', self::ORG_ID, ['organization.interventions.plan']],
      ['user-inactive', self::ORG_ID, ['organization.interventions.plan']],
      ['user-regular', self::ORG_ID, ['organization.interventions.read']],
      ['user-wildcard', self::ORG_ID, ['organization.*']],
    ]);

    $resolver = new InterventionRecurrenceRecipientResolver($members, $authorization);

    self::assertSame(['user-admin', 'user-wildcard'], $resolver->organizationAdministrators(self::ORG_ID));
  }

  #[Test]
  public function testOrganizationAdministratorsDeduplicatesRepeatedUserIds(): void
  {
    $first = $this->member('550e8400-e29b-41d4-a716-446655440021', 'user-dup', true);
    $second = $this->member('550e8400-e29b-41d4-a716-446655440022', 'user-dup', true);

    $members = $this->createStub(OrganizationMemberRepositoryPort::class);
    $members->method('findByOrganizationId')->willReturn([$first, $second]);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('getUserPermissions')->willReturn(['*']);

    $resolver = new InterventionRecurrenceRecipientResolver($members, $authorization);

    self::assertSame(['user-dup'], $resolver->organizationAdministrators(self::ORG_ID));
  }

  #[Test]
  public function testOrganizationAdministratorsReturnsEmptyListWhenNoneQualify(): void
  {
    $members = $this->createStub(OrganizationMemberRepositoryPort::class);
    $members->method('findByOrganizationId')->willReturn([
      $this->member('550e8400-e29b-41d4-a716-446655440031', 'user-1', true),
    ]);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('getUserPermissions')->willReturn(['organization.interventions.read']);

    $resolver = new InterventionRecurrenceRecipientResolver($members, $authorization);

    self::assertSame([], $resolver->organizationAdministrators(self::ORG_ID));
  }

  private function member(string $memberId, string $userId, bool $isActive): OrganizationMember
  {
    return OrganizationMember::reconstitute(
      OrganizationMemberId::fromString($memberId),
      OrganizationId::fromString(self::ORG_ID),
      $userId,
      $isActive,
      new DateTimeImmutable(),
    );
  }
}
