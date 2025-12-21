<?php

declare(strict_types=1);

namespace Otp\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use DateTimeImmutable;
use Otp\Application\UseCase\Command\GenerateOtp\GenerateOtpCommand;
use Otp\Application\UseCase\Command\GenerateOtp\GenerateOtpHandler;
use Otp\Domain\ValueObject\OtpChannel;
use Otp\Domain\ValueObject\OtpContext;
use Otp\Domain\ValueObject\OtpMetadata;
use Otp\Domain\ValueObject\OtpPurpose;
use Otp\Presentation\Api\Dto\ChallengeOutput;
use Otp\Presentation\Api\Dto\CreateChallengeInput;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
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
   * @param GenerateOtpHandler $handler      the handler
   * @param Security           $security     the security service
   * @param RequestStack       $requestStack the request stack
   */
  public function __construct(
    private GenerateOtpHandler $handler,
    private Security $security,
    private RequestStack $requestStack,
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
    $request = $this->requestStack->getCurrentRequest();

    // Get recipient - use provided or get from user
    $recipient = $data->recipient ?? $this->getDefaultRecipient($data->channel, $user);

    if (null === $recipient) {
      throw new BadRequestHttpException('Recipient is required for this channel.');
    }

    // Build metadata from request
    $metadata = OtpMetadata::create(
      ipAddress: $request?->getClientIp(),
      userAgent: $request?->headers->get('User-Agent'),
    );

    // Build context from input
    $otpContext = null;
    if (null !== $data->context) {
      $otpContext = OtpContext::fromArray($data->context);
    }

    $command = new GenerateOtpCommand(
      userId: $userId,
      purpose: OtpPurpose::from($data->purpose),
      channel: OtpChannel::from($data->channel),
      recipient: $recipient,
      ttlSeconds: $data->ttlSeconds,
    );

    $result = $this->handler->__invoke($command);

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
    $output->canResendIn = self::RESEND_COOLDOWN_SECONDS;

    return $output;
  }

  /**
   * Method getDefaultRecipient.
   *
   * Gets the default recipient from user.
   *
   * @param string $channel the channel
   * @param object $user    the user
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
