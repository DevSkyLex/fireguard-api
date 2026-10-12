<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Idempotency;

use Shared\Application\Contract\Idempotency\IdempotencyProof;
use Shared\Application\Message\CommandMessage;
use Shared\Application\Port\Outbound\Idempotency\SensitiveIdempotencyProofPort;

/**
 * Class HttpIdempotencySensitiveExecutionProof
 *
 * Binds the owner's credential policy to its exact command and authenticated receipt.
 * Activation validates the binding before a sensitive result can be replayed.
 *
 * @category Context
 */
final readonly class HttpIdempotencySensitiveExecutionProof
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param SensitiveIdempotencyProofPort $policy fresh owner credential and revision policy
   * @param CommandMessage $command exact typed command verified by that policy
   * @param IdempotencyProof|null $stored authenticated completed receipt when replaying
   *
   * @return void
   */
  public function __construct(
    public SensitiveIdempotencyProofPort $policy,
    public CommandMessage $command,
    public ?IdempotencyProof $stored = null,
  ) {
  }
  // #endregion
}
