<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Presentation\Api\Port\RefreshTokenCookieServicePort;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Token\Plain;
use League\OAuth2\Server\CryptTrait;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Processor LogoutProcessor
 * @final
 *
 * Handles user logout by revoking tokens and
 * clearing the refresh token cookie.
 *
 * @category Processor
 * @package Auth\Presentation\Api\Processor
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<object, JsonResponse>
 */
final class LogoutProcessor implements ProcessorInterface
{
  //#region Traits
  /**
   * Trait CryptTrait
   *
   * Handles encryption and decryption of tokens.
   *
   * @since 1.0.0
   */
  use CryptTrait;
  //#endregion

  //#region Constructor
  /**
   * Constructor
   *
   * Initializes the processor with the given
   * dependencies.
   *
   * @access public
   * @since 1.0.0
   *
   * @param RequestStack $requestStack The request stack.
   * @param RefreshTokenCookieServicePort $cookieService The cookie service.
   * @param AccessTokenRepositoryInterface $accessTokenRepository The access token repository.
   * @param RefreshTokenRepositoryInterface $refreshTokenRepository The refresh token repository.
   * @param string $encryptionKey The encryption key.
   */
  public function __construct(
    private readonly RequestStack $requestStack,
    private readonly RefreshTokenCookieServicePort $cookieService,
    private readonly AccessTokenRepositoryInterface $accessTokenRepository,
    private readonly RefreshTokenRepositoryInterface $refreshTokenRepository,
    string $encryptionKey,
  ) { $this->setEncryptionKey(key: $encryptionKey); }
  //#endregion

  //#region Methods
  /**
   * Method process
   * {@inheritDoc}
   *
   * Processes the logout.
   *
   * @access public
   * @since 1.0.0
   *
   * @param mixed $data The data.
   * @param Operation $operation The operation.
   * @param array<string, mixed> $uriVariables The URI variables.
   * @param array<string, mixed> $context The context.
   *
   * @return JsonResponse The result.
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): JsonResponse
  {
    $request = $this->requestStack->getCurrentRequest();

    if ($request) {
      // Revoke refresh token from cookie
      $refreshToken = $this->cookieService->getRefreshTokenFromRequest($request);
      if ($refreshToken !== null && $refreshToken !== '') {
        $this->revokeRefreshToken($refreshToken);
      }

      // Revoke access token from Authorization header
      $authHeader = $request->headers->get('Authorization', '');
      if (str_starts_with($authHeader, 'Bearer ')) {
        $accessToken = substr($authHeader, 7);
        $this->revokeAccessToken($accessToken);
      }

      // Clear the refresh token cookie
      $clearCookie = $this->cookieService->createClearCookie();

      $request->attributes->set(
        key: '_refresh_token_cookie',
        value: $clearCookie
      );
    }

    return new JsonResponse(
      data: ['message' => 'Logged out successfully'],
      status: Response::HTTP_OK
    );
  }

  /**
   * Method revokeRefreshToken
   *
   * Revoke a refresh token.
   *
   * @access private
   * @since 1.0.0
   *
   * @param string $token The refresh token.
   *
   * @return void No return value.
   */
  private function revokeRefreshToken(string $token): void
  {
    try {
      $decrypted = $this->decrypt($token);
      $payload = json_decode($decrypted, true);

      if (isset($payload['refresh_token_id'])) {
        $this->refreshTokenRepository->revokeRefreshToken($payload['refresh_token_id']);
      }
    } catch (\Throwable) {
      // Ignore errors - token may be invalid
    }
  }

  /**
   * Method revokeAccessToken
   *
   * Revoke an access token.
   *
   * @access private
   * @since 1.0.0
   *
   * @param string $token The access token.
   *
   * @return void No return value.
   */
  private function revokeAccessToken(string $token): void
  {
    if ($token === '') return;

    try {
      $parser = new Parser(new JoseEncoder());
      $parsedToken = $parser->parse($token);

      if ($parsedToken instanceof Plain) {
        $claims = $parsedToken->claims();
        if ($claims->has('jti')) {
          $this->accessTokenRepository->revokeAccessToken(tokenId: $claims->get(name: 'jti'));
        }
      }
    } catch (Throwable) {
      // Ignore errors - token may be invalid
    }
  }
}
