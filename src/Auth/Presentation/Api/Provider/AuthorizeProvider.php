<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Presentation\Api\Dto\AuthorizationOutput;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Provider AuthorizeProvider
 * @final
 *
 * Provider for OAuth2 Authorization Request.
 * Validates the authorization request and returns details for the consent screen.
 *
 * OAuth 2.1 Requirements:
 * - state parameter is REQUIRED (CSRF protection)
 * - PKCE (code_challenge) is REQUIRED for authorization_code grant
 * - response_type must be "code" (implicit flow is deprecated)
 *
 * @category Provider
 * @package Auth\Presentation\Api\Provider
 * @version 2.0.0
 *
 * @see https://datatracker.ietf.org/doc/html/draft-ietf-oauth-v2-1-07
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<AuthorizationOutput>
 */
final readonly class AuthorizeProvider implements ProviderInterface
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the AuthorizeProvider class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param AuthorizationServer $authorizationServer The authorization server.
   * @param RequestStack $requestStack The request stack.
   */
  public function __construct(
    private AuthorizationServer $authorizationServer,
    private RequestStack $requestStack
  ) {
  }
  //#endregion

  //#region Methods
  /**
   * Method provide
   * {@inheritDoc}
   *
   * Validates the authorization request and returns details for the consent screen.
   *
   * @access public
   * @since 1.0.0
   *
   * @param Operation $operation The operation.
   * @param array<string, mixed> $uriVariables The URI variables.
   * @param array<string, mixed> $context The context.
   *
   * @return AuthorizationOutput|null The result.
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?AuthorizationOutput
  {
    $request = $this->requestStack->getCurrentRequest();
    if (!$request) {
      return null;
    }

    // OAuth 2.1: state parameter is REQUIRED
    $state = $request->query->get('state');
    if (empty($state)) {
      throw new BadRequestHttpException('The state parameter is required (OAuth 2.1 CSRF protection).');
    }

    // OAuth 2.1: PKCE is REQUIRED for authorization_code grant
    $codeChallenge = $request->query->get('code_challenge');
    if (empty($codeChallenge)) {
      throw new BadRequestHttpException('The code_challenge parameter is required (OAuth 2.1 PKCE).');
    }

    $codeChallengeMethod = $request->query->get('code_challenge_method', 'plain');
    if (!in_array($codeChallengeMethod, ['S256', 'plain'], true)) {
      throw new BadRequestHttpException('Invalid code_challenge_method. Allowed values: S256, plain.');
    }

    // Convert Symfony Request to PSR-7 Request
    $psr17Factory = new Psr17Factory();
    $psrHttpFactory = new PsrHttpFactory($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);
    $psrRequest = $psrHttpFactory->createRequest($request);

    try {
      // Validate the authorization request
      $authRequest = $this->authorizationServer->validateAuthorizationRequest($psrRequest);

      $client = $authRequest->getClient();

      // Build the authorization output for the consent screen
      $resource = new AuthorizationOutput();
      $resource->clientId = $client->getIdentifier();
      $resource->clientName = $client->getName();
      $resource->redirectUri = $authRequest->getRedirectUri();
      $resource->responseType = $authRequest->getGrantTypeId();
      $resource->scope = implode(' ', array_map(fn($s) => $s->getIdentifier(), $authRequest->getScopes()));
      $resource->state = $authRequest->getState();
      $resource->nonce = $request->query->get('nonce');
      $resource->codeChallenge = $codeChallenge;
      $resource->codeChallengeMethod = $codeChallengeMethod;

      // These flags indicate what the frontend needs to do
      // In a real implementation, you would check session/authentication state
      $resource->requiresLogin = true;
      $resource->requiresConsent = true;

      return $resource;

    } catch (OAuthServerException $exception) {
      throw new BadRequestHttpException($exception->getMessage(), $exception);
    }
  }
  //#endregion
}
