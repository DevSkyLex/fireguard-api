<?php

declare(strict_types=1);

namespace Shared\Application\Contract\Http;

/**
 * Class HttpIdempotencyPrincipal
 *
 * Publishes only the currently verified actor and tenant identities to the generic HTTP mechanism.
 *
 * @category Contract
 */
final readonly class HttpIdempotencyPrincipal
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param string $principalId current authenticated actor identity
   * @param string|null $tenantId current owner-verified tenant context
   *
   * @return void
   */
  public function __construct(public string $principalId, public ?string $tenantId = null)
  {
  }
  // #endregion
}
