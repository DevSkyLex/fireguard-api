<?php

declare(strict_types=1);

namespace OAuth\Presentation\Api\Processor\Token;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response as Psr7Response;
use OAuth\Presentation\Api\Dto\Input\TokenInput;
use OAuth\Presentation\Api\Dto\Output\TokenOutput;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

use function array_filter;
use function is_array;
use function is_int;
use function is_string;
use function json_decode;

/**
 * Processor IssueTokenProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, TokenOutput|null>
 */
final readonly class IssueTokenProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * IssueTokenProcessor class.
   *
   * @since 1.0.0
   *
   * @param AuthorizationServer $authorizationServer the authorization server
   * @param RequestStack $requestStack the request stack
   * @param LoggerInterface $logger the logger
   */
  public function __construct(
    private readonly AuthorizationServer $authorizationServer,
    private readonly RequestStack $requestStack,
    #[Autowire(service: 'monolog.logger.security')]
    private readonly LoggerInterface $logger,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method process
   * {@inheritDoc}
   *
   * Processes the token issuance request.
   *
   * @since 1.0.0
   *
   * @param mixed $data the data
   * @param Operation $operation the operation
   * @param array<string, mixed> $uriVariables the URI variables
   * @param array<string, mixed> $context the context
   *
   * @return ?TokenOutput the token output
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?TokenOutput
  {
    if (!$data instanceof TokenInput) {
      return null;
    }

    $request = $this->requestStack->getCurrentRequest();
    if (!$request) {
      return null;
    }

    $psr17Factory = new Psr17Factory();
    $psrHttpFactory = new PsrHttpFactory(
      serverRequestFactory: $psr17Factory,
      streamFactory: $psr17Factory,
      uploadedFileFactory: $psr17Factory,
      responseFactory: $psr17Factory,
    );

    $psrRequest = $psrHttpFactory->createRequest($request);

    $parsedBody = array_filter([
      'grant_type' => $data->grantType,
      'client_id' => $data->clientId,
      'client_secret' => $data->clientSecret,
      'scope' => $data->scope,
      'refresh_token' => $data->refreshToken,
      'code' => $data->code,
      'redirect_uri' => $data->redirectUri,
      'code_verifier' => $data->codeVerifier,
    ], fn ($value) => null !== $value);

    $psrRequest = $psrRequest->withParsedBody(data: $parsedBody);

    try {
      $response = $this->authorizationServer->respondToAccessTokenRequest(
        request: $psrRequest,
        response: new Psr7Response(),
      );

      $body = json_decode(
        json: (string) $response->getBody(),
        associative: true,
      );

      if (!is_array($body)) {
        $body = [];
      }

      /** @var array<string, mixed> $body */
      $this->logger->info('OAuth2 token issued', [
        'grant_type' => $data->grantType,
        'client_id' => $data->clientId,
        'ip' => $request->getClientIp(),
      ]);

      $accessToken = $body['access_token'] ?? null;
      $tokenType = $body['token_type'] ?? null;
      $expiresIn = $body['expires_in'] ?? null;
      $refreshToken = $body['refresh_token'] ?? null;
      $scope = $body['scope'] ?? null;

      $tokenResponse = new TokenOutput();
      $tokenResponse->accessToken = is_string($accessToken) ? $accessToken : null;
      $tokenResponse->tokenType = is_string($tokenType) ? $tokenType : null;
      $tokenResponse->expiresIn = is_int($expiresIn) ? $expiresIn : null;
      $tokenResponse->refreshToken = is_string($refreshToken) ? $refreshToken : null;
      $tokenResponse->scope = is_string($scope) ? $scope : null;

      return $tokenResponse;

    } catch (OAuthServerException $exception) {
      $this->logger->warning('OAuth2 token issuance failed', [
        'grant_type' => $data->grantType,
        'client_id' => $data->clientId,
        'error' => $exception->getMessage(),
        'ip' => $request->getClientIp(),
      ]);

      throw new BadRequestHttpException(
        message: $exception->getMessage(),
        previous: $exception,
      );
    }
  }
  // #endregion
}
