<?php

declare(strict_types=1);

namespace OAuth\Presentation\Api\Processor\Token;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Token\Plain;
use League\OAuth2\Server\CryptTrait;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use OAuth\Presentation\Api\Dto\Input\TokenRevocationInput;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

use function is_array;
use function is_string;
use function json_decode;

/**
 * Processor RevokeTokenProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, JsonResponse|null>
 */
final class RevokeTokenProcessor implements ProcessorInterface
{
  use CryptTrait;

  // #region Constructor
  public function __construct(
    private readonly AccessTokenRepositoryInterface $accessTokenRepository,
    private readonly RefreshTokenRepositoryInterface $refreshTokenRepository,
    #[Autowire(service: 'monolog.logger.security')]
    private readonly LoggerInterface $logger,
    string $encryptionKey,
  ) {
    $this->setEncryptionKey($encryptionKey);
  }
  // #endregion

  // #region Methods
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?JsonResponse
  {
    if (!$data instanceof TokenRevocationInput) {
      return null;
    }

    $token = $data->token;
    $hint = $data->tokenTypeHint;

    if ('refresh_token' === $hint && null !== $token) {
      if ($this->revokeRefreshToken($token)) {
        return new JsonResponse(null, Response::HTTP_OK);
      }
    } elseif ('access_token' === $hint && null !== $token) {
      if ($this->revokeAccessToken($token)) {
        return new JsonResponse(null, Response::HTTP_OK);
      }
    }

    if (null !== $token && ($this->revokeRefreshToken($token) || $this->revokeAccessToken($token))) {
      return new JsonResponse(null, Response::HTTP_OK);
    }

    return new JsonResponse(null, Response::HTTP_OK);
  }

  private function revokeRefreshToken(string $token): bool
  {
    try {
      $decrypted = $this->decrypt($token);
      $payload = json_decode($decrypted, true);

      if (!is_array($payload)) {
        return false;
      }

      $refreshTokenId = $payload['refresh_token_id'] ?? null;
      if (is_string($refreshTokenId)) {
        $this->refreshTokenRepository->revokeRefreshToken($refreshTokenId);
        $this->logger->info('Refresh token revoked', [
          'token_id' => $refreshTokenId,
        ]);

        return true;
      }
    } catch (Throwable $e) {
    }

    return false;
  }

  private function revokeAccessToken(string $token): bool
  {
    try {
      if ('' === $token) {
        return false;
      }

      $parser = new Parser(new JoseEncoder());
      $parsedToken = $parser->parse($token);

      if ($parsedToken instanceof Plain) {
        $claims = $parsedToken->claims();
        if ($claims->has('jti')) {
          $tokenId = $claims->get('jti');
          if (is_string($tokenId)) {
            $this->accessTokenRepository->revokeAccessToken($tokenId);
            $this->logger->info('Access token revoked', [
              'token_id' => $tokenId,
            ]);

            return true;
          }
        }
      }
    } catch (Throwable $e) {
    }

    return false;
  }
  // #endregion
}
