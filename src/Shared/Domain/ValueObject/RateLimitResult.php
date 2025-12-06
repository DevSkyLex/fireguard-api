<?php

declare(strict_types=1);

namespace Shared\Domain\ValueObject;

/**
 * Class RateLimitResult
 * @final
 *
 * Result of a rate limit check.
 *
 * @category ValueObject
 * @package Shared\Domain\ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RateLimitResult
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the
   * RateLimitResult class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param bool $accepted Whether the request is accepted.
   * @param int $remainingTokens Remaining tokens.
   * @param int $retryAfter Seconds to wait before retry (if rejected).
   */
  public function __construct(
    public bool $accepted,
    public int $remainingTokens = 0,
    public int $retryAfter = 0,
  ) {}
  //#endregion

  //#region Factory Methods
  /**
   * Method accepted
   *
   * Creates an accepted result.
   *
   * @access public
   * @since 1.0.0
   *
   * @param int $remainingTokens Remaining tokens.
   *
   * @return self The result.
   */
  public static function accepted(int $remainingTokens = 0): self
  {
    return new self(
      accepted: true,
      remainingTokens: $remainingTokens,
    );
  }

  /**
   * Method rejected
   *
   * Creates a rejected result.
   *
   * @access public
   * @since 1.0.0
   *
   * @param int $retryAfter Seconds to wait.
   *
   * @return self The result.
   */
  public static function rejected(int $retryAfter): self
  {
    return new self(
      accepted: false,
      retryAfter: $retryAfter,
    );
  }
  //#endregion
}
