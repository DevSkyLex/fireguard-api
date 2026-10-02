<?php

declare(strict_types=1);

namespace OAuth\Presentation\Api\Processor\Authorization;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\{ProcessorInterface, ProviderInterface};
use Auth\Infrastructure\Security\User\SecurityUser;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;
use League\OAuth2\Server\RequestTypes\AuthorizationRequestInterface;
use Nyholm\Psr7\{Response as Psr7Response, ServerRequest};
use OAuth\Application\Port\Outbound\Token\AuthCodeRepositoryPort;
use OAuth\Application\Port\Outbound\User\OidcUserProviderPort;
use OAuth\Application\UseCase\Query\Consent\CheckConsent\{CheckConsentQuery, CheckConsentResult};
use OAuth\Infrastructure\OAuth2\League\Entity\User as LeagueUser;
use OAuth\Presentation\Api\Service\{AuthorizationGrantCompletion, AuthorizationResponseSupport};
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\{JsonResponse, Request, RequestStack, Response};
use Symfony\Component\HttpKernel\Exception\{BadRequestHttpException, TooManyRequestsHttpException};
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Throwable;

use function array_filter;
use function array_map;
use function array_merge;
use function array_unique;
use function array_values;
use function count;
use function ctype_digit;
use function explode;
use function hash;
use function in_array;
use function is_string;
use function max;
use function sprintf;
use function strtoupper;
use function substr;
use function time;
use function trim;

/**
 * Processor AuthorizeProcessor.
 *
 * Handles OAuth2 authorization requests (GET/POST /authorize).
 *
 * @category Processor
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<Response>
 * @implements ProcessorInterface<mixed, Response>
 */
final readonly class AuthorizeProcessor implements ProviderInterface, ProcessorInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * AuthorizeProcessor class.
   *
   * @since 1.0.0
   *
   * @param AuthorizationServer $authorizationServer the League authorization server
   * @param Security $security the security service
   * @param QueryBusPort $queryBus the query bus
   * @param RequestStack $requestStack the request stack
   * @param AuthCodeRepositoryPort $authCodeRepository the auth code repository
   * @param OidcUserProviderPort $oidcUserProvider the OIDC user provider
   */
  public function __construct(
    private AuthorizationServer $authorizationServer,
    private Security $security,
    private QueryBusPort $queryBus,
    private RequestStack $requestStack,
    private AuthCodeRepositoryPort $authCodeRepository,
    private OidcUserProviderPort $oidcUserProvider,
    private ScopeRepositoryInterface $scopeRepository,
    private AuthorizationGrantCompletion $grantCompletion,
    #[Autowire(service: 'limiter.oauth_authorize')]
    private ?RateLimiterFactory $rateLimiter = null,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method provide
   * {@inheritDoc}
   *
   * @return Response the authorization response
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): Response
  {
    return $this->handleAuthorizationRequest();
  }

  /**
   * Method process
   * {@inheritDoc}
   *
   * @return Response the authorization response
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Response
  {
    return $this->handleAuthorizationRequest();
  }

  /**
   * Method handleAuthorizationRequest.
   *
   * Validates the OAuth authorization request, user session, consent, and completion response.
   *
   * @access private
   *
   * @return Response OAuth or OIDC authorization response
   */
  private function handleAuthorizationRequest(): Response
  {
    $request = $this->requestStack->getCurrentRequest();
    if (null === $request) {
      throw new BadRequestHttpException(message: 'Request not found.');
    }

    $this->enforceRateLimit($request);

    $pkceError = $this->validatePkceRequest($request);
    if (null !== $pkceError) {
      return $pkceError;
    }

    $psrRequest = $this->buildAuthorizationRequest($request);

    try {
      $authorizationRequest = $this->authorizationServer->validateAuthorizationRequest($psrRequest);
      $this->scopeRepository->finalizeScopes(
        $authorizationRequest->getScopes(),
        'authorization_code',
        $authorizationRequest->getClient(),
      );
    } catch (OAuthServerException $exception) {
      return AuthorizationResponseSupport::convertPsrResponse($exception->generateHttpResponse(new Psr7Response()));
    } catch (Throwable $exception) {
      throw new BadRequestHttpException(
        message: 'Invalid authorization request.',
        previous: $exception,
      );
    }

    return $this->authorizeValidatedRequest($request, $authorizationRequest);
  }

  /**
   * Method authorizeValidatedRequest
   *
   * Checks OIDC prompt requirements and the authenticated user only after League and client scopes have been validated.
   *
   * @access private
   *
   * @param Request $request the incoming authorization request
   * @param AuthorizationRequestInterface $authorizationRequest the validated League request
   *
   * @return Response the prompt refusal or consent-stage authorization response
   */
  private function authorizeValidatedRequest(Request $request, AuthorizationRequestInterface $authorizationRequest): Response
  {
    $prompts = $this->parseAndValidatePrompt($request);
    if ($prompts instanceof JsonResponse) {
      return $prompts;
    }

    $securityUser = $this->validateAuthorizationUser($request, $prompts);
    if ($securityUser instanceof JsonResponse) {
      return $securityUser;
    }

    return $this->completeConsentedRequest($request, $authorizationRequest, $securityUser, $prompts);
  }

  /**
   * Method completeConsentedRequest
   *
   * Requires the existing consent decision before completing the grant under the fresh principal guard.
   *
   * @access private
   *
   * @param Request $request the incoming authorization request
   * @param AuthorizationRequestInterface $authorizationRequest the validated League request
   * @param SecurityUser $securityUser the authenticated local user
   * @param list<string> $prompts the validated OIDC prompts
   *
   * @return Response the consent refusal or completed authorization response
   */
  private function completeConsentedRequest(Request $request, AuthorizationRequestInterface $authorizationRequest, SecurityUser $securityUser, array $prompts): Response
  {
    $requestedScopes = $this->extractScopeIdentifiers($authorizationRequest->getScopes());
    $clientId = (string) $authorizationRequest->getClient()->getIdentifier();

    /** @var CheckConsentResult $consent */
    $consent = $this->queryBus->ask(query: new CheckConsentQuery(
      userId: $securityUser->getId(),
      clientId: $clientId,
      requestedScopes: $requestedScopes,
    ));

    $requiresConsent = $consent->requiresConsentScreen || in_array('consent', $prompts, true);
    if ($requiresConsent) {
      return new JsonResponse(
        data: [
          'error' => 'consent_required',
          'error_description' => 'User consent is required.',
          'client_id' => $clientId,
          'client_name' => $authorizationRequest->getClient()->getName(),
          'redirect_uri' => $authorizationRequest->getRedirectUri(),
          'state' => $authorizationRequest->getState(),
          'requested_scopes' => $requestedScopes,
          'granted_scopes' => $consent->grantedScopes,
          'missing_scopes' => $consent->missingScopes,
          'requires_consent' => true,
        ],
        status: Response::HTTP_FORBIDDEN,
      );
    }

    $userEntity = new LeagueUser();
    $userId = $securityUser->getId();
    if ('' === $userId) {
      throw new BadRequestHttpException(message: 'User identifier cannot be empty.');
    }
    $userEntity->setIdentifier($userId);

    $authorizationRequest->setUser($userEntity);
    $authorizationRequest->setAuthorizationApproved(true);

    return $this->grantCompletion->complete($request, $userId, function () use ($request, $authorizationRequest): Response {
      $psrResponse = $this->authorizationServer->completeAuthorizationRequest(
        authRequest: $authorizationRequest,
        response: new Psr7Response(),
      );
      $this->storeNonceFromResponse($request, $psrResponse);

      return AuthorizationResponseSupport::convertPsrResponse($psrResponse);
    });
  }

  /**
   * @param list<string> $prompts
   */
  private function validateAuthorizationUser(Request $request, array $prompts): SecurityUser|JsonResponse
  {
    $maxAgeValue = $this->readParam($request, 'max_age');
    $maxAge = null;
    if (null !== $maxAgeValue) {
      if (!ctype_digit($maxAgeValue)) {
        return $this->buildInvalidRequest('Invalid max_age parameter.');
      }
      $maxAge = (int) $maxAgeValue;
    }

    $securityUser = $this->security->getUser();
    if (!$securityUser instanceof SecurityUser) {
      return AuthorizationResponseSupport::buildOidcError(
        error: 'login_required',
        description: 'Authentication required.',
        status: Response::HTTP_UNAUTHORIZED,
      );
    }

    return $this->validateAuthenticatedUser($securityUser, $prompts, $maxAge);
  }

  /**
   * @param list<string> $prompts
   */
  private function validateAuthenticatedUser(SecurityUser $securityUser, array $prompts, ?int $maxAge): SecurityUser|JsonResponse
  {
    if ($this->requiresLogin($prompts)) {
      return AuthorizationResponseSupport::buildOidcError(
        error: 'login_required',
        description: 'User authentication required.',
        status: Response::HTTP_UNAUTHORIZED,
      );
    }
    if (null !== $maxAge && $this->requiresRecentAuth($securityUser->getId(), $maxAge)) {
      return AuthorizationResponseSupport::buildOidcError(
        error: 'login_required',
        description: 'User authentication too old.',
        status: Response::HTTP_UNAUTHORIZED,
      );
    }

    return $securityUser;
  }

  /**
   * Method buildAuthorizationRequest.
   *
   * Converts normalized framework request parameters into the League server request.
   *
   * @access private
   *
   * @param Request $request incoming authorization request
   *
   * @return ServerRequest request passed to the authorization server
   */
  private function buildAuthorizationRequest(Request $request): ServerRequest
  {
    $params = $this->normalizeAuthorizationParams($request);

    $psrRequest = new ServerRequest(
      method: $request->getMethod(),
      uri: $request->getUri(),
    );

    $psrRequest = $psrRequest->withQueryParams($params);

    if ('POST' === strtoupper($request->getMethod())) {
      $psrRequest = $psrRequest->withParsedBody($params);
    }

    return $psrRequest;
  }

  /**
   * @return array<string, string>
   */
  private function normalizeAuthorizationParams(Request $request): array
  {
    $rawParams = array_merge($request->query->all(), $request->request->all());
    $params = [];

    foreach ($rawParams as $key => $value) {
      if (!is_string($value)) {
        continue;
      }

      $normalized = trim($value);
      if ('' === $normalized) {
        continue;
      }

      $params[(string) $key] = $normalized;
    }

    return $params;
  }

  /**
   * Method validatePkceRequest.
   *
   * Requires a challenge for code responses and rejects unsupported challenge methods.
   *
   * @access private
   *
   * @param Request $request incoming authorization request
   *
   * @return ?JsonResponse invalid-request response, or null when the PKCE parameters pass
   */
  private function validatePkceRequest(Request $request): ?JsonResponse
  {
    $responseType = $this->readParam($request, 'response_type');
    if ('code' !== $responseType) {
      return null;
    }

    $codeChallenge = $this->readParam($request, 'code_challenge');
    if (null === $codeChallenge) {
      return $this->buildInvalidRequest('Missing code_challenge parameter.');
    }

    $codeChallengeMethod = $this->readParam($request, 'code_challenge_method') ?? 'plain';

    return in_array($codeChallengeMethod, ['S256', 'plain'], true)
      ? null
      : $this->buildInvalidRequest('Invalid code_challenge_method value.');
  }

  /**
   * Method readParam.
   *
   * Reads and trims a string value from request attributes, query, or body parameters.
   *
   * @access private
   *
   * @param Request $request incoming request
   * @param string $key parameter name
   *
   * @return ?string non-empty parameter value, or null when absent or not a string
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
   * @param array<\League\OAuth2\Server\Entities\ScopeEntityInterface> $scopes
   *
   * @return list<non-empty-string>
   */
  private function extractScopeIdentifiers(array $scopes): array
  {
    $identifiers = [];
    foreach ($scopes as $scope) {
      $identifiers[] = $scope->getIdentifier();
    }

    return $identifiers;
  }

  /**
   * Method buildInvalidRequest.
   *
   * Creates the OAuth invalid_request JSON response.
   *
   * @access private
   *
   * @param string $message error description
   *
   * @return JsonResponse HTTP 400 OAuth error response
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

  /**
   * Method enforceRateLimit.
   *
   * Consumes an authorization-request rate-limit token when a limiter is configured.
   *
   * @access private
   *
   * @param Request $request incoming request used to derive the rate-limit key
   *
   * @return void no return value
   *
   * @throws TooManyRequestsHttpException when the request exceeds its rate limit
   */
  private function enforceRateLimit(Request $request): void
  {
    if (null === $this->rateLimiter) {
      return;
    }

    $clientId = $this->readParam($request, 'client_id') ?? 'unknown';
    $ipAddress = $request->getClientIp() ?? '127.0.0.1';
    $limit = $this->rateLimiter->create($this->getRateLimitKey($clientId, $ipAddress))->consume();
    if ($limit->isAccepted()) {
      return;
    }

    $retryAfter = $limit->getRetryAfter();
    $seconds = max(0, $retryAfter->getTimestamp() - time());

    throw new TooManyRequestsHttpException(
      $seconds,
      sprintf('Too many authorization requests. Please try again in %d seconds.', $seconds),
    );
  }

  /**
   * Method getRateLimitKey.
   *
   * Builds a limiter key from truncated hashes of client and address values.
   *
   * @access private
   *
   * @param string $clientId OAuth client identifier
   * @param string $ipAddress client IP address
   *
   * @return string rate-limit key
   */
  private function getRateLimitKey(string $clientId, string $ipAddress): string
  {
    $clientHash = hash('sha256', $clientId);
    $ipHash = hash('sha256', $ipAddress);

    return sprintf('oauth_authorize_%s_%s', substr($clientHash, 0, 16), substr($ipHash, 0, 16));
  }

  /**
   * Method parseAndValidatePrompt
   *
   * Normalizes supplied OIDC prompts and rejects unknown values or incompatible prompt=none combinations before checking the user.
   *
   * @access private
   *
   * @param Request $request the incoming authorization request
   *
   * @return list<string>|JsonResponse the normalized prompts or the existing invalid-request response
   */
  private function parseAndValidatePrompt(Request $request): array|JsonResponse
  {
    $prompt = $this->readParam($request, 'prompt');
    $prompts = [];
    if (null !== $prompt) {
      $values = array_filter(
        array_map('trim', explode(' ', $prompt)),
        static fn (string $value): bool => '' !== $value,
      );

      $normalized = array_map('strtolower', $values);
      $prompts = array_values(array_unique($normalized));
    }

    $allowed = ['none', 'login', 'consent', 'select_account'];
    foreach ($prompts as $prompt) {
      if (!in_array($prompt, $allowed, true)) {
        return $this->buildInvalidRequest('Invalid prompt parameter.');
      }
    }

    if (in_array('none', $prompts, true) && count($prompts) > 1) {
      return $this->buildInvalidRequest('prompt=none cannot be combined with other values.');
    }

    return $prompts;
  }

  /**
   * @param list<string> $prompts
   */
  private function requiresLogin(array $prompts): bool
  {
    return in_array('login', $prompts, true) || in_array('select_account', $prompts, true);
  }

  /**
   * Method requiresRecentAuth.
   *
   * Checks whether the user's recorded authentication time is missing or older than max_age.
   *
   * @access private
   *
   * @param string $userId authenticated user identifier
   * @param int $maxAge maximum authentication age in seconds
   *
   * @return bool whether recent authentication is required
   */
  private function requiresRecentAuth(string $userId, int $maxAge): bool
  {
    if ('' === trim($userId)) {
      return true;
    }

    $oidcUser = $this->oidcUserProvider->findByIdentifier($userId);
    $authTime = $oidcUser?->authTime();

    return null === $authTime || $maxAge <= 0 || ($authTime->getTimestamp() + $maxAge) < time();
  }

  /**
   * Method storeNonceFromResponse.
   *
   * Associates a supplied OIDC nonce with the authorization code returned in the response.
   *
   * @access private
   *
   * @param Request $request incoming authorization request
   * @param \Psr\Http\Message\ResponseInterface $response authorization response
   *
   * @return void no return value
   */
  private function storeNonceFromResponse(Request $request, \Psr\Http\Message\ResponseInterface $response): void
  {
    $nonce = $this->readParam($request, 'nonce');
    if (null === $nonce) {
      return;
    }

    $code = AuthorizationResponseSupport::extractCodeFromResponse($response);
    if (null === $code) {
      return;
    }

    $this->authCodeRepository->updateNonce($code, $nonce);
  }

  // #endregion
}
