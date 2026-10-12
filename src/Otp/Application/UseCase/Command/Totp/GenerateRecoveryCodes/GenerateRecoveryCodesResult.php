<?php

declare(strict_types=1);

namespace Otp\Application\UseCase\Command\Totp\GenerateRecoveryCodes;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * Class GenerateRecoveryCodesResult
 *
 * Carries the one-time plaintext lot to the protected response only.
 *
 * @category Result
 */
final readonly class GenerateRecoveryCodesResult implements ResultMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param list<string> $codes generated recovery proofs
   * @param DateTimeImmutable $generatedAt generation time
   *
   * @return void
   */
  public function __construct(public array $codes, public DateTimeImmutable $generatedAt)
  {
  }
  // #endregion
}
