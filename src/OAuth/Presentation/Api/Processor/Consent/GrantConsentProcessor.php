<?php

declare(strict_types=1);

namespace OAuth\Presentation\Api\Processor\Consent;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;
use League\OAuth2\Server\RequestTypes\AuthorizationRequestInterface;
use Nyholm\Psr7\{Response as Psr7Response, ServerRequest};
use OAuth\Application\Port\Outbound\Token\AuthCodeRepositoryPort;
use OAuth\Application\UseCase\Command\Consent\GrantConsent\GrantConsentCommand;
use OAuth\Infrastructure\OAuth2\League\Entity\User as LeagueUser;
use OAuth\Presentation\Api\Dto\Input\Consent\GrantConsentInput;
use OAuth\Presentation\Api\Service\AuthorizationGrantCompletion;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\{JsonResponse, RequestStack, Response};
use Symfony\Component\HttpKernel\Exception\{BadRequestHttpException, TooManyRequestsHttpException, UnauthorizedHttpException};
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Throwable;

use function array_filter;
use function array_map;
use function array_unique;
use function array_values;
use function explode;
use function hash;
use function is_array;
use function is_string;
use function max;
use function parse_str;
use function parse_url;
use function preg_match;
use function sprintf;
use function substr;
use function time;
use function trim;
use function urldecode;

/**
 * Class GrantConsentProcessor
 *
 * Records an authenticated user's consent decision and completes the validated authorization request through League OAuth.
 *
 * @category Processor
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<GrantConsentInput, Response>
 */
final readonly class GrantConsentProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Initializes a new instance of the
   * GrantConsentProcessor class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param AuthorizationServer $authorizationServer the League authorization server
   * @param CommandBusPort $commandBus the command bus
   * @param Security $security the security service
   * @param AuthCodeRepositoryPort $authCodeRepository the auth code repository
   * @param ?RateLimiterFactory $rateLimiter optional consent grant limiter
   *
   * @return void
   */
  public function __construct(
    private AuthorizationServer $authorizationServer,
    private CommandBusPort $commandBus,
    private Security $security,
    private AuthCodeRepositoryPort $authCodeRepository,
    private ScopeRepositoryInterface $scopeRepository,
    private AuthorizationGrantCompletion $grantCompletion,
    private RequestStack $requestStack,
    #[Autowire(service: 'limiter.oauth_consent_grant')]
    private ?RateLimiterFactory $rateLimiter = null,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method process
   * {@inheritDoc}
   *
   * Processes the consent grant request.
   *
   * @access public
   * @since 1.0.0
   *
   * @param mixed $data request data, checked below so malformed bodies receive an HTTP 400 response
   * @param Operation $operation API Platform operation metadata
   * @param array<string, mixed> $uriVariables route variables supplied by API Platform
   * @param array<string, mixed> $context processor context supplied by API Platform
   *
   * @return Response the authorization response
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Response
  {
    if (!$data instanceof GrantConsentInput) {
      throw new BadRequestHttpException(message: 'Invalid request body.');
    }

    $securityUser = $this->security->getUser();
    if (!$securityUser instanceof SecurityUser) {
      throw new UnauthorizedHttpException(
        challenge: 'Bearer',
        message: 'Authentication required',
      );
    }

    $this->enforceRateLimit($securityUser->getId(), (string) $data->clientId);

    $approved = true === $data->approved;
    $scopes = $this->parseScopes($data->scope);

    $authorizationRequest = $this->validateAuthorizationRequest($data);
    if ($authorizationRequest instanceof Response) {
      return $authorizationRequest;
    }

    $userEntity = new LeagueUser();
    $userId = $securityUser->getId();
    if ('' === $userId) {
      return new JsonResponse(
        data: [
          'error' => 'invalid_request',
          'error_description' => 'User identifier cannot be empty.',
        ],
        status: Response::HTTP_BAD_REQUEST,
      );
    }
    $userEntity->setIdentifier($userId);

    $authorizationRequest->setUser($userEntity);
    $authorizationRequest->setAuthorizationApproved((bool) $approved);

    $request = $this->requestStack->getCurrentRequest();
    if (null === $request) {
      throw new UnauthorizedHttpException(challenge: 'Bearer', message: 'Authentication required.');
    }

    return $this->grantCompletion->complete($request, $userId, function () use ($approved, $userId, $data, $scopes, $authorizationRequest): Response {
      if ($approved) {
        $this->commandBus->dispatch(new GrantConsentCommand(userId: $userId, clientId: (string) $data->clientId, scopes: $scopes));
      }

      try {
        $psrResponse = $this->authorizationServer->completeAuthorizationRequest(
          authRequest: $authorizationRequest,
          response: new Psr7Response(),
        );
      } catch (OAuthServerException $exception) {
        return $this->convertPsrResponse($exception->generateHttpResponse(new Psr7Response()));
      }
      $this->storeNonceFromResponse($data, $psrResponse);

      return $this->convertPsrResponse($psrResponse);
    });
  }

  /**
   * Method validateAuthorizationRequest
   *
   * Validates the League request and current client scope allowlist before consent or grant writes can occur.
   *
   * @access private
   *
   * @param GrantConsentInput $input the consent and authorization request fields
   *
   * @return AuthorizationRequestInterface|Response the validated request or its existing protocol error response
   */
  private function validateAuthorizationRequest(GrantConsentInput $input): AuthorizationRequestInterface|Response
  {
    $psrRequest = $this->buildAuthorizationRequest($input);

    try {
      $authorizationRequest = $this->authorizationServer->validateAuthorizationRequest($psrRequest);
      $this->scopeRepository->finalizeScopes($authorizationRequest->getScopes(), 'authorization_code', $authorizationRequest->getClient());
    } catch (OAuthServerException $exception) {
      return $this->convertPsrResponse($exception->generateHttpResponse(new Psr7Response()));
    } catch (Throwable $exception) {
      return new JsonResponse(
        data: [
          'error' => 'invalid_request',
          'error_description' => 'Invalid authorization request.',
        ],
        status: Response::HTTP_BAD_REQUEST,
      );
    }

    return $authorizationRequest;
  }

  /**
   * Method buildAuthorizationRequest
   *
   * Reconstructs the authorization parameters for League after consent is recorded, omitting values that were not supplied.
   *
   * @access private
   *
   * @param GrantConsentInput $input validated consent and authorization request fields
   *
   * @return ServerRequest PSR request used to validate the authorization flow
   */
  private function buildAuthorizationRequest(GrantConsentInput $input): ServerRequest
  {
    $params = array_filter([
      'response_type' => $this->normalizeValue($input->responseType),
      'client_id' => $this->normalizeValue($input->clientId),
      'redirect_uri' => $this->normalizeValue($input->redirectUri),
      'scope' => $this->normalizeValue($input->scope),
      'state' => $this->normalizeValue($input->state),
      'code_challenge' => $this->normalizeValue($input->codeChallenge),
      'code_challenge_method' => $this->normalizeValue($input->codeChallengeMethod),
      'nonce' => $this->normalizeValue($input->nonce),
    ], static fn ($value) => null !== $value);

    $psrRequest = new ServerRequest(method: 'GET', uri: '/oauth2/authorize');

    $psrRequest = $psrRequest->withQueryParams($params);
    $psrRequest = $psrRequest->withParsedBody($params);

    return $psrRequest;
  }

  /**
   * Method enforceRateLimit
   *
   * Consumes the per-user/client grant budget and raises HTTP 429 when the limiter rejects the attempt.
   *
   * @access private
   *
   * @param string $userId authenticated local user identifier
   * @param string $clientId requested OAuth client identifier
   *
   * @return void
   *
   * @throws TooManyRequestsHttpException when the configured budget is exhausted
   */
  private function enforceRateLimit(string $userId, string $clientId): void
  {
    if (null === $this->rateLimiter) {
      return;
    }

    $limit = $this->rateLimiter->create($this->getRateLimitKey($userId, $clientId))->consume();
    if ($limit->isAccepted()) {
      return;
    }

    $retryAfter = $limit->getRetryAfter();
    $seconds = max(0, $retryAfter->getTimestamp() - time());

    throw new TooManyRequestsHttpException(
      $seconds,
      sprintf('Too many consent submissions. Please try again in %d seconds.', $seconds),
    );
  }

  /**
   * Method getRateLimitKey
   *
   * Builds a stable limiter key from truncated hashes so raw user and client identifiers are not stored in the key.
   *
   * @access private
   *
   * @param string $userId authenticated local user identifier
   * @param string $clientId requested OAuth client identifier
   *
   * @return string limiter key containing hashed identifiers
   */
  private function getRateLimitKey(string $userId, string $clientId): string
  {
    $userHash = hash('sha256', $userId);
    $clientHash = hash('sha256', $clientId);

    return sprintf('oauth_consent_grant_%s_%s', substr($userHash, 0, 16), substr($clientHash, 0, 16));
  }

  /**
   * Method parseScopes
   *
   * Splits a space-delimited scope string, trims entries and removes duplicates while preserving order.
   *
   * @access private
   *
   * @param ?string $scope requested OAuth scope string
   *
   * @return list<string> normalized unique scopes
   */
  private function parseScopes(?string $scope): array
  {
    $normalized = trim((string) $scope);
    if ('' === $normalized) {
      return [];
    }

    $items = array_filter(
      array_map('trim', explode(' ', $normalized)),
      static fn (string $item): bool => '' !== $item,
    );

    return array_values(array_unique($items));
  }

  /**
   * Method normalizeValue
   *
   * Keeps only non-empty strings from optional authorization fields and trims surrounding whitespace.
   *
   * @access private
   *
   * @param mixed $value optional raw request value
   *
   * @return ?string normalized string, or null when the value is not a usable string
   */
  private function normalizeValue(mixed $value): ?string
  {
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
   * Method storeNonceFromResponse
   *
   * Stores the request nonce against the authorization code returned by the completed authorization response.
   *
   * @access private
   *
   * @param GrantConsentInput $input consent request containing the optional nonce
   * @param \Psr\Http\Message\ResponseInterface $response completed League authorization response
   *
   * @return void
   */
  private function storeNonceFromResponse(GrantConsentInput $input, \Psr\Http\Message\ResponseInterface $response): void
  {
    $nonce = $this->normalizeValue($input->nonce);
    if (null === $nonce) {
      return;
    }

    $code = $this->extractCodeFromResponse($response);
    if (null === $code) {
      return;
    }

    $this->authCodeRepository->updateNonce($code, $nonce);
  }

  /**
   * Method extractCodeFromResponse
   *
   * Finds the authorization code in the Location header first, then checks the form-post body.
   *
   * @access private
   *
   * @param \Psr\Http\Message\ResponseInterface $response completed League authorization response
   *
   * @return ?string authorization code, or null when the response contains none
   */
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

  /**
   * Method extractCodeFromLocation
   *
   * Searches the redirect query and fragment parameters for the authorization code.
   *
   * @access private
   *
   * @param string $location response Location header
   *
   * @return ?string authorization code, or null when the URI has none
   */
  private function extractCodeFromLocation(string $location): ?string
  {
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
   * Method extractCodeFromParams
   *
   * Returns a string authorization code from parsed redirect parameters.
   *
   * @access private
   *
   * @param array<int|string, mixed> $params parsed query or fragment values
   *
   * @return ?string code value, or null when absent or not a string
   */
  private function extractCodeFromParams(array $params): ?string
  {
    $code = $params['code'] ?? null;
    if (!is_string($code) || '' === $code) {
      return null;
    }

    return $code;
  }

  /**
   * Method extractCodeFromFormPostBody
   *
   * Extracts a code from either ordering of the HTML form name/value attributes or from a query-like body.
   *
   * @access private
   *
   * @param string $body response body produced by the form-post response mode
   *
   * @return ?string authorization code, or null when no supported representation matches
   */
  private function extractCodeFromFormPostBody(string $body): ?string
  {
    if (1 === preg_match('/name=["\']code["\'][^>]*value=["\']([^"\']+)["\']/i', $body, $matches)) {
      return $matches[1];
    }

    if (1 === preg_match('/value=["\']([^"\']+)["\'][^>]*name=["\']code["\']/i', $body, $matches)) {
      return $matches[1];
    }

    return 1 === preg_match('/(?:^|[?&])code=([^&\\s"\']+)/i', $body, $matches)
      ? urldecode($matches[1])
      : null;
  }

  /**
   * Method readResponseBody
   *
   * Reads a seekable response body without changing its current position and returns an empty string on read failure.
   *
   * @access private
   *
   * @param \Psr\Http\Message\ResponseInterface $response response whose body may contain the form-post code
   *
   * @return string response body, or an empty string when it cannot be read
   */
  private function readResponseBody(\Psr\Http\Message\ResponseInterface $response): string
  {
    try {
      $body = $response->getBody();
      if (!$body->isReadable()) {
        return '';
      }

      if ($body->isSeekable()) {
        $position = $body->tell();
        $body->rewind();
        $contents = $body->getContents();
        $body->seek($position);
      } else {
        $contents = $body->getContents();
      }

      return $contents;
    } catch (Throwable) {
      return '';
    }
  }

  /**
   * Method convertPsrResponse
   *
   * Converts League's PSR-7 response to the Symfony response returned by API Platform.
   *
   * @access private
   *
   * @param \Psr\Http\Message\ResponseInterface $psrResponse response generated by League OAuth
   *
   * @return Response HTTP Foundation response for the API client
   */
  private function convertPsrResponse(\Psr\Http\Message\ResponseInterface $psrResponse): Response
  {
    $httpFoundationFactory = new HttpFoundationFactory();

    return $httpFoundationFactory->createResponse($psrResponse);
  }
  // #endregion
}
