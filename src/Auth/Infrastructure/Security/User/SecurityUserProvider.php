<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Security\User;

use Auth\Application\Service\SecurityUserCacheKeys;
use Authorization\Application\Port\Inbound\AuthorizationPort;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Application\Port\Outbound\CachePort;
use Symfony\Component\Security\Core\Exception\{UnsupportedUserException, UserNotFoundException};
use Symfony\Component\Security\Core\User\{UserInterface, UserProviderInterface};
use Throwable;
use User\Application\UseCase\Query\User\GetUser\{GetUserQuery, GetUserResult};

use function array_filter;
use function array_key_exists;
use function array_map;
use function array_merge;
use function array_unique;
use function array_values;
use function is_array;
use function is_bool;
use function is_string;
use function sprintf;
use function strtoupper;

/**
 * Provider SecurityUserProvider.
 *
 * @category User
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements UserProviderInterface<SecurityUser>
 */
final readonly class SecurityUserProvider implements UserProviderInterface
{
  private const int DEFAULT_CACHE_TTL_SECONDS = 15;

  // #region Constructor
  public function __construct(
    private QueryBusPort $queryBus,
    private AuthorizationPort $authorizationService,
    private ?CachePort $cache = null,
    private int $cacheTtl = self::DEFAULT_CACHE_TTL_SECONDS,
  ) {
  }
  // #endregion

  // #region Methods
  public function loadUserByIdentifier(string $identifier): UserInterface
  {
    return $this->loadUserById(userId: $identifier);
  }

  /**
   * @param string $userId the user ID
   * @param list<string> $scopes optional OAuth2 scopes
   *
   * @throws UserNotFoundException if the user is not found
   *
   * @return SecurityUser the security user
   */
  public function loadUserById(string $userId, array $scopes = []): SecurityUser
  {
    $cached = $this->readCache($userId);
    if (null !== $cached) {
      return $this->createSecurityUserFromPayload($cached, $scopes);
    }

    try {
      /** @var GetUserResult $result */
      $result = $this->queryBus->ask(new GetUserQuery(id: $userId));

      if (null === $result->user) {
        throw new UserNotFoundException(
          message: sprintf('User "%s" not found.', $userId),
        );
      }

      $user = $result->user;

      // Get RBAC roles
      $rbacRoles = $this->authorizationService->getUserRoleNames(userId: $user->id);

      // Normalize RBAC roles (e.g. "admin" -> "ROLE_ADMIN")
      $normalizedRbacRoles = array_map(
        fn (string $role) => 'ROLE_' . strtoupper($role),
        $rbacRoles,
      );

      // Merge with status-based roles
      $roles = array_values(array_unique(array_merge(
        $this->mapStatusToRoles($user->canLogin),
        $normalizedRbacRoles,
      )));

      $securityUser = new SecurityUser(
        id: $user->id,
        email: $user->email,
        password: '',
        roles: $roles,
        scopes: $scopes,
        isActive: $user->canLogin,
        tenantId: ('' !== $user->tenantId && null !== $user->tenantId) ? $user->tenantId : null,
      );
      $this->writeCache($userId, [
        'id' => $securityUser->getId(),
        'email' => $securityUser->getUserIdentifier(),
        'roles' => $securityUser->getRoles(),
        'isActive' => $securityUser->isActive(),
        'tenantId' => $securityUser->getTenantId(),
      ]);

      return $securityUser;
    } catch (UserNotFoundException $exception) {
      throw $exception;
    } catch (Throwable $exception) {
      throw new UserNotFoundException(
        message: sprintf('User "%s" not found: %s', $userId, $exception->getMessage()),
      );
    }
  }

  public function refreshUser(UserInterface $user): UserInterface
  {
    if (!$user instanceof SecurityUser) {
      throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
    }

    return $this->loadUserById($user->getId(), $user->getScopes());
  }

  public function supportsClass(string $class): bool
  {
    return SecurityUser::class === $class;
  }

  /**
   * @return list<string>
   */
  private function mapStatusToRoles(bool $canLogin): array
  {
    $roles = ['ROLE_USER'];

    if ($canLogin) {
      $roles[] = 'ROLE_VERIFIED';
    }

    return $roles;
  }

  /**
   * @return array{id: string, email: string, roles: list<string>, isActive: bool, tenantId: ?string}|null
   */
  private function readCache(string $userId): ?array
  {
    if (null === $this->cache || $this->cacheTtl <= 0) {
      return null;
    }

    try {
      $cached = $this->cache->get(SecurityUserCacheKeys::user($userId));
    } catch (Throwable) {
      return null;
    }

    if (!is_array($cached)) {
      return null;
    }

    if (
      !isset($cached['id'], $cached['email'], $cached['roles'])
      || !array_key_exists('isActive', $cached)
      || !is_string($cached['id'])
      || !is_string($cached['email'])
      || !is_array($cached['roles'])
      || !is_bool($cached['isActive'])
    ) {
      return null;
    }

    /** @var list<string> $roles */
    $roles = array_values(array_filter($cached['roles'], 'is_string'));
    $tenantId = $cached['tenantId'] ?? null;

    return [
      'id' => $cached['id'],
      'email' => $cached['email'],
      'roles' => $roles,
      'isActive' => (bool) $cached['isActive'],
      'tenantId' => is_string($tenantId) && '' !== $tenantId ? $tenantId : null,
    ];
  }

  /**
   * @param array{id: string, email: string, roles: list<string>, isActive: bool, tenantId: ?string} $payload
   * @param list<string> $scopes
   */
  private function createSecurityUserFromPayload(array $payload, array $scopes): SecurityUser
  {
    return new SecurityUser(
      id: $payload['id'],
      email: $payload['email'],
      password: '',
      roles: $payload['roles'],
      scopes: $scopes,
      isActive: $payload['isActive'],
      tenantId: $payload['tenantId'],
    );
  }

  /**
   * @param array{id: string, email: string, roles: list<string>, isActive: bool, tenantId: ?string} $payload
   */
  private function writeCache(string $userId, array $payload): void
  {
    if (null === $this->cache || $this->cacheTtl <= 0) {
      return;
    }

    try {
      $this->cache->set(SecurityUserCacheKeys::user($userId), $payload, $this->cacheTtl);
    } catch (Throwable) {
      // Cache failures should not block authentication.
    }
  }
  // #endregion
}
