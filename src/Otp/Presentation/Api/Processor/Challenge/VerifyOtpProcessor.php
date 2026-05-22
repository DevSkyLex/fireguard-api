<?php

declare(strict_types=1);

namespace Otp\Presentation\Api\Processor\Challenge;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Otp\Application\Exception\OtpNotFoundException;
use Otp\Application\UseCase\Command\Challenge\VerifyOtp\{VerifyOtpCommand, VerifyOtpResult};
use Otp\Presentation\Api\Dto\Input\Challenge\VerifyOtpInput;
use Otp\Presentation\Api\Dto\Output\Challenge\VerifyOtpOutput;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\{BadRequestHttpException, NotFoundHttpException, TooManyRequestsHttpException};
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Throwable;

use function ctype_digit;
use function hash;
use function is_string;
use function max;
use function sprintf;
use function strlen;
use function substr;
use function time;

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
    #[Autowire(service: 'limiter.otp_challenge_verify')]
    private readonly ?RateLimiterFactory $rateLimiter = null,
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

    $this->enforceRateLimit(is_string($challengeToken) ? $challengeToken : $otpId);

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

  private function enforceRateLimit(?string $identifier): void
  {
    if (null === $this->rateLimiter || null === $identifier || '' === $identifier) {
      return;
    }

    $limit = $this->rateLimiter->create($this->getRateLimitKey($identifier))->consume();
    if ($limit->isAccepted()) {
      return;
    }

    $retryAfter = $limit->getRetryAfter();
    $seconds = max(0, $retryAfter->getTimestamp() - time());

    throw new TooManyRequestsHttpException(
      $seconds,
      sprintf('Too many OTP verification attempts. Please try again in %d seconds.', $seconds),
    );
  }

  private function getRateLimitKey(string $identifier): string
  {
    $identifierHash = hash('sha256', $identifier);

    return sprintf('otp_challenge_verify_%s', substr($identifierHash, 0, 16));
  }
  // #endregion
}
