<?php

declare(strict_types=1);

namespace Session\Application\UseCase\Command\Session\UpdateSessionTokens;

use Shared\Application\Message\ResultMessage;

/**
 * Result UpdateSessionTokensResult.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdateSessionTokensResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param bool $updated whether tokens were updated
   */
  public function __construct(
    public bool $updated,
  ) {
  }
  // #endregion
}
