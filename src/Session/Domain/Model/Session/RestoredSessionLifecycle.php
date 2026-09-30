<?php

declare(strict_types=1);

namespace Session\Domain\Model\Session;

use DateTimeImmutable;

/**
 * Timestamps restored from a persisted session.
 *
 * @category Model
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RestoredSessionLifecycle
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Carries persisted session creation, activity and revocation timestamps.
   *
   * @access public
   *
   * @param DateTimeImmutable $createdAt session creation timestamp
   * @param DateTimeImmutable $lastActivityAt most recent recorded session activity
   * @param ?DateTimeImmutable $revokedAt optional time when the session was revoked
   *
   * @return void
   */
  public function __construct(
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $lastActivityAt,
    public ?DateTimeImmutable $revokedAt,
  ) {
  }
  // #endregion
}
