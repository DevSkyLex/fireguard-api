<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Processor\PasswordReset;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Application\UseCase\Command\PasswordReset\ConfirmPasswordReset\{ConfirmPasswordResetCommand, ConfirmPasswordResetResult};
use Auth\Presentation\Api\Dto\Input\PasswordReset\ConfirmPasswordResetInput;
use Auth\Presentation\Api\Dto\Output\PasswordReset\ConfirmPasswordResetOutput;
use InvalidArgumentException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{BadRequestHttpException, TooManyRequestsHttpException, UnauthorizedHttpException};

/**
 * Processor ConfirmPasswordResetProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<ConfirmPasswordResetInput, ConfirmPasswordResetOutput>
 */
final readonly class ConfirmPasswordResetProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the ConfirmPasswordResetProcessor class.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus
   * @param RequestStack $requestStack the request stack
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private RequestStack $requestStack,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method process.
   *
   * Process the password reset confirmation.
   *
   * @param mixed $data the input data
   * @param Operation $operation the API operation
   * @param array<mixed> $uriVariables URI variables
   * @param array<mixed> $context processing context
   *
   * @throws UnauthorizedHttpException when token/code is invalid
   * @throws TooManyRequestsHttpException when max attempts exceeded
   * @throws BadRequestHttpException when request is malformed
   *
   * @return ConfirmPasswordResetOutput the output
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ConfirmPasswordResetOutput
  {
    if (!$data instanceof ConfirmPasswordResetInput) {
      throw new InvalidArgumentException(message: 'Invalid input data');
    }

    $request = $this->requestStack->getCurrentRequest();
    $ipAddress = null !== $request ? ($request->getClientIp() ?? '127.0.0.1') : '127.0.0.1';

    $command = new ConfirmPasswordResetCommand(
      token: $data->token ?? '',
      code: $data->code ?? '',
      newPassword: $data->newPassword ?? '',
      ipAddress: $ipAddress,
    );

    /** @var ConfirmPasswordResetResult $result */
    $result = $this->commandBus->dispatch($command);

    if (!$result->success) {
      $this->handleError($result);
    }

    return new ConfirmPasswordResetOutput(
      success: $result->success,
      message: $result->message ?? 'Password has been reset successfully.',
      errorCode: $result->errorCode,
      attemptsRemaining: $result->attemptsRemaining,
    );
  }

  /**
   * Handle error responses.
   *
   * @throws UnauthorizedHttpException
   * @throws TooManyRequestsHttpException
   * @throws BadRequestHttpException
   */
  private function handleError(ConfirmPasswordResetResult $result): void
  {
    match ($result->errorCode) {
      ConfirmPasswordResetResult::ERROR_MAX_ATTEMPTS => throw new TooManyRequestsHttpException(
        null,
        $result->message ?? 'Maximum verification attempts exceeded.',
      ),
      ConfirmPasswordResetResult::ERROR_EXPIRED,
      ConfirmPasswordResetResult::ERROR_INVALID_TOKEN,
      ConfirmPasswordResetResult::ERROR_INVALID_CODE => throw new UnauthorizedHttpException(
        '',
        $result->message ?? 'Invalid or expired token/code.',
      ),
      default => throw new BadRequestHttpException(
        $result->message ?? 'Password reset failed.',
      ),
    };
  }
  // #endregion
}
