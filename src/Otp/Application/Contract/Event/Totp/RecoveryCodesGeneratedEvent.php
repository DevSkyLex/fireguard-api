<?php

declare(strict_types=1);

namespace Otp\Application\Contract\Event\Totp;

use DateTimeImmutable;

/**
 * Class RecoveryCodesGeneratedEvent
 *
 * Publishes a durable recovery lot creation fact without proofs, digests or authenticator material.
 *
 * @category Event
 */
final readonly class RecoveryCodesGeneratedEvent
{
  // #region Properties
  /**
   * Property occurredAt
   *
   * Original lot generation time.
   */
  public DateTimeImmutable $occurredAt;
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param string $userId enrollment owner
   * @param int $count generated proof count
   * @param bool $regenerated whether an existing lot was replaced
   * @param DateTimeImmutable $generatedAt lot generation time
   *
   * @return void
   */
  public function __construct(public string $userId, public int $count, public bool $regenerated, public DateTimeImmutable $generatedAt)
  {
    $this->occurredAt = $generatedAt;
  }
  // #endregion
}
