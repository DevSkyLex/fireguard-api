<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Processor\Registration;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Application\UseCase\Command\Registration\ResendRegistration\{ResendRegistrationCommand, ResendRegistrationResult};
use Auth\Presentation\Api\Dto\Input\Registration\ResendRegistrationInput;
use Auth\Presentation\Api\Dto\Output\Registration\RegisterOutput;
use InvalidArgumentException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Component\HttpKernel\Exception\{NotFoundHttpException, TooManyRequestsHttpException};

/**
 * Processor ResendRegistrationProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<ResendRegistrationInput, RegisterOutput>
 */
final readonly class ResendRegistrationProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the ResendRegistrationProcessor class.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus
   */
  public function __construct(
    private CommandBusPort $commandBus,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method process.
   *
   * Processes the verification-code resend request.
   *
   * @param mixed $data the input data
   * @param Operation $operation the API operation
   * @param array<mixed> $uriVariables URI variables
   * @param array<mixed> $context processing context
   *
   * @throws NotFoundHttpException when the challenge is unknown or expired
   * @throws TooManyRequestsHttpException when the resend cooldown is not elapsed
   *
   * @return RegisterOutput the output
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): RegisterOutput
  {
    if (!$data instanceof ResendRegistrationInput) {
      throw new InvalidArgumentException('Invalid input data');
    }

    $command = new ResendRegistrationCommand(
      token: $data->token ?? '',
    );

    /** @var ResendRegistrationResult $result */
    $result = $this->commandBus->dispatch($command);

    if (!$result->success) {
      $this->handleError($result);
    }

    return new RegisterOutput(
      success: $result->success,
      message: $result->message ?? 'A new verification code has been sent.',
      challengeToken: $result->challengeToken,
      maskedRecipient: $result->maskedRecipient,
      expiresAt: $result->expiresAt,
      maxAttempts: $result->maxAttempts,
      canResendIn: $result->canResendIn,
    );
  }

  /**
   * Handle error responses.
   *
   * @throws NotFoundHttpException
   * @throws TooManyRequestsHttpException
   */
  private function handleError(ResendRegistrationResult $result): void
  {
    match ($result->errorCode) {
      ResendRegistrationResult::ERROR_RESEND_NOT_ALLOWED => throw new TooManyRequestsHttpException(
        $result->retryAfter,
        $result->message ?? 'Please wait before resending.',
      ),
      default => throw new NotFoundHttpException(
        $result->message ?? 'Verification challenge not found or no longer valid.',
      ),
    };
  }
  // #endregion
}
