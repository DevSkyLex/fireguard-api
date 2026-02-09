<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Processor\PasswordReset;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Application\UseCase\Command\PasswordReset\ResendPasswordReset\{ResendPasswordResetCommand, ResendPasswordResetResult};
use Auth\Presentation\Api\Dto\Input\PasswordReset\ResendPasswordResetInput;
use Auth\Presentation\Api\Dto\Output\PasswordReset\RequestPasswordResetOutput;
use InvalidArgumentException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{BadRequestHttpException, NotFoundHttpException, TooManyRequestsHttpException};
use Symfony\Component\RateLimiter\RateLimiterFactory;

use function hash;
use function max;
use function sprintf;
use function substr;
use function time;

/**
 * Processor ResendPasswordResetProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<ResendPasswordResetInput, RequestPasswordResetOutput>
 */
final readonly class ResendPasswordResetProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the ResendPasswordResetProcessor class.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus
   * @param RequestStack $requestStack the request stack
   * @param RateLimiterFactory $rateLimiter the password reset rate limiter
   */
  public function __construct(
    private CommandBusPort $commandBus,
    private RequestStack $requestStack,
    #[Autowire(service: 'limiter.password_reset')]
    private RateLimiterFactory $rateLimiter,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Process the password reset resend request.
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
    /** @phpstan-ignore instanceof.alwaysTrue */
    if (!$data instanceof ResendPasswordResetInput) {
      throw new InvalidArgumentException('Invalid input data');
    }

    $request = $this->requestStack->getCurrentRequest();
    $ipAddress = null !== $request ? ($request->getClientIp() ?? '127.0.0.1') : '127.0.0.1';

    $token = $data->token ?? '';
    $this->enforceRateLimit($token, $ipAddress);

    $command = new ResendPasswordResetCommand(
      token: $token,
      ipAddress: $ipAddress,
    );

    /** @var ResendPasswordResetResult $result */
    $result = $this->commandBus->dispatch($command);

    if (!$result->success) {
      $this->handleError($result);
    }

    return new RequestPasswordResetOutput(
      success: $result->success,
      message: $result->message ?? 'A new password reset code has been sent.',
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
   * @param ResendPasswordResetResult $result
   *
   * @throws NotFoundHttpException
   * @throws TooManyRequestsHttpException
   * @throws BadRequestHttpException
   */
  private function handleError(ResendPasswordResetResult $result): void
  {
    match ($result->errorCode) {
      ResendPasswordResetResult::ERROR_RESEND_NOT_ALLOWED => throw new TooManyRequestsHttpException(
        $result->retryAfter,
        $result->message ?? 'Resend not allowed yet.',
      ),
      ResendPasswordResetResult::ERROR_INVALID_TOKEN => throw new NotFoundHttpException(
        $result->message ?? 'Reset challenge not found.',
      ),
      default => throw new BadRequestHttpException(
        $result->message ?? 'Password reset resend failed.',
      ),
    };
  }

  private function enforceRateLimit(string $token, ?string $ipAddress): void
  {
    $key = $this->getRateLimitKey($token, $ipAddress);
    $limit = $this->rateLimiter->create($key)->consume();

    if ($limit->isAccepted()) {
      return;
    }

    $retryAfter = $limit->getRetryAfter();
    $seconds = max(0, $retryAfter->getTimestamp() - time());

    throw new TooManyRequestsHttpException(
      $seconds,
      sprintf('Too many password reset resend requests. Please try again in %d seconds.', $seconds),
    );
  }

  /**
   * Generates a rate limit key based on token and IP.
   *
   * @param string $token the challenge token
   * @param string|null $ipAddress the client IP address
   *
   * @return string the rate limit key
   */
  private function getRateLimitKey(string $token, ?string $ipAddress): string
  {
    $tokenHash = hash('sha256', $token);
    $ipHash = hash('sha256', $ipAddress ?? 'unknown');

    return sprintf('password_reset_%s_%s', substr($tokenHash, 0, 16), substr($ipHash, 0, 16));
  }
  // #endregion
}
