<?php

declare(strict_types=1);

namespace Shared\Application\Contract\Idempotency;

use InvalidArgumentException;

use function trim;

/**
 * Class IdempotencyScope
 *
 * Identifies an operation after the caller has verified its principal and current scope.
 *
 * @category Contract
 */
final readonly class IdempotencyScope
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Null tenant/organization identifies an explicitly principal-only operation.
   *
   * @access public
   *
   * @param string $principalId verified principal identifier, never a raw credential
   * @param string|null $tenantId verified tenant identifier
   * @param string|null $organizationId verified organization identifier
   * @param string $operation stable operation identity
   * @param string $resourceUri canonical URI including the resource identifier
   *
   * @return void
   */
  public function __construct(
    public string $principalId,
    public ?string $tenantId,
    public ?string $organizationId,
    public string $operation,
    public string $resourceUri,
  ) {
    foreach ([$principalId, $operation, $resourceUri] as $required) {
      if ('' === trim($required)) {
        throw new InvalidArgumentException('Idempotency scope is incomplete.');
      }
    }
    if ('' === $tenantId || '' === $organizationId) {
      throw new InvalidArgumentException('Idempotency scope is incomplete.');
    }
  }
  // #endregion
}
