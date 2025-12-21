<?php

declare(strict_types=1);

namespace Shared\Domain\ValueObject;

/**
 * Class RateLimitResult.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RateLimitResult
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * RateLimitResult class.
   *
   * @since 1.0.0
   *
   * @param bool $accepted        whether the request is accepted
   * @param int  $remainingTokens remaining tokens
   * @param int  $retryAfter      seconds to wait before retry (if rejected)
   */
  public function __construct(
    public bool $accepted,
    public int $remainingTokens = 0,
    public int $retryAfter = 0,
  ) {
  }
  // #endregion

  // #region Factory Methods
  /**
   * Method accepted.
   *
   * Creates an accepted result.
   *
   * @since 1.0.0
   *
   * @param int $remainingTokens remaining tokens
   *
   * @return self the result
   */
  public static function accepted(int $remainingTokens = 0): self
  {
    return new self(
      accepted: true,
      remainingTokens: $remainingTokens,
    );
  }

  /**
   * Method rejected.
   *
   * Creates a rejected result.
   *
   * @since 1.0.0
   *
   * @param int $retryAfter seconds to wait
   *
   * @return self the result
   */
  public static function rejected(int $retryAfter): self
  {
    return new self(
      accepted: false,
      retryAfter: $retryAfter,
    );
  }
  // #endregion
}
