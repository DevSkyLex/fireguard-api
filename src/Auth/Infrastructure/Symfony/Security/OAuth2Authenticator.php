<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Symfony\Security;

use Auth\Application\Port\Outbound\AccessTokenRepositoryPort;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\UnencryptedToken;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Throwable;

/**
 * Authenticator OAuth2Authenticator
 * @final
 *
 * Symfony Security Authenticator for OAuth2 Bearer tokens.
 * Validates JWT access tokens and loads the associated user.
 *
 * @category Security
 * @package Auth\Infrastructure\Symfony\Security
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OAuth2Authenticator extends AbstractAuthenticator
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the OAuth2Authenticator class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param AccessTokenRepositoryPort $accessTokenRepository The access token repository.
   * @param SecurityUserProvider $userProvider The user provider.
   */
  public function __construct(
    private readonly AccessTokenRepositoryPort $accessTokenRepository,
    private readonly SecurityUserProvider $userProvider
  ) {}
  //#endregion

  //#region Methods
  /**
   * Method supports
   * {@inheritDoc}
   *
   * Checks if this authenticator supports the given request.
   *
   * @access public
   * @since 1.0.0
   *
   * @param Request $request The request.
   *
   * @return bool True if this authenticator supports the request.
   */
  public function supports(Request $request): bool
  {
    return $request->headers->has(key: 'Authorization')
      && str_starts_with(
        haystack: $request->headers->get(key: 'Authorization', default: ''),
        needle: 'Bearer '
      );
  }

  /**
   * Method authenticate
   * {@inheritDoc}
   *
   * Authenticates the request.
   *
   * @access public
   * @since 1.0.0
   *
   * @param Request $request The request.
   *
   * @return Passport The passport.
   *
   * @throws AuthenticationException If authentication fails.
   */
  public function authenticate(Request $request): Passport
  {
    $authHeader = $request->headers->get(key: 'Authorization', default: '');
    $token = substr($authHeader, 7);

    if ($token === '') throw new CustomUserMessageAuthenticationException(
      message: 'No access token provided'
    );

    try {
      // Parse the JWT token
      $parser = new Parser(new JoseEncoder());
      $parsedToken = $parser->parse($token);

      if (!$parsedToken instanceof UnencryptedToken) {
        throw new CustomUserMessageAuthenticationException(
          message: 'Invalid token format'
        );
      }

      $claims = $parsedToken->claims();
      $tokenId = $claims->get('jti');
      $userId = $claims->get('sub');

      if ($tokenId === null || $userId === null) {
        throw new CustomUserMessageAuthenticationException(
          message: 'Invalid token: missing required claims'
        );
      }

      // Verify token is not revoked and not expired
      $accessToken = $this->accessTokenRepository->find($tokenId);
      if ($accessToken === null) {
        throw new CustomUserMessageAuthenticationException(
          message: 'Token not found'
        );
      }

      if ($accessToken->isRevoked()) {
        throw new CustomUserMessageAuthenticationException(
          message: 'Token has been revoked'
        );
      }

      if ($accessToken->isExpired()) {
        throw new CustomUserMessageAuthenticationException(
          message: 'Token has expired'
        );
      }

      // Get scopes from the token
      $scopes = $accessToken->scopes()->toArray();

      // Create a user badge that loads the user with scopes
      $userBadge = new UserBadge(
        userIdentifier: (string) $userId,
        userLoader: fn(string $id) => $this->userProvider->loadUserById($id, $scopes)
      );

      return new SelfValidatingPassport($userBadge);
    }
    catch (CustomUserMessageAuthenticationException $exception) {
      throw $exception;
    }
    catch (Throwable $exception) {
      throw new CustomUserMessageAuthenticationException(
        message: 'Invalid access token: ' . $exception->getMessage()
      );
    }
  }

  /**
   * Method onAuthenticationSuccess
   * {@inheritDoc}
   *
   * Called when authentication is successful.
   *
   * @access public
   * @since 1.0.0
   *
   * @param Request $request The request.
   * @param TokenInterface $token The token.
   * @param string $firewallName The firewall name.
   *
   * @return Response|null The response or null to continue.
   */
  public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
  {
    // Let the request continue
    return null;
  }

  /**
   * Method onAuthenticationFailure
   * {@inheritDoc}
   *
   * Called when authentication fails.
   *
   * @access public
   * @since 1.0.0
   *
   * @param Request $request The request.
   * @param AuthenticationException $exception The exception.
   *
   * @return Response The response.
   */
  public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
  {
    return new JsonResponse(
      data: [
        'error' => 'invalid_token',
        'error_description' => $exception->getMessageKey(),
      ],
      status: Response::HTTP_UNAUTHORIZED,
      headers: ['WWW-Authenticate' => 'Bearer error="invalid_token"']
    );
  }
  //#endregion
}
