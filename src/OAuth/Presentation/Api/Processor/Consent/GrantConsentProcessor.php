<?php

declare(strict_types=1);

namespace OAuth\Presentation\Api\Processor\Consent;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use Nyholm\Psr7\{Response as Psr7Response, ServerRequest};
use OAuth\Application\Port\Outbound\Token\AuthCodeRepositoryPort;
use OAuth\Application\UseCase\Command\Consent\GrantConsent\{GrantConsentCommand, GrantConsentResult};
use OAuth\Infrastructure\OAuth2\League\Entity\User as LeagueUser;
use OAuth\Presentation\Api\Dto\Input\Consent\GrantConsentInput;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{JsonResponse, Response};
use Symfony\Component\HttpKernel\Exception\{BadRequestHttpException, UnauthorizedHttpException};
use Throwable;

use function array_filter;
use function array_map;
use function array_unique;
use function array_values;
use function explode;
use function is_array;
use function is_string;
use function parse_str;
use function parse_url;
use function preg_match;
use function trim;
use function urldecode;

/**
 * Processor GrantConsentProcessor.
 *
 * @category Processor
 *
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
   * Constructor.
   *
   * Initializes a new instance of the
   * GrantConsentProcessor class.
   *
   * @since 1.0.0
   *
   * @param AuthorizationServer $authorizationServer the League authorization server
   * @param CommandBusPort $commandBus the command bus
   * @param Security $security the security service
   * @param AuthCodeRepositoryPort $authCodeRepository the auth code repository
   */
  public function __construct(
    private AuthorizationServer $authorizationServer,
    private CommandBusPort $commandBus,
    private Security $security,
    private AuthCodeRepositoryPort $authCodeRepository,
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
   * @since 1.0.0
   *
   * @param mixed $data the input data
   * @param Operation $operation the operation
   * @param array<string, mixed> $uriVariables the URI variables
   * @param array<string, mixed> $context the context
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

    $approved = true === $data->approved;
    $scopes = $this->parseScopes($data->scope);

    if (true === $approved) {
      /** @var GrantConsentResult $result */
      $result = $this->commandBus->dispatch(command: new GrantConsentCommand(
        userId: $securityUser->getId(),
        clientId: (string) $data->clientId,
        scopes: $scopes,
      ));
      unset($result);
    }

    $psrRequest = $this->buildAuthorizationRequest($data);

    try {
      $authorizationRequest = $this->authorizationServer->validateAuthorizationRequest($psrRequest);
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
  }

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
   * @return list<string>
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
