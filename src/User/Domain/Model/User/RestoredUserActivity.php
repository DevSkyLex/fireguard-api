<?php

declare(strict_types=1);

namespace User\Domain\Model\User;

use DateTimeImmutable;
use User\Domain\ValueObject\Locale;

/**
 * Timestamps and preferences restored from a previously persisted user.
 *
 * @category Model
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RestoredUserActivity
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Carries persisted account activity and locale during user restoration.
   *
   * @access public
   *
   * @param DateTimeImmutable $createdAt user account creation timestamp
   * @param ?DateTimeImmutable $lastLoginAt optional most recent successful login timestamp
   * @param ?string $lastSignInMethod optional method used for the last sign-in
   * @param Locale $locale preferred locale for user-facing messages
   *
   * @return void
   */
  public function __construct(
    public DateTimeImmutable $createdAt,
    public ?DateTimeImmutable $lastLoginAt,
    public ?string $lastSignInMethod,
    public Locale $locale,
  ) {
  }
  // #endregion
}
