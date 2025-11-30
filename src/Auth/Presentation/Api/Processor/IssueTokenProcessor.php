<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Presentation\Api\Dto\TokenInput;
use Auth\Presentation\Api\Dto\TokenOutput;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response as Psr7Response;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Processor IssueTokenProcessor
 * @final
 *
 * Processor for OAuth2 Token Issuance.
 *
 * @category Processor
 * @package Auth\Presentation\Api\Processor
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<TokenInput, TokenOutput|null>
 */
final readonly class IssueTokenProcessor implements ProcessorInterface
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the IssueTokenProcessor class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param AuthorizationServer $authorizationServer The authorization server.
   * @param RequestStack $requestStack The request stack.
   */
  public function __construct(
    private readonly AuthorizationServer $authorizationServer,
    private readonly RequestStack $requestStack
  ) {
  }
  //#endregion

  //#region Methods
  /**
   * Method process
   * {@inheritDoc}
   *
   * Processes the token issuance.
   *
   * @access public
   * @since 1.0.0
   *
   * @param mixed $data The data.
   * @param Operation $operation The operation.
   * @param array<string, mixed> $uriVariables The URI variables.
   * @param array<string, mixed> $context The context.
   *
   * @return TokenOutput|null The result.
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?TokenOutput
  {
    if (!$data instanceof TokenInput) return null;

    $request = $this->requestStack->getCurrentRequest();
    if (!$request) return null;

    // Convert Symfony Request to PSR-7 Request
    $psr17Factory = new Psr17Factory();
    $psrHttpFactory = new PsrHttpFactory(
      serverRequestFactory: $psr17Factory,
      streamFactory: $psr17Factory,
      uploadedFileFactory: $psr17Factory,
      responseFactory: $psr17Factory
    );

    $psrRequest = $psrHttpFactory->createRequest($request);

    // Manually inject parsed body from DTO because API Platform might have consumed it
    // and OAuth2 Server expects it in the PSR-7 request
    $parsedBody = [
      'grant_type' => $data->grantType,
      'client_id' => $data->clientId,
      'client_secret' => $data->clientSecret,
      'scope' => $data->scope,
      'refresh_token' => $data->refreshToken,
      'code' => $data->code,
      'redirect_uri' => $data->redirectUri,
      'code_verifier' => $data->codeVerifier,
    ];
    // Filter out null values
    $parsedBody = array_filter($parsedBody, fn($value) => !is_null($value));

    $psrRequest = $psrRequest->withParsedBody($parsedBody);

    try {
      $response = $this->authorizationServer->respondToAccessTokenRequest($psrRequest, new Psr7Response());

      $body = json_decode((string) $response->getBody(), true);

      $tokenResponse = new TokenOutput();
      $tokenResponse->accessToken = $body['access_token'] ?? null;
      $tokenResponse->tokenType = $body['token_type'] ?? null;
      $tokenResponse->expiresIn = $body['expires_in'] ?? null;
      $tokenResponse->refreshToken = $body['refresh_token'] ?? null;
      $tokenResponse->scope = $body['scope'] ?? null;

      return $tokenResponse;

    } catch (OAuthServerException $exception) {
      throw new BadRequestHttpException(
        message: $exception->getMessage(),
        previous: $exception
      );
    }
  }
  //#endregion
}
