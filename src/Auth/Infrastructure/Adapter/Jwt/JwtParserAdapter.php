<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Adapter\Jwt;

use Auth\Application\Port\Outbound\JwtParserPort;
use DateTimeImmutable;
use DateTimeInterface;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\UnencryptedToken;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Constraint\StrictValidAt;
use OAuth\Application\Port\Outbound\JwtParserPort as OAuthJwtParserPort;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

use function is_string;

/**
 * Adapter JwtParserAdapter.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class JwtParserAdapter implements JwtParserPort, OAuthJwtParserPort
{
  // #region Properties
  /**
   * Property jwtConfig.
   */
  private Configuration $jwtConfig;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * JwtParserAdapter class.
   *
   * @since 1.0.0
   *
   * @param string $publicKeyPath path to the public key file
   */
  public function __construct(
    #[Autowire('%kernel.project_dir%/config/jwt/public.key')]
    private readonly string $publicKeyPath,
  ) {
    /** @var non-empty-string $publicPath */
    $publicPath = $this->publicKeyPath;

    $this->jwtConfig = Configuration::forAsymmetricSigner(
      signer: new Sha256(),
      signingKey: InMemory::plainText('dummy-key-not-used-for-parsing'),
      verificationKey: InMemory::file(path: $publicPath),
    );
  }
  // #endregion

  // #region Methods
  public function parse(string $token): ?array
  {
    try {
      if ('' === $token) {
        return null;
      }

      $parsedToken = $this->jwtConfig->parser()->parse($token);

      if (!$parsedToken instanceof UnencryptedToken) {
        return null;
      }

      $claims = $parsedToken->claims();

      /** @var array<string, mixed> $result */
      $result = [
        'jti' => $claims->get('jti'),
        'sub' => $claims->get('sub'),
        'iss' => $claims->get('iss'),
        'aud' => $claims->get('aud'),
        'email' => $claims->get('email'),
        'scopes' => $claims->get('scopes', []),
      ];

      $iat = $claims->get('iat');
      $exp = $claims->get('exp');
      $nbf = $claims->get('nbf');

      $result['iat'] = $iat instanceof DateTimeInterface ? $iat->getTimestamp() : $iat;
      $result['exp'] = $exp instanceof DateTimeInterface ? $exp->getTimestamp() : $exp;
      $result['nbf'] = $nbf instanceof DateTimeInterface ? $nbf->getTimestamp() : $nbf;

      return $result;

    } catch (Throwable) {
      return null;
    }
  }

  public function validate(string $token): bool
  {
    try {
      if ('' === $token) {
        return false;
      }

      $parsedToken = $this->jwtConfig->parser()->parse($token);

      if (!$parsedToken instanceof UnencryptedToken) {
        return false;
      }

      $constraints = [
        new SignedWith($this->jwtConfig->signer(), $this->jwtConfig->verificationKey()),
        new StrictValidAt(new class () implements ClockInterface {
          public function now(): DateTimeImmutable
          {
            return new DateTimeImmutable();
          }
        }),
      ];

      return $this->jwtConfig->validator()->validate($parsedToken, ...$constraints);

    } catch (Throwable) {
      return false;
    }
  }

  public function getTokenId(string $token): ?string
  {
    $claims = $this->parse($token);
    if (null === $claims) {
      return null;
    }

    $jti = $claims['jti'] ?? null;

    return is_string($jti) ? $jti : null;
  }

  public function getUserId(string $token): ?string
  {
    $claims = $this->parse($token);
    if (null === $claims) {
      return null;
    }

    $sub = $claims['sub'] ?? null;

    return is_string($sub) ? $sub : null;
  }
  // #endregion
}
