<?php

declare(strict_types=1);

namespace User\Application\Contract;

use DateTimeImmutable;

/**
 * Contract EmailOwnershipResult.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class EmailOwnershipResult
{
  // #region Constructor
  /**
   * @since 1.0.0
   *
   * @param string $userId the active account identifier
   * @param string $email the current normalized account email
   * @param bool $verified whether Fireguard has proven possession of that address
   * @param string $locale the preferred notification language
   * @param DateTimeImmutable|null $verifiedAt the current proof generation, changed after an address update
   */
  public function __construct(public string $userId, public string $email, public bool $verified, public string $locale = 'en', public ?DateTimeImmutable $verifiedAt = null)
  {
  }
  // #endregion
}
