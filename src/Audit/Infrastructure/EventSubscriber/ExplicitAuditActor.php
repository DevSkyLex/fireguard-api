<?php

declare(strict_types=1);

namespace Audit\Infrastructure\EventSubscriber;

/** Actor identity supplied by an event rather than the current request. */
final readonly class ExplicitAuditActor
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Carries an explicitly supplied actor identity for audit attribution when the caller provides one.
   *
   * @access public
   *
   * @param ?string $userId explicit actor account identifier, or null when no user is attributed
   * @param ?string $email explicit actor email used for audit context, when supplied
   *
   * @return void
   */
  public function __construct(
    public ?string $userId,
    public ?string $email = null,
  ) {
  }
  // #endregion
}
