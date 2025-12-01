<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Auth\Application\Port\Outbound\JwtTokenServicePort;
use Auth\Presentation\Api\Dto\LoginInput;
use Auth\Presentation\Api\Dto\LoginOutput;
use Auth\Presentation\Api\Port\RefreshTokenCookieServicePort;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Throwable;
use User\Application\UseCase\Query\AuthenticateUser\AuthenticateUserQuery;
use User\Application\UseCase\Query\AuthenticateUser\AuthenticateUserResult;

/**
 * Processor LoginProcessor
 * @final
 *
 * Handles user login with email and password.
 * Issues a JWT access token in the response body and a refresh token in an HttpOnly cookie.
 *
 * Note: This uses direct JWT generation, NOT OAuth2.
 * OAuth2 is for third-party app authorization, not direct user authentication.
 *
 * @category Processor
 * @package Auth\Presentation\Api\Processor
 * @version 2.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<LoginInput, LoginOutput|null>
 */
final readonly class LoginProcessor implements ProcessorInterface
{
  //#region Properties
  /**
   * Constant DEFAULT_SCOPES
   *
   * The default scopes for authenticated users.
   *
   * @access private
   * @since 1.0.0
   *
   * @var array<string>
   */
  private const array DEFAULT_SCOPES = ['OPENID', 'PROFILE', 'EMAIL', 'READ', 'WRITE'];
  //#endregion

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
   * @param QueryBusPort $queryBus The query bus.
   * @param RefreshTokenCookieServicePort $cookieService The cookie service.
   */
  public function __construct(
    private readonly JwtTokenServicePort $tokenService,
    private readonly RequestStack $requestStack,
    private readonly QueryBusPort $queryBus,
    private readonly RefreshTokenCookieServicePort $cookieService,
  ) {}
  //#endregion

  //#region Methods
  /**
   * Method process
   * {@inheritDoc}
   *
   * Processes the login.
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
    if (!$data instanceof LoginInput) return null;

    $request = $this->requestStack->getCurrentRequest();
    if (!$request) {
      return null;
    }

    // Validate user credentials
    $authResult = $this->validateCredentials($data->email, $data->password);

    if ($authResult->userId === null || $authResult->userId === '') {
      throw new UnauthorizedHttpException(
        challenge: 'Bearer',
        message: 'Invalid credentials'
      );
    }

    // Generate JWT tokens directly (no OAuth2 needed for direct login)
    $tokens = $this->tokenService->generateTokens(
      userId: $authResult->userId,
      email: $authResult->email,
      scopes: self::DEFAULT_SCOPES
    );

    // Build output
    $output = new LoginOutput();
    $output->accessToken = $tokens['access_token'];
    $output->tokenType = $tokens['token_type'];
    $output->expiresIn = $tokens['expires_in'];
    $output->scope = implode(' ', self::DEFAULT_SCOPES);

    // Set refresh token in HttpOnly cookie
    $cookie = $this->cookieService->createCookie(
      refreshToken: $tokens['refresh_token'],
      rememberMe: $data->rememberMe
    );
    $request->attributes->set('_refresh_token_cookie', $cookie);

    return $output;
  }

  /**
   * Method validateCredentials
   *
   * Validate user credentials using AuthenticateUserQuery.
   *
   * @access private
   * @since 1.0.0
   *
   * @param string|null $email The email (used as username).
   * @param string|null $password The password.
   *
   * @return AuthenticateUserResult The authentication result.
   *
   * @throws UnauthorizedHttpException If credentials are invalid.
   */
  private function validateCredentials(?string $email, ?string $password): AuthenticateUserResult
  {
    if ($email === null || $password === null) {
      throw new UnauthorizedHttpException(
        challenge: 'Bearer',
        message: 'Invalid credentials'
      );
    }

    try {
      /**
       * Query result
       * @var AuthenticateUserResult $result
       */
      $result = $this->queryBus->ask(
        query: new AuthenticateUserQuery(
          username: $email,
          password: $password
        )
      );

      if (!$result->authenticated) {
        throw new UnauthorizedHttpException(
          challenge: 'Bearer',
          message: 'Invalid credentials'
        );
      }

      return $result;

    }
    catch (UnauthorizedHttpException $exception) {
      throw $exception;
    }
    catch (Throwable) {
      throw new UnauthorizedHttpException(
        challenge: 'Bearer',
        message: 'Invalid credentials'
      );
    }
  }
}
