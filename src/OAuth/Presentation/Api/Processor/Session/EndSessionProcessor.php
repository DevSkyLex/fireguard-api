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
 * Class EndSessionProcessor
 *
 * Handles OpenID Connect end-session requests.
 *
 * @category Processor
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
   * Constant BEARER_PREFIX
   *
   * Bearer prefix for Authorization header.
   *
   * @since 1.0.0
   *
   * @var string
   */
  private const string BEARER_PREFIX = 'Bearer ';

  /**
   * Constant COOKIE_ATTRIBUTE
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
   * Method __construct
   *
   * Initializes a new instance of the
   * EndSessionProcessor class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param RequestStack $requestStack the request stack
   * @param CommandBusPort $commandBus the command bus
   * @param QueryBusPort $queryBus the query bus
   * @param JwtParserPort $jwtParser the JWT parser
   * @param RefreshTokenCookieService $cookieService the refresh token cookie service
   *
   * @return void
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
   * Exposes the end-session response through the read operation.
   *
   * @access public
   *
   * @param Operation $operation API Platform operation metadata
   * @param array<string, mixed> $uriVariables route variables supplied by API Platform
   * @param array<string, mixed> $context provider context supplied by API Platform
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
   * Processes the end-session operation using the same logout response path as the provider entry point.
   *
   * @access public
   *
   * @param mixed $data unused processor input
   * @param Operation $operation API Platform operation metadata
   * @param array<string, mixed> $uriVariables route variables supplied by API Platform
   * @param array<string, mixed> $context processor context supplied by API Platform
   *
   * @return Response the end-session response
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Response
  {
    return $this->handleEndSession();
  }

  /**
   * Method handleEndSession
   *
   * Revokes presented tokens and clears the refresh cookie before validating any post-logout redirect.
   *
   * @access private
   *
   * @return Response JSON logout result or an allowed client redirect
   *
   * @throws BadRequestHttpException when no current request exists
   */
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

  /**
   * Method revokeTokens
   *
   * Requests revocation of the cookie refresh token and bearer access token independently; failures do not block logout.
   *
   * @access private
   *
   * @param Request $request current logout request
   *
   * @return void
   */
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

  /**
   * Method clearRefreshTokenCookie
   *
   * Places the clearing cookie on the request for the response listener to attach.
   *
   * @access private
   *
   * @param Request $request current logout request
   *
   * @return void
   */
  private function clearRefreshTokenCookie(Request $request): void
  {
    $request->attributes->set(
      key: self::COOKIE_ATTRIBUTE,
      value: $this->cookieService->createClearCookie(),
    );
  }

  /**
   * Method extractAccessToken
   *
   * Extracts a non-empty token only from a bearer Authorization header.
   *
   * @access private
   *
   * @param Request $request current logout request
   *
   * @return ?string bearer token, or null when the header is absent or malformed
   */
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

  /**
   * Method resolveClientIdFromHint
   *
   * Reads the first non-empty audience claim from an ID-token hint already validated by the caller.
   *
   * @access private
   *
   * @param string $idTokenHint validated ID-token hint
   *
   * @return ?string first client audience, or null when absent
   */
  private function resolveClientIdFromHint(string $idTokenHint): ?string
  {
    $claims = $this->jwtParser->parse($idTokenHint) ?? [];

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

  /**
   * Method isAllowedPostLogoutRedirectUri
   *
   * Allows a redirect only when the client can be loaded, is active, and has registered the exact URI.
   *
   * @access private
   *
   * @param string $clientId OAuth client identifier
   * @param string $postLogoutRedirectUri requested post-logout URI
   *
   * @return bool whether the exact URI is registered for an active client
   */
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

  /**
   * Method appendState
   *
   * Appends a URL-encoded state parameter while preserving any existing query string.
   *
   * @access private
   *
   * @param string $uri approved post-logout redirect URI
   * @param ?string $state caller-provided state value
   *
   * @return string redirect URI with state when supplied
   */
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

  /**
   * Method readParam
   *
   * Reads a trimmed non-empty string from request attributes, query parameters, then form data.
   *
   * @access private
   *
   * @param Request $request current logout request
   * @param string $key parameter name
   *
   * @return ?string normalized parameter value, or null when unavailable
   */
  private function readParam(Request $request, string $key): ?string
  {
    $value = $request->attributes->get($key)
      ?? $request->query->get($key)
      ?? $request->request->get($key);
    if (!is_string($value)) {
      return null;
    }

    $normalized = trim($value);
    if ('' === $normalized) {
      return null;
    }

    return $normalized;
  }

  /**
   * Method buildInvalidRequest
   *
   * Creates an OAuth invalid_request payload with an HTTP 400 status.
   *
   * @access private
   *
   * @param string $message public error description
   *
   * @return JsonResponse protocol error response
   */
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
