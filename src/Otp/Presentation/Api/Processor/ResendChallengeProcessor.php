<?php

declare(strict_types=1);

namespace Otp\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use DateTimeImmutable;
use Otp\Application\UseCase\Command\GenerateOtp\GenerateOtpCommand;
use Otp\Application\UseCase\Command\GenerateOtp\GenerateOtpHandler;
use Otp\Application\UseCase\Query\GetOtpStatus\GetOtpStatusHandler;
use Otp\Application\UseCase\Query\GetOtpStatus\GetOtpStatusQuery;
use Otp\Domain\Exception\OtpNotFoundException;
use Otp\Domain\ValueObject\OtpChannel;
use Otp\Domain\ValueObject\OtpPurpose;
use Otp\Presentation\Api\Dto\ChallengeOutput;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

use function is_string;
use function max;

/**
 * Processor ResendChallengeProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, ChallengeOutput>
 */
final readonly class ResendChallengeProcessor implements ProcessorInterface
{
  // #region Constants
  /**
   * Resend cooldown in seconds.
   */
  private const int RESEND_COOLDOWN_SECONDS = 60;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @param GetOtpStatusHandler $getStatusHandler the status handler
   * @param GenerateOtpHandler  $generateHandler  the generate handler
   * @param Security            $security         the security service
   */
  public function __construct(
    private GetOtpStatusHandler $getStatusHandler,
    private GenerateOtpHandler $generateHandler,
    private Security $security,
  ) {
  }
  // #endregion

  // #region Methods
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ChallengeOutput
  {
    $token = $uriVariables['token'] ?? null;

    if (!is_string($token)) {
      throw new NotFoundHttpException('Challenge token is required.');
    }

    $user = $this->security->getUser();
    if (null === $user) {
      throw new NotFoundHttpException('User must be authenticated.');
    }

    try {
      // Get existing OTP status
      $query = new GetOtpStatusQuery(otpId: $token);
      $result = $this->getStatusHandler->__invoke($query);

      // Check if resend is allowed
      $now = new DateTimeImmutable();
      $createdAt = $result->createdAt ?? $now;
      $elapsed = $now->getTimestamp() - $createdAt->getTimestamp();
      $canResendIn = max(0, self::RESEND_COOLDOWN_SECONDS - $elapsed);

      if ($canResendIn > 0) {
        throw new TooManyRequestsHttpException(
          $canResendIn,
          "Please wait {$canResendIn} seconds before resending.",
        );
      }

      // Check if challenge is still pending
      if ('pending' !== $result->status) {
        throw new NotFoundHttpException('Challenge is no longer active.');
      }

      // Generate new OTP with same parameters
      // Note: In full implementation, use dedicated ResendOtpCommand
      $command = new GenerateOtpCommand(
        userId: $user->getUserIdentifier(),
        purpose: OtpPurpose::from($result->purpose),
        channel: OtpChannel::from($result->channel),
        recipient: $result->recipient ?? '',
        ttlSeconds: null,
      );

      $newResult = $this->generateHandler->__invoke($command);

      // Calculate times
      $expiresIn = max(0, $newResult->expiresAt->getTimestamp() - $now->getTimestamp());

      $output = new ChallengeOutput();
      $output->token = $newResult->otpId; // New token
      $output->purpose = $result->purpose;
      $output->channel = $result->channel;
      $output->maskedRecipient = $newResult->maskedRecipient;
      $output->status = 'pending';
      $output->expiresIn = $expiresIn;
      $output->attemptsRemaining = $newResult->maxAttempts;
      $output->canResendIn = self::RESEND_COOLDOWN_SECONDS;

      return $output;
    } catch (OtpNotFoundException) {
      throw new NotFoundHttpException('Challenge not found.');
    }
  }
  // #endregion
}
