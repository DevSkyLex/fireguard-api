<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\Service;

use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Outbound\{OrganizationMemberRepositoryPort, OrganizationRepositoryPort};
use Organization\Application\Service\OrganizationAuthorizationService;
use Organization\Domain\Exception\OrganizationAccessDeniedException;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationStatus};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Outbound\CachePort;
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

    $service = new OrganizationAuthorizationService($memberRepository, $this->activeOrganizationRepository());

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

    $service = new OrganizationAuthorizationService($memberRepository, $this->activeOrganizationRepository());

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

    $service = new OrganizationAuthorizationService($memberRepository, $this->activeOrganizationRepository());

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
  public function testGetUserPermissionsUsesSharedCacheBeforeRepository(): void
  {
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::never())->method('getPermissionNamesForUserInOrganization');

    $cache = $this->createMock(CachePort::class);
    $cache->expects(self::once())
      ->method('get')
      ->with('organization.permissions.550e8400-e29b-41d4-a716-446655440010.550e8400-e29b-41d4-a716-446655440001')
      ->willReturn(['organization.read']);
    $cache->expects(self::never())->method('set');

    $service = new OrganizationAuthorizationService($memberRepository, $this->activeOrganizationRepository(), $cache);

    self::assertSame(
      ['organization.read'],
      $service->getUserPermissions('550e8400-e29b-41d4-a716-446655440001', '550e8400-e29b-41d4-a716-446655440010'),
    );
  }

  #[Test]
  public function testGetUserPermissionsRefreshesStaleEmptySharedCache(): void
  {
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())
      ->method('getPermissionNamesForUserInOrganization')
      ->with(
        '550e8400-e29b-41d4-a716-446655440001',
        self::isInstanceOf(OrganizationId::class),
      )
      ->willReturn(['organization.*']);

    $cache = $this->createMock(CachePort::class);
    $cache->expects(self::once())
      ->method('get')
      ->with('organization.permissions.550e8400-e29b-41d4-a716-446655440010.550e8400-e29b-41d4-a716-446655440001')
      ->willReturn([]);
    $cache->expects(self::once())
      ->method('set')
      ->with(
        'organization.permissions.550e8400-e29b-41d4-a716-446655440010.550e8400-e29b-41d4-a716-446655440001',
        ['organization.*'],
        self::anything(),
      );

    $service = new OrganizationAuthorizationService($memberRepository, $this->activeOrganizationRepository(), $cache);

    self::assertSame(
      ['organization.*'],
      $service->getUserPermissions('550e8400-e29b-41d4-a716-446655440001', '550e8400-e29b-41d4-a716-446655440010'),
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

    $service = new OrganizationAuthorizationService($memberRepository, $this->activeOrganizationRepository());

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

    $service = new OrganizationAuthorizationService($memberRepository, $this->activeOrganizationRepository());

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

    $service = new OrganizationAuthorizationService($memberRepository, $this->activeOrganizationRepository());

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

    $service = new OrganizationAuthorizationService($memberRepository, $this->activeOrganizationRepository());

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

    $service = new OrganizationAuthorizationService($memberRepository, $this->activeOrganizationRepository());

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

    $service = new OrganizationAuthorizationService($memberRepository, $this->activeOrganizationRepository());

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

    $service = new OrganizationAuthorizationService($memberRepository, $this->activeOrganizationRepository());

    $this->expectException(OrganizationAccessDeniedException::class);
    $this->expectExceptionMessage('Missing organization.roles.manage permission.');

    $service->assertGrantedPermissions(
      userId: '550e8400-e29b-41d4-a716-446655440001',
      organizationId: '550e8400-e29b-41d4-a716-446655440010',
      permissions: ['organization.members.read', 'organization.roles.manage'],
    );
  }

  #[Test]
  public function testHasPermissionReturnsFalseForEmptyGrantedPattern(): void
  {
    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())
      ->method('getPermissionNamesForUserInOrganization')
      ->willReturn(['']);

    $service = new OrganizationAuthorizationService($memberRepository, $this->activeOrganizationRepository());

    self::assertFalse($service->hasPermission(
      userId: '550e8400-e29b-41d4-a716-446655440001',
      organizationId: '550e8400-e29b-41d4-a716-446655440010',
      permission: 'organization.read',
    ));
  }

  #[Test]
  public function testHasPermissionReturnsFalseForEmptyRequiredPermission(): void
  {
    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())
      ->method('getPermissionNamesForUserInOrganization')
      ->willReturn(['organization.read']);

    $service = new OrganizationAuthorizationService($memberRepository, $this->activeOrganizationRepository());

    self::assertFalse($service->hasPermission(
      userId: '550e8400-e29b-41d4-a716-446655440001',
      organizationId: '550e8400-e29b-41d4-a716-446655440010',
      permission: '',
    ));
  }

  #[Test]
  public function testHasPermissionReturnsTrueForGlobalWildcardPatterns(): void
  {
    foreach (['*', '*.*', '*.*.*'] as $wildcard) {
      /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
      $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
      $memberRepository->expects(self::once())
        ->method('getPermissionNamesForUserInOrganization')
        ->willReturn([$wildcard]);

      $service = new OrganizationAuthorizationService($memberRepository, $this->activeOrganizationRepository());

      self::assertTrue($service->hasPermission(
        userId: '550e8400-e29b-41d4-a716-446655440001',
        organizationId: '550e8400-e29b-41d4-a716-446655440010',
        permission: 'organization.members.manage',
      ));
    }
  }

  #[Test]
  public function testHasPermissionMatchesMidPatternWildcardSegment(): void
  {
    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())
      ->method('getPermissionNamesForUserInOrganization')
      ->willReturn(['organization.*.read']);

    $service = new OrganizationAuthorizationService($memberRepository, $this->activeOrganizationRepository());

    self::assertTrue($service->hasPermission(
      userId: '550e8400-e29b-41d4-a716-446655440001',
      organizationId: '550e8400-e29b-41d4-a716-446655440010',
      permission: 'organization.members.read',
    ));

    self::assertTrue($service->hasPermission(
      userId: '550e8400-e29b-41d4-a716-446655440001',
      organizationId: '550e8400-e29b-41d4-a716-446655440010',
      permission: 'organization.owners.read',
    ));
  }

  #[Test]
  public function testHasPermissionRejectsMidWildcardWhenRequiredIsShorter(): void
  {
    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())
      ->method('getPermissionNamesForUserInOrganization')
      ->willReturn(['organization.*.read']);

    $service = new OrganizationAuthorizationService($memberRepository, $this->activeOrganizationRepository());

    self::assertFalse($service->hasPermission(
      userId: '550e8400-e29b-41d4-a716-446655440001',
      organizationId: '550e8400-e29b-41d4-a716-446655440010',
      permission: 'organization',
    ));
  }

  #[Test]
  public function testHasPermissionRejectsWhenGrantedIsMoreSpecificThanRequired(): void
  {
    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())
      ->method('getPermissionNamesForUserInOrganization')
      ->willReturn(['organization.members.read']);

    $service = new OrganizationAuthorizationService($memberRepository, $this->activeOrganizationRepository());

    self::assertFalse($service->hasPermission(
      userId: '550e8400-e29b-41d4-a716-446655440001',
      organizationId: '550e8400-e29b-41d4-a716-446655440010',
      permission: 'organization.members',
    ));
  }

  #[Test]
  public function testHasPermissionRejectsWhenGrantedPrefixIsShorterThanRequired(): void
  {
    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())
      ->method('getPermissionNamesForUserInOrganization')
      ->willReturn(['organization.members']);

    $service = new OrganizationAuthorizationService($memberRepository, $this->activeOrganizationRepository());

    self::assertFalse($service->hasPermission(
      userId: '550e8400-e29b-41d4-a716-446655440001',
      organizationId: '550e8400-e29b-41d4-a716-446655440010',
      permission: 'organization.members.read',
    ));
  }

  #[Test]
  public function testEmptyResolvedPermissionsAreNotCachedLocally(): void
  {
    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::exactly(2))
      ->method('getPermissionNamesForUserInOrganization')
      ->willReturn([]);

    $service = new OrganizationAuthorizationService($memberRepository, $this->activeOrganizationRepository());

    self::assertFalse($service->hasPermission(
      userId: '550e8400-e29b-41d4-a716-446655440001',
      organizationId: '550e8400-e29b-41d4-a716-446655440010',
      permission: 'organization.read',
    ));

    self::assertFalse($service->hasPermission(
      userId: '550e8400-e29b-41d4-a716-446655440001',
      organizationId: '550e8400-e29b-41d4-a716-446655440010',
      permission: 'organization.read',
    ));
  }

  #[Test]
  public function testSharedCacheIsBypassedWhenTtlIsNotPositive(): void
  {
    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())
      ->method('getPermissionNamesForUserInOrganization')
      ->willReturn(['organization.read']);

    /** @var CachePort&MockObject $cache */
    $cache = $this->createMock(CachePort::class);
    $cache->expects(self::never())->method('get');
    $cache->expects(self::never())->method('set');

    $service = new OrganizationAuthorizationService($memberRepository, $this->activeOrganizationRepository(), $cache, 0);

    self::assertSame(
      ['organization.read'],
      $service->getUserPermissions('550e8400-e29b-41d4-a716-446655440001', '550e8400-e29b-41d4-a716-446655440010'),
    );
  }

  #[Test]
  public function testSharedCacheReadFailureFallsBackToRepository(): void
  {
    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())
      ->method('getPermissionNamesForUserInOrganization')
      ->willReturn(['organization.read']);

    /** @var CachePort&MockObject $cache */
    $cache = $this->createMock(CachePort::class);
    $cache->expects(self::once())
      ->method('get')
      ->willThrowException(new RuntimeException('cache down'));
    $cache->expects(self::once())->method('set');

    $service = new OrganizationAuthorizationService($memberRepository, $this->activeOrganizationRepository(), $cache);

    self::assertSame(
      ['organization.read'],
      $service->getUserPermissions('550e8400-e29b-41d4-a716-446655440001', '550e8400-e29b-41d4-a716-446655440010'),
    );
  }

  #[Test]
  public function testSharedCacheReadIgnoresNonArrayValues(): void
  {
    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())
      ->method('getPermissionNamesForUserInOrganization')
      ->willReturn(['organization.read']);

    /** @var CachePort&MockObject $cache */
    $cache = $this->createMock(CachePort::class);
    $cache->expects(self::once())
      ->method('get')
      ->willReturn('corrupted-entry');
    $cache->expects(self::once())->method('set');

    $service = new OrganizationAuthorizationService($memberRepository, $this->activeOrganizationRepository(), $cache);

    self::assertSame(
      ['organization.read'],
      $service->getUserPermissions('550e8400-e29b-41d4-a716-446655440001', '550e8400-e29b-41d4-a716-446655440010'),
    );
  }

  #[Test]
  public function testSharedCacheReadFiltersNonStringEntries(): void
  {
    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::never())->method('getPermissionNamesForUserInOrganization');

    /** @var CachePort&MockObject $cache */
    $cache = $this->createMock(CachePort::class);
    $cache->expects(self::once())
      ->method('get')
      ->willReturn(['organization.read', 42, 'organization.members.read', null]);
    $cache->expects(self::never())->method('set');

    $service = new OrganizationAuthorizationService($memberRepository, $this->activeOrganizationRepository(), $cache);

    self::assertSame(
      ['organization.read', 'organization.members.read'],
      $service->getUserPermissions('550e8400-e29b-41d4-a716-446655440001', '550e8400-e29b-41d4-a716-446655440010'),
    );
  }

  #[Test]
  public function testSharedCacheWriteFailureIsSwallowed(): void
  {
    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())
      ->method('getPermissionNamesForUserInOrganization')
      ->willReturn(['organization.read']);

    /** @var CachePort&MockObject $cache */
    $cache = $this->createMock(CachePort::class);
    $cache->expects(self::once())
      ->method('get')
      ->willReturn(null);
    $cache->expects(self::once())
      ->method('set')
      ->willThrowException(new RuntimeException('cache write failed'));

    $service = new OrganizationAuthorizationService($memberRepository, $this->activeOrganizationRepository(), $cache);

    self::assertSame(
      ['organization.read'],
      $service->getUserPermissions('550e8400-e29b-41d4-a716-446655440001', '550e8400-e29b-41d4-a716-446655440010'),
    );
  }

  #[Test]
  public function testResolveAccessGrantsWithoutEverQueryingMembership(): void
  {
    // The whole point of resolving scope lazily: an authorized request must
    // cost exactly what hasPermission() costs. A granted permission already
    // proves an active membership, since permissions are resolved through
    // that same membership row.
    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())
      ->method('getPermissionNamesForUserInOrganization')
      ->willReturn(['organization.read']);
    $memberRepository->expects(self::never())->method('hasActiveMembership');

    $service = new OrganizationAuthorizationService($memberRepository, $this->activeOrganizationRepository());

    self::assertSame(
      OrganizationAccessDecision::GRANTED,
      $service->resolveAccess(
        userId: '550e8400-e29b-41d4-a716-446655440001',
        organizationId: '550e8400-e29b-41d4-a716-446655440010',
        permission: 'organization.read',
      ),
    );
  }

  #[Test]
  public function testResolveAccessReportsMissingPermissionForAnActiveMember(): void
  {
    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->method('getPermissionNamesForUserInOrganization')->willReturn(['organization.members.read']);
    $memberRepository->expects(self::once())
      ->method('hasActiveMembership')
      ->with(
        self::callback(static fn (OrganizationId $id): bool => '550e8400-e29b-41d4-a716-446655440010' === (string) $id),
        '550e8400-e29b-41d4-a716-446655440001',
      )
      ->willReturn(true);

    $service = new OrganizationAuthorizationService($memberRepository, $this->activeOrganizationRepository());

    self::assertSame(
      OrganizationAccessDecision::MISSING_PERMISSION,
      $service->resolveAccess(
        userId: '550e8400-e29b-41d4-a716-446655440001',
        organizationId: '550e8400-e29b-41d4-a716-446655440010',
        permission: 'organization.roles.manage',
      ),
    );
  }

  #[Test]
  public function testResolveAccessReportsOutsideScopeForANonMember(): void
  {
    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->method('getPermissionNamesForUserInOrganization')->willReturn([]);
    $memberRepository->expects(self::once())->method('hasActiveMembership')->willReturn(false);

    $service = new OrganizationAuthorizationService($memberRepository, $this->activeOrganizationRepository());

    self::assertSame(
      OrganizationAccessDecision::OUTSIDE_SCOPE,
      $service->resolveAccess(
        userId: '550e8400-e29b-41d4-a716-446655440001',
        organizationId: '550e8400-e29b-41d4-a716-446655440010',
        permission: 'organization.read',
      ),
    );
  }

  #[Test]
  public function testResolveAccessSeparatesAMemberWithNoPermissionsFromANonMember(): void
  {
    // Both resolve to an EMPTY permission list, which is exactly why an empty
    // list cannot stand in for the membership check: an active member holding
    // a role that grants nothing looks identical to a stranger until
    // hasActiveMembership() is asked.
    $memberRepository = self::createStub(OrganizationMemberRepositoryPort::class);
    $memberRepository->method('getPermissionNamesForUserInOrganization')->willReturn([]);
    $memberRepository->method('hasActiveMembership')->willReturn(true);

    $service = new OrganizationAuthorizationService($memberRepository, $this->activeOrganizationRepository());

    self::assertSame(
      OrganizationAccessDecision::MISSING_PERMISSION,
      $service->resolveAccess(
        userId: '550e8400-e29b-41d4-a716-446655440001',
        organizationId: '550e8400-e29b-41d4-a716-446655440010',
        permission: 'organization.read',
      ),
    );
  }

  #[Test]
  public function testMembershipLookupIsMemoizedPerRequestAndClearedByReset(): void
  {
    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->method('getPermissionNamesForUserInOrganization')->willReturn([]);
    $memberRepository->expects(self::exactly(2))->method('hasActiveMembership')->willReturn(false);

    $service = new OrganizationAuthorizationService($memberRepository, $this->activeOrganizationRepository());

    // Two denials in the same request share one membership query...
    $service->resolveAccess('550e8400-e29b-41d4-a716-446655440001', '550e8400-e29b-41d4-a716-446655440010', 'organization.read');
    $service->resolveAccess('550e8400-e29b-41d4-a716-446655440001', '550e8400-e29b-41d4-a716-446655440010', 'organization.members.read');

    // ...and reset() must drop it, or a long-running worker would keep
    // answering from a membership that has since changed.
    $service->reset();
    $service->resolveAccess('550e8400-e29b-41d4-a716-446655440001', '550e8400-e29b-41d4-a716-446655440010', 'organization.read');
  }

  #[Test]
  public function testIsMemberOfReportsActiveMembershipWithoutResolvingPermissions(): void
  {
    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::never())->method('getPermissionNamesForUserInOrganization');
    $memberRepository->expects(self::once())->method('hasActiveMembership')->willReturn(true);

    $service = new OrganizationAuthorizationService($memberRepository, $this->activeOrganizationRepository());

    self::assertTrue($service->isMemberOf(
      userId: '550e8400-e29b-41d4-a716-446655440001',
      organizationId: '550e8400-e29b-41d4-a716-446655440010',
    ));
  }

  #[Test]
  public function testSuspendedOrganizationRefusesAWriteEvenToAWildcardHolder(): void
  {
    $memberRepository = $this->createStub(OrganizationMemberRepositoryPort::class);
    $memberRepository->method('getPermissionNamesForUserInOrganization')->willReturn(['organization.*']);

    $service = new OrganizationAuthorizationService(
      $memberRepository,
      $this->organizationRepositoryWithStatus(OrganizationStatus::SUSPENDED),
    );

    self::assertFalse($service->hasPermission(
      userId: '550e8400-e29b-41d4-a716-446655440001',
      organizationId: '550e8400-e29b-41d4-a716-446655440010',
      permission: 'organization.facilities.write',
    ));
  }

  #[Test]
  public function testSuspendedOrganizationStillAllowsReads(): void
  {
    $memberRepository = $this->createStub(OrganizationMemberRepositoryPort::class);
    $memberRepository->method('getPermissionNamesForUserInOrganization')->willReturn(['organization.*']);

    $service = new OrganizationAuthorizationService(
      $memberRepository,
      $this->organizationRepositoryWithStatus(OrganizationStatus::SUSPENDED),
    );

    self::assertTrue($service->hasPermission(
      userId: '550e8400-e29b-41d4-a716-446655440001',
      organizationId: '550e8400-e29b-41d4-a716-446655440010',
      permission: 'organization.facilities.read',
    ));
  }

  #[Test]
  public function testSuspendedOrganizationStillAllowsTheRestoreEscapeHatch(): void
  {
    // Without this, a suspended organization walls itself in: RestoreOrganization
    // requires exactly this permission and there is no platform-level bypass.
    $memberRepository = $this->createStub(OrganizationMemberRepositoryPort::class);
    $memberRepository->method('getPermissionNamesForUserInOrganization')->willReturn(['organization.settings.write']);

    $service = new OrganizationAuthorizationService(
      $memberRepository,
      $this->organizationRepositoryWithStatus(OrganizationStatus::SUSPENDED),
    );

    self::assertTrue($service->hasPermission(
      userId: '550e8400-e29b-41d4-a716-446655440001',
      organizationId: '550e8400-e29b-41d4-a716-446655440010',
      permission: 'organization.settings.write',
    ));
  }

  #[Test]
  public function testSuspendedOrganizationRefusalNamesSuspensionNotAMissingPermission(): void
  {
    $memberRepository = $this->createStub(OrganizationMemberRepositoryPort::class);
    $memberRepository->method('getPermissionNamesForUserInOrganization')->willReturn(['organization.*']);

    $service = new OrganizationAuthorizationService(
      $memberRepository,
      $this->organizationRepositoryWithStatus(OrganizationStatus::SUSPENDED),
    );

    $this->expectException(OrganizationAccessDeniedException::class);
    $this->expectExceptionMessage('suspended');

    $service->assertGrantedPermissions(
      '550e8400-e29b-41d4-a716-446655440001',
      '550e8400-e29b-41d4-a716-446655440010',
      ['organization.facilities.write'],
    );
  }

  #[Test]
  public function testArchivedOrganizationRefusesWritesLikeASuspendedOne(): void
  {
    $memberRepository = $this->createStub(OrganizationMemberRepositoryPort::class);
    $memberRepository->method('getPermissionNamesForUserInOrganization')->willReturn(['organization.*']);

    $service = new OrganizationAuthorizationService(
      $memberRepository,
      $this->organizationRepositoryWithStatus(OrganizationStatus::ARCHIVED),
    );

    self::assertFalse($service->hasPermission(
      userId: '550e8400-e29b-41d4-a716-446655440001',
      organizationId: '550e8400-e29b-41d4-a716-446655440010',
      permission: 'organization.facilities.write',
    ));
  }

  #[Test]
  public function testArchivedOrganizationKeepsTheEscapeHatchPermission(): void
  {
    // Not because archiving is reversible from inside — it is not — but because
    // `organization.settings.write` also gates suspend, update-settings,
    // remove-logo, transfer-ownership and reactivate-member, five operations
    // that already answer 409 naming the archived state. Withholding it here
    // would flatten all five into a 403. The platform-only rule for reopening
    // lives in RestoreOrganizationProcessor.
    $memberRepository = $this->createStub(OrganizationMemberRepositoryPort::class);
    $memberRepository->method('getPermissionNamesForUserInOrganization')->willReturn(['organization.*']);

    $service = new OrganizationAuthorizationService(
      $memberRepository,
      $this->organizationRepositoryWithStatus(OrganizationStatus::ARCHIVED),
    );

    self::assertTrue($service->hasPermission(
      userId: '550e8400-e29b-41d4-a716-446655440001',
      organizationId: '550e8400-e29b-41d4-a716-446655440010',
      permission: 'organization.settings.write',
    ));
  }

  #[Test]
  public function testArchivedOrganizationStillAllowsReads(): void
  {
    $memberRepository = $this->createStub(OrganizationMemberRepositoryPort::class);
    $memberRepository->method('getPermissionNamesForUserInOrganization')->willReturn(['organization.*']);

    $service = new OrganizationAuthorizationService(
      $memberRepository,
      $this->organizationRepositoryWithStatus(OrganizationStatus::ARCHIVED),
    );

    self::assertTrue($service->hasPermission(
      userId: '550e8400-e29b-41d4-a716-446655440001',
      organizationId: '550e8400-e29b-41d4-a716-446655440010',
      permission: 'organization.facilities.read',
    ));
  }

  #[Test]
  public function testArchivedRefusalDoesNotAdviseARestoreTheCallerCannotPerform(): void
  {
    $memberRepository = $this->createStub(OrganizationMemberRepositoryPort::class);
    $memberRepository->method('getPermissionNamesForUserInOrganization')->willReturn(['organization.*']);

    $service = new OrganizationAuthorizationService(
      $memberRepository,
      $this->organizationRepositoryWithStatus(OrganizationStatus::ARCHIVED),
    );

    $this->expectException(OrganizationAccessDeniedException::class);
    $this->expectExceptionMessage('platform administrator');

    $service->assertGrantedPermissions(
      '550e8400-e29b-41d4-a716-446655440001',
      '550e8400-e29b-41d4-a716-446655440010',
      ['organization.facilities.write'],
    );
  }

  #[Test]
  public function testAnUnreadableStatusDoesNotDenyAccess(): void
  {
    // Failing closed here would lock every member out of an organization that
    // was never suspended, on nothing worse than a database blip.
    $memberRepository = $this->createStub(OrganizationMemberRepositoryPort::class);
    $memberRepository->method('getPermissionNamesForUserInOrganization')->willReturn(['organization.*']);

    $organizationRepository = $this->createStub(OrganizationRepositoryPort::class);
    $organizationRepository->method('statusOf')->willThrowException(new RuntimeException('database down'));

    $service = new OrganizationAuthorizationService($memberRepository, $organizationRepository);

    self::assertTrue($service->hasPermission(
      userId: '550e8400-e29b-41d4-a716-446655440001',
      organizationId: '550e8400-e29b-41d4-a716-446655440010',
      permission: 'organization.facilities.write',
    ));
  }

  #[Test]
  public function testStatusIsReadOncePerRequestAndClearedByReset(): void
  {
    $memberRepository = $this->createStub(OrganizationMemberRepositoryPort::class);
    $memberRepository->method('getPermissionNamesForUserInOrganization')->willReturn(['organization.*']);

    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::exactly(2))
      ->method('statusOf')
      ->willReturn(OrganizationStatus::ACTIVE);

    $service = new OrganizationAuthorizationService($memberRepository, $organizationRepository);

    $service->hasPermission('550e8400-e29b-41d4-a716-446655440001', '550e8400-e29b-41d4-a716-446655440010', 'organization.facilities.write');
    $service->hasPermission('550e8400-e29b-41d4-a716-446655440001', '550e8400-e29b-41d4-a716-446655440010', 'organization.equipment.write');

    $service->reset();

    $service->hasPermission('550e8400-e29b-41d4-a716-446655440001', '550e8400-e29b-41d4-a716-446655440010', 'organization.facilities.write');
  }

  private function activeOrganizationRepository(): OrganizationRepositoryPort
  {
    return $this->organizationRepositoryWithStatus(OrganizationStatus::ACTIVE);
  }

  private function organizationRepositoryWithStatus(OrganizationStatus $status): OrganizationRepositoryPort
  {
    $repository = $this->createStub(OrganizationRepositoryPort::class);
    $repository->method('statusOf')->willReturn($status);

    return $repository;
  }
}
