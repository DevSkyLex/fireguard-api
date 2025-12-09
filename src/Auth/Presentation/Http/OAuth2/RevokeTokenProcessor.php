<?php

declare(strict_types=1);

namespace Auth\Presentation\Http\OAuth2;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Presentation\Dto\Input\TokenRevocationInput;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Token\Plain;
use League\OAuth2\Server\CryptTrait;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Processor RevokeTokenProcessor
 * @final
 *
 * Processor for OAuth2 Token Revocation (RFC 7009).
 *
 * @category Processor
 * @package Auth\Presentation\Http\OAuth2
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, JsonResponse|null>
 */
final class RevokeTokenProcessor implements ProcessorInterface
{
  use CryptTrait;

  //#region Constructor
  public function __construct(
    private readonly AccessTokenRepositoryInterface $accessTokenRepository,
    private readonly RefreshTokenRepositoryInterface $refreshTokenRepository,
    #[Autowire(service: 'monolog.logger.security')]
    private readonly LoggerInterface $logger,
    string $encryptionKey,
  ) {
    $this->setEncryptionKey($encryptionKey);
  }
  //#endregion

  //#region Methods
  /**
   * {@inheritDoc}
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

    if ($this->revokeRefreshToken($token) || $this->revokeAccessToken($token)) {
      return new JsonResponse(null, Response::HTTP_OK);
    }

    return new JsonResponse(null, Response::HTTP_OK);
  }

  private function revokeRefreshToken(string $token): bool
  {
    try {
      $decrypted = $this->decrypt($token);
      $payload = json_decode($decrypted, true);

      if (isset($payload['refresh_token_id'])) {
        $this->refreshTokenRepository->revokeRefreshToken($payload['refresh_token_id']);
        $this->logger->info('Refresh token revoked', [
          'token_id' => $payload['refresh_token_id'],
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
      if ($token === '') {
        return false;
      }

      $parser = new Parser(new JoseEncoder());
      $parsedToken = $parser->parse($token);

      if ($parsedToken instanceof Plain) {
        $claims = $parsedToken->claims();
        if ($claims->has('jti')) {
          $tokenId = $claims->get('jti');
          $this->accessTokenRepository->revokeAccessToken($tokenId);
          $this->logger->info('Access token revoked', [
            'token_id' => $tokenId,
          ]);
          return true;
        }
      }
    } catch (Throwable $e) {
    }

    return false;
  }
  //#endregion
}
