<?php

declare(strict_types=1);

namespace Authorization\Infrastructure\Adapter\Idempotency;

use Authorization\Domain\Exception\{PermissionNotFoundException, RoleNotFoundException};
use Doctrine\DBAL\Connection;
use Shared\Application\Contract\Http\HttpIdempotencyAuthorizationRequest;
use Shared\Application\Contract\Idempotency\{IdempotencyDeniedException, IdempotencyProof, IdempotencyScope};
use Shared\Application\Port\Outbound\Idempotency\{HttpIdempotencyReplayAuthorizationPort, OrdinaryHttpReplayAuthorizationPort};

use function in_array;
use function is_string;

/**
 * Class AuthorizationHttpIdempotencyReplayAuthorizationAdapter
 *
 * Binds current auth-owned targets and their original tenant to fresh registered platform requirements before result disclosure.
 *
 * @category Adapter
 */
final readonly class AuthorizationHttpIdempotencyReplayAuthorizationAdapter implements HttpIdempotencyReplayAuthorizationPort
{
  // #region Constructor
  /**
   * @param OrdinaryHttpReplayAuthorizationPort $authorizer current account and original required authority
   * @param Connection $connection explicitly selected auth connection
   */
  public function __construct(private OrdinaryHttpReplayAuthorizationPort $authorizer, private Connection $connection)
  {
  }
  // #endregion

  // #region Methods
  /**
   * {@inheritdoc}
   */
  public function supports(string $operationName): bool
  {
    foreach (AuthorizationHttpIdempotencyOperationContributionAdapter::definitions() as $operation) {
      if ($operation->name === $operationName) {
        return true;
      }
    }

    return false;
  }

  /**
   * {@inheritdoc}
   */
  public function scope(HttpIdempotencyAuthorizationRequest $request): IdempotencyScope
  {
    $this->assertSupported($request);

    return $this->authorizer->scope($request);
  }

  /**
   * {@inheritdoc}
   */
  public function authorize(HttpIdempotencyAuthorizationRequest $request, ?IdempotencyProof $storedProof): array
  {
    $this->assertSupported($request);
    $kind = $request->operation->resourceKind;
    if (!in_array($kind, ['role', 'permission'], true)) {
      throw new IdempotencyDeniedException('authorization_unavailable');
    }
    $id = $request->uriVariables['id'] ?? $request->uriVariables['roleId'] ?? $storedProof?->metadata['resourceId'] ?? null;
    $tenant = '';
    if (null !== $id) {
      if (!is_string($id) || '' === $id) {
        throw new IdempotencyDeniedException('access_denied');
      }
      $row = $this->connection->fetchAssociative('SELECT id' . ('role' === $kind ? ', tenant_id' : '') . ' FROM ' . ('role' === $kind ? 'roles' : 'permissions') . ' WHERE id = :id', ['id' => $id]);
      if (false === $row) {
        if (null === $storedProof || !in_array($request->operation->name, ['role_delete', 'permission_delete'], true)) {
          throw 'role' === $kind ? RoleNotFoundException::withId($id) : PermissionNotFoundException::withId($id);
        }
        $tenant = $storedProof->metadata['authorizationTargetTenantId'] ?? '';
      } else {
        $tenant = is_string($row['tenant_id'] ?? null) ? $row['tenant_id'] : '';
      }
    }
    if (null !== $storedProof && (($storedProof->metadata['authorizationTargetKind'] ?? null) !== $kind || ($storedProof->metadata['authorizationTargetTenantId'] ?? null) !== $tenant || (isset($storedProof->metadata['authorizationTargetId']) && $storedProof->metadata['authorizationTargetId'] !== $id))) {
      throw new IdempotencyDeniedException('access_denied');
    }
    $proof = $this->authorizer->authorize($request, $storedProof, null);
    if (null === $storedProof) {
      $proof['authorizationTargetKind'] = $kind;
      $proof['authorizationTargetTenantId'] = $tenant;
      if (is_string($id)) {
        $proof['authorizationTargetId'] = $id;
      }
    }

    return $proof;
  }

  /**
   * @param HttpIdempotencyAuthorizationRequest $request trusted current route facts
   */
  private function assertSupported(HttpIdempotencyAuthorizationRequest $request): void
  {
    if (!$this->supports($request->operation->name)) {
      throw new IdempotencyDeniedException('authorization_unavailable');
    }
  }
  // #endregion
}
