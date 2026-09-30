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
 * Class SecurityUserProvider
 *
 * Builds Symfony principals from the user query and authorization ports, using validated cache data when available.
 *
 * @category User
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements UserProviderInterface<SecurityUser>
 */
final readonly class SecurityUserProvider implements UserProviderInterface
{
  // #region Constants
  /**
   * Constant DEFAULT_CACHE_TTL_SECONDS
   *
   * Default freshness window used when caching a user's authentication projection.
   *
   * @access private
   */
  private const int DEFAULT_CACHE_TTL_SECONDS = 15;
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * Resolves users and roles through application ports and optionally caches the user projection for the configured seconds.
   *
   * @access public
   *
   * @param QueryBusPort $queryBus retrieves the local user record
   * @param AuthorizationPort $authorizationService supplies the user's assigned role names
   * @param ?CachePort $cache optional cache for the authentication projection
   * @param int $cacheTtl cache freshness in seconds; non-positive values disable caching
   *
   * @return void
   */
  public function __construct(
    private QueryBusPort $queryBus,
    private AuthorizationPort $authorizationService,
    private ?CachePort $cache = null,
    private int $cacheTtl = self::DEFAULT_CACHE_TTL_SECONDS,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method loadUserByIdentifier
   *
   * Loads the framework user by treating its identifier as the local user ID.
   *
   * @access public
   *
   * @param string $identifier local user identifier
   *
   * @return SecurityUser the concrete security user required by authentication and authorization callers
   */
  public function loadUserByIdentifier(string $identifier): UserInterface
  {
    return $this->loadUserById(userId: $identifier);
  }

  /**
   * Method loadUserById
   *
   * Resolves a user's status and RBAC roles, reusing a valid cache projection when available.
   *
   * @access public
   *
   * @param string $userId the local user identifier
   * @param list<string> $scopes OAuth2 scopes attached to this authenticated principal
   *
   * @return SecurityUser the security user
   *
   * @throws UserNotFoundException if the user is not found
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

  /**
   * Method refreshUser
   *
   * Reloads a supported SecurityUser by local ID while preserving its current scopes.
   *
   * @access public
   *
   * @param UserInterface $user the user instance to refresh
   *
   * @return UserInterface the refreshed security user
   *
   * @throws UnsupportedUserException when the instance is not a SecurityUser
   */
  public function refreshUser(UserInterface $user): UserInterface
  {
    if (!$user instanceof SecurityUser) {
      throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
    }

    return $this->loadUserById($user->getId(), $user->getScopes());
  }

  /**
   * Method supportsClass
   *
   * Accepts only the concrete user class produced by this provider.
   *
   * @access public
   *
   * @param string $class fully qualified user class name
   *
   * @return bool whether this provider supports the class
   */
  public function supportsClass(string $class): bool
  {
    return SecurityUser::class === $class;
  }

  /**
   * Method mapStatusToRoles
   *
   * Adds the base user role and includes the verified role only when the account can sign in.
   *
   * @access private
   *
   * @param bool $canLogin whether the account is eligible to sign in
   *
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
   * Method readCache
   *
   * Accepts only cached payloads with the fields and scalar types required to rebuild a security user.
   *
   * @access private
   *
   * @param string $userId local user identifier used in the cache key
   *
   * @return array{id: string, email: string, roles: list<string>, isActive: bool, tenantId: ?string}|null
   */
  private function readCache(string $userId): ?array
  {
    if (null === $this->cache || $this->cacheTtl <= 0) {
      return null;
    }

    $cached = $this->cachedValue($userId);
    if (
      !is_array($cached)
      || !isset($cached['id'], $cached['email'], $cached['roles'])
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
   * Method cachedValue
   *
   * Reads the user projection from cache and treats backend failures as a cache miss.
   *
   * @access private
   *
   * @param string $userId local user identifier used in the cache key
   *
   * @return mixed the cached value, or null when unavailable
   */
  private function cachedValue(string $userId): mixed
  {
    try {
      return $this->cache?->get(SecurityUserCacheKeys::user($userId));
    } catch (Throwable) {
      return null;
    }
  }

  /**
   * Method createSecurityUserFromPayload
   *
   * Rebuilds the framework principal from a validated cache payload while applying the current request scopes.
   *
   * @access private
   *
   * @param array{id: string, email: string, roles: list<string>, isActive: bool, tenantId: ?string} $payload
   * @param list<string> $scopes
   *
   * @return SecurityUser reconstructed authentication principal
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
   * Method writeCache
   *
   * Stores the user projection when caching is enabled and suppresses cache failures so authentication can continue.
   *
   * @access private
   *
   * @param string $userId local user identifier used in the cache key
   * @param array{id: string, email: string, roles: list<string>, isActive: bool, tenantId: ?string} $payload
   *
   * @return void
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
