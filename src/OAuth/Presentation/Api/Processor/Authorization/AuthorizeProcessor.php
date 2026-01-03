<?php

declare(strict_types=1);

namespace OAuth\Presentation\Api\Processor\Authorization;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use Nyholm\Psr7\Response as Psr7Response;
use Nyholm\Psr7\ServerRequest;
use OAuth\Application\Port\Outbound\Token\AuthCodeRepositoryPort;
use OAuth\Application\Port\Outbound\User\OidcUserProviderPort;
use OAuth\Application\UseCase\Query\Consent\CheckConsent\CheckConsentQuery;
use OAuth\Application\UseCase\Query\Consent\CheckConsent\CheckConsentResult;
use OAuth\Infrastructure\OAuth2\League\Entity\User as LeagueUser;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Throwable;

use function array_filter;
use function array_map;
use function array_merge;
use function array_unique;
use function array_values;
use function count;
use function ctype_digit;
use function explode;
use function in_array;
use function is_array;
use function is_string;
use function parse_str;
use function parse_url;
use function preg_match;
use function strtoupper;
use function time;
use function trim;
use function urldecode;

/**
 * Processor AuthorizeProcessor.
 *
 * Handles OAuth2 authorization requests (GET/POST /authorize).
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

  private function handleAuthorizationRequest(): Response
  {
    $request = $this->requestStack->getCurrentRequest();
    if (null === $request) {
      throw new BadRequestHttpException(message: 'Request not found.');
    }

    $pkceError = $this->validatePkceRequest($request);
    if (null !== $pkceError) {
      return $pkceError;
    }

    $psrRequest = $this->buildAuthorizationRequest($request);

    try {
      $authorizationRequest = $this->authorizationServer->validateAuthorizationRequest($psrRequest);
    } catch (OAuthServerException $exception) {
      return $this->convertPsrResponse($exception->generateHttpResponse(new Psr7Response()));
    } catch (Throwable $exception) {
      throw new BadRequestHttpException(
        message: 'Invalid authorization request.',
        previous: $exception,
      );
    }

    $prompts = $this->parsePrompt($request);
    $promptError = $this->validatePromptValues($prompts);
    if (null !== $promptError) {
      return $promptError;
    }

    $maxAgeValue = $this->readParam($request, 'max_age');
    $maxAge = null;
    if (null !== $maxAgeValue) {
      if (!ctype_digit($maxAgeValue)) {
        return $this->buildInvalidRequest('Invalid max_age parameter.');
      }
      $maxAge = (int) $maxAgeValue;
    }

    $requiresLogin = $this->requiresLogin($prompts);

    $securityUser = $this->security->getUser();
    if (!$securityUser instanceof SecurityUser) {
      return $this->buildOidcError(
        error: 'login_required',
        description: 'Authentication required.',
        status: Response::HTTP_UNAUTHORIZED,
      );
    }

    if ($requiresLogin) {
      return $this->buildOidcError(
        error: 'login_required',
        description: 'User authentication required.',
        status: Response::HTTP_UNAUTHORIZED,
      );
    }

    if (null !== $maxAge) {
      if ($this->requiresRecentAuth($securityUser->getId(), $maxAge)) {
        return $this->buildOidcError(
          error: 'login_required',
          description: 'User authentication too old.',
          status: Response::HTTP_UNAUTHORIZED,
        );
      }
    }

    $requestedScopes = $this->extractScopeIdentifiers($authorizationRequest->getScopes());
    $clientId = (string) $authorizationRequest->getClient()->getIdentifier();

    /** @var CheckConsentResult $consent */
    $consent = $this->queryBus->ask(query: new CheckConsentQuery(
      userId: $securityUser->getId(),
      clientId: $clientId,
      requestedScopes: $requestedScopes,
    ));

    $requiresConsent = $consent->requiresConsentScreen || $this->requiresConsentPrompt($prompts);
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

    $psrResponse = $this->authorizationServer->completeAuthorizationRequest(
      authRequest: $authorizationRequest,
      response: new Psr7Response(),
    );

    $this->storeNonceFromResponse($request, $psrResponse);

    return $this->convertPsrResponse($psrResponse);
  }

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

    if (!in_array($codeChallengeMethod, ['S256', 'plain'], true)) {
      return $this->buildInvalidRequest('Invalid code_challenge_method value.');
    }

    return null;
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
   * @return list<string>
   */
  private function parsePrompt(Request $request): array
  {
    $prompt = $this->readParam($request, 'prompt');
    if (null === $prompt) {
      return [];
    }

    $values = array_filter(
      array_map('trim', explode(' ', $prompt)),
      static fn (string $value): bool => '' !== $value,
    );

    $normalized = array_map('strtolower', $values);

    return array_values(array_unique($normalized));
  }

  /**
   * @param list<string> $prompts
   */
  private function validatePromptValues(array $prompts): ?JsonResponse
  {
    if ([] === $prompts) {
      return null;
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

    return null;
  }

  /**
   * @param list<string> $prompts
   */
  private function requiresLogin(array $prompts): bool
  {
    return in_array('login', $prompts, true) || in_array('select_account', $prompts, true);
  }

  /**
   * @param list<string> $prompts
   */
  private function requiresConsentPrompt(array $prompts): bool
  {
    return in_array('consent', $prompts, true);
  }

  private function requiresRecentAuth(string $userId, int $maxAge): bool
  {
    if ('' === trim($userId)) {
      return true;
    }

    $oidcUser = $this->oidcUserProvider->findByIdentifier($userId);
    $authTime = $oidcUser?->authTime();
    if (null === $authTime) {
      return true;
    }

    if ($maxAge <= 0) {
      return true;
    }

    return ($authTime->getTimestamp() + $maxAge) < time();
  }

  private function buildOidcError(string $error, string $description, int $status): JsonResponse
  {
    return new JsonResponse(
      data: [
        'error' => $error,
        'error_description' => $description,
      ],
      status: $status,
    );
  }

  private function storeNonceFromResponse(Request $request, \Psr\Http\Message\ResponseInterface $response): void
  {
    $nonce = $this->readParam($request, 'nonce');
    if (null === $nonce) {
      return;
    }

    $code = $this->extractCodeFromResponse($response);
    if (null === $code) {
      return;
    }

    $this->authCodeRepository->updateNonce($code, $nonce);
  }

  private function extractCodeFromResponse(\Psr\Http\Message\ResponseInterface $response): ?string
  {
    $code = $this->extractCodeFromLocation($response->getHeaderLine('Location'));
    if (null !== $code) {
      return $code;
    }

    $body = $this->readResponseBody($response);
    if ('' === $body) {
      return null;
    }

    return $this->extractCodeFromFormPostBody($body);
  }

  private function extractCodeFromLocation(string $location): ?string
  {
    if ('' === $location) {
      return null;
    }

    $parts = parse_url($location);
    if (!is_array($parts)) {
      return null;
    }

    foreach (['query', 'fragment'] as $part) {
      if (!isset($parts[$part])) {
        continue;
      }

      $params = [];
      parse_str((string) $parts[$part], $params);

      $code = $this->extractCodeFromParams($params);
      if (null !== $code) {
        return $code;
      }
    }

    return null;
  }

  /**
   * @param array<int|string, mixed> $params
   */
  private function extractCodeFromParams(array $params): ?string
  {
    $code = $params['code'] ?? null;
    if (!is_string($code) || '' === $code) {
      return null;
    }

    return $code;
  }

  private function extractCodeFromFormPostBody(string $body): ?string
  {
    if ('' === $body) {
      return null;
    }

    if (1 === preg_match('/name=["\']code["\'][^>]*value=["\']([^"\']+)["\']/i', $body, $matches)) {
      return $matches[1];
    }

    if (1 === preg_match('/value=["\']([^"\']+)["\'][^>]*name=["\']code["\']/i', $body, $matches)) {
      return $matches[1];
    }

    if (1 === preg_match('/(?:^|[?&])code=([^&\\s"\']+)/i', $body, $matches)) {
      return urldecode($matches[1]);
    }

    return null;
  }

  private function readResponseBody(\Psr\Http\Message\ResponseInterface $response): string
  {
    try {
      $body = $response->getBody();
      if (!$body->isReadable()) {
        return '';
      }

      if (!$body->isSeekable()) {
        return $body->getContents();
      }

      $position = $body->tell();
      $body->rewind();
      $contents = $body->getContents();
      $body->seek($position);

      return $contents;
    } catch (Throwable) {
      return '';
    }
  }

  private function convertPsrResponse(\Psr\Http\Message\ResponseInterface $psrResponse): Response
  {
    $httpFoundationFactory = new HttpFoundationFactory();

    return $httpFoundationFactory->createResponse($psrResponse);
  }
  // #endregion
}
