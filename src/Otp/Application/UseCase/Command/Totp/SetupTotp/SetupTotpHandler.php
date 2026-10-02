<?php

declare(strict_types=1);

namespace Otp\Application\UseCase\Command\Totp\SetupTotp;

use Otp\Application\Port\Outbound\Totp\{TotpEnrollmentRepositoryPort, TotpServicePort};
use Otp\Domain\Exception\{TotpEnrollmentAlreadyActiveException, TotpEnrollmentUnavailableException};
use Otp\Domain\Model\Totp\TotpEnrollment;
use Shared\Application\Message\CommandHandler;
use User\Application\Port\Inbound\AccountStatusPort;

/**
 * Handler SetupTotpHandler.
 *
 * Generates a new TOTP secret and stores it server-side as a PENDING
 * (unconfirmed) enrollment for the authenticated user. The secret is never
 * trusted from the client. Calling this again replaces the pending secret;
 * an ACTIVE factor must first be disabled with its current authenticator code.
 *
 * @category Handler
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SetupTotpHandler implements CommandHandler
{
  // #region Constants
  /**
   * Constant DEFAULT_MAX_ATTEMPTS.
   *
   * Maximum confirmation attempts allowed against a pending secret before
   * it must be regenerated via setup again.
   *
   * @since 1.0.0
   *
   * @var int
   */
  private const int DEFAULT_MAX_ATTEMPTS = 5;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @param TotpServicePort $totpService the TOTP service
   * @param TotpEnrollmentRepositoryPort $enrollmentRepository the enrollment repository
   * @param AccountStatusPort $accountStatus the fresh auth-side account eligibility check
   */
  public function __construct(
    private TotpServicePort $totpService,
    private TotpEnrollmentRepositoryPort $enrollmentRepository,
    private AccountStatusPort $accountStatus,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Handles the SetupTotpCommand.
   *
   * @param SetupTotpCommand $command the command
   *
   * @return SetupTotpResult the result
   */
  public function __invoke(SetupTotpCommand $command): SetupTotpResult
  {
    return $this->enrollmentRepository->withUserLock($command->userId, function () use ($command): SetupTotpResult {
      if (!$this->accountStatus->isActive($command->userId)) {
        throw TotpEnrollmentUnavailableException::forAccount();
      }

      $enrollment = $this->enrollmentRepository->findByUserId($command->userId);
      if (null !== $enrollment && $enrollment->isActive()) {
        throw TotpEnrollmentAlreadyActiveException::forUser();
      }
      $secret = $this->totpService->generateSecret();

      if (null === $enrollment) {
        $enrollment = TotpEnrollment::startEnrollment(
          userId: $command->userId,
          secret: $secret,
          maxAttempts: self::DEFAULT_MAX_ATTEMPTS,
        );
      } else {
        $enrollment->requestNewSecret(
          secret: $secret,
          maxAttempts: self::DEFAULT_MAX_ATTEMPTS,
        );
      }

      $this->enrollmentRepository->save($enrollment);

      // Generate provisioning URI for QR code
      $qrCodeUri = $this->totpService->getProvisioningUri(
        secret: $secret,
        accountName: $command->accountName,
        issuer: 'Fireguard Auth',
      );

      return new SetupTotpResult(
        secret: $secret->secret,
        qrCodeUri: $qrCodeUri,
      );
    });
  }
  // #endregion
}
