<?php

declare(strict_types=1);

namespace User\Application\Contract\User;

/**
 * Class UserSecurityProof
 *
 * Publishes current account state without credentials or a mutable aggregate. Revision changes invalidate sensitive response replay.
 *
 * @category Contract
 */
final readonly class UserSecurityProof
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param string $userId verified stored account identifier
   * @param int $securityRevision current durable security revision
   * @param bool $active whether the account can currently authenticate
   * @param bool $emailVerified current verification state
   *
   * @return void
   */
  public function __construct(public string $userId, public int $securityRevision, public bool $active, public bool $emailVerified, public ?string $tenantId = null)
  {
  }
  // #endregion
}
