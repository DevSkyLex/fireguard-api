<?php

declare(strict_types=1);

namespace Otp\Application\UseCase\Query\Challenge\GetChallengeStatus;

use Shared\Application\Message\QueryMessage;

/**
 * Query GetChallengeStatusQuery.
 *
 * @category Query
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetChallengeStatusQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * GetChallengeStatusQuery class.
   *
   * @since 1.0.0
   *
   * @param string $challengeToken the challenge token
   */
  public function __construct(
    public readonly string $challengeToken,
  ) {
  }
  // #endregion
}
