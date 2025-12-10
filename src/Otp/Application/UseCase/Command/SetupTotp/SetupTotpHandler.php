<?php

declare(strict_types=1);

namespace Otp\Application\UseCase\Command\SetupTotp;

use Otp\Application\Port\Outbound\TotpServicePort;
use Shared\Application\Message\CommandHandler;

/**
 * Handler SetupTotpHandler
 * @final
 *
 * Handles TOTP setup for users.
 *
 * @category Handler
 * @package Otp\Application\UseCase\Command\SetupTotp
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SetupTotpHandler implements CommandHandler
{
  //#region Constructor
  /**
   * Constructor
   *
   * @param TotpServicePort $totpService The TOTP service.
   */
  public function __construct(
    private TotpServicePort $totpService,
  ) {
  }
  //#endregion

  //#region Methods
  /**
   * Method __invoke
   *
   * Handles the SetupTotpCommand.
   *
   * @param SetupTotpCommand $command The command.
   *
   * @return SetupTotpResult The result.
   */
  public function __invoke(SetupTotpCommand $command): SetupTotpResult
  {
    // Generate new TOTP secret
    $secret = $this->totpService->generateSecret();

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
  //#endregion
}
