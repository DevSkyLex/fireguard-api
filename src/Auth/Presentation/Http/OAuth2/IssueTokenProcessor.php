<?php

declare(strict_types=1);

namespace Auth\Presentation\Http\OAuth2;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Presentation\Dto\Input\TokenInput;
use Auth\Presentation\Dto\Output\TokenOutput;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response as Psr7Response;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Processor IssueTokenProcessor
 * @final
 *
 * Processor for OAuth2 Token Issuance.
 *
 * @category Processor
 * @package Auth\Presentation\Http\OAuth2
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, TokenOutput|null>
 */
final readonly class IssueTokenProcessor implements ProcessorInterface
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the
   * IssueTokenProcessor class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param AuthorizationServer $authorizationServer The authorization server.
   * @param RequestStack $requestStack The request stack.
   * @param LoggerInterface $logger The logger.
   */
  public function __construct(
    private readonly AuthorizationServer $authorizationServer,
    private readonly RequestStack $requestStack,
    #[Autowire(service: 'monolog.logger.security')]
    private readonly LoggerInterface $logger,
  ) {}
  //#endregion

  //#region Methods
  /**
   * Method process
   * {@inheritDoc}
   * 
   * Processes the token issuance request.
   * 
   * @access public
   * @since 1.0.0
   * 
   * @param mixed $data The data.
   * @param Operation $operation The operation.
   * @param array<string, mixed> $uriVariables The URI variables.
   * @param array<string, mixed> $context The context.
   * 
   * @return ?TokenOutput The token output.
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?TokenOutput
  {
    if (!$data instanceof TokenInput) return null;

    $request = $this->requestStack->getCurrentRequest();
    if (!$request) return null;

    $psr17Factory = new Psr17Factory();
    $psrHttpFactory = new PsrHttpFactory(
      serverRequestFactory: $psr17Factory,
      streamFactory: $psr17Factory,
      uploadedFileFactory: $psr17Factory,
      responseFactory: $psr17Factory
    );

    $psrRequest = $psrHttpFactory->createRequest($request);

    $parsedBody = array_filter([
      'grant_type'    => $data->grantType,
      'client_id'     => $data->clientId,
      'client_secret' => $data->clientSecret,
      'scope'         => $data->scope,
      'refresh_token' => $data->refreshToken,
      'code'          => $data->code,
      'redirect_uri'  => $data->redirectUri,
      'code_verifier' => $data->codeVerifier,
    ], fn($value)     => $value !== null);

    $psrRequest = $psrRequest->withParsedBody(data: $parsedBody);

    try {
      $response = $this->authorizationServer->respondToAccessTokenRequest(
        request: $psrRequest,
        response: new Psr7Response()
      );

      $body = json_decode(
        json: (string) $response->getBody(),
        associative: true
      );

      $this->logger->info('OAuth2 token issued', [
        'grant_type' => $data->grantType,
        'client_id'  => $data->clientId,
        'ip'         => $request->getClientIp(),
      ]);

      $tokenResponse = new TokenOutput();
      $tokenResponse->accessToken = $body['access_token'] ?? null;
      $tokenResponse->tokenType = $body['token_type'] ?? null;
      $tokenResponse->expiresIn = $body['expires_in'] ?? null;
      $tokenResponse->refreshToken = $body['refresh_token'] ?? null;
      $tokenResponse->scope = $body['scope'] ?? null;

      return $tokenResponse;

    } catch (OAuthServerException $exception) {
      $this->logger->warning('OAuth2 token issuance failed', [
        'grant_type' => $data->grantType,
        'client_id'  => $data->clientId,
        'error'      => $exception->getMessage(),
        'ip'         => $request->getClientIp(),
      ]);

      throw new BadRequestHttpException(
        message: $exception->getMessage(),
        previous: $exception
      );
    }
  }
  //#endregion
}
