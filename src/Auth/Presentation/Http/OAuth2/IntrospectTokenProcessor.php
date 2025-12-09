<?php

declare(strict_types=1);

namespace Auth\Presentation\Http\OAuth2;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Application\Port\Outbound\AccessTokenRepositoryPort;
use Auth\Application\Port\Outbound\RefreshTokenRepositoryPort;
use Auth\Presentation\Dto\Input\TokenIntrospectionInput;
use Auth\Presentation\Dto\Output\TokenIntrospectionOutput;
use DateTimeImmutable;
use DateTimeInterface;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\UnencryptedToken;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

/**
 * Processor IntrospectTokenProcessor
 * @final
 *
 * Processor for OAuth2 Token Introspection (RFC 7662).
 *
 * @category Processor
 * @package Auth\Presentation\Http\OAuth2
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, TokenIntrospectionOutput>
 */
final readonly class IntrospectTokenProcessor implements ProcessorInterface
{
  //#region Constructor
  public function __construct(
    private AccessTokenRepositoryPort $accessTokenRepository,
    private RefreshTokenRepositoryPort $refreshTokenRepository,
    #[Autowire('%env(OAUTH_ISSUER)%')]
    private string $issuer = '',
  ) {
  }
  //#endregion

  //#region Methods
  /**
   * {@inheritDoc}
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TokenIntrospectionOutput
  {
    $output = new TokenIntrospectionOutput();

    if (!$data instanceof TokenIntrospectionInput || empty($data->token)) {
      return $output;
    }

    $tokenTypeHint = $data->tokenTypeHint ?? 'access_token';

    try {
      if ($tokenTypeHint === 'refresh_token') {
        return $this->introspectRefreshToken($data->token, $output);
      }

      return $this->introspectAccessToken($data->token, $output);
    } catch (Throwable) {
      return $output;
    }
  }

  private function introspectAccessToken(string $token, TokenIntrospectionOutput $output): TokenIntrospectionOutput
  {
    if ($token === '')
      return $output;

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

    $accessToken = $this->accessTokenRepository->find($tokenId);

    if ($accessToken === null || $accessToken->isRevoked() || $accessToken->isExpired()) {
      return $output;
    }

    $output->active = true;
    $output->tokenType = 'Bearer';
    $output->jti = $tokenId;
    $output->clientId = (string) $accessToken->clientIdentifier();
    $output->scope = implode(' ', $accessToken->scopes()->toArray());
    $output->exp = $accessToken->expiry()->getTimestamp();
    $output->sub = $accessToken->userIdentifier();
    $output->iss = $this->issuer;

    if ($claims->has('iat')) {
      $iat = $claims->get('iat');
      $output->iat = $iat instanceof DateTimeInterface ? $iat->getTimestamp() : (int) $iat;
    }

    if ($claims->has('nbf')) {
      $nbf = $claims->get('nbf');
      $output->nbf = $nbf instanceof DateTimeInterface ? $nbf->getTimestamp() : (int) $nbf;
    }

    if ($claims->has('aud')) {
      $aud = $claims->get('aud');
      $output->aud = is_array($aud) ? implode(' ', $aud) : (string) $aud;
    }

    return $output;
  }

  private function introspectRefreshToken(string $token, TokenIntrospectionOutput $output): TokenIntrospectionOutput
  {
    $refreshToken = $this->refreshTokenRepository->find($token);

    if ($refreshToken === null || $refreshToken->isRevoked()) {
      return $output;
    }

    if ($refreshToken->expiryDateTime() < new DateTimeImmutable()) {
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
