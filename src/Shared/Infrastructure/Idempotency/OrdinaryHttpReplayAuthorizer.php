<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Idempotency;

use JsonException;
use Shared\Application\Contract\Http\{HttpIdempotencyAccount, HttpIdempotencyAuthorizationRequest};
use Shared\Application\Contract\Idempotency\{IdempotencyDeniedException, IdempotencyProof, IdempotencyScope};
use Shared\Application\Port\Outbound\Idempotency\{HttpIdempotencyAccountPort, HttpIdempotencyOrganizationAuthorityPort, HttpIdempotencyPlatformAuthorityPort, OrdinaryHttpReplayAuthorizationPort};

use function array_is_list;
use function count;
use function in_array;
use function is_array;
use function is_bool;
use function is_string;
use function json_decode;
use function preg_match;
use function strtolower;

use const JSON_THROW_ON_ERROR;

/**
 * Class OrdinaryHttpReplayAuthorizer
 *
 * Rechecks fresh actor and signed original requirements before any saved response is decoded. Resource identity belongs to the operation owner.
 *
 * @category Adapter
 */
final readonly class OrdinaryHttpReplayAuthorizer implements OrdinaryHttpReplayAuthorizationPort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Core-only compositions omit Organization; an organization-scoped receipt then fails closed.
   *
   * @access public
   *
   * @param HttpIdempotencyAccountPort $users fresh owner-contributed account facts
   * @param HttpIdempotencyPlatformAuthorityPort $platform fresh owner-contributed platform authority
   * @param HttpIdempotencyOrganizationAuthorityPort|null $organizations fresh owner-contributed organization authority when selected
   *
   * @return void
   */
  public function __construct(
    private HttpIdempotencyAccountPort $users,
    private HttpIdempotencyPlatformAuthorityPort $platform,
    private ?HttpIdempotencyOrganizationAuthorityPort $organizations = null,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritdoc}
   */
  public function scope(HttpIdempotencyAuthorizationRequest $request): IdempotencyScope
  {
    $organization = $this->routeOrganization($request);

    return new IdempotencyScope($request->principalId, $request->tenantId, $organization, $request->operation->name, $request->resourceUri);
  }

  /**
   * {@inheritdoc}
   */
  public function authorize(HttpIdempotencyAuthorizationRequest $request, ?IdempotencyProof $storedProof, ?string $currentOrganizationId): array
  {
    $actor = $this->users->current($request->principalId);
    if (null === $actor || !$actor->active || $actor->principalId !== $request->principalId || $actor->tenantId !== $request->tenantId) {
      $this->deny();
    }
    $platformKey = $this->platformKey($request->platformSecurityExpression);
    $this->assertPlatformKey($request, $actor, $platformKey);
    $routeOrganization = $this->routeOrganization($request);
    if ((null !== $routeOrganization || null !== $currentOrganizationId) && null === $this->organizations) {
      $this->deny();
    }
    if (null !== $routeOrganization && null !== $currentOrganizationId && $routeOrganization !== $currentOrganizationId) {
      if (null === $storedProof) {
        throw new IdempotencyDeniedException('scope_not_found');
      }
      $this->deny();
    }
    $facts = [
      'authorizationVersion' => '1',
      'principalId' => $request->principalId,
      'tenantId' => $request->tenantId ?? '',
      'operation' => $request->operation->name,
      'resourceUri' => $request->resourceUri,
      'principalSecurityRevision' => (string) $actor->securityRevision,
      'platformSecurityKey' => $platformKey,
    ];
    if (null === $storedProof) {
      $organization = $currentOrganizationId ?? $routeOrganization;
      if (null !== $organization) {
        $facts['organizationId'] = $organization;
      }

      return $facts;
    }
    $proof = $storedProof->metadata;
    foreach (['authorizationVersion', 'principalId', 'tenantId', 'operation', 'resourceUri', 'principalSecurityRevision'] as $key) {
      if (($proof[$key] ?? null) !== $facts[$key]) {
        $this->deny();
      }
    }
    $this->assertPlatformKey($request, $actor, $proof['platformSecurityKey'] ?? '');
    $originalOrganization = $proof['organizationId'] ?? null;
    if (null !== $originalOrganization && null === $this->organizations) {
      $this->deny();
    }
    if ((null !== $routeOrganization && $originalOrganization !== $routeOrganization) || (null !== $currentOrganizationId && $originalOrganization !== $currentOrganizationId)) {
      $this->deny();
    }
    if (null !== $originalOrganization && '' === $originalOrganization) {
      $this->deny();
    }
    $organizations = $this->rows($proof['authorizationRequirements'] ?? null);
    foreach ($organizations as $row) {
      $organization = $row['organizationId'] ?? null;
      $permissions = $row['permissions'] ?? null;
      $membership = $row['membership'] ?? null;
      if (!is_string($organization) || '' === $organization || !is_array($permissions) || !array_is_list($permissions) || count($permissions) > 128 || !is_bool($membership) || null === $this->organizations) {
        $this->deny();
      }
      if ($membership && !$this->organizations->isMemberOf($request->principalId, $organization)) {
        $this->deny();
      }
      foreach ($permissions as $permission) {
        if (!is_string($permission) || !$this->organizations->hasPermission($request->principalId, $organization, $permission)) {
          $this->deny();
        }
      }
    }
    foreach ($this->rows($proof['platformAuthorizationRequirements'] ?? null) as $row) {
      $this->assertPlatformRequirement($actor, $row);
    }

    return $proof;
  }

  /**
   * Method routeOrganization
   *
   * @access private
   *
   * @param HttpIdempotencyAuthorizationRequest $request trusted route facts
   *
   * @return string|null organization-prefixed scope identifier
   */
  private function routeOrganization(HttpIdempotencyAuthorizationRequest $request): ?string
  {
    $organization = $request->uriVariables['organizationId'] ?? null;
    if (null !== $organization && (!is_string($organization) || '' === $organization)) {
      $this->deny();
    }

    return is_string($organization) && 1 === preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/iD', $organization) ? strtolower($organization) : $organization;
  }

  /**
   * Method platformKey
   *
   * @access private
   *
   * @param string|null $expression explicit current API declaration
   *
   * @return string required registered key, or empty for an explicitly public declaration
   */
  private function platformKey(?string $expression): string
  {
    if (null === $expression || '' === $expression) {
      return '';
    }
    if (1 !== preg_match('/^is_granted\([\'\"]([^\'\"]+)[\'\"]\)$/D', $expression, $matches)) {
      $this->deny();
    }

    return $matches[1];
  }

  /**
   * Method assertPlatformKey
   *
   * @access private
   *
   * @param HttpIdempotencyAuthorizationRequest $request current caller
   * @param HttpIdempotencyAccount $actor fresh active actor
   * @param string $key declared current or signed original security requirement
   *
   * @return void
   */
  private function assertPlatformKey(HttpIdempotencyAuthorizationRequest $request, HttpIdempotencyAccount $actor, string $key): void
  {
    if (!$this->platform->permitsKey($actor, $key)) {
      $this->deny();
    }
  }

  /**
   * Method assertPlatformRequirement
   *
   * @access private
   *
   * @param HttpIdempotencyAccount $actor current caller
   * @param array<string, mixed> $row signed required predicate
   *
   * @return void
   */
  private function assertPlatformRequirement(HttpIdempotencyAccount $actor, array $row): void
  {
    $names = $row['names'] ?? null;
    $kind = $row['kind'] ?? null;
    if (!is_array($names) || !array_is_list($names) || count($names) > 128 || !in_array($kind, ['permission', 'roles_any', 'roles_all', 'platform_keys_all'], true)) {
      $this->deny();
    }
    $matched = false;
    foreach ($names as $name) {
      if (!is_string($name)) {
        $this->deny();
      }
      $granted = match ($kind) {
        'permission' => $this->platform->hasPermission($actor, $name),
        'platform_keys_all' => $this->platform->permitsKey($actor, $name),
        default => $this->platform->hasRole($actor, $name),
      };
      $matched = $matched || $granted;
      if ('roles_any' !== $kind && !$granted) {
        $this->deny();
      }
    }
    if ('roles_any' === $kind && !$matched) {
      $this->deny();
    }
  }

  /**
   * Method rows
   *
   * @access private
   *
   * @param string|null $json signed scalar JSON metadata
   *
   * @return list<array<string, mixed>> bounded parsed requirement rows
   */
  private function rows(?string $json): array
  {
    if (null === $json) {
      $this->deny();
    }

    try {
      $rows = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
      $this->deny();
    }
    if (!is_array($rows) || !array_is_list($rows) || count($rows) > 128) {
      $this->deny();
    }
    foreach ($rows as $row) {
      if (!is_array($row)) {
        $this->deny();
      }
    }
    /** @var list<array<string, mixed>> $rows */

    return $rows;
  }

  /**
   * Method deny
   *
   * @access private
   *
   * @return never denies before revealing or decoding a saved response
   */
  private function deny(): never
  {
    throw new IdempotencyDeniedException('access_denied');
  }
  // #endregion
}
