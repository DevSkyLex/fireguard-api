<?php

declare(strict_types=1);

namespace Otp\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Otp\Application\UseCase\Command\VerifyOtp\VerifyOtpCommand;
use Otp\Application\UseCase\Command\VerifyOtp\VerifyOtpHandler;
use Otp\Domain\Exception\OtpNotFoundException;
use Otp\Presentation\Api\Dto\VerifyOtpInput;
use Otp\Presentation\Api\Dto\VerifyOtpOutput;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use function is_string;

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
   * @param VerifyOtpHandler $handler the handler
   */
  public function __construct(
    private VerifyOtpHandler $handler,
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

    if (!is_string($otpId)) {
      throw new NotFoundHttpException('OTP ID is required.');
    }

    try {
      $command = new VerifyOtpCommand(
        otpId: $otpId,
        code: $data->code,
      );

      $result = $this->handler->__invoke($command);

      $output = new VerifyOtpOutput();
      $output->success = $result->success;
      $output->attemptsRemaining = $result->attemptsRemaining;
      $output->error = $result->error;

      return $output;
    } catch (OtpNotFoundException) {
      throw new NotFoundHttpException('OTP not found.');
    }
  }
  // #endregion
}
