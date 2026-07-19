<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\Service;

use DateTimeImmutable;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Port\Outbound\OrganizationRoleRepositoryPort;
use Organization\Application\Service\OrganizationPermissionGrantGuardService;
use Organization\Domain\Event\Security\OrganizationPermissionGrantDeniedEvent;
use Organization\Domain\Exception\OrganizationAccessDeniedException;
use Organization\Domain\Model\OrganizationRole\OrganizationRole;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationRoleId, OrganizationRoleName};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\EventDispatcherPort;

#[CoversClass(OrganizationPermissionGrantGuardService::class)]
final class OrganizationPermissionGrantGuardServiceTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440000';

  private const string ACTOR_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string ROLE_ID = '550e8400-e29b-41d4-a716-446655440010';

  #[Test]
  public function testGrantAllowedWhenActorHoldsEveryPermission(): void
  {
    $service = new OrganizationPermissionGrantGuardService(
      $this->authorization(true),
      $this->createStub(OrganizationRoleRepositoryPort::class),
      $this->neverDispatchingEventDispatcher(),
    );

    $service->assertCanGrantPermissions(self::ACTOR_ID, self::ORG_ID, ['organization.read', 'organization.members.read']);
  }

  #[Test]
  public function testGrantRejectedWhenActorLacksAPermission(): void
  {
    // Actor holds organization.read but not the elevated wildcard.
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')
      ->willReturnCallback(static fn (string $user, string $org, string $permission): bool => 'organization.read' === $permission);

    $service = new OrganizationPermissionGrantGuardService(
      $authorization,
      $this->createStub(OrganizationRoleRepositoryPort::class),
      $this->expectsDeniedEventDispatch('organization.*', 'grant_permissions'),
    );

    $this->expectException(OrganizationAccessDeniedException::class);

    $service->assertCanGrantPermissions(self::ACTOR_ID, self::ORG_ID, ['organization.read', 'organization.*']);
  }

  #[Test]
  public function testAssignRolesAllowedWhenActorHoldsRolePermissions(): void
  {
    $roleRepository = $this->createStub(OrganizationRoleRepositoryPort::class);
    $roleRepository->method('findById')->willReturn($this->role(['organization.read']));

    $service = new OrganizationPermissionGrantGuardService(
      $this->authorization(true),
      $roleRepository,
      $this->neverDispatchingEventDispatcher(),
    );

    $service->assertCanAssignRoles(self::ACTOR_ID, self::ORG_ID, [self::ROLE_ID]);
  }

  #[Test]
  public function testAssignRolesRejectedWhenRoleCarriesUnheldPermission(): void
  {
    $roleRepository = $this->createStub(OrganizationRoleRepositoryPort::class);
    $roleRepository->method('findById')->willReturn($this->role(['organization.*']));

    $service = new OrganizationPermissionGrantGuardService(
      $this->authorization(false),
      $roleRepository,
      $this->expectsDeniedEventDispatch('organization.*', 'assign_roles'),
    );

    $this->expectException(OrganizationAccessDeniedException::class);

    $service->assertCanAssignRoles(self::ACTOR_ID, self::ORG_ID, [self::ROLE_ID]);
  }

  #[Test]
  public function testAssignUnknownRoleIsSkipped(): void
  {
    $roleRepository = $this->createStub(OrganizationRoleRepositoryPort::class);
    $roleRepository->method('findById')->willReturn(null);

    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::never())->method('hasPermission');

    $service = new OrganizationPermissionGrantGuardService($authorization, $roleRepository, $this->neverDispatchingEventDispatcher());

    $service->assertCanAssignRoles(self::ACTOR_ID, self::ORG_ID, [self::ROLE_ID]);
  }

  #[Test]
  public function testAssignCrossOrganizationRoleIsSkipped(): void
  {
    $foreignRole = OrganizationRole::reconstitute(
      id: new OrganizationRoleId(self::ROLE_ID),
      organizationId: new OrganizationId('550e8400-e29b-41d4-a716-446655449999'),
      name: new OrganizationRoleName('owner'),
      permissions: ['organization.*'],
      isSystem: true,
      createdAt: new DateTimeImmutable('-1 day'),
    );
    $roleRepository = $this->createStub(OrganizationRoleRepositoryPort::class);
    $roleRepository->method('findById')->willReturn($foreignRole);

    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::never())->method('hasPermission');

    $service = new OrganizationPermissionGrantGuardService($authorization, $roleRepository, $this->neverDispatchingEventDispatcher());

    $service->assertCanAssignRoles(self::ACTOR_ID, self::ORG_ID, [self::ROLE_ID]);
  }

  private function authorization(bool $grants): OrganizationAuthorizationPort
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn($grants);

    return $authorization;
  }

  private function neverDispatchingEventDispatcher(): EventDispatcherPort
  {
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    return $eventDispatcher;
  }

  private function expectsDeniedEventDispatch(string $deniedPermission, string $context): EventDispatcherPort
  {
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(
        static fn (object $event): bool => $event instanceof OrganizationPermissionGrantDeniedEvent
          && self::ORG_ID === $event->organizationId
          && self::ACTOR_ID === $event->actorUserId
          && $deniedPermission === $event->deniedPermission
          && $context === $event->context,
      ));

    return $eventDispatcher;
  }

  /**
   * @param list<string> $permissions
   */
  private function role(array $permissions): OrganizationRole
  {
    return OrganizationRole::reconstitute(
      id: new OrganizationRoleId(self::ROLE_ID),
      organizationId: new OrganizationId(self::ORG_ID),
      name: new OrganizationRoleName('role'),
      permissions: $permissions,
      isSystem: false,
      createdAt: new DateTimeImmutable('-1 day'),
    );
  }
}
