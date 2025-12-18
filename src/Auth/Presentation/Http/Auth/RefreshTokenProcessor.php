<?php

declare(strict_types=1);

namespace Auth\Presentation\Http\Auth;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use OAuth\Application\UseCase\Query\RefreshToken\RefreshTokenQuery;
use OAuth\Application\UseCase\Query\RefreshToken\RefreshTokenResult;
use Auth\Presentation\Dto\Output\LoginOutput;
use Auth\Presentation\Service\RefreshTokenCookieService;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * Processor RefreshTokenProcessor
 * @final
 *
 * Handles token refresh using the refresh token from HttpOnly cookie.
 * Delegates to RefreshTokenHandler for business logic.
 *
 * @category Processor
 * @package Auth\Presentation\Http\Auth
 * @version 3.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<object, LoginOutput|null>
 */
final readonly class RefreshTokenProcessor implements ProcessorInterface
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the
   * RefreshTokenProcessor class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus The query bus.
   * @param RequestStack $requestStack The request stack.
   * @param RefreshTokenCookieService $cookieService The cookie service.
   */
  public function __construct(
    private readonly QueryBusPort $queryBus,
    private readonly RequestStack $requestStack,
    private readonly RefreshTokenCookieService $cookieService,
  ) {
  }
  //#endregion

  //#region Methods
  /**
   * Method process
   * {@inheritDoc}
   *
   * Processes the token refresh request.
   *
   * @access public
   * @since 1.0.0
   *
   * @param mixed $data The input data.
   * @param Operation $operation The operation.
   * @param array<string, mixed> $uriVariables The URI variables.
   * @param array<string, mixed> $context The context.
   *
   * @return LoginOutput|null The output.
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?LoginOutput
  {
    $request = $this->requestStack->getCurrentRequest();
    if ($request === null)
      return null;

    $refreshToken = $this->cookieService->getRefreshTokenFromRequest(request: $request);

    if ($refreshToken === null || $refreshToken === '') {
      throw new UnauthorizedHttpException(
        challenge: 'Bearer',
        message: 'No refresh token provided'
      );
    }

    /**
     * Query result
     * @var RefreshTokenResult $result
     */
    $result = $this->queryBus->ask(
      query: new RefreshTokenQuery(
        refreshToken: $refreshToken,
        ipAddress: $request->getClientIp(),
      )
    );

    if (!$result->success) {
      throw new UnauthorizedHttpException(
        challenge: 'Bearer',
        message: $result->errorMessage ?? 'Invalid refresh token'
      );
    }

    $output = new LoginOutput();
    $output->accessToken = $result->accessToken;
    $output->tokenType = $result->tokenType;
    $output->expiresIn = $result->expiresIn;
    $output->scope = implode(' ', $result->scopes);

    if ($result->refreshToken !== null) {
      $cookie = $this->cookieService->createCookie(
        refreshToken: $result->refreshToken
      );

      $request->attributes->set(
        key: '_refresh_token_cookie',
        value: $cookie
      );
    }

    return $output;
  }
  //#endregion
}
