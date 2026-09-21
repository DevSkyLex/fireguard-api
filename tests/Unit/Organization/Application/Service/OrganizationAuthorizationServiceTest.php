<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\Service;

use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Outbound\{OrganizationMemberRepositoryPort, OrganizationRepositoryPort};
use Organization\Application\Service\{OrganizationAuthorizationService, OrganizationCacheInvalidator};
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
  public function testMembershipInvalidationClearsPermissionsAndDenialMemoEvenWhenLegacyCacheFails(): void
  {
    $members = $this->createMock(OrganizationMemberRepositoryPort::class);
    $members->expects(self::exactly(2))->method('getPermissionNamesForUserInOrganization')
      ->willReturnOnConsecutiveCalls(['organization.read'], []);
    $members->expects(self::exactly(2))->method('hasActiveMembership')->willReturnOnConsecutiveCalls(true, false);
    $cache = $this->createMock(CachePort::class);
    $cache->expects(self::never())->method('get');
    $cache->expects(self::exactly(2))->method('delete')->willThrowException(new RuntimeException('offline'));
    $invalidator = new OrganizationCacheInvalidator($cache);
    $service = new OrganizationAuthorizationService($members, $this->activeOrganizationRepository(), $invalidator);
    $user = '550e8400-e29b-41d4-a716-446655440001';
    $org = '550e8400-e29b-41d4-a716-446655440010';
    self::assertTrue($service->hasPermission($user, $org, 'organization.read'));
    self::assertTrue($service->isMemberOf($user, $org));
    $invalidator->invalidateCurrentMemberProfile($org, $user);
    self::assertSame(OrganizationAccessDecision::OUTSIDE_SCOPE, $service->resolveAccess($user, $org, 'organization.read'));
  }

  #[Test]
  public function testSuspensionInvalidatesStatusWithinTheRequest(): void
  {
    $members = $this->createStub(OrganizationMemberRepositoryPort::class);
    $members->method('getPermissionNamesForUserInOrganization')->willReturn(['organization.*']);
    $organizations = $this->createMock(OrganizationRepositoryPort::class);
    $organizations->expects(self::exactly(2))->method('statusOf')
      ->willReturnOnConsecutiveCalls(OrganizationStatus::ACTIVE, OrganizationStatus::SUSPENDED);
    $invalidator = new OrganizationCacheInvalidator($this->createStub(CachePort::class));
    $service = new OrganizationAuthorizationService($members, $organizations, $invalidator);
    $user = '550e8400-e29b-41d4-a716-446655440001';
    $org = '550e8400-e29b-41d4-a716-446655440010';
    self::assertTrue($service->hasPermission($user, $org, 'organization.members.manage'));
    $invalidator->invalidateOrganization();
    self::assertFalse($service->hasPermission($user, $org, 'organization.members.manage'));
  }

  #[Test]
  public function testUnavailableStatusNeverGrantsAWrite(): void
  {
    $organizations = $this->createStub(OrganizationRepositoryPort::class);
    $organizations->method('statusOf')->willThrowException(new RuntimeException('database unavailable'));
    $service = new OrganizationAuthorizationService($this->createStub(OrganizationMemberRepositoryPort::class), $organizations);
    $this->expectException(RuntimeException::class);
    $service->hasPermission('550e8400-e29b-41d4-a716-446655440001', '550e8400-e29b-41d4-a716-446655440010', 'organization.members.manage');
  }

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
