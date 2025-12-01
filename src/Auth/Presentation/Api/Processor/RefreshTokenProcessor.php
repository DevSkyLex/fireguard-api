<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Application\Port\Outbound\JwtTokenServicePort;
use Auth\Presentation\Api\Dto\LoginOutput;
use Auth\Presentation\Api\Port\RefreshTokenCookieServicePort;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use User\Application\Port\Outbound\UserRepositoryPort;
use User\Domain\ValueObject\UserId;

/**
 * Processor RefreshTokenProcessor
 * @final
 *
 * Handles token refresh using the refresh token from HttpOnly cookie.
 * Uses direct JWT generation (not OAuth2).
 *
 * @category Processor
 * @package Auth\Presentation\Api\Processor
 * @version 2.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<object, LoginOutput|null>
 */
final readonly class RefreshTokenProcessor implements ProcessorInterface
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initialize the processor with dependencies.
   *
   * @access public
   * @since 1.0.0
   *
   * @param JwtTokenServicePort $tokenService The JWT token service.
   * @param RequestStack $requestStack The request stack.
   * @param RefreshTokenCookieServicePort $cookieService The cookie service.
   * @param UserRepositoryPort $userRepository The user repository.
   */
  public function __construct(
    private readonly JwtTokenServicePort $tokenService,
    private readonly RequestStack $requestStack,
    private readonly RefreshTokenCookieServicePort $cookieService,
    private readonly UserRepositoryPort $userRepository,
  ) {}
  //#endregion

  //#region Methods
  /**
   * Method process
   * {@inheritDoc}
   *
   * Processes the refresh token.
   *
   * @access public
   * @since 1.0.0
   *
   * @param mixed $data The data.
   * @param Operation $operation The operation.
   * @param array<string, mixed> $uriVariables The URI variables.
   * @param array<string, mixed> $context The context.
   *
   * @return LoginOutput|null The result.
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?LoginOutput
  {
    $request = $this->requestStack->getCurrentRequest();
    if (!$request) return null;

    // Get refresh token from cookie
    $refreshToken = $this->cookieService->getRefreshTokenFromRequest(request: $request);

    if ($refreshToken === null || $refreshToken === '') {
      throw new UnauthorizedHttpException(
        challenge: 'Bearer',
        message: 'No refresh token provided'
      );
    }

    // Decode and validate refresh token
    $payload = $this->tokenService->decodeRefreshToken(refreshToken: $refreshToken);

    if ($payload === null) {
      throw new UnauthorizedHttpException(
        challenge: 'Bearer',
        message: 'Invalid or expired refresh token'
      );
    }

    // Validate user_id is not empty before creating UserId
    if ($payload['user_id'] === '') {
      throw new UnauthorizedHttpException(
        challenge: 'Bearer',
        message: 'Invalid refresh token'
      );
    }

    // Verify user still exists and is active
    $user = $this->userRepository->findById(id: new UserId($payload['user_id']));

    if ($user === null || !$user->canLogin()) {
      throw new UnauthorizedHttpException(
        challenge: 'Bearer',
        message: 'User account is not active'
      );
    }

    $tokens = $this->tokenService->generateTokens(
      userId: $payload['user_id'],
      email: $user->email()->value,
      scopes: $payload['scopes']
    );

    // Build output
    $output = new LoginOutput();
    $output->accessToken = $tokens['access_token'];
    $output->tokenType = $tokens['token_type'];
    $output->expiresIn = $tokens['expires_in'];
    $output->scope = implode(' ', $payload['scopes']);

    // Set new refresh token in cookie
    $cookie = $this->cookieService->createCookie(
      refreshToken: $tokens['refresh_token']
    );

    $request->attributes->set(
      key: '_refresh_token_cookie',
      value: $cookie
    );

    return $output;
  }
}
