<?php

declare(strict_types=1);

namespace Otp\Presentation\Api\Processor\Challenge;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Otp\Application\Exception\OtpNotFoundException;
use Otp\Application\UseCase\Command\Challenge\VerifyOtp\{VerifyOtpCommand, VerifyOtpResult};
use Otp\Presentation\Api\Dto\Input\Challenge\VerifyOtpInput;
use Otp\Presentation\Api\Dto\Output\Challenge\VerifyOtpOutput;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Infrastructure\Exception\MessengerRuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\{BadRequestHttpException, NotFoundHttpException};
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Throwable;

use function ctype_digit;
use function is_string;
use function strlen;

/**
 * Processor VerifyOtpProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<VerifyOtpInput, VerifyOtpOutput>
 */
final readonly class VerifyOtpProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @param CommandBusPort $commandBus the command bus
   * @param int $otpCodeLength expected OTP length
   */
  public function __construct(
    private CommandBusPort $commandBus,
    #[Autowire('%env(int:OTP_CODE_LENGTH)%')]
    private readonly int $otpCodeLength = 6,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * @param VerifyOtpInput $data
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): VerifyOtpOutput
  {
    $otpId = $uriVariables['id'] ?? null;
    $challengeToken = $uriVariables['token'] ?? null;

    if (!is_string($otpId) && !is_string($challengeToken)) {
      throw new NotFoundHttpException('OTP ID or challenge token is required.');
    }

    if (!$this->isValidOtpCode($data->code)) {
      throw new BadRequestHttpException('Invalid code format.');
    }

    try {
      $command = new VerifyOtpCommand(
        otpId: is_string($otpId) ? $otpId : null,
        challengeToken: is_string($challengeToken) ? $challengeToken : null,
        code: $data->code,
      );

      /** @var VerifyOtpResult $result */
      $result = $this->commandBus->dispatch($command);

      $output = new VerifyOtpOutput();
      $output->success = $result->success;
      $output->attemptsRemaining = $result->attemptsRemaining;
      $output->error = $result->error;

      return $output;
    } catch (Throwable $exception) {
      $otpNotFound = $this->extractOtpNotFoundException($exception);
      if (null !== $otpNotFound) {
        throw new NotFoundHttpException('OTP not found.');
      }

      throw $exception;
    }
  }

  private function extractOtpNotFoundException(Throwable $exception): ?OtpNotFoundException
  {
    if ($exception instanceof OtpNotFoundException) {
      return $exception;
    }

    if ($exception instanceof HandlerFailedException) {
      foreach ($exception->getWrappedExceptions() as $nestedException) {
        if ($nestedException instanceof OtpNotFoundException) {
          return $nestedException;
        }
      }

      return null;
    }

    if ($exception instanceof MessengerRuntimeException) {
      $previous = $exception->getPrevious();

      if ($previous instanceof HandlerFailedException) {
        foreach ($previous->getWrappedExceptions() as $nestedException) {
          if ($nestedException instanceof OtpNotFoundException) {
            return $nestedException;
          }
        }
      }

      while ($previous) {
        if ($previous instanceof OtpNotFoundException) {
          return $previous;
        }

        $previous = $previous->getPrevious();
      }
    }

    return null;
  }

  private function isValidOtpCode(string $code): bool
  {
    return strlen($code) === $this->otpCodeLength && ctype_digit($code);
  }
  // #endregion
}
