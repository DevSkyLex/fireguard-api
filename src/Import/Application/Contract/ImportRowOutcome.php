<?php

declare(strict_types=1);

namespace Import\Application\Contract;

/**
 * Contract ImportRowOutcome
 *
 * Distinguishes confirmed row success from business rejection without exposing owner types.
 * Technical failures must propagate instead of being returned as row outcomes.
 *
 * @category Contract
 */
final readonly class ImportRowOutcome
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param bool $successful whether the engine records a success
   * @param ?string $resourceId durable created identifier, absent for simulations and failures
   * @param ?string $code safe report code, absent for unreported real successes
   * @param ?string $message safe report message
   * @param ?string $column offending source column, when known
   *
   * @return void
   */
  public function __construct(
    public bool $successful,
    public ?string $resourceId = null,
    public ?string $code = null,
    public ?string $message = null,
    public ?string $column = null,
  ) {
  }
  // #endregion
}
