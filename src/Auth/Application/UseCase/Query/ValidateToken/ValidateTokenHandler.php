<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Query\ValidateToken;

use Auth\Application\Port\Outbound\AccessTokenRepositoryPort;
use Auth\Application\Port\Outbound\JwtParserPort;
use Throwable;
use Shared\Application\Message\QueryHandler;

/**
 * Handler ValidateTokenHandler
 * @final
 *
 * Handles access token validation.
 *
 * @category Handler
 * @package Auth\Application\UseCase\Query\ValidateToken
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ValidateTokenHandler implements QueryHandler
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the
   * ValidateTokenHandler class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param JwtParserPort $jwtParser The JWT parser.
   * @param AccessTokenRepositoryPort $accessTokenRepository The access token repository.
   */
  public function __construct(
    private readonly JwtParserPort $jwtParser,
    private readonly AccessTokenRepositoryPort $accessTokenRepository,
  ) {}
  //#endregion

  //#region Methods
  /**
   * Method __invoke
   *
   * Handles the ValidateTokenQuery.
   *
   * @access public
   * @since 1.0.0
   *
   * @param ValidateTokenQuery $query The query.
   *
   * @return ValidateTokenResult The result.
   */
  public function __invoke(ValidateTokenQuery $query): ValidateTokenResult
  {
    try {
      $tokenData = $this->jwtParser->parse($query->accessToken);

      if ($tokenData === null) {
        return ValidateTokenResult::invalid('Failed to parse token');
      }

      $tokenId = $tokenData['jti'] ?? null;
      if ($tokenId === null) {
        return ValidateTokenResult::invalid('Token has no identifier');
      }

      $accessToken = $this->accessTokenRepository->find($tokenId);

      if ($accessToken === null) {
        return ValidateTokenResult::invalid('Token not found');
      }

      if ($accessToken->isRevoked()) {
        return ValidateTokenResult::invalid('Token has been revoked');
      }

      if ($accessToken->isExpired()) {
        return ValidateTokenResult::invalid('Token has expired');
      }

      return new ValidateTokenResult(
        valid: true,
        tokenId: $tokenId,
        userId: $accessToken->userIdentifier(),
        clientId: (string) $accessToken->clientIdentifier(),
        scopes: $accessToken->scopes()->toArray(),
        expiresAt: $accessToken->expiry()->getTimestamp(),
      );

    } catch (Throwable $e) {
      return ValidateTokenResult::invalid($e->getMessage());
    }
  }

  //#endregion
}
