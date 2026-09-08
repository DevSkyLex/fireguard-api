<?php

declare(strict_types=1);

namespace User\Application\UseCase\Command\EmailChange\CancelEmailChange;

use Shared\Application\Message\ResultMessage;

/**
 * Class CancelEmailChangeResult.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CancelEmailChangeResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param bool $cancelled whether a pending request was actually cancelled
   *                        (false when there was nothing to cancel — the
   *                        endpoint stays idempotent and answers 204 anyway)
   */
  public function __construct(
    public bool $cancelled,
  ) {
  }
  // #endregion
}
