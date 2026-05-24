<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\Service;

use Organization\Application\Port\Outbound\OrganizationMemberRepositoryPort;
use Organization\Application\Service\OrganizationAuthorizationService;
use Organization\Domain\Exception\OrganizationAccessDeniedException;
use Organization\Domain\ValueObject\OrganizationId;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Service\ResetInterface;

#[CoversClass(OrganizationAuthorizationService::class)]
final class OrganizationAuthorizationServiceTest extends TestCase
{
  #[Test]
  public function testHasPermissionReturnsTrueForExactMatch(): void
  {
    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())
      ->method('getPermissionNamesForUserInOrganization')
      ->with(
        '550e8400-e29b-41d4-a716-446655440001',
        self::isInstanceOf(OrganizationId::class),
      )
      ->willReturn(['organization.read']);

    $service = new OrganizationAuthorizationService($memberRepository);

    self::assertTrue($service->hasPermission(
      userId: '550e8400-e29b-41d4-a716-446655440001',
      organizationId: '550e8400-e29b-41d4-a716-446655440010',
      permission: 'organization.read',
    ));
  }

  #[Test]
  public function testHasPermissionReturnsTrueForWildcardMatch(): void
  {
    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())
      ->method('getPermissionNamesForUserInOrganization')
      ->willReturn(['organization.*']);

    $service = new OrganizationAuthorizationService($memberRepository);

    self::assertTrue($service->hasPermission(
      userId: '550e8400-e29b-41d4-a716-446655440001',
      organizationId: '550e8400-e29b-41d4-a716-446655440010',
      permission: 'organization.roles.manage',
    ));

    self::assertTrue($service->hasPermission(
      userId: '550e8400-e29b-41d4-a716-446655440001',
      organizationId: '550e8400-e29b-41d4-a716-446655440010',
      permission: 'organization.members.read',
    ));
  }

  #[Test]
  public function testPermissionLookupIsCachedPerUserAndOrganization(): void
  {
    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())
      ->method('getPermissionNamesForUserInOrganization')
      ->willReturn(['organization.dashboard.read', 'organization.members.read']);

    $service = new OrganizationAuthorizationService($memberRepository);

    self::assertTrue($service->hasPermission(
      userId: '550e8400-e29b-41d4-a716-446655440001',
      organizationId: '550e8400-e29b-41d4-a716-446655440010',
      permission: 'organization.dashboard.read',
    ));

    $service->assertGrantedPermissions(
      userId: '550e8400-e29b-41d4-a716-446655440001',
      organizationId: '550e8400-e29b-41d4-a716-446655440010',
      permissions: ['organization.members.read'],
    );
  }

  #[Test]
  public function testResetClearsPermissionCache(): void
  {
    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::exactly(2))
      ->method('getPermissionNamesForUserInOrganization')
      ->willReturnOnConsecutiveCalls(
        ['organization.read'],
        ['organization.manage'],
      );

    $service = new OrganizationAuthorizationService($memberRepository);

    self::assertInstanceOf(ResetInterface::class, $service);
    self::assertTrue($service->hasPermission(
      userId: '550e8400-e29b-41d4-a716-446655440001',
      organizationId: '550e8400-e29b-41d4-a716-446655440010',
      permission: 'organization.read',
    ));

    $service->reset();

    self::assertTrue($service->hasPermission(
      userId: '550e8400-e29b-41d4-a716-446655440001',
      organizationId: '550e8400-e29b-41d4-a716-446655440010',
      permission: 'organization.manage',
    ));
  }

  #[Test]
  public function testHasPermissionReturnsFalseWhenNoPermissionMatches(): void
  {
    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())
      ->method('getPermissionNamesForUserInOrganization')
      ->willReturn(['organization.members.read']);

    $service = new OrganizationAuthorizationService($memberRepository);

    self::assertFalse($service->hasPermission(
      userId: '550e8400-e29b-41d4-a716-446655440001',
      organizationId: '550e8400-e29b-41d4-a716-446655440010',
      permission: 'organization.roles.manage',
    ));
  }

  #[Test]
  public function testHasPermissionDoesNotEscalateReadToManageWithinSameResource(): void
  {
    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())
      ->method('getPermissionNamesForUserInOrganization')
      ->willReturn(['organization.members.read']);

    $service = new OrganizationAuthorizationService($memberRepository);

    self::assertFalse($service->hasPermission(
      userId: '550e8400-e29b-41d4-a716-446655440001',
      organizationId: '550e8400-e29b-41d4-a716-446655440010',
      permission: 'organization.members.manage',
    ));
  }

  #[Test]
  public function testGetUserPermissionsReturnsRepositoryValues(): void
  {
    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())
      ->method('getPermissionNamesForUserInOrganization')
      ->with(
        '550e8400-e29b-41d4-a716-446655440001',
        self::callback(static fn (OrganizationId $id): bool => '550e8400-e29b-41d4-a716-446655440010' === (string) $id),
      )
      ->willReturn(['organization.read', 'organization.members.read']);

    $service = new OrganizationAuthorizationService($memberRepository);

    self::assertSame(
      ['organization.read', 'organization.members.read'],
      $service->getUserPermissions('550e8400-e29b-41d4-a716-446655440001', '550e8400-e29b-41d4-a716-446655440010'),
    );
  }

  #[Test]
  public function testAssertGrantedPermissionsAcceptsWildcardPermissionsInSingleRepositoryLookup(): void
  {
    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())
      ->method('getPermissionNamesForUserInOrganization')
      ->with(
        '550e8400-e29b-41d4-a716-446655440001',
        self::callback(static fn (OrganizationId $id): bool => '550e8400-e29b-41d4-a716-446655440010' === (string) $id),
      )
      ->willReturn(['organization.*']);

    $service = new OrganizationAuthorizationService($memberRepository);

    $service->assertGrantedPermissions(
      userId: '550e8400-e29b-41d4-a716-446655440001',
      organizationId: '550e8400-e29b-41d4-a716-446655440010',
      permissions: ['organization.roles.manage', 'organization.members.read'],
    );
  }

  #[Test]
  public function testAssertGrantedPermissionsThrowsOnFirstMissingPermission(): void
  {
    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())
      ->method('getPermissionNamesForUserInOrganization')
      ->with(
        '550e8400-e29b-41d4-a716-446655440001',
        self::callback(static fn (OrganizationId $id): bool => '550e8400-e29b-41d4-a716-446655440010' === (string) $id),
      )
      ->willReturn(['organization.members.read']);

    $service = new OrganizationAuthorizationService($memberRepository);

    $this->expectException(OrganizationAccessDeniedException::class);
    $this->expectExceptionMessage('Missing organization.roles.manage permission.');

    $service->assertGrantedPermissions(
      userId: '550e8400-e29b-41d4-a716-446655440001',
      organizationId: '550e8400-e29b-41d4-a716-446655440010',
      permissions: ['organization.roles.manage', 'organization.members.read'],
    );
  }

  #[Test]
  public function testAssertGrantedPermissionsThrowsWhenLaterPermissionIsMissing(): void
  {
    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())
      ->method('getPermissionNamesForUserInOrganization')
      ->with(
        '550e8400-e29b-41d4-a716-446655440001',
        self::callback(static fn (OrganizationId $id): bool => '550e8400-e29b-41d4-a716-446655440010' === (string) $id),
      )
      ->willReturn(['organization.members.read']);

    $service = new OrganizationAuthorizationService($memberRepository);

    $this->expectException(OrganizationAccessDeniedException::class);
    $this->expectExceptionMessage('Missing organization.roles.manage permission.');

    $service->assertGrantedPermissions(
      userId: '550e8400-e29b-41d4-a716-446655440001',
      organizationId: '550e8400-e29b-41d4-a716-446655440010',
      permissions: ['organization.members.read', 'organization.roles.manage'],
    );
  }
}
