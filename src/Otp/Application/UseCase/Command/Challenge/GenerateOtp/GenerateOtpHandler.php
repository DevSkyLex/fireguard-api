<?php

declare(strict_types=1);

namespace Otp\Application\UseCase\Command\Challenge\GenerateOtp;

use Otp\Application\Port\Outbound\Challenge\{OtpNotifierPort, OtpRepositoryPort};
use Otp\Domain\Model\Otp;
use Otp\Domain\ValueObject\{OtpChannel as DomainOtpChannel, OtpGenerationOptions, OtpId, OtpPurpose as DomainOtpPurpose};
use Shared\Application\Contract\Notification\EmailRequestDetails;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{GeoIpLookupPort, RequestOriginPort};
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

/**
 * Handler GenerateOtpHandler.
 *
 * @category Handler
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GenerateOtpHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the GenerateOtpHandler class.
   *
   * @since 1.0.0
   *
   * @param OtpRepositoryPort $otpRepository the OTP repository
   * @param OtpNotifierPort $otpNotifier the OTP notifier
   * @param UuidFactory $uuidFactory the UUID factory
   * @param int $otpCodeLength the OTP code length
   * @param ?GeoIpLookupPort $geoIp optional local email enrichment
   * @param ?RequestOriginPort $requestOrigin transient origin of the email request
   */
  public function __construct(
    private readonly OtpRepositoryPort $otpRepository,
    private readonly OtpNotifierPort $otpNotifier,
    private readonly UuidFactory $uuidFactory,
    #[Autowire('%env(int:OTP_CODE_LENGTH)%')]
    private readonly int $otpCodeLength = 6,
    private ?GeoIpLookupPort $geoIp = null,
    private ?RequestOriginPort $requestOrigin = null,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Handles the GenerateOtpCommand.
   *
   * @since 1.0.0
   *
   * @param GenerateOtpCommand $command the command
   *
   * @return GenerateOtpResult the result
   */
  public function __invoke(GenerateOtpCommand $command): GenerateOtpResult
  {
    $purpose = DomainOtpPurpose::from(value: $command->purpose->value);
    $channel = DomainOtpChannel::from(value: $command->channel->value);

    // Revoke any existing pending OTPs for this user/purpose
    $this->otpRepository->revokeAllForUser(
      userId: $command->userId,
      purpose: $purpose,
    );

    // Generate new OTP
    $otpId = $this->uuidFactory->create(OtpId::class);

    $otp = Otp::generate(
      id: $otpId,
      userId: $command->userId,
      purpose: $purpose,
      channel: $channel,
      recipient: $command->recipient,
      options: new OtpGenerationOptions($command->ttlSeconds, $command->maxAttempts, $this->otpCodeLength),
    );

    // Persist OTP
    $this->otpRepository->save(otp: $otp);

    // Send notification (if channel requires it)
    if ($channel->requiresDelivery()) {
      $details = null;
      if (DomainOtpChannel::EMAIL === $channel) {
        $origin = null;
        $location = null;

        try {
          $origin = $this->requestOrigin?->current();
        } catch (Throwable) {
          // Optional request context must never prevent code delivery.
        }

        try {
          $location = null === $origin?->ipAddress ? null : $this->geoIp?->locate($origin->ipAddress);
        } catch (Throwable) {
          // Keep the known browser and locale when local geography is unavailable.
        }
        $details = EmailRequestDetails::fromOrigin($origin, $location);
      }

      $this->otpNotifier->send(otp: $otp, details: $details);
    }

    return new GenerateOtpResult(
      otpId: $otpId->value,
      token: $otp->challengeToken()->value,
      maskedRecipient: $otp->maskedRecipient(),
      expiresAt: $otp->expiresAt(),
      maxAttempts: $otp->maxAttempts(),
    );
  }
  // #endregion
}
