<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\GetCurrentOrganizationMemberProfile;

use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Port\Outbound\{
  OrganizationMemberRepositoryPort,
  OrganizationRepositoryPort,
  OrganizationRoleRepositoryPort
};
use Organization\Application\Service\OrganizationCacheKeys;
use Organization\Domain\Exception\{OrganizationMemberNotFoundException, OrganizationNotFoundException};
use Organization\Domain\ValueObject\{OrganizationId, OrganizationRoleId};
use Shared\Application\Message\QueryHandler;
use Shared\Application\Port\Outbound\CachePort;
use Throwable;

use function array_map;

/**
 * UseCase GetCurrentOrganizationMemberProfileHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetCurrentOrganizationMemberProfileHandler implements QueryHandler
{
  private const int DEFAULT_CACHE_TTL_SECONDS = 30;

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * GetCurrentOrganizationMemberProfileHandler class.
   *
   * @since 1.0.0
   *
   * @param OrganizationRepositoryPort $organizationRepository the organization repository
   * @param OrganizationMemberRepositoryPort $memberRepository the organization member repository
   * @param OrganizationRoleRepositoryPort $roleRepository the organization role repository
   * @param OrganizationAuthorizationPort $authorization the organization authorization port
   */
  public function __construct(
    private OrganizationRepositoryPort $organizationRepository,
    private OrganizationMemberRepositoryPort $memberRepository,
    private OrganizationRoleRepositoryPort $roleRepository,
    private OrganizationAuthorizationPort $authorization,
    private ?CachePort $cache = null,
    private int $cacheTtl = self::DEFAULT_CACHE_TTL_SECONDS,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Handles the corresponding use case execution.
   *
   * @since 1.0.0
   *
   * @param GetCurrentOrganizationMemberProfileQuery $query the query payload
   *
   * @throws OrganizationNotFoundException when the organization does not exist
   * @throws OrganizationMemberNotFoundException when the user has no active membership
   */
  public function __invoke(GetCurrentOrganizationMemberProfileQuery $query): GetCurrentOrganizationMemberProfileResult
  {
    $organizationId = OrganizationId::fromString($query->organizationId);
    $organization = $this->organizationRepository->findById($organizationId);

    if (null === $organization) {
      throw OrganizationNotFoundException::withId($query->organizationId);
    }

    $cacheKey = OrganizationCacheKeys::currentMemberProfile($query->organizationId, $query->userId);
    $cached = $this->readCache($cacheKey);
    if (
      $cached instanceof GetCurrentOrganizationMemberProfileResult
      && ([] !== $cached->roles || [] !== $cached->permissions)
    ) {
      return $cached;
    }

    $member = $this->memberRepository->findByOrganizationAndUser($organizationId, $query->userId);

    if (null === $member || !$member->isActive()) {
      throw OrganizationMemberNotFoundException::forUserInOrganization($query->userId, $query->organizationId);
    }

    $roleIds = array_map(
      static fn (string $roleId): OrganizationRoleId => OrganizationRoleId::fromString($roleId),
      $this->memberRepository->findRoleIdsForMember($member->id()),
    );
    $roles = $this->roleRepository->findByIdsInOrganization($organizationId, $roleIds);
    $memberCountsByRoleId = $this->memberRepository->countActiveMembersGroupedByRoleId($organizationId);

    $roleResults = [];
    foreach ($roles as $role) {
      $roleId = (string) $role->id();
      $roleResults[] = new GetCurrentOrganizationMemberProfileRoleResult(
        id: $roleId,
        organizationId: (string) $role->organizationId(),
        name: (string) $role->name(),
        permissions: $role->permissions(),
        isSystem: $role->isSystem(),
        createdAt: $role->createdAt(),
        description: $role->description(),
        memberCount: $memberCountsByRoleId[$roleId] ?? 0,
      );
    }

    $result = new GetCurrentOrganizationMemberProfileResult(
      id: (string) $member->id(),
      organizationId: (string) $member->organizationId(),
      userId: $member->userId(),
      isActive: $member->isActive(),
      joinedAt: $member->joinedAt(),
      roles: $roleResults,
      permissions: $this->authorization->getUserPermissions($query->userId, $query->organizationId),
    );
    $this->writeCache($cacheKey, $result);

    return $result;
  }

  private function readCache(string $cacheKey): ?GetCurrentOrganizationMemberProfileResult
  {
    if (null === $this->cache || $this->cacheTtl <= 0) {
      return null;
    }

    try {
      $cached = $this->cache->get($cacheKey);
    } catch (Throwable) {
      return null;
    }

    return $cached instanceof GetCurrentOrganizationMemberProfileResult ? $cached : null;
  }

  private function writeCache(string $cacheKey, GetCurrentOrganizationMemberProfileResult $result): void
  {
    if (null === $this->cache || $this->cacheTtl <= 0) {
      return;
    }

    try {
      $this->cache->set($cacheKey, $result, $this->cacheTtl);
    } catch (Throwable) {
      // Cache failures should not block current member profile resolution.
    }
  }
  // #endregion
}
