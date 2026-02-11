<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Processor\PasswordReset;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Application\UseCase\Command\PasswordReset\RequestPasswordReset\{RequestPasswordResetCommand, RequestPasswordResetResult};
use Auth\Presentation\Api\Dto\Input\PasswordReset\RequestPasswordResetInput;
use Auth\Presentation\Api\Dto\Output\PasswordReset\RequestPasswordResetOutput;
use InvalidArgumentException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Processor RequestPasswordResetProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<RequestPasswordResetInput, RequestPasswordResetOutput>
 */
final readonly class RequestPasswordResetProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the RequestPasswordResetProcessor class.
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
   * Process the password reset request.
   *
   * @param mixed $data the input data
   * @param Operation $operation the API operation
   * @param array<mixed> $uriVariables URI variables
   * @param array<mixed> $context processing context
   *
   * @return RequestPasswordResetOutput the output
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): RequestPasswordResetOutput
  {
    if (!$data instanceof RequestPasswordResetInput) {
      throw new InvalidArgumentException('Invalid input data');
    }

    $request = $this->requestStack->getCurrentRequest();
    $ipAddress = null !== $request ? ($request->getClientIp() ?? '127.0.0.1') : '127.0.0.1';

    $command = new RequestPasswordResetCommand(
      email: $data->email ?? '',
      ipAddress: $ipAddress,
    );

    /** @var RequestPasswordResetResult $result */
    $result = $this->commandBus->dispatch($command);

    return new RequestPasswordResetOutput(
      success: $result->success,
      message: $result->message ?? 'If an account exists with this email, you will receive a password reset code.',
      challengeToken: $result->challengeToken,
      maskedRecipient: $result->maskedRecipient,
      expiresAt: $result->expiresAt,
      maxAttempts: $result->maxAttempts,
      canResendIn: $result->canResendIn,
    );
  }
  // #endregion
}
