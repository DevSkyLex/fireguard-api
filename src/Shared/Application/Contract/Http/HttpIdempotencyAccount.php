<?php

declare(strict_types=1);

namespace Shared\Application\Contract\Http;

/**
 * Class HttpIdempotencyAccount
 *
 * Publishes the current scalar account facts required by durable response authorization.
 *
 * @category Contract
 */
final readonly class HttpIdempotencyAccount
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @param string $principalId current account identity
   * @param string|null $tenantId current tenant context
   * @param int $securityRevision current durable authorization revision
   * @param bool $active whether the account may currently authenticate
   * @param bool $emailVerified current verification state
   */
  public function __construct(public string $principalId, public ?string $tenantId, public int $securityRevision, public bool $active, public bool $emailVerified)
  {
  }
  // #endregion
}
