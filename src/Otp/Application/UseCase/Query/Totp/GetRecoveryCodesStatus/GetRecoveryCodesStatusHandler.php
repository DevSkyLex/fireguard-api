<?php

declare(strict_types=1);

namespace Otp\Application\UseCase\Query\Totp\GetRecoveryCodesStatus;

use Otp\Application\Port\Outbound\Internal\Totp\TotpEnrollmentRepositoryPort;
use Otp\Domain\Exception\TotpEnrollmentUnavailableException;
use Shared\Application\Message\QueryHandler;
use User\Application\Port\Inbound\AccountStatusPort;

/**
 * Class GetRecoveryCodesStatusHandler
 *
 * Reads recovery availability from the owning enrollment only.
 *
 * @category Handler
 */
final readonly class GetRecoveryCodesStatusHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param TotpEnrollmentRepositoryPort $enrollments owning persistence
   * @param AccountStatusPort $accounts fresh account eligibility
   *
   * @return void
   */
  public function __construct(private TotpEnrollmentRepositoryPort $enrollments, private AccountStatusPort $accounts)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke
   *
   * @access public
   *
   * @param GetRecoveryCodesStatusQuery $query authenticated owner lookup
   *
   * @return GetRecoveryCodesStatusResult safe recovery metadata
   */
  public function __invoke(GetRecoveryCodesStatusQuery $query): GetRecoveryCodesStatusResult
  {
    if (!$this->accounts->isActive($query->userId)) {
      throw TotpEnrollmentUnavailableException::forAccount();
    }
    $enrollment = $this->enrollments->findByUserId($query->userId);
    if (null === $enrollment || !$enrollment->isActive()) {
      return new GetRecoveryCodesStatusResult(0, null);
    }

    return new GetRecoveryCodesStatusResult($enrollment->recoveryCodesRemaining(), $enrollment->recoveryCodesGeneratedAt());
  }
  // #endregion
}
