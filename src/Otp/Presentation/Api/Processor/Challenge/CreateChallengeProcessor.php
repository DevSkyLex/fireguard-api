<?php

declare(strict_types=1);

namespace Otp\Presentation\Api\Processor\Challenge;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use DateTimeImmutable;
use Otp\Application\Contract\Challenge\{OtpChannel, OtpPurpose};
use Otp\Application\Service\ChallengeResendPolicy;
use Otp\Application\UseCase\Command\Challenge\GenerateOtp\{GenerateOtpCommand, GenerateOtpResult};
use Otp\Presentation\Api\Dto\Input\Challenge\CreateChallengeInput;
use Otp\Presentation\Api\Dto\Output\Challenge\ChallengeOutput;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

use function max;
use function method_exists;

/**
 * Processor CreateChallengeProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<CreateChallengeInput, ChallengeOutput>
 */
final readonly class CreateChallengeProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @param CommandBusPort $commandBus the command bus
   * @param Security $security the security service
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private Security $security,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * @param CreateChallengeInput $data
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ChallengeOutput
  {
    $user = $this->security->getUser();
    if (null === $user) {
      throw new BadRequestHttpException('User must be authenticated.');
    }

    $userId = $user->getUserIdentifier();

    // Get recipient - use provided or get from user
    $recipient = $data->recipient ?? $this->getDefaultRecipient($data->channel, $user);

    if (null === $recipient) {
      throw new BadRequestHttpException('Recipient is required for this channel.');
    }

    $command = new GenerateOtpCommand(
      userId: $userId,
      purpose: OtpPurpose::from($data->purpose),
      channel: OtpChannel::from($data->channel),
      recipient: $recipient,
      ttlSeconds: $data->ttlSeconds,
    );

    /** @var GenerateOtpResult $result */
    $result = $this->commandBus->dispatch($command);

    // Calculate times
    $now = new DateTimeImmutable();
    $expiresIn = max(0, $result->expiresAt->getTimestamp() - $now->getTimestamp());

    $output = new ChallengeOutput();
    $output->token = $result->token;
    $output->purpose = $data->purpose;
    $output->channel = $data->channel;
    $output->maskedRecipient = $result->maskedRecipient;
    $output->status = 'pending';
    $output->expiresIn = $expiresIn;
    $output->attemptsRemaining = $result->maxAttempts;
    $output->canResendIn = ChallengeResendPolicy::RESEND_COOLDOWN_SECONDS;

    return $output;
  }

  /**
   * Method getDefaultRecipient.
   *
   * Gets the default recipient from user.
   *
   * @param string $channel the channel
   * @param object $user the user
   *
   * @return string|null the recipient
   */
  private function getDefaultRecipient(string $channel, object $user): ?string
  {
    // Try to get email from user
    if ('email' === $channel && method_exists($user, 'getEmail')) {
      /** @var string|null */
      return $user->getEmail();
    }

    // Try to get phone from user
    if ('sms' === $channel && method_exists($user, 'getPhone')) {
      /** @var string|null */
      return $user->getPhone();
    }

    return null;
  }
  // #endregion
}
