<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Presentation\Api\Dto\TokenRevocationInput;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\{
  Parser,
  Plain
};
use League\OAuth2\Server\CryptTrait;
use League\OAuth2\Server\Repositories\{
  AccessTokenRepositoryInterface,
  RefreshTokenRepositoryInterface
};
use Symfony\Component\HttpFoundation\{
  JsonResponse,
  Response
};
use Throwable;

/**
 * Processor RevokeTokenProcessor
 * @final
 *
 * Processor for OAuth2 Token Revocation.
 *
 * @category Processor
 * @package Auth\Presentation\Api\Processor
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<TokenRevocationInput, JsonResponse|null>
 */
final class RevokeTokenProcessor implements ProcessorInterface
{
  //#region Traits
  /**
   * Trait CryptTrait
   *
   * Provides encryption and decryption
   * functionality.
   *
   * @since 1.0.0
   */
  use CryptTrait;
  //#endregion

  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the RevokeTokenProcessor class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param AccessTokenRepositoryInterface $accessTokenRepository The access token repository.
   * @param RefreshTokenRepositoryInterface $refreshTokenRepository The refresh token repository.
   * @param string $encryptionKey The encryption key.
   */
  public function __construct(
    private readonly AccessTokenRepositoryInterface $accessTokenRepository,
    private readonly RefreshTokenRepositoryInterface $refreshTokenRepository,
    string $encryptionKey
  ) {
    $this->setEncryptionKey($encryptionKey);
  }
  //#endregion

  //#region Methods
  /**
   * Method process
   * {@inheritDoc}
   *
   * Processes the token revocation.
   *
   * @access public
   * @since 1.0.0
   *
   * @param mixed $data The data.
   * @param Operation $operation The operation.
   * @param array<string, mixed> $uriVariables The URI variables.
   * @param array<string, mixed> $context The context.
   *
   * @return JsonResponse|null The result.
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?JsonResponse
  {
    if (!$data instanceof TokenRevocationInput) {
      return null;
    }

    $token = $data->token;
    $hint = $data->tokenTypeHint;

    if ($hint === 'refresh_token') {
      if ($this->revokeRefreshToken($token)) {
        return new JsonResponse(null, Response::HTTP_OK);
      }
    } elseif ($hint === 'access_token') {
      if ($this->revokeAccessToken($token)) {
        return new JsonResponse(null, Response::HTTP_OK);
      }
    }

    // If hint is not provided or revocation failed, try both
    if ($this->revokeRefreshToken($token) || $this->revokeAccessToken($token)) {
      return new JsonResponse(null, Response::HTTP_OK);
    }

    // RFC 7009: The content of the response body is ignored by the client as long as the HTTP status code is 200.
    // If the token is invalid, the authorization server still returns 200.
    return new JsonResponse(null, Response::HTTP_OK);
  }

  /**
   * Method revokeRefreshToken
   *
   * Revokes a refresh token.
   *
   * @access private
   * @since 1.0.0
   *
   * @param string $token The token.
   *
   * @return bool True if revoked, false otherwise.
   */
  private function revokeRefreshToken(string $token): bool
  {
    try {
      $decrypted = $this->decrypt($token);
      $payload = json_decode($decrypted, true);

      if (isset($payload['refresh_token_id'])) {
        $this->refreshTokenRepository->revokeRefreshToken($payload['refresh_token_id']);
        return true;
      }
    } catch (Throwable $e) {
      // Ignore decryption errors
    }

    return false;
  }

  /**
   * Method revokeAccessToken
   *
   * Revokes an access token.
   *
   * @access private
   * @since 1.0.0
   *
   * @param string $token The token.
   *
   * @return bool True if revoked, false otherwise.
   */
  private function revokeAccessToken(string $token): bool
  {
    try {
      if ($token === '') {
        return false;
      }

      $parser = new Parser(new JoseEncoder());
      $parsedToken = $parser->parse($token);

      if ($parsedToken instanceof Plain) {
        $claims = $parsedToken->claims();
        if ($claims->has('jti')) {
          $this->accessTokenRepository->revokeAccessToken($claims->get('jti'));
          return true;
        }
      }
    } catch (Throwable $e) {
      // Ignore parsing errors
    }

    return false;
  }
  //#endregion
}
