<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Security\Authenticator;

use Auth\Application\Port\Outbound\AccessTokenRepositoryPort;
use Auth\Infrastructure\Security\User\SecurityUser;
use Auth\Infrastructure\Security\User\SecurityUserProvider;
use DateTimeImmutable;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\UnencryptedToken;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Validator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
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
 * OAuth2 Bearer tokens and JWT login tokens.
 *
 * @category Authenticator
 * @package Auth\Infrastructure\Security\Authenticator
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OAuth2Authenticator extends AbstractAuthenticator
{
  //#region Properties
  /**
   * Property jwtConfig
   * 
   * JWT Configuration
   * 
   * @access private
   * @since 1.0.0
   * 
   * @var Configuration
   */
  private Configuration $jwtConfig;
  //#endregion

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
    private readonly SecurityUserProvider $userProvider,
    #[Autowire('%kernel.project_dir%/config/jwt/public.key')]
    string $publicKeyPath
  ) {
    if ($publicKeyPath === '') {
      throw new \InvalidArgumentException('Public key path cannot be empty');
    }

    $this->jwtConfig = Configuration::forAsymmetricSigner(
      signer: new Sha256(),
      signingKey: InMemory::plainText('dummy-key-not-used-for-verification'),
      verificationKey: InMemory::file($publicKeyPath)
    );
  }
  //#endregion

  //#region Methods
  /**
   * Method supports
   * {@inheritDoc}
   * 
   * Support OAuth2 Bearer tokens and 
   * JWT login tokens.
   *
   * @access public
   * @since 1.0.0
   * 
   * @param Request $request The request.
   * 
   * @return bool True if the request is supported, false otherwise.
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
   * Authenticate the request.
   *
   * @access public
   * @since 1.0.0
   * 
   * @param Request $request The request.
   * 
   * @return Passport The passport.
   */
  public function authenticate(Request $request): Passport
  {
    $authHeader = $request->headers->get(key: 'Authorization', default: '');
    $token = substr($authHeader, 7);

    if ($token === '')
      throw new CustomUserMessageAuthenticationException(
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

      // First, try to find token in database (OAuth2 tokens)
      $accessToken = $this->accessTokenRepository->find($tokenId);

      if ($accessToken !== null) {
        // OAuth2 flow: validate from database
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
      } else {
        // Login flow: validate JWT signature directly
        $validator = new Validator();

        if (
          !$validator->validate($parsedToken, new SignedWith(
            $this->jwtConfig->signer(),
            $this->jwtConfig->verificationKey()
          ))
        ) {
          throw new CustomUserMessageAuthenticationException(
            message: 'Invalid token signature'
          );
        }

        // Check expiry
        if ($parsedToken->isExpired(new DateTimeImmutable())) {
          throw new CustomUserMessageAuthenticationException(
            message: 'Token has expired'
          );
        }

        // Get scopes from JWT claims
        $scopes = $claims->get('scopes', []);
      }

      $userBadge = new UserBadge(
        userIdentifier: (string) $userId,
        userLoader: fn(string $id) => $this->userProvider->loadUserById($id, $scopes)
      );

      return new SelfValidatingPassport($userBadge);
    } catch (CustomUserMessageAuthenticationException $exception) {
      throw $exception;
    } catch (Throwable $exception) {
      throw new CustomUserMessageAuthenticationException(
        message: 'Invalid access token: ' . $exception->getMessage()
      );
    }
  }

  /**
   * Method onAuthenticationSuccess
   * {@inheritDoc}
   * 
   * Handle successful authentication.
   * 
   * @access public
   * @since 1.0.0
   * 
   * @param Request $request The request.
   * @param TokenInterface $token The token.
   * @param string $firewallName The firewall name.
   * 
   * @return ?Response The response.
   */
  public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
  {
    return null;
  }

  /**
   * Method onAuthenticationFailure
   * {@inheritDoc}
   * 
   * Handle failed authentication.
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
