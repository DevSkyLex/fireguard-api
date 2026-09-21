<?php

declare(strict_types=1);

namespace Tests\Integration\Organization;

use DateTimeImmutable;
use Organization\Application\Port\Outbound\{OrganizationMemberRepositoryPort, OrganizationRepositoryPort, OrganizationRoleRepositoryPort};
use Organization\Application\Service\{OrganizationAuthorizationService, OrganizationCacheInvalidator};
use Organization\Application\UseCase\Query\Organization\GetCurrentOrganizationMemberProfile\{GetCurrentOrganizationMemberProfileHandler, GetCurrentOrganizationMemberProfileQuery};
use Organization\Domain\Exception\OrganizationMemberNotFoundException;
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\Model\OrganizationRole\OrganizationRole;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId, OrganizationName, OrganizationRoleId, OrganizationRoleName};
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Verifies container-wired request cache invalidation against PostgreSQL.
 *
 * @category Test
 *
 * @version 1.0.0
 */
final class OrganizationAccessFreshnessTest extends KernelTestCase
{
  #[Test]
  public function membershipStatusAndOwnershipMutationsInvalidateWarmAccess(): void
  {
    self::bootKernel();
    $container = self::getContainer();
    /** @var OrganizationRepositoryPort $organizations */
    $organizations = $container->get(OrganizationRepositoryPort::class);
    /** @var OrganizationMemberRepositoryPort $members */
    $members = $container->get(OrganizationMemberRepositoryPort::class);
    /** @var OrganizationRoleRepositoryPort $roles */
    $roles = $container->get(OrganizationRoleRepositoryPort::class);
    /** @var OrganizationAuthorizationService $authorization */
    $authorization = $container->get(OrganizationAuthorizationService::class);
    /** @var OrganizationCacheInvalidator $invalidator */
    $invalidator = $container->get(OrganizationCacheInvalidator::class);
    /** @var GetCurrentOrganizationMemberProfileHandler $profile */
    $profile = $container->get(GetCurrentOrganizationMemberProfileHandler::class);
    $orgId = OrganizationId::fromString('b5000000-0000-4000-8000-000000000001');
    $userId = 'b5000000-0000-4000-8000-000000000002';
    $organization = Organization::create($orgId, new OrganizationName('Access freshness'), $userId);
    $organizations->save($organization);
    $member = OrganizationMember::join(OrganizationMemberId::fromString('b5000000-0000-4000-8000-000000000003'), $orgId, $userId);
    $members->save($member);
    $roleId = OrganizationRoleId::fromString('b5000000-0000-4000-8000-000000000004');
    $roles->save(OrganizationRole::reconstitute($roleId, $orgId, new OrganizationRoleName('freshness'), ['organization.*'], false, new DateTimeImmutable()));
    $members->assignRole($member->id(), $roleId);
    self::assertTrue($authorization->isMemberOf($userId, (string) $orgId));
    self::assertTrue($authorization->hasPermission($userId, (string) $orgId, 'organization.members.manage'));
    self::assertNotEmpty($profile(new GetCurrentOrganizationMemberProfileQuery((string) $orgId, $userId))->permissions);

    $organization->deactivate();
    $organizations->save($organization);
    self::assertFalse($authorization->hasPermission($userId, (string) $orgId, 'organization.members.manage'));
    $organization->activate();
    $organizations->save($organization);
    self::assertTrue($authorization->hasPermission($userId, (string) $orgId, 'organization.members.manage'));
    $beforeTransfer = $invalidator->revision();
    $organization->transferOwnership('b5000000-0000-4000-8000-000000000005');
    $organizations->save($organization);
    self::assertGreaterThan($beforeTransfer, $invalidator->revision());

    $member->deactivate();
    $members->save($member);
    self::assertFalse($authorization->isMemberOf($userId, (string) $orgId));
    self::assertFalse($authorization->hasPermission($userId, (string) $orgId, 'organization.read'));
    $member->activate();
    $members->save($member);
    self::assertTrue($authorization->isMemberOf($userId, (string) $orgId));
    self::assertTrue($authorization->hasPermission($userId, (string) $orgId, 'organization.read'));
    $members->unassignRole($member->id(), $roleId);
    self::assertSame([], $authorization->getUserPermissions($userId, (string) $orgId));
    $member->deactivate();
    $members->save($member);
    $this->expectException(OrganizationMemberNotFoundException::class);
    $profile(new GetCurrentOrganizationMemberProfileQuery((string) $orgId, $userId));
  }
}
