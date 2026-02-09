<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Processor\Auth;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Application\UseCase\Command\Mfa\MfaResend\{MfaResendCommand, MfaResendHandler, MfaResendResult};
use Auth\Domain\Exception\Session\AuthorizationException;
use Auth\Presentation\Api\Dto\Input\Auth\MfaResendInput;
use Auth\Presentation\Api\Dto\Output\Auth\LoginOutput;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{BadRequestHttpException, NotFoundHttpException, TooManyRequestsHttpException, UnauthorizedHttpException};
use Symfony\Component\RateLimiter\RateLimiterFactory;

use function hash;
use function max;
use function sprintf;
use function substr;
use function time;

/**
 * Processor MfaResendProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<MfaResendInput, LoginOutput>
 */
final readonly class MfaResendProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes the processor with dependencies.
   *
   * @since 1.0.0
   *
   * @param MfaResendHandler $handler the MFA resend handler
   * @param RequestStack $requestStack the request stack
   * @param RateLimiterFactory $rateLimiter the MFA resend rate limiter
   */
  public function __construct(
    private readonly MfaResendHandler $handler,
    private readonly RequestStack $requestStack,
    #[Autowire(service: 'limiter.mfa_resend')]
    private readonly RateLimiterFactory $rateLimiter,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method process
   * {@inheritDoc}
   *
   * Processes the MFA resend request.
   *
   * @since 1.0.0
   *
   * @param MfaResendInput $data the input data
   * @param Operation $operation the operation
   * @param array<string, mixed> $uriVariables the URI variables
   * @param array<string, mixed> $context the context
   *
   * @return LoginOutput the output
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): LoginOutput
  {
    /** @phpstan-ignore instanceof.alwaysTrue */
    if (!$data instanceof MfaResendInput) {
      throw new InvalidArgumentException('Invalid input data');
    }

    $request = $this->requestStack->getCurrentRequest();
    $ipAddress = $request?->getClientIp() ?? '127.0.0.1';
    $userAgent = $request?->headers->get('User-Agent');

    $this->enforceRateLimit($data->preAuthToken, $ipAddress);

    $command = new MfaResendCommand(
      preAuthToken: $data->preAuthToken,
      ipAddress: $ipAddress,
      userAgent: $userAgent,
    );

    try {
      $result = $this->handler->__invoke($command);
    } catch (AuthorizationException $exception) {
      throw new UnauthorizedHttpException(
        challenge: 'Bearer',
        message: $exception->getMessage(),
      );
    }

    if (!$result->success) {
      $this->handleError($result);
    }

    $output = new LoginOutput();
    $output->mfaRequired = true;
    $output->mfaToken = $result->preAuthToken;
    $output->challengeToken = $result->challengeToken;
    $output->mfaMethod = $result->mfaMethod;
    $output->mfaDestination = $result->mfaDestination;
    $output->mfaResendIn = $result->canResendIn;

    return $output;
  }

  /**
   * Handle error responses.
   *
   * @param MfaResendResult $result
   *
   * @throws NotFoundHttpException
   * @throws TooManyRequestsHttpException
   * @throws BadRequestHttpException
   */
  private function handleError(MfaResendResult $result): void
  {
    match ($result->errorCode) {
      MfaResendResult::ERROR_RESEND_NOT_ALLOWED => throw new TooManyRequestsHttpException(
        $result->retryAfter,
        $result->message ?? 'Resend not allowed yet.',
      ),
      MfaResendResult::ERROR_INVALID_CHALLENGE => throw new NotFoundHttpException(
        $result->message ?? 'MFA challenge not found.',
      ),
      default => throw new BadRequestHttpException(
        $result->message ?? 'MFA resend failed.',
      ),
    };
  }

  private function enforceRateLimit(string $preAuthToken, string $ipAddress): void
  {
    $key = $this->getRateLimitKey($preAuthToken, $ipAddress);
    $limit = $this->rateLimiter->create($key)->consume();

    if ($limit->isAccepted()) {
      return;
    }

    $retryAfter = $limit->getRetryAfter();
    $seconds = max(0, $retryAfter->getTimestamp() - time());

    throw new TooManyRequestsHttpException(
      $seconds,
      sprintf('Too many MFA resend requests. Please try again in %d seconds.', $seconds),
    );
  }

  /**
   * Generates a rate limit key based on pre-auth token and IP.
   *
   * @param string $preAuthToken the pre-auth token
   * @param string $ipAddress the client IP address
   *
   * @return string the rate limit key
   */
  private function getRateLimitKey(string $preAuthToken, string $ipAddress): string
  {
    $tokenHash = hash('sha256', $preAuthToken);
    $ipHash = hash('sha256', $ipAddress);

    return sprintf('mfa_resend_%s_%s', substr($tokenHash, 0, 16), substr($ipHash, 0, 16));
  }
  // #endregion
}
