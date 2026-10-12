<?php

declare(strict_types=1);

namespace Otp\Application\UseCase\Query\Totp\GetRecoveryCodesStatus;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

/**
 * Class GetRecoveryCodesStatusResult
 *
 * Publishes availability without revealing codes or digests.
 *
 * @category Result
 */
final readonly class GetRecoveryCodesStatusResult implements ResultMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param int $remainingCount unconsumed code count
   * @param ?DateTimeImmutable $generatedAt current lot generation time
   *
   * @return void
   */
  public function __construct(public int $remainingCount, public ?DateTimeImmutable $generatedAt)
  {
  }
  // #endregion
}
