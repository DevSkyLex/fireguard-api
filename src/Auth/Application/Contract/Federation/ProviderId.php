<?php

declare(strict_types=1);

namespace Auth\Application\Contract\Federation;

use InvalidArgumentException;

use function preg_match;

/**
 * Contract ProviderId
 *
 * Open provider identity fitting existing persistence and callback route segments.
 * Availability is a server registry decision, separate from this representation.
 *
 * @category Contract
 */
final readonly class ProviderId
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access private
   *
   * @param string $value validated provider identifier
   *
   * @return void
   */
  private function __construct(public string $value)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method from
   *
   * @access public
   *
   * @param string $value provider identifier
   *
   * @return self validated identifier
   */
  public static function from(string $value): self
  {
    return self::tryFrom($value) ?? throw new InvalidArgumentException('Invalid federated provider identifier.');
  }

  /**
   * Method tryFrom
   *
   * @access public
   *
   * @param string $value provider identifier
   *
   * @return ?self validated identifier, absent for invalid input
   */
  public static function tryFrom(string $value): ?self
  {
    return 1 === preg_match('/^[a-z][a-z0-9_-]{0,19}$/D', $value) ? new self($value) : null;
  }
  // #endregion
}
