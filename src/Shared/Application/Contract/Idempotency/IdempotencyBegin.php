<?php

declare(strict_types=1);

namespace Shared\Application\Contract\Idempotency;

use InvalidArgumentException;

/**
 * Class IdempotencyBegin
 *
 * Returns exactly one ownership lease or committed application result.
 *
 * @category Contract
 */
final readonly class IdempotencyBegin
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param IdempotencyLease|null $lease newly reserved ownership
   * @param IdempotencySnapshot|null $replay already committed result
   *
   * @return void
   */
  public function __construct(public ?IdempotencyLease $lease = null, public ?IdempotencySnapshot $replay = null)
  {
    if ((null === $lease) === (null === $replay)) {
      throw new InvalidArgumentException('Idempotency begin requires exactly one outcome.');
    }
  }
  // #endregion
}
