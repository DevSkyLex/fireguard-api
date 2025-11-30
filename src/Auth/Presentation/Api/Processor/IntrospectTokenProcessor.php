<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Application\Port\Outbound\AccessTokenRepositoryPort;
use Auth\Application\Port\Outbound\RefreshTokenRepositoryPort;
use Auth\Presentation\Api\Dto\TokenIntrospectionInput;
use Auth\Presentation\Api\Dto\TokenIntrospectionOutput;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\UnencryptedToken;
use Throwable;

/**
 * Processor IntrospectTokenProcessor
 * @final
 *
 * Processor for OAuth2 Token Introspection (RFC 7662).
 * Allows resource servers to query the authorization server
 * to determine the active state of an access token.
 *
 * @category Processor
 * @package Auth\Presentation\Api\Processor
 * @version 1.0.0
 *
 * @see https://datatracker.ietf.org/doc/html/rfc7662
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<TokenIntrospectionInput, TokenIntrospectionOutput>
 */
final readonly class IntrospectTokenProcessor implements ProcessorInterface
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the IntrospectTokenProcessor class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param AccessTokenRepositoryPort $accessTokenRepository The access token repository.
   * @param RefreshTokenRepositoryPort $refreshTokenRepository The refresh token repository.
   * @param string $issuer The token issuer (usually the authorization server URL).
   */
  public function __construct(
    private AccessTokenRepositoryPort $accessTokenRepository,
    private RefreshTokenRepositoryPort $refreshTokenRepository,
    private string $issuer = ''
  ) {
  }
  //#endregion

  //#region Methods
  /**
   * Method process
   * {@inheritDoc}
   *
   * Processes the token introspection request.
   *
   * @access public
   * @since 1.0.0
   *
   * @param mixed $data The data.
   * @param Operation $operation The operation.
   * @param array<string, mixed> $uriVariables The URI variables.
   * @param array<string, mixed> $context The context.
   *
   * @return TokenIntrospectionOutput The introspection result.
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TokenIntrospectionOutput
  {
    $output = new TokenIntrospectionOutput();

    if (!$data instanceof TokenIntrospectionInput || empty($data->token)) {
      return $output; // Return inactive token response
    }

    $tokenTypeHint = $data->tokenTypeHint ?? 'access_token';

    try {
      if ($tokenTypeHint === 'refresh_token') {
        return $this->introspectRefreshToken($data->token, $output);
      }

      return $this->introspectAccessToken($data->token, $output);
    } catch (Throwable) {
      // Any error means the token is invalid/inactive
      return $output;
    }
  }

  /**
   * Method introspectAccessToken
   *
   * Introspects an access token.
   *
   * @access private
   * @since 1.0.0
   *
   * @param string $token The token string.
   * @param TokenIntrospectionOutput $output The output DTO.
   *
   * @return TokenIntrospectionOutput The introspection result.
   */
  private function introspectAccessToken(string $token, TokenIntrospectionOutput $output): TokenIntrospectionOutput
  {
    if ($token === '') {
      return $output;
    }

    // Parse the JWT token
    $parser = new Parser(new JoseEncoder());
    $parsedToken = $parser->parse($token);

    if (!$parsedToken instanceof UnencryptedToken) {
      return $output;
    }

    $claims = $parsedToken->claims();
    $tokenId = $claims->get('jti');

    if ($tokenId === null) {
      return $output;
    }

    // Check if token exists and is not revoked
    $accessToken = $this->accessTokenRepository->find($tokenId);

    if ($accessToken === null || $accessToken->isRevoked() || $accessToken->isExpired()) {
      return $output;
    }

    // Token is active
    $output->active = true;
    $output->tokenType = 'Bearer';
    $output->jti = $tokenId;
    $output->clientId = (string) $accessToken->clientIdentifier();
    $output->scope = implode(' ', $accessToken->scopes()->toArray());
    $output->exp = $accessToken->expiry()->getTimestamp();
    $output->sub = $accessToken->userIdentifier();
    $output->iss = $this->issuer;

    // Extract additional claims from JWT if available
    if ($claims->has('iat')) {
      $iat = $claims->get('iat');
      $output->iat = $iat instanceof \DateTimeInterface ? $iat->getTimestamp() : (int) $iat;
    }

    if ($claims->has('nbf')) {
      $nbf = $claims->get('nbf');
      $output->nbf = $nbf instanceof \DateTimeInterface ? $nbf->getTimestamp() : (int) $nbf;
    }

    if ($claims->has('aud')) {
      $aud = $claims->get('aud');
      $output->aud = is_array($aud) ? implode(' ', $aud) : (string) $aud;
    }

    return $output;
  }

  /**
   * Method introspectRefreshToken
   *
   * Introspects a refresh token.
   *
   * @access private
   * @since 1.0.0
   *
   * @param string $token The token string.
   * @param TokenIntrospectionOutput $output The output DTO.
   *
   * @return TokenIntrospectionOutput The introspection result.
   */
  private function introspectRefreshToken(string $token, TokenIntrospectionOutput $output): TokenIntrospectionOutput
  {
    $refreshToken = $this->refreshTokenRepository->find($token);

    if ($refreshToken === null || $refreshToken->isRevoked()) {
      return $output;
    }

    // Check if expired
    if ($refreshToken->expiryDateTime() < new \DateTimeImmutable()) {
      return $output;
    }

    $output->active = true;
    $output->tokenType = 'refresh_token';
    $output->jti = $refreshToken->identifier();
    $output->exp = $refreshToken->expiryDateTime()->getTimestamp();
    $output->iss = $this->issuer;

    return $output;
  }
  //#endregion
}
