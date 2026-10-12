<?php

declare(strict_types=1);

namespace Tenant\Infrastructure\Adapter\Idempotency;

use Doctrine\DBAL\Connection;
use Shared\Application\Contract\Http\HttpIdempotencyAuthorizationRequest;
use Shared\Application\Contract\Idempotency\{IdempotencyDeniedException, IdempotencyProof, IdempotencyScope};
use Shared\Application\Port\Outbound\Idempotency\{HttpIdempotencyReplayAuthorizationPort, OrdinaryHttpReplayAuthorizationPort};
use Tenant\Domain\Exception\TenantNotFoundException;

use function is_string;

/**
 * Class TenantHttpIdempotencyReplayAuthorizationAdapter
 *
 * Rechecks current account and platform requirements against an explicit auth-side tenant target before opening its receipt.
 *
 * @category Adapter
 */
final readonly class TenantHttpIdempotencyReplayAuthorizationAdapter implements HttpIdempotencyReplayAuthorizationPort
{
  // #region Constructor
  /**
   * @param OrdinaryHttpReplayAuthorizationPort $authorizer current registered platform authority
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
    foreach (TenantHttpIdempotencyOperationContributionAdapter::definitions() as $operation) {
      if ($operationName === $operation->name) {
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
    $id = $request->uriVariables['id'] ?? $storedProof?->metadata['resourceId'] ?? null;
    if (null !== $id) {
      if (!is_string($id) || '' === $id) {
        throw new IdempotencyDeniedException('access_denied');
      }
      $exists = $this->connection->fetchOne('SELECT id FROM tenants WHERE id = :id', ['id' => $id]);
      if (false === $exists && (null === $storedProof || 'tenant_delete' !== $request->operation->name)) {
        throw TenantNotFoundException::withId($id);
      }
      if (null !== $storedProof && isset($storedProof->metadata['authorizationTargetId']) && $storedProof->metadata['authorizationTargetId'] !== $id) {
        throw new IdempotencyDeniedException('access_denied');
      }
    }
    $proof = $this->authorizer->authorize($request, $storedProof, null);
    if (null === $storedProof && is_string($id)) {
      $proof['authorizationTargetId'] = $id;
    }

    return $proof;
  }

  /**
   * @param HttpIdempotencyAuthorizationRequest $request trusted route facts
   */
  private function assertSupported(HttpIdempotencyAuthorizationRequest $request): void
  {
    if (!$this->supports($request->operation->name)) {
      throw new IdempotencyDeniedException('authorization_unavailable');
    }
  }
  // #endregion
}
