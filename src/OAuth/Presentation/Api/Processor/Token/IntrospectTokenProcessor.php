<?php

declare(strict_types=1);

namespace OAuth\Presentation\Api\Processor\Token;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use DateTimeImmutable;
use DateTimeInterface;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\UnencryptedToken;
use OAuth\Application\Port\Outbound\AccessTokenRepositoryPort;
use OAuth\Application\Port\Outbound\RefreshTokenRepositoryPort;
use OAuth\Presentation\Api\Dto\Input\TokenIntrospectionInput;
use OAuth\Presentation\Api\Dto\Output\TokenIntrospectionOutput;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

use function array_map;
use function implode;
use function is_array;
use function is_int;
use function is_scalar;
use function is_string;

/**
 * Processor IntrospectTokenProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, TokenIntrospectionOutput>
 */
final readonly class IntrospectTokenProcessor implements ProcessorInterface
{
    // #region Constructor
    public function __construct(
        private AccessTokenRepositoryPort $accessTokenRepository,
        private RefreshTokenRepositoryPort $refreshTokenRepository,
        #[Autowire('%env(OAUTH_ISSUER)%')]
        private string $issuer = '',
    ) {
    }
    // #endregion

    // #region Methods
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TokenIntrospectionOutput
    {
        $output = new TokenIntrospectionOutput();

        if (!$data instanceof TokenIntrospectionInput || empty($data->token)) {
            return $output;
        }

        $tokenTypeHint = $data->tokenTypeHint ?? 'access_token';

        try {
            if ('refresh_token' === $tokenTypeHint) {
                return $this->introspectRefreshToken($data->token, $output);
            }

            return $this->introspectAccessToken($data->token, $output);
        } catch (Throwable) {
            return $output;
        }
    }

    private function introspectAccessToken(string $token, TokenIntrospectionOutput $output): TokenIntrospectionOutput
    {
        if ('' === $token) {
            return $output;
        }

        $parser = new Parser(new JoseEncoder());
        $parsedToken = $parser->parse($token);

        if (!$parsedToken instanceof UnencryptedToken) {
            return $output;
        }

        $claims = $parsedToken->claims();
        $tokenId = $claims->get('jti');

        if (!is_string($tokenId)) {
            return $output;
        }

        $accessToken = $this->accessTokenRepository->find($tokenId);

        if (null === $accessToken || $accessToken->isRevoked() || $accessToken->isExpired()) {
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
            if ($iat instanceof DateTimeInterface) {
                $output->iat = $iat->getTimestamp();
            } elseif (is_int($iat)) {
                $output->iat = $iat;
            }
        }

        if ($claims->has('nbf')) {
            $nbf = $claims->get('nbf');
            if ($nbf instanceof DateTimeInterface) {
                $output->nbf = $nbf->getTimestamp();
            } elseif (is_int($nbf)) {
                $output->nbf = $nbf;
            }
        }

        if ($claims->has('aud')) {
            $aud = $claims->get('aud');
            if (is_array($aud)) {
                $output->aud = implode(' ', array_map(static fn ($v): string => is_scalar($v) ? (string) $v : '', $aud));
            } elseif (is_string($aud)) {
                $output->aud = $aud;
            }
        }

        return $output;
    }

    private function introspectRefreshToken(string $token, TokenIntrospectionOutput $output): TokenIntrospectionOutput
    {
        $refreshToken = $this->refreshTokenRepository->find($token);

        if (null === $refreshToken || $refreshToken->isRevoked()) {
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
    // #endregion
}
