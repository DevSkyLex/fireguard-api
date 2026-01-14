<?php

declare(strict_types=1);

namespace OAuth\Presentation\Api\Processor\Session;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\{ProcessorInterface, ProviderInterface};
use Auth\Presentation\Api\Service\RefreshTokenCookieService;
use OAuth\Application\Port\Outbound\Token\JwtParserPort;
use OAuth\Application\UseCase\Command\Token\RevokeToken\RevokeTokenCommand;
use OAuth\Application\UseCase\Query\Client\GetClient\{GetClientQuery, GetClientResult};
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Symfony\Component\HttpFoundation\{JsonResponse, RedirectResponse, Request, RequestStack, Response};
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Throwable;

use function in_array;
use function is_array;
use function is_string;
use function parse_url;
use function rawurlencode;
use function str_starts_with;
use function strlen;
use function substr;
use function trim;

/**
 * Processor EndSessionProcessor.
 *
 * Handles OpenID Connect end-session requests.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<Response>
 * @implements ProcessorInterface<mixed, Response>
 */
final readonly class EndSessionProcessor implements ProviderInterface, ProcessorInterface
{
  // #region Constants
  /**
   * Constant BEARER_PREFIX.
   *
   * Bearer prefix for Authorization header.
   *
   * @since 1.0.0
   *
   * @var string
   */
  private const string BEARER_PREFIX = 'Bearer ';

  /**
   * Constant COOKIE_ATTRIBUTE.
   *
   * Request attribute storing the refresh token cookie.
   *
   * @since 1.0.0
   *
   * @var string
   */
  private const string COOKIE_ATTRIBUTE = '_refresh_token_cookie';
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * EndSessionProcessor class.
   *
   * @since 1.0.0
   *
   * @param RequestStack $requestStack the request stack
   * @param CommandBusPort $commandBus the command bus
   * @param QueryBusPort $queryBus the query bus
   * @param JwtParserPort $jwtParser the JWT parser
   * @param RefreshTokenCookieService $cookieService the refresh token cookie service
   */
  public function __construct(
    private readonly RequestStack $requestStack,
    private readonly CommandBusPort $commandBus,
    private readonly QueryBusPort $queryBus,
    private readonly JwtParserPort $jwtParser,
    private readonly RefreshTokenCookieService $cookieService,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method provide
   * {@inheritDoc}
   *
   * @return Response the end-session response
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): Response
  {
    return $this->handleEndSession();
  }

  /**
   * Method process
   * {@inheritDoc}
   *
   * @return Response the end-session response
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Response
  {
    return $this->handleEndSession();
  }

  private function handleEndSession(): Response
  {
    $request = $this->requestStack->getCurrentRequest();
    if (null === $request) {
      throw new BadRequestHttpException(message: 'Request not found.');
    }

    $this->revokeTokens($request);
    $this->clearRefreshTokenCookie($request);

    $idTokenHint = $this->readParam($request, 'id_token_hint');
    if (null !== $idTokenHint && !$this->jwtParser->validate($idTokenHint)) {
      return $this->buildInvalidRequest(
        message: 'id_token_hint is invalid.',
      );
    }

    $postLogoutRedirectUri = $this->readParam($request, 'post_logout_redirect_uri');
    if (null !== $postLogoutRedirectUri) {
      $clientId = $this->readParam($request, 'client_id');
      $clientIdFromHint = null;
      if (null !== $idTokenHint) {
        $clientIdFromHint = $this->resolveClientIdFromHint($idTokenHint);
        if (null !== $clientId && null !== $clientIdFromHint && $clientId !== $clientIdFromHint) {
          return $this->buildInvalidRequest(
            message: 'client_id does not match id_token_hint.',
          );
        }
      }

      $resolvedClientId = $clientId ?? $clientIdFromHint;

      if (null === $resolvedClientId) {
        return $this->buildInvalidRequest(
          message: 'client_id is required when post_logout_redirect_uri is provided.',
        );
      }

      if (!$this->isAllowedPostLogoutRedirectUri($resolvedClientId, $postLogoutRedirectUri)) {
        return $this->buildInvalidRequest(
          message: 'post_logout_redirect_uri is not registered for this client.',
        );
      }

      $redirectUri = $this->appendState(
        uri: $postLogoutRedirectUri,
        state: $this->readParam($request, 'state'),
      );

      return new RedirectResponse(url: $redirectUri);
    }

    return new JsonResponse(
      data: [
        'logged_out' => true,
        'message' => 'Session terminated.',
      ],
      status: Response::HTTP_OK,
    );
  }

  private function revokeTokens(Request $request): void
  {
    $refreshToken = $this->cookieService->getRefreshTokenFromRequest($request);
    $accessToken = $this->extractAccessToken($request);

    if (null !== $refreshToken && '' !== $refreshToken) {
      try {
        $this->commandBus->dispatch(new RevokeTokenCommand(
          token: $refreshToken,
          tokenTypeHint: RevokeTokenCommand::HINT_REFRESH_TOKEN,
        ));
      } catch (Throwable) {
        // Best-effort token revocation to avoid blocking logout.
      }
    }

    if (null !== $accessToken && '' !== $accessToken) {
      try {
        $this->commandBus->dispatch(new RevokeTokenCommand(
          token: $accessToken,
          tokenTypeHint: RevokeTokenCommand::HINT_ACCESS_TOKEN,
        ));
      } catch (Throwable) {
        // Best-effort token revocation to avoid blocking logout.
      }
    }
  }

  private function clearRefreshTokenCookie(Request $request): void
  {
    $request->attributes->set(
      key: self::COOKIE_ATTRIBUTE,
      value: $this->cookieService->createClearCookie(),
    );
  }

  private function extractAccessToken(Request $request): ?string
  {
    $authHeader = $request->headers->get('Authorization', '');
    if (!str_starts_with($authHeader, self::BEARER_PREFIX)) {
      return null;
    }

    $token = substr($authHeader, strlen(self::BEARER_PREFIX));
    $token = trim($token);

    return '' !== $token ? $token : null;
  }

  private function resolveClientIdFromHint(string $idTokenHint): ?string
  {
    $claims = $this->jwtParser->parse($idTokenHint);
    if (null === $claims) {
      return null;
    }

    $audience = $claims['aud'] ?? null;
    if (is_string($audience) && '' !== $audience) {
      return $audience;
    }

    if (is_array($audience)) {
      foreach ($audience as $value) {
        if (is_string($value) && '' !== $value) {
          return $value;
        }
      }
    }

    return null;
  }

  private function isAllowedPostLogoutRedirectUri(string $clientId, string $postLogoutRedirectUri): bool
  {
    try {
      /** @var GetClientResult $result */
      $result = $this->queryBus->ask(new GetClientQuery(clientId: $clientId));
    } catch (Throwable) {
      return false;
    }

    if (false === $result->isActive) {
      return false;
    }

    return in_array($postLogoutRedirectUri, $result->redirectUris, true);
  }

  private function appendState(string $uri, ?string $state): string
  {
    if (null === $state || '' === $state) {
      return $uri;
    }

    $parts = parse_url($uri);
    $hasQuery = is_array($parts) && isset($parts['query']) && '' !== $parts['query'];
    $separator = $hasQuery ? '&' : '?';

    return $uri . $separator . 'state=' . rawurlencode($state);
  }

  private function readParam(Request $request, string $key): ?string
  {
    $value = $request->get($key);
    if (!is_string($value)) {
      return null;
    }

    $normalized = trim($value);
    if ('' === $normalized) {
      return null;
    }

    return $normalized;
  }

  private function buildInvalidRequest(string $message): JsonResponse
  {
    return new JsonResponse(
      data: [
        'error' => 'invalid_request',
        'error_description' => $message,
      ],
      status: Response::HTTP_BAD_REQUEST,
    );
  }
  // #endregion
}
