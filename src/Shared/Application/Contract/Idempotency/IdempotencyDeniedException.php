<?php

declare(strict_types=1);

namespace Shared\Application\Contract\Idempotency;

use RuntimeException;

/**
 * Class IdempotencyDeniedException
 *
 * Publishes a stable denial reason without disclosing receipt or input data.
 *
 * @category Exception
 */
final class IdempotencyDeniedException extends RuntimeException
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param string $reason stable input_mismatch, in_progress, expired, commit_unknown or owner_lost reason
   * @param bool $operationCompleted true only for an authenticated completed receipt with matching canonical input
   *
   * @return void
   */
  public function __construct(public readonly string $reason, public readonly bool $operationCompleted = false)
  {
    parent::__construct('Idempotency request denied: ' . $reason . '.');
  }
  // #endregion
}
