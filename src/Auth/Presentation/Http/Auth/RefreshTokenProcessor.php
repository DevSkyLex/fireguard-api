<?php

declare(strict_types=1);

namespace Auth\Presentation\Http\Auth;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Presentation\Dto\Output\LoginOutput;
use Auth\Presentation\Service\RefreshTokenCookieService;
use OAuth\Application\UseCase\Query\RefreshToken\RefreshTokenQuery;
use OAuth\Application\UseCase\Query\RefreshToken\RefreshTokenResult;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

use function implode;

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
      $cookie = $this->cookieService->createCookie(
        refreshToken: $result->refreshToken,
      );

      $request->attributes->set(
        key: '_refresh_token_cookie',
        value: $cookie,
      );
    }

    return $output;
  }
  // #endregion
}
