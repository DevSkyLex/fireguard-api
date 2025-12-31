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
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Throwable;

use function array_merge;
use function in_array;
use function is_array;
use function is_string;
use function parse_str;
use function parse_url;
use function strtoupper;
use function trim;

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
   */
  public function __construct(
    private AuthorizationServer $authorizationServer,
    private Security $security,
    private QueryBusPort $queryBus,
    private RequestStack $requestStack,
    private AuthCodeRepositoryPort $authCodeRepository,
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

    $securityUser = $this->security->getUser();
    if (!$securityUser instanceof SecurityUser) {
      throw new UnauthorizedHttpException(
        challenge: 'Bearer',
        message: 'Authentication required',
      );
    }

    $requestedScopes = $this->extractScopeIdentifiers($authorizationRequest->getScopes());
    $clientId = (string) $authorizationRequest->getClient()->getIdentifier();

    /** @var CheckConsentResult $consent */
    $consent = $this->queryBus->ask(query: new CheckConsentQuery(
      userId: $securityUser->getId(),
      clientId: $clientId,
      requestedScopes: $requestedScopes,
    ));

    if ($consent->requiresConsentScreen) {
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

    $codeChallengeMethod = $this->readParam($request, 'code_challenge_method');
    if (null === $codeChallengeMethod) {
      return $this->buildInvalidRequest('Missing code_challenge_method parameter.');
    }

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

  private function storeNonceFromResponse(Request $request, \Psr\Http\Message\ResponseInterface $response): void
  {
    $nonce = $this->readParam($request, 'nonce');
    if (null === $nonce) {
      return;
    }

    $code = $this->extractCodeFromLocation($response->getHeaderLine('Location'));
    if (null === $code) {
      return;
    }

    $this->authCodeRepository->updateNonce($code, $nonce);
  }

  private function extractCodeFromLocation(string $location): ?string
  {
    if ('' === $location) {
      return null;
    }

    $parts = parse_url($location);
    if (!is_array($parts) || !isset($parts['query'])) {
      return null;
    }

    $query = [];
    parse_str((string) $parts['query'], $query);

    $code = $query['code'] ?? null;
    if (!is_string($code) || '' === $code) {
      return null;
    }

    return $code;
  }

  private function convertPsrResponse(\Psr\Http\Message\ResponseInterface $psrResponse): Response
  {
    $httpFoundationFactory = new HttpFoundationFactory();

    return $httpFoundationFactory->createResponse($psrResponse);
  }
  // #endregion
}
