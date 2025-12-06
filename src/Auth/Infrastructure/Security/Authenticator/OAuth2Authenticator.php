<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Security\Authenticator;

use Auth\Application\Port\Outbound\AccessTokenRepositoryPort;
use Auth\Infrastructure\Security\User\SecurityUser;
use Auth\Infrastructure\Security\User\SecurityUserProvider;
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
 * Symfony Security Authenticator for
 * OAuth2 Bearer tokens.
 *
 * @category Authenticator
 * @package Auth\Infrastructure\Security\Authenticator
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
   * Initialize the authenticator with the access token repository
   * and the security user provider.
   *
   * @access public
   * @since 1.0.0
   *
   * @param AccessTokenRepositoryPort $accessTokenRepository The access token repository.
   * @param SecurityUserProvider $userProvider The security user provider.
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
   * 
   *
   * @access public
   * @since 1.0.0
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
   * @access public
   * @since 1.0.0
   */
  public function authenticate(Request $request): Passport
  {
    $authHeader = $request->headers->get(key: 'Authorization', default: '');
    $token = substr($authHeader, 7);

    if ($token === '') throw new CustomUserMessageAuthenticationException(
      message: 'No access token provided'
    );

    try {
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

      $scopes = $accessToken->scopes()->toArray();

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
   * {@inheritDoc}
   */
  public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
  {
    return null;
  }

  /**
   * {@inheritDoc}
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
