<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Idempotency;

use Shared\Application\Contract\Http\HttpIdempotencyOperation;

/**
 * Class HttpIdempotencyPendingCommand
 *
 * Holds request-local facts until a sensitive owner receives its exact typed command and can prove current credentials.
 *
 * @category Context
 */
final readonly class HttpIdempotencyPendingCommand
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param HttpIdempotencyOperation $operation explicit operation ownership
   * @param string|null $key the client key, never persisted as plaintext
   * @param array<string, mixed> $canonicalInput current logical HTTP input
   * @param string $resourceUri canonical route identity
   * @param string|null $tenantId the verified current tenant when available
   * @param bool $required whether mandatory-key rollout applies
   * @param bool $invalidKey whether header syntax must be rejected after the owner proves current access
   * @param string|null $ambientPrincipalId authenticated actor supplying an ambient tenant, distinct from a proved native owner
   * @param string|null $routeTenantId trusted route/host tenant to preserve when native owner identity differs
   *
   * @return void
   */
  public function __construct(
    public HttpIdempotencyOperation $operation,
    public ?string $key,
    public array $canonicalInput,
    public string $resourceUri,
    public ?string $tenantId,
    public bool $required,
    public bool $invalidKey = false,
    public ?string $ambientPrincipalId = null,
    public ?string $routeTenantId = null,
  ) {
  }
  // #endregion
}
