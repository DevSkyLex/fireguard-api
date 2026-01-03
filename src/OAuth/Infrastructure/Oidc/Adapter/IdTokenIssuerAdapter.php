<?php

declare(strict_types=1);

namespace OAuth\Infrastructure\Oidc\Adapter;

use DateInterval;
use DateTimeImmutable;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use OAuth\Application\Port\Outbound\Token\IdTokenIssuerPort;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

use function bin2hex;
use function file_get_contents;
use function hash;
use function is_string;
use function random_bytes;

/**
 * Adapter IdTokenIssuerAdapter.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class IdTokenIssuerAdapter implements IdTokenIssuerPort
{
  // #region Properties
  /**
   * Property jwtConfig.
   *
   * The JWT configuration.
   *
   * @since 1.0.0
   */
  private Configuration $jwtConfig;

  /**
   * Property issuer.
   *
   * Issuer identifier.
   *
   * @since 1.0.0
   *
   * @var non-empty-string
   */
  private string $issuer;

  /**
   * Property idTokenTtl.
   *
   * ID token TTL in seconds.
   *
   * @since 1.0.0
   */
  private int $idTokenTtl;

  /**
   * Property keyId.
   *
   * Key ID for JWT headers.
   *
   * @since 1.0.0
   */
  private ?string $keyId = null;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * IdTokenIssuerAdapter class.
   *
   * @since 1.0.0
   *
   * @param non-empty-string $privateKeyPath
   * @param non-empty-string $publicKeyPath
   * @param string|null $issuer the issuer URL
   * @param string|null $defaultUri the default URI
   * @param int $accessTokenTtl access token TTL in seconds
   * @param string|null $idTokenTtl ID token TTL in seconds
   */
  public function __construct(
    #[Autowire('%kernel.project_dir%/config/jwt/private.key')]
    private readonly string $privateKeyPath,
    #[Autowire('%kernel.project_dir%/config/jwt/public.key')]
    private readonly string $publicKeyPath,
    #[Autowire('%env(OAUTH_ISSUER)%')]
    ?string $issuer = null,
    #[Autowire('%env(default::DEFAULT_URI)%')]
    ?string $defaultUri = null,
    #[Autowire('%env(int:ACCESS_TOKEN_TTL)%')]
    int $accessTokenTtl = 3600,
    #[Autowire('%env(default::ID_TOKEN_TTL)%')]
    ?string $idTokenTtl = null,
  ) {
    $resolvedIssuer = $issuer ?? '';
    if ('' === $resolvedIssuer && null !== $defaultUri) {
      $resolvedIssuer = $defaultUri;
    }
    if ('' === $resolvedIssuer) {
      $resolvedIssuer = 'https://localhost';
    }
    $this->issuer = $resolvedIssuer;
    $this->idTokenTtl = $accessTokenTtl;
    if (null !== $idTokenTtl && '' !== $idTokenTtl) {
      $ttl = (int) $idTokenTtl;
      if ($ttl > 0) {
        $this->idTokenTtl = $ttl;
      }
    }

    /** @var non-empty-string $privatePath */
    $privatePath = $this->privateKeyPath;
    /** @var non-empty-string $publicPath */
    $publicPath = $this->publicKeyPath;

    $this->jwtConfig = Configuration::forAsymmetricSigner(
      signer: new Sha256(),
      signingKey: InMemory::file(path: $privatePath),
      verificationKey: InMemory::file(path: $publicPath),
    );

    $publicKeyContent = file_get_contents($publicPath);
    if (is_string($publicKeyContent)) {
      $this->keyId = hash('sha256', $publicKeyContent);
    }
  }
  // #endregion

  // #region Methods
  public function issueIdToken(string $subject, string $audience, ?string $nonce = null, array $claims = []): string
  {
    $now = new DateTimeImmutable();
    $expiry = $now->add(new DateInterval("PT{$this->idTokenTtl}S"));

    $builder = $this->jwtConfig->builder()
      ->issuedBy($this->issuer)
      ->permittedFor($audience)
      ->relatedTo($subject)
      ->identifiedBy(bin2hex(random_bytes(20)))
      ->issuedAt($now)
      ->canOnlyBeUsedAfter($now)
      ->expiresAt($expiry);

    if (null !== $this->keyId && '' !== $this->keyId) {
      $builder = $builder->withHeader('kid', $this->keyId);
    }

    if (null !== $nonce && '' !== $nonce) {
      $builder = $builder->withClaim('nonce', $nonce);
    }

    foreach ($claims as $key => $value) {
      $claimKey = (string) $key;
      if ('' !== $claimKey) {
        $builder = $builder->withClaim($claimKey, $value);
      }
    }

    return $builder->getToken($this->jwtConfig->signer(), $this->jwtConfig->signingKey())->toString();
  }
  // #endregion
}
