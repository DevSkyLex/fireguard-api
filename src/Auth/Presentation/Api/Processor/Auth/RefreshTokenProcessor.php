<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Processor\Auth;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Application\Port\Outbound\JwtTokenServicePort;
use Auth\Application\UseCase\Query\Session\RefreshToken\{RefreshTokenQuery, RefreshTokenResult};
use Auth\Presentation\Api\Dto\Output\Auth\LoginOutput;
use Auth\Presentation\Api\Service\RefreshTokenCookieService;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{TooManyRequestsHttpException, UnauthorizedHttpException};
use Symfony\Component\RateLimiter\RateLimiterFactory;

use function array_key_exists;
use function hash;
use function implode;
use function is_array;
use function max;
use function sprintf;
use function substr;
use function time;

/**
 * Processor RefreshTokenProcessor.
 *
 * @category Processor
 *
 * @version 3.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<object, LoginOutput|null>
 */
final readonly class RefreshTokenProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * RefreshTokenProcessor class.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus
   * @param RequestStack $requestStack the request stack
   * @param RefreshTokenCookieService $cookieService the cookie service
   */
  public function __construct(
    private readonly QueryBusPort $queryBus,
    private readonly RequestStack $requestStack,
    private readonly RefreshTokenCookieService $cookieService,
    private readonly JwtTokenServicePort $jwtService,
    #[Autowire(service: 'limiter.token_refresh')]
    private readonly ?RateLimiterFactory $rateLimiter = null,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method process
   * {@inheritDoc}
   *
   * Processes the token refresh request.
   *
   * @since 1.0.0
   *
   * @param mixed $data the input data
   * @param Operation $operation the operation
   * @param array<string, mixed> $uriVariables the URI variables
   * @param array<string, mixed> $context the context
   *
   * @return LoginOutput|null the output
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?LoginOutput
  {
    $request = $this->requestStack->getCurrentRequest();
    if (null === $request) {
      return null;
    }

    $refreshToken = $this->cookieService->getRefreshTokenFromRequest(request: $request);

    if (null === $refreshToken || '' === $refreshToken) {
      throw new UnauthorizedHttpException(
        challenge: 'Bearer',
        message: 'No refresh token provided',
      );
    }

    $this->enforceRateLimit($refreshToken, $request->getClientIp() ?? '127.0.0.1');

    /**
     * Query result.
     *
     * @var RefreshTokenResult $result
     */
    $result = $this->queryBus->ask(
      query: new RefreshTokenQuery(
        refreshToken: $refreshToken,
        ipAddress: $request->getClientIp(),
      ),
    );

    if (!$result->success) {
      $request->attributes->set(
        key: '_refresh_token_cookie',
        value: $this->cookieService->createClearCookie(),
      );

      throw new UnauthorizedHttpException(
        challenge: 'Bearer',
        message: $result->errorMessage ?? 'Invalid refresh token',
      );
    }

    $output = new LoginOutput();
    $output->accessToken = $result->accessToken;
    $output->tokenType = $result->tokenType;
    $output->expiresIn = $result->expiresIn;
    $output->scope = implode(' ', $result->scopes);

    if (null !== $result->refreshToken) {
      $rememberMe = $result->rememberMe ?? false;
      if (null === $result->rememberMe) {
        $payload = $this->jwtService->decodeRefreshToken($result->refreshToken);
        if (is_array($payload) && array_key_exists('remember_me', $payload)) {
          $rememberMe = $payload['remember_me'];
        }
      }

      $cookie = $this->cookieService->createCookie(
        refreshToken: $result->refreshToken,
        rememberMe: $rememberMe,
      );

      $request->attributes->set(
        key: '_refresh_token_cookie',
        value: $cookie,
      );
    }

    return $output;
  }

  private function enforceRateLimit(string $refreshToken, string $ipAddress): void
  {
    if (null === $this->rateLimiter) {
      return;
    }

    $limit = $this->rateLimiter->create($this->getRateLimitKey($refreshToken, $ipAddress))->consume();
    if ($limit->isAccepted()) {
      return;
    }

    $retryAfter = $limit->getRetryAfter();
    $seconds = max(0, $retryAfter->getTimestamp() - time());

    throw new TooManyRequestsHttpException(
      $seconds,
      sprintf('Too many refresh token requests. Please try again in %d seconds.', $seconds),
    );
  }

  private function getRateLimitKey(string $refreshToken, string $ipAddress): string
  {
    $identityHash = hash('sha256', $this->getRateLimitIdentity($refreshToken));
    $ipHash = hash('sha256', $ipAddress);

    return sprintf('token_refresh_%s_%s', substr($identityHash, 0, 16), substr($ipHash, 0, 16));
  }

  private function getRateLimitIdentity(string $refreshToken): string
  {
    $payload = $this->jwtService->decodeRefreshToken($refreshToken);
    if (is_array($payload) && '' !== $payload['user_id']) {
      return 'user:' . $payload['user_id'];
    }

    return 'token:' . $refreshToken;
  }
  // #endregion
}
