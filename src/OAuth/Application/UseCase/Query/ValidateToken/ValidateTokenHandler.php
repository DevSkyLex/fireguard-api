<?php

declare(strict_types=1);

namespace OAuth\Application\UseCase\Query\ValidateToken;

use OAuth\Application\Port\Outbound\AccessTokenRepositoryPort;
use OAuth\Application\Port\Outbound\JwtParserPort;
use Shared\Application\Message\QueryHandler;
use Throwable;

use function is_string;

/**
 * Handler ValidateTokenHandler.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ValidateTokenHandler implements QueryHandler
{
    // #region Constructor
    /**
     * Constructor.
     *
     * Initializes a new instance of the
     * ValidateTokenHandler class.
     *
     * @since 1.0.0
     *
     * @param JwtParserPort             $jwtParser             the JWT parser
     * @param AccessTokenRepositoryPort $accessTokenRepository the access token repository
     */
    public function __construct(
        private readonly JwtParserPort $jwtParser,
        private readonly AccessTokenRepositoryPort $accessTokenRepository,
    ) {
    }
    // #endregion

    // #region Methods
    /**
     * Method __invoke.
     *
     * Handles the ValidateTokenQuery.
     *
     * @since 1.0.0
     *
     * @param ValidateTokenQuery $query the query
     *
     * @return ValidateTokenResult the result
     */
    public function __invoke(ValidateTokenQuery $query): ValidateTokenResult
    {
        try {
            $tokenData = $this->jwtParser->parse($query->accessToken);

            if (null === $tokenData) {
                return ValidateTokenResult::invalid('Failed to parse token');
            }

            $tokenId = $tokenData['jti'] ?? null;
            if (null === $tokenId || !is_string($tokenId)) {
                return ValidateTokenResult::invalid('Token has no identifier');
            }

            $accessToken = $this->accessTokenRepository->find($tokenId);

            if (null === $accessToken) {
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

    // #endregion
}
