<?php

declare(strict_types=1);

namespace Otp\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Otp\Application\UseCase\Command\GenerateOtp\GenerateOtpCommand;
use Otp\Application\UseCase\Command\GenerateOtp\GenerateOtpHandler;
use Otp\Domain\ValueObject\OtpChannel;
use Otp\Domain\ValueObject\OtpPurpose;
use Otp\Presentation\Api\Dto\GenerateOtpInput;
use Otp\Presentation\Api\Dto\OtpOutput;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Processor GenerateOtpProcessor
 * @final
 *
 * API Platform processor for OTP generation.
 *
 * @category Processor
 * @package Otp\Presentation\Api\Processor
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<GenerateOtpInput, OtpOutput>
 */
final readonly class GenerateOtpProcessor implements ProcessorInterface
{
  //#region Constructor
  /**
   * Constructor
   *
   * @param GenerateOtpHandler $handler The handler.
   * @param Security $security The security service.
   */
  public function __construct(
    private GenerateOtpHandler $handler,
    private Security $security,
  ) {
  }
  //#endregion

  //#region Methods
  /**
   * {@inheritDoc}
   *
   * @param GenerateOtpInput $data
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): OtpOutput
  {
    $user = $this->security->getUser();
    if ($user === null) {
      throw new BadRequestHttpException('User must be authenticated.');
    }

    $userId = $user->getUserIdentifier();

    // Get recipient - use provided or get from user
    $recipient = $data->recipient ?? $this->getDefaultRecipient($data->channel, $user);

    if ($recipient === null) {
      throw new BadRequestHttpException('Recipient is required for this channel.');
    }

    $command = new GenerateOtpCommand(
      userId: $userId,
      purpose: OtpPurpose::from($data->purpose),
      channel: OtpChannel::from($data->channel),
      recipient: $recipient,
      ttlSeconds: $data->ttlSeconds,
    );

    $result = $this->handler->__invoke($command);

    $output = new OtpOutput();
    $output->id = $result->otpId;
    $output->status = 'pending';
    $output->maskedRecipient = $result->maskedRecipient;
    $output->expiresAt = $result->expiresAt;
    $output->attemptsRemaining = $result->maxAttempts;

    return $output;
  }

  /**
   * Method getDefaultRecipient
   *
   * Gets the default recipient from user.
   *
   * @param string $channel The channel.
   * @param object $user The user.
   *
   * @return string|null The recipient.
   */
  private function getDefaultRecipient(string $channel, object $user): ?string
  {
    // Try to get email from user
    if ($channel === 'email' && method_exists($user, 'getEmail')) {
      /** @var string|null */
      return $user->getEmail();
    }

    // Try to get phone from user
    if ($channel === 'sms' && method_exists($user, 'getPhone')) {
      /** @var string|null */
      return $user->getPhone();
    }

    return null;
  }
  //#endregion
}
