<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Idempotency;

use Closure;
use Shared\Application\Contract\Http\HttpIdempotencyOperation;
use Shared\Application\Contract\Idempotency\{IdempotencyBegin, IdempotencyProof, IdempotencySnapshot};
use Shared\Application\Message\{CommandMessage, ResultMessage};
use Shared\Application\Port\Outbound\Idempotency\{HttpIdempotencyPort, SensitiveIdempotencyProofPort};

/**
 * Class HttpIdempotencyExecutionState
 *
 * Holds one request's execution state on that request, independently of other requests and nested bus calls.
 *
 * @category Context
 */
final class HttpIdempotencyExecutionState
{
  // #region Properties
  /**
   * Property primaryActive
   */
  public bool $primaryActive = false;

  /**
   * Property claimed
   */
  public bool $claimed = false;

  /**
   * Property prepared
   */
  public bool $prepared = false;

  /**
   * Property finished
   */
  public bool $finished = false;

  /**
   * Property completed
   */
  public bool $completed = false;

  /**
   * Property snapshot
   */
  public ?IdempotencySnapshot $snapshot = null;

  /**
   * Property captured
   */
  public ?ResultMessage $captured = null;

  /**
   * Property primaryCommand
   */
  public ?CommandMessage $primaryCommand = null;

  /**
   * Property externalStarted
   */
  public bool $externalStarted = false;

  /**
   * Property externalPurposes
   *
   * @var array<string, true> distinct attempted owner-declared external effects
   */
  public array $externalPurposes = [];

  /**
   * Property reconciliationRequested
   */
  public bool $reconciliationRequested = false;
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param HttpIdempotencyOperation $operation the declared mutation ownership and result schema
   * @param HttpIdempotencyPort $engine its explicitly selected database engine
   * @param IdempotencyBegin $begin the reserved execution or authorized replay
   * @param array<string, string> $proof owner-verified nonsecret authorization facts
   * @param SensitiveIdempotencyProofPort|null $proofPolicy the sensitive owner's fresh credential/revision policy
   * @param CommandMessage|null $proofCommand the exact command proved by that policy
   * @param IdempotencyProof|null $storedProof the authenticated completed receipt proof for replay
   * @param (Closure(IdempotencyProof):void)|null $authorizeReconciliation fresh owner proof for the selected uncertain-outcome receipt
   *
   * @return void
   */
  public function __construct(
    public readonly HttpIdempotencyOperation $operation,
    public readonly HttpIdempotencyPort $engine,
    public readonly IdempotencyBegin $begin,
    public readonly array $proof,
    public readonly ?SensitiveIdempotencyProofPort $proofPolicy = null,
    public readonly ?CommandMessage $proofCommand = null,
    public readonly ?IdempotencyProof $storedProof = null,
    public readonly ?Closure $authorizeReconciliation = null,
  ) {
  }
  // #endregion
}
