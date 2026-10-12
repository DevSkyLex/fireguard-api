<?php

declare(strict_types=1);

namespace TrustedDevice\Infrastructure\Adapter\Idempotency;

use Doctrine\DBAL\Connection;
use Shared\Application\Contract\Http\HttpIdempotencyAuthorizationRequest;
use Shared\Application\Contract\Idempotency\{IdempotencyDeniedException, IdempotencyProof, IdempotencyScope};
use Shared\Application\Port\Outbound\Idempotency\{HttpIdempotencyReplayAuthorizationPort, OrdinaryHttpReplayAuthorizationPort};

use function is_string;

/**
 * Class TrustedDeviceHttpIdempotencyReplayAuthorizationAdapter
 *
 * Rechecks fresh owner scope before any stored result is decoded; deleted targets require signed original ownership.
 *
 * @category Adapter
 */
final readonly class TrustedDeviceHttpIdempotencyReplayAuthorizationAdapter implements HttpIdempotencyReplayAuthorizationPort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param OrdinaryHttpReplayAuthorizationPort $authorizer current caller and signed permission checks
   * @param Connection $connection explicitly wired owner database, bypassing managed resource caches
   *
   * @return void
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
    foreach (TrustedDeviceHttpIdempotencyOperationContributionAdapter::definitions() as $operation) {
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
    if (!$this->supports($request->operation->name)) {
      throw new IdempotencyDeniedException('access_denied');
    }

    return $this->authorizer->scope($request);
  }

  /**
   * {@inheritdoc}
   */
  public function authorize(HttpIdempotencyAuthorizationRequest $request, ?IdempotencyProof $storedProof): array
  {
    if (!$this->supports($request->operation->name)) {
      throw new IdempotencyDeniedException('access_denied');
    }
    $facts = $this->authorizer->authorize($request, $storedProof, null);
    $target = match ($request->operation->name) {
      'trusted_device_trust' => null === $storedProof ? null : $this->trustedTarget($request, $storedProof),
      'trusted_device_revoke' => $this->revokedTarget($request, $storedProof),
      default => null,
    };
    if (null !== $target) {
      $facts['targetId'] = $target;
      $facts['deviceOwnerId'] = $request->principalId;
    }

    return $facts;
  }

  /**
   * Method trustedTarget
   *
   * Rechecks the current live trust credential against the signed result owner.
   *
   * @access private
   *
   * @param HttpIdempotencyAuthorizationRequest $request current verified account
   * @param IdempotencyProof $storedProof authenticated trust receipt
   *
   * @return string current trusted credential identity
   */
  private function trustedTarget(HttpIdempotencyAuthorizationRequest $request, IdempotencyProof $storedProof): string
  {
    $target = $storedProof->metadata['resourceId'] ?? null;
    if (!is_string($target) || '' === $target || 'trusted_device' !== ($storedProof->metadata['resourceKind'] ?? null)) {
      throw new IdempotencyDeniedException('access_denied');
    }
    $row = $this->connection->fetchAssociative('SELECT id, user_id FROM trusted_devices WHERE id = ? AND revoked = FALSE AND expires_at > CURRENT_TIMESTAMP', [$target]);
    if (false === $row || ($row['user_id'] ?? null) !== $request->principalId) {
      throw new IdempotencyDeniedException('access_denied');
    }

    return $target;
  }

  /**
   * Method revokedTarget
   *
   * Retains signed ownership for a deleted credential while rejecting every other account's target.
   *
   * @access private
   *
   * @param HttpIdempotencyAuthorizationRequest $request current verified account and URI
   * @param IdempotencyProof|null $storedProof authenticated original revoke receipt
   *
   * @return string validated current or previously deleted credential identity
   */
  private function revokedTarget(HttpIdempotencyAuthorizationRequest $request, ?IdempotencyProof $storedProof): string
  {
    $target = $request->uriVariables['id'] ?? null;
    if (!is_string($target) || '' === $target) {
      throw new IdempotencyDeniedException('access_denied');
    }
    $row = $this->connection->fetchAssociative('SELECT id, user_id FROM trusted_devices WHERE id = ?', [$target]);
    if (false === $row) {
      if (null === $storedProof || ($storedProof->metadata['deviceOwnerId'] ?? null) !== $request->principalId || ($storedProof->metadata['targetId'] ?? null) !== $target) {
        throw new IdempotencyDeniedException('access_denied');
      }
    } elseif (($row['user_id'] ?? null) !== $request->principalId) {
      throw new IdempotencyDeniedException('access_denied');
    }

    return $target;
  }
  // #endregion
}
