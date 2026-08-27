<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Security\Authenticator;

use Auth\Application\Contract\Token\AccessTokenStatus;
use Auth\Application\Port\Outbound\{AccessTokenLookupPort, SessionStatusPort};
use Auth\Infrastructure\Security\User\SecurityUserProvider;
use DateTimeImmutable;
use InvalidArgumentException;
use Lcobucci\JWT\{Configuration, Parser as JwtParser, UnencryptedToken};
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Parser as TokenParser;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Validator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\{JsonResponse, Request, Response};
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\{AuthenticationException, CustomUserMessageAuthenticationException};
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\{Passport, SelfValidatingPassport};
use Throwable;

use function array_filter;
use function array_values;
use function is_array;
use function is_string;
use function str_starts_with;
use function substr;

/**
 * Authenticator OAuth2Authenticator.
 *
 * @category Authenticator
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OAuth2Authenticator extends AbstractAuthenticator
{
  private const ACCESS_TOKEN_USE_AUTH_SESSION = 'auth_session';

  // #region Properties
  /**
   * Property jwtConfig.
   *
   * JWT Configuration
   *
   * @since 1.0.0
   */
  private Configuration $jwtConfig;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initialize the authenticator with the access token repository
   * and the security user provider.
   *
   * @since 1.0.0
   *
   * @param AccessTokenLookupPort $accessTokenLookup the access token lookup port
   * @param SecurityUserProvider $userProvider the security user provider
   * @param SessionStatusPort $sessionStatus the session revocation lookup for login-flow tokens
   */
  public function __construct(
    private readonly AccessTokenLookupPort $accessTokenLookup,
    private readonly SecurityUserProvider $userProvider,
    private readonly SessionStatusPort $sessionStatus,
    #[Autowire('%kernel.project_dir%/config/jwt/public.key')]
    string $publicKeyPath,
    private readonly ?JwtParser $parser = null,
  ) {
    if ('' === $publicKeyPath) {
      throw new InvalidArgumentException('Public key path cannot be empty');
    }

    $this->jwtConfig = Configuration::forAsymmetricSigner(
      signer: new Sha256(),
      signingKey: InMemory::plainText('dummy-key-not-used-for-verification'),
      verificationKey: InMemory::file($publicKeyPath),
    );
  }
  // #endregion

  // #region Methods
  /**
   * Method supports
   * {@inheritDoc}
   *
   * Support OAuth2 Bearer tokens and
   * JWT login tokens.
   *
   * @since 1.0.0
   *
   * @param Request $request the request
   *
   * @return bool true if the request is supported, false otherwise
   */
  public function supports(Request $request): bool
  {
    return $request->headers->has(key: 'Authorization')
      && str_starts_with(
        haystack: $request->headers->get(key: 'Authorization', default: ''),
        needle: 'Bearer ',
      );
  }

  /**
   * Method authenticate
   * {@inheritDoc}
   *
   * Authenticate the request.
   *
   * @since 1.0.0
   *
   * @param Request $request the request
   *
   * @return Passport the passport
   */
  public function authenticate(Request $request): Passport
  {
    $authHeader = $request->headers->get(key: 'Authorization', default: '');
    $token = substr($authHeader, 7);

    if ('' === $token) {
      throw new CustomUserMessageAuthenticationException(
        message: 'No access token provided',
      );
    }

    try {
      $parser = $this->parser ?? new TokenParser(new JoseEncoder());
      $parsedToken = $parser->parse($token);

      if (!$parsedToken instanceof UnencryptedToken) {
        throw new CustomUserMessageAuthenticationException(
          message: 'Invalid token format',
        );
      }

      $claims = $parsedToken->claims();
      $tokenId = $claims->get('jti');
      $userId = $claims->get('sub');

      if (!is_string($tokenId) || !is_string($userId)) {
        throw new CustomUserMessageAuthenticationException(
          message: 'Invalid token: missing required claims',
        );
      }

      // The signature is verified for EVERY token, before anything reads a
      // claim as trusted. Both issuance paths sign with config/jwt/private.key
      // — the login flow through JwtTokenAdapter, the OAuth2 flow through
      // League's AuthorizationServer (config/modules/oauth.yaml) — so one
      // verification key covers both. Verifying only on the fall-through
      // branch let an unsigned token carrying a known `jti` and an arbitrary
      // `sub` authenticate as any user, because the database lookup keys on
      // `jti` alone and never binds it back to the subject.
      $validator = new Validator();

      if (
        !$validator->validate($parsedToken, new SignedWith(
          $this->jwtConfig->signer(),
          $this->jwtConfig->verificationKey(),
        ))
      ) {
        throw new CustomUserMessageAuthenticationException(
          message: 'Invalid token signature',
        );
      }

      if ($parsedToken->isExpired(new DateTimeImmutable())) {
        throw new CustomUserMessageAuthenticationException(
          message: 'Token has expired',
        );
      }

      $accessToken = null;
      if (self::ACCESS_TOKEN_USE_AUTH_SESSION === $claims->get('_fireguard_token_use', null)) {
        // Login-flow tokens are not rows in the OAuth2 token table; the session
        // that issued them is what carries their revocation state. Without this
        // check, revoking a session — "sign out everywhere", a password change,
        // a password reset — left the access token usable until it expired.
        if ($this->sessionStatus->isAccessTokenRevoked($tokenId)) {
          throw new CustomUserMessageAuthenticationException(
            message: 'Token has been revoked',
          );
        }
      } else {
        // OAuth2 tokens must keep database-backed revocation and expiry checks.
        $accessToken = $this->accessTokenLookup->find($tokenId);
      }

      if ($accessToken instanceof AccessTokenStatus) {
        // OAuth2 flow: the database is authoritative on revocation and on an
        // expiry that may precede the one stamped in the token.
        if ($accessToken->revoked) {
          throw new CustomUserMessageAuthenticationException(
            message: 'Token has been revoked',
          );
        }

        if ($accessToken->expired) {
          throw new CustomUserMessageAuthenticationException(
            message: 'Token has expired',
          );
        }

        $scopes = $accessToken->scopes;
      } else {
        // Login flow, and any signed token with no database row: scopes come
        // from the claims, which the signature check above has now vouched for.
        $scopesClaim = $claims->get('scopes', []);
        $scopes = is_array($scopesClaim) ? array_values(array_filter($scopesClaim, 'is_string')) : [];
      }

      $userBadge = new UserBadge(
        userIdentifier: $userId,
        userLoader: fn (string $id) => $this->userProvider->loadUserById(
          userId: $id,
          scopes: $scopes,
        ),
      );

      return new SelfValidatingPassport(userBadge: $userBadge);
    } catch (CustomUserMessageAuthenticationException $exception) {
      throw $exception;
    } catch (Throwable $exception) {
      throw new CustomUserMessageAuthenticationException(
        message: 'Invalid access token: ' . $exception->getMessage(),
      );
    }
  }

  /**
   * Method onAuthenticationSuccess
   * {@inheritDoc}
   *
   * Handle successful authentication.
   *
   * @since 1.0.0
   *
   * @param Request $request the request
   * @param TokenInterface $token the token
   * @param string $firewallName the firewall name
   *
   * @return ?Response the response
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
   * @since 1.0.0
   *
   * @param Request $request the request
   * @param AuthenticationException $exception the exception
   *
   * @return Response the response
   */
  public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
  {
    return new JsonResponse(
      data: [
        'error' => 'invalid_token',
        'error_description' => $exception->getMessageKey(),
      ],
      status: Response::HTTP_UNAUTHORIZED,
      headers: ['WWW-Authenticate' => 'Bearer error="invalid_token"'],
    );
  }
  // #endregion
}
