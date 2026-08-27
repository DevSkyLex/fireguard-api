<?php

declare(strict_types=1);

namespace OAuth\Presentation\Api\Provider\Discovery;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Closure;
use OAuth\Presentation\Api\Dto\Output\Discovery\JwksOutput;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

use function base64_encode;
use function file_get_contents;
use function hash;
use function is_array;
use function is_string;
use function openssl_pkey_get_details;
use function openssl_pkey_get_public;
use function rtrim;
use function strtr;

/**
 * Provider JwksProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<JwksOutput>
 */
final readonly class JwksProvider implements ProviderInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * JwksProvider class.
   *
   * @since 1.0.0
   *
   * @param string $publicKeyPath the path to the public key
   * @param Closure|null $keyDetailsResolver optional resolver for key details
   * @param LoggerInterface|null $logger records why an empty key set was returned
   */
  public function __construct(
    #[Autowire('%kernel.project_dir%/config/jwt/public.key')]
    private readonly string $publicKeyPath,
    private readonly ?Closure $keyDetailsResolver = null,
    private readonly ?LoggerInterface $logger = null,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method provide
   * {@inheritDoc}
   *
   * Provides the JSON Web Key Set.
   *
   * Every failure path returns an empty key set, because a discovery document
   * must stay reachable even when the key behind it cannot be published. That
   * silence is what let an unreadable `config/jwt/public.key` sit in production
   * for weeks: `{"keys":[]}` is indistinguishable from "no key configured", so
   * each bail is logged as an error before the empty set goes out.
   *
   * @since 1.0.0
   *
   * @param Operation $operation the operation
   * @param array<string, mixed> $uriVariables the URI variables
   * @param array<string, mixed> $context the context
   *
   * @return JwksOutput the JWKS output
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): JwksOutput
  {
    $output = new JwksOutput();

    $publicKeyContent = file_get_contents($this->publicKeyPath);
    if (false === $publicKeyContent) {
      return $this->empty($output, 'the public key file could not be read');
    }

    $keyData = openssl_pkey_get_public($publicKeyContent);
    if (false === $keyData) {
      return $this->empty($output, 'the public key file is not a parsable public key');
    }

    $details = null === $this->keyDetailsResolver
      ? openssl_pkey_get_details($keyData)
      : ($this->keyDetailsResolver)($keyData);
    if (false === $details || !is_array($details)) {
      return $this->empty($output, 'the public key details could not be read');
    }

    $rsa = $details['rsa'] ?? null;
    if (!is_array($rsa)) {
      return $this->empty($output, 'the public key is not an RSA key');
    }

    $rsaN = $rsa['n'] ?? '';
    $rsaE = $rsa['e'] ?? '';

    if (!is_string($rsaN) || !is_string($rsaE)) {
      return $this->empty($output, 'the RSA modulus or exponent is missing');
    }

    $output->keys = [
      [
        'kty' => 'RSA',
        'use' => 'sig',
        'alg' => 'RS256',
        'kid' => hash('sha256', $publicKeyContent),
        'n' => rtrim(strtr(base64_encode($rsaN), '+/', '-_'), '='),
        'e' => rtrim(strtr(base64_encode($rsaE), '+/', '-_'), '='),
      ],
    ];

    return $output;
  }

  /**
   * Method empty.
   *
   * Logs why the key set is empty, then returns it unchanged.
   *
   * @since 1.0.0
   *
   * @param JwksOutput $output the untouched, empty key set
   * @param string $reason why no key could be published
   *
   * @return JwksOutput the empty key set
   */
  private function empty(JwksOutput $output, string $reason): JwksOutput
  {
    $this->logger?->error(
      'Publishing an empty JWKS: {reason}. Bearer tokens cannot be verified by any client while this holds.',
      [
        'reason' => $reason,
        'public_key_path' => $this->publicKeyPath,
      ],
    );

    return $output;
  }
  // #endregion
}
