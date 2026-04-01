<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Processor\Auth;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Application\UseCase\Command\Mfa\MfaVerify\{MfaVerifyCommand, MfaVerifyHandler};
use Auth\Domain\Exception\Session\AuthorizationException;
use Auth\Presentation\Api\Dto\Input\Auth\MfaVerifyInput;
use Auth\Presentation\Api\Dto\Output\Auth\LoginOutput;
use Auth\Presentation\Api\Service\RefreshTokenCookieService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{BadRequestHttpException, TooManyRequestsHttpException, UnauthorizedHttpException};
use Symfony\Component\RateLimiter\RateLimiterFactory;

use function ctype_digit;
use function hash;
use function implode;
use function max;
use function sprintf;
use function strlen;
use function substr;
use function time;

/**
 * Processor MfaVerifyProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<MfaVerifyInput, LoginOutput>
 */
final readonly class MfaVerifyProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes the processor with dependencies.
   *
   * @since 1.0.0
   *
   * @param MfaVerifyHandler $handler the MFA verify handler
   * @param RequestStack $requestStack the request stack
   * @param RefreshTokenCookieService $cookieService the cookie service
   * @param int $otpCodeLength expected OTP length
   */
  public function __construct(
    private readonly MfaVerifyHandler $handler,
    private readonly RequestStack $requestStack,
    private readonly RefreshTokenCookieService $cookieService,
    #[Autowire('%env(int:OTP_CODE_LENGTH)%')]
    private readonly int $otpCodeLength = 6,
    #[Autowire(service: 'limiter.mfa_verify')]
    private readonly ?RateLimiterFactory $rateLimiter = null,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method process
   * {@inheritDoc}
   *
   * Processes the MFA verification request.
   *
   * @since 1.0.0
   *
   * @param MfaVerifyInput $data the input data
   * @param Operation $operation the operation
   * @param array<string, mixed> $uriVariables the URI variables
   * @param array<string, mixed> $context the context
   *
   * @return LoginOutput the output
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): LoginOutput
  {
    $request = $this->requestStack->getCurrentRequest();
    $ipAddress = $request?->getClientIp() ?? '127.0.0.1';

    $this->enforceRateLimit($data->preAuthToken, $ipAddress);

    if (!$this->isValidOtpCode($data->code)) {
      throw new BadRequestHttpException('Invalid code format.');
    }

    // Dispatch command to handler
    $command = new MfaVerifyCommand(
      preAuthToken: $data->preAuthToken,
      code: $data->code,
      ipAddress: $ipAddress,
      userAgent: $request?->headers->get('User-Agent'),
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
      throw new BadRequestHttpException(
        message: $result->error ?? 'Invalid code',
      );
    }

    // Build output
    $output = new LoginOutput();
    $output->accessToken = $result->accessToken;
    $output->tokenType = $result->tokenType;
    $output->expiresIn = $result->expiresIn;
    $output->scope = implode(' ', $result->scopes);

    // Handle Refresh Token Cookie
    if (null !== $result->refreshToken) {
      $cookie = $this->cookieService->createCookie(
        refreshToken: $result->refreshToken,
        rememberMe: $result->rememberMe,
      );

      $request?->attributes->set(
        key: '_refresh_token_cookie',
        value: $cookie,
      );
    }

    return $output;
  }

  private function isValidOtpCode(string $code): bool
  {
    return strlen($code) === $this->otpCodeLength && ctype_digit($code);
  }

  private function enforceRateLimit(string $preAuthToken, string $ipAddress): void
  {
    if (null === $this->rateLimiter) {
      return;
    }

    $limit = $this->rateLimiter->create($this->getRateLimitKey($preAuthToken, $ipAddress))->consume();
    if ($limit->isAccepted()) {
      return;
    }

    $retryAfter = $limit->getRetryAfter();
    $seconds = max(0, $retryAfter->getTimestamp() - time());

    throw new TooManyRequestsHttpException(
      $seconds,
      sprintf('Too many MFA verification attempts. Please try again in %d seconds.', $seconds),
    );
  }

  private function getRateLimitKey(string $preAuthToken, string $ipAddress): string
  {
    $tokenHash = hash('sha256', $preAuthToken);
    $ipHash = hash('sha256', $ipAddress);

    return sprintf('mfa_verify_%s_%s', substr($tokenHash, 0, 16), substr($ipHash, 0, 16));
  }
  // #endregion
}
