<?php

declare(strict_types=1);

namespace Otp\Application\UseCase\Command\Totp\SetupTotp;

use Otp\Application\Port\Outbound\Totp\{TotpEnrollmentRepositoryPort, TotpServicePort};
use Otp\Domain\Model\Totp\TotpEnrollment;
use Shared\Application\Message\CommandHandler;

/**
 * Handler SetupTotpHandler.
 *
 * Generates a new TOTP secret and stores it server-side as a PENDING
 * (unconfirmed) enrollment for the authenticated user. The secret is never
 * trusted from the client. Calling this again replaces the pending secret;
 * any previously ACTIVE enrollment is left untouched until the new secret
 * is confirmed via {@see \Otp\Application\UseCase\Command\Totp\ConfirmTotp\ConfirmTotpHandler}.
 *
 * @category Handler
 *
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
   */
  public function __construct(
    private TotpServicePort $totpService,
    private TotpEnrollmentRepositoryPort $enrollmentRepository,
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
    // Generate new TOTP secret
    $secret = $this->totpService->generateSecret();

    // Store (or replace) the pending secret server-side
    $enrollment = $this->enrollmentRepository->findByUserId($command->userId);

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
      issuer: 'FireGuard Auth',
    );

    return new SetupTotpResult(
      secret: $secret->secret,
      qrCodeUri: $qrCodeUri,
    );
  }
  // #endregion
}
